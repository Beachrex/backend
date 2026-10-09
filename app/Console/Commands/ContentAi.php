<?php

namespace App\Console\Commands;

use Anthropic\Client;
use App\Services\AI\Content\BeachContentAi;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * AI data-quality pass over beach names, region names and descriptions (owner 2026-10-09).
 * Runbook: docs/runbooks/content-ai.md.
 *
 *   plan    - counts and a rough cost, no AI calls, no writes
 *   preview - asks Claude for a few items now and prints the changes, no writes
 *   submit  - sends every item as a Message Batch (half price, runs in parallel, up to 24 h)
 *   status  - progress of a batch
 *   apply   - reads a finished batch and prints the changes; writes only with --write
 */
class ContentAi extends Command
{
    protected $signature = 'content:ai
                            {action : plan, preview, submit, status or apply}
                            {--part=* : names, regions, descriptions, generate (plan: all when omitted)}
                            {--batch= : Batch id (status, apply)}
                            {--id=* : Only these beach or region ids}
                            {--limit= : Only the first N items per part}
                            {--sample=3 : preview: items per part}
                            {--show=40 : apply: changes to print}
                            {--full : preview/apply: print whole descriptions, not the first 80 characters}
                            {--locale=* : preview/apply: print only these languages (e.g. --locale=sl --locale=en)}
                            {--force : submit: send even if an earlier batch of the same part was not applied yet}
                            {--write : apply: write to the database (default is a dry run)}';

    protected $description = 'AI data quality: beach names without "Beach", region and description translations, generated descriptions';

    /** USD per 1M tokens (input, output) at batch price (half the standard price). */
    private const BATCH_PRICES = [
        'claude-opus-5-5' => [2.00, 10.00],
        'claude-sonnet-5-5' => [1.00, 5.00],
        'claude-haiku-5-5' => [0.05, 0.25],
    ];

    /** Rough tokens per item (input, output incl. thinking) for the plan estimate. */
    private const TOKENS_PER_ITEM = [
        'names' => [700, 500],
        'regions' => [600, 1500],
        'descriptions' => [1500, 8000],
        'generate' => [900, 5000],
    ];

    public function handle(BeachContentAi $ai): int
    {
        $action = (string) $this->argument('action');
        if (! in_array($action, ['plan', 'preview', 'submit', 'status', 'apply'], true)) {
            $this->error("Unknown action {$action}. Use plan, preview, submit, status or apply.");

            return self::INVALID;
        }

        if (in_array($action, ['status', 'apply'], true)) {
            if (! $this->option('batch')) {
                $this->error('--batch=<id> is required.');

                return self::INVALID;
            }

            return $action === 'status' ? $this->status() : $this->applyBatch($ai);
        }

        $parts = $this->option('part') ?: ($action === 'plan' ? BeachContentAi::PARTS : []);
        $unknown = array_diff($parts, BeachContentAi::PARTS);
        if ($parts === [] || $unknown !== []) {
            $this->error('--part must be one or more of: '.implode(', ', BeachContentAi::PARTS));

            return self::INVALID;
        }

        return match ($action) {
            'plan' => $this->plan($ai, $parts),
            'preview' => $this->preview($ai, $parts),
            'submit' => $this->submit($ai, $parts),
        };
    }

    private function plan(BeachContentAi $ai, array $parts): int
    {
        [$model] = $this->modelAndEffort();
        $price = self::BATCH_PRICES[$model] ?? null;
        $rows = [];
        $total = 0.0;
        foreach ($parts as $part) {
            $count = $this->items($ai, $part)->count();
            [$in, $out] = self::TOKENS_PER_ITEM[$part];
            $cost = $price ? $count * ($in * $price[0] + $out * $price[1]) / 1_000_000 : null;
            $total += $cost ?? 0;
            $rows[] = [$part, $count, $cost === null ? '?' : '$'.number_format($cost, 2)];
        }

        $this->info("Model {$model} (batch price). Nothing is sent and nothing is written.");
        $this->table(['Part', 'Items', 'Rough cost'], $rows);
        $this->line('Rough total: $'.number_format($total, 2).' (estimate; the real cost is shown in the Anthropic console).');

        foreach ($parts as $part) {
            $examples = $this->items($ai, $part)->limit(5)->get();
            if ($examples->isEmpty()) {
                continue;
            }
            $this->newLine();
            $this->line("<comment>{$part}</comment> examples:");
            foreach ($examples as $item) {
                $item->loadMissing('translations');
                $en = $item->translations->firstWhere('locale', 'en') ?? $item->translations->first();
                $this->line("  #{$item->getKey()} ".($en?->name ?? '?'));
            }
        }

        return self::SUCCESS;
    }

    private function preview(BeachContentAi $ai, array $parts): int
    {
        [$model, $effort] = $this->modelAndEffort();
        $client = $this->client();
        $sample = max(1, (int) $this->option('sample'));

        foreach ($parts as $part) {
            $query = $this->items($ai, $part);
            if (! $this->option('id')) {
                $query->limit($sample);
            }
            foreach ($query->get() as $item) {
                $request = $ai->request($part, $item, $model, $effort);
                if (! $request) {
                    $this->line("{$part}-{$item->getKey()}: nothing to send");

                    continue;
                }
                $message = $client->messages->create(...$request['params']);
                $this->report($ai, $request['customID'], $message, dryRun: true, show: PHP_INT_MAX);
            }
        }

        $this->newLine();
        $this->info('Preview only: nothing was written.');

        return self::SUCCESS;
    }

    private function submit(BeachContentAi $ai, array $parts): int
    {
        // Items leave the selection only once their results are written, so a second submit before
        // `apply --write` would pay for the same items twice.
        foreach ($parts as $part) {
            $pending = Cache::get(self::pendingKey($part), []);
            if ($pending !== [] && ! $this->option('force')) {
                $this->error("Part {$part} has batches that were not applied yet: ".implode(', ', $pending));
                $this->line('Run apply --write for them first (or add --force to send again anyway).');

                return self::FAILURE;
            }
        }

        [$model, $effort] = $this->modelAndEffort();
        $requests = [];
        foreach ($parts as $part) {
            foreach ($this->items($ai, $part)->cursor() as $item) {
                if ($request = $ai->request($part, $item, $model, $effort)) {
                    $requests[] = $request;
                }
            }
        }

        if ($requests === []) {
            $this->info('Nothing to send.');

            return self::SUCCESS;
        }

        $client = $this->client();
        // The API takes up to 100 000 requests per batch; smaller batches finish and fail independently.
        foreach (array_chunk($requests, 5000) as $chunk) {
            $batch = $client->messages->batches->create(requests: $chunk);
            $this->info('Batch '.$batch->id.': '.count($chunk).' requests');
            foreach (array_unique(array_map(fn ($r) => explode('-', $r['customID'])[0], $chunk)) as $part) {
                Cache::forever(self::pendingKey($part), [...Cache::get(self::pendingKey($part), []), $batch->id]);
            }
        }
        $this->line('Check with: php artisan content:ai status --batch=<id>');

        return self::SUCCESS;
    }

    private function status(): int
    {
        $batch = $this->client()->messages->batches->retrieve((string) $this->option('batch'));
        $c = $batch->requestCounts;
        $this->info("{$batch->id}: {$batch->processingStatus}");
        $this->line("processing {$c->processing}, succeeded {$c->succeeded}, errored {$c->errored}, expired {$c->expired}, canceled {$c->canceled}");

        return self::SUCCESS;
    }

    private function applyBatch(BeachContentAi $ai): int
    {
        $client = $this->client();
        $id = (string) $this->option('batch');
        $batch = $client->messages->batches->retrieve($id);
        if ($batch->processingStatus !== 'ended') {
            $this->error("Batch {$id} is {$batch->processingStatus}; wait until it has ended.");

            return self::FAILURE;
        }

        $dryRun = ! $this->option('write');
        $show = (int) $this->option('show');
        $counts = ['written' => 0, 'skipped' => 0, 'invalid' => 0, 'failed' => 0];
        $shown = 0;
        foreach ($client->messages->batches->resultsStream($id) as $entry) {
            if ($entry->result->type !== 'succeeded') {
                $counts['failed']++;
                $this->warn("{$entry->customID}: {$entry->result->type}");

                continue;
            }
            $status = $this->report($ai, $entry->customID, $entry->result->message, $dryRun, $show - $shown);
            $counts[$status]++;
            if ($status !== 'skipped') {
                $shown++;
            }
        }

        $this->newLine();
        $this->table(['Result', 'Items'], collect($counts)->map(fn ($n, $k) => [$k, $n])->values()->all());
        if (! $dryRun) {
            foreach (BeachContentAi::PARTS as $part) {
                $left = array_values(array_diff(Cache::get(self::pendingKey($part), []), [$id]));
                $left === [] ? Cache::forget(self::pendingKey($part)) : Cache::forever(self::pendingKey($part), $left);
            }
        }
        if ($counts['failed'] > 0 || $counts['invalid'] > 0) {
            $this->line('Items that failed or were refused stay in the selection: submit the same part again later to retry only those.');
        }
        if ($dryRun) {
            $this->info('Dry run: nothing was written. Run again with --write to save.');
        } else {
            $this->info('Saved. Now run: php artisan cache:clear-api --type=all, then php artisan search:index');
        }

        return self::SUCCESS;
    }

    /** Validate and apply one answer; prints the changes while $show > 0. */
    private function report(BeachContentAi $ai, string $customId, object $message, bool $dryRun, int $show): string
    {
        if (($message->stopReason ?? null) !== 'end_turn') {
            $this->warn("{$customId}: stopped ({$message->stopReason})");

            return 'invalid';
        }
        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $result = $ai->apply($customId, $text, $dryRun, $this->option('full') ? PHP_INT_MAX : 80);
        if ($locales = $this->option('locale')) {
            $result['changes'] = array_values(array_filter(
                $result['changes'],
                fn ($c) => in_array(strstr($c, ':', true), $locales, true),
            ));
        }
        if ($result['status'] === 'invalid') {
            $this->warn("{$customId}: not applied - {$result['reason']}");
        } elseif ($show > 0 && $result['changes'] !== []) {
            $this->line("<comment>{$customId}</comment>");
            foreach ($result['changes'] as $change) {
                $this->line("  {$change}");
            }
        }

        return $result['status'];
    }

    public static function pendingKey(string $part): string
    {
        return "content-ai:pending:{$part}";
    }

    private function items(BeachContentAi $ai, string $part): Builder
    {
        $query = $ai->query($part);
        if ($ids = $this->option('id')) {
            $query->whereKey(array_map('intval', $ids));
        }
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        return $query;
    }

    /** @return array{0: string, 1: string} */
    private function modelAndEffort(): array
    {
        return [(string) config('ai.content.model'), (string) config('ai.content.effort')];
    }

    private function client(): Client
    {
        if (app()->bound('content-ai.client')) {
            return app('content-ai.client');
        }

        return new Client(
            apiKey: (string) config('ai.providers.anthropic.api_key'),
            requestOptions: ['timeout' => 600.0, 'maxRetries' => 2],
        );
    }
}
