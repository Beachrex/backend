<?php

namespace App\Console\Commands;

use Anthropic\Client;
use App\Http\Middleware\SetLocaleFromHeader;
use App\Services\AI\Content\BeachContentAi;
use App\Support\TranslationTables;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fills missing reference-data translations with Claude (owner 2026-10-12: Greek pages showed
 * "small_stones", Albanian English names; `translations:audit` found da, pt, nl, el, sq empty in most
 * attribute tables). Runbook: docs/runbooks/content-ai.md.
 *
 * Works on the small `*_translation` tables (beach attributes, badges, islands, lakes, rivers, countries,
 * continents). Beaches, cities and regions have their own passes. One request per table chunk.
 * A value is written only where the language has no row, or its name is still the English one; a name a
 * person translated is never overwritten. New rows copy the English row (slug included, so URLs stay).
 *
 *   plan    - tables, rows and languages to fill; no AI calls
 *   preview - asks Claude for one table now and prints the changes; writes nothing
 *   submit  - sends every table as a Message Batch
 *   status  - progress of a batch
 *   apply   - reads a finished batch; writes only with --write
 */
class TranslationsAi extends Command
{
    protected $signature = 'translations:ai
                            {action : plan, preview, submit, status or apply}
                            {--table=* : Only these translation tables}
                            {--batch= : Batch id (status, apply)}
                            {--show=60 : apply/preview: changes to print}
                            {--force : submit: send even if an earlier batch was not applied yet}
                            {--write : apply: write to the database (default is a dry run)}';

    protected $description = 'AI translations for reference data (attributes, badges, islands, countries, …) in every language';

    /** Handled elsewhere (content:ai) or long texts, not names. */
    private const SKIP = [
        'region_translation', 'static_page_translation',
        'article_translation', 'first_page_content_translation', 'first_page_content_item_translation',
    ];

    /**
     * Beach and city names keep their local spelling in Latin-script languages (the English row is the
     * fallback); only Greek, Ukrainian, Russian and Bulgarian need them in their own script (owner 2026-10-12).
     */
    private const PLACE_TABLES = ['beach_translation', 'city_translation'];

    /** Long texts that a new row must not copy from English (a description is translated separately). */
    private const NOT_COPIED = ['description'];

    /** Extra text columns translated together with `name` when the English row has them. */
    private const EXTRA_FIELDS = ['full_name', 'filter_title'];

    private const CHUNK = 40;

    private const PENDING_KEY = 'translations-ai:pending';

    public function handle(): int
    {
        $action = (string) $this->argument('action');
        if (in_array($action, ['status', 'apply'], true) && ! $this->option('batch')) {
            $this->error('--batch=<id> is required.');

            return self::INVALID;
        }

        return match ($action) {
            'plan' => $this->plan(),
            'preview' => $this->preview(),
            'submit' => $this->submit(),
            'status' => $this->status(),
            'apply' => $this->applyBatch(),
            default => $this->unknown($action),
        };
    }

    private function unknown(string $action): int
    {
        $this->error("Unknown action {$action}. Use plan, preview, submit, status or apply.");

        return self::INVALID;
    }

    // ------------------------------------------------------------------ selection

    /** @return list<array{table: string, parent: string, fk: string, parentKey: string}> */
    private function tables(): array
    {
        return array_values(array_filter(
            TranslationTables::all($this->option('table')),
            fn ($t) => ! in_array($t['table'], self::SKIP, true),
        ));
    }

    /**
     * Rows of one table that miss a language or still show the English name there.
     *
     * @return Collection<int, array{key: string, en: array<string, string>, locales: list<string>}>
     */
    public function work(array $t): Collection
    {
        $isPlace = in_array($t['table'], self::PLACE_TABLES, true);
        $locales = $isPlace
            ? BeachContentAi::NON_LATIN
            : array_values(array_diff(SetLocaleFromHeader::SUPPORTED_LOCALES, ['en']));
        $fields = $isPlace ? [] : $this->fields($t['table']);
        $query = DB::table($t['table']);
        $countries = collect();
        if ($isPlace) {
            // Active places only, with their country as context for the spelling.
            $parentColumns = Schema::getColumnListing($t['parent']);
            $active = DB::table($t['parent'])->when(in_array('status_id', $parentColumns, true), fn ($q) => $q->where('status_id', 1));
            $countries = $active->pluck('country_code', $t['parentKey'])->mapWithKeys(fn ($c, $k) => [(string) $k => $c]);
            $query->whereIn($t['fk'], $countries->keys()->all());
        }
        $rows = $query->get()->groupBy(fn ($r) => (string) $r->{$t['fk']});

        return $rows->map(function (Collection $byKey, string $key) use ($locales, $fields, $countries) {
            $en = $byKey->firstWhere('locale', 'en');
            if (! $en || trim((string) $en->name) === '') {
                return null;
            }
            $need = array_values(array_filter($locales, function ($locale) use ($byKey, $en) {
                $row = $byKey->firstWhere('locale', $locale);

                return ! $row || trim((string) $row->name) === '' || $row->name === $en->name;
            }));
            if ($need === []) {
                return null;
            }
            $source = ['name' => $en->name];
            foreach ($fields as $field) {
                if (! empty($en->{$field})) {
                    $source[$field] = $en->{$field};
                }
            }

            $item = ['key' => $key, 'en' => $source, 'locales' => $need];
            if ($countries->has($key)) {
                $item['country'] = $countries[$key];
            }

            return $item;
        })->filter()->values();
    }

    /** @return list<string> */
    private function fields(string $table): array
    {
        return array_values(array_intersect(self::EXTRA_FIELDS, Schema::getColumnListing($table)));
    }

    // ------------------------------------------------------------------ requests

    /** @return list<array{customID: string, params: array<string, mixed>}> */
    public function requests(array $t, string $model, string $effort): array
    {
        $requests = [];
        foreach ($this->work($t)->chunk(self::CHUNK)->values() as $i => $chunk) {
            $locales = $chunk->pluck('locales')->flatten()->unique()->sort()->values()->all();
            $fieldNames = array_values(array_unique(array_merge(['name'], ...$chunk->map(fn ($r) => array_keys($r['en']))->all())));
            $requests[] = [
                'customID' => 'tr-'.$t['table'].'-'.$i,
                'params' => [
                    'model' => $model,
                    'maxTokens' => 32000,
                    'system' => in_array($t['table'], self::PLACE_TABLES, true) ? $this->placePrompt() : $this->systemPrompt(),
                    'messages' => [['role' => 'user', 'content' => json_encode([
                        'list' => $this->describe($t['table']),
                        'items' => $chunk->map(fn ($r) => ['key' => $r['key']] + $r['en'] + (isset($r['country']) ? ['country' => $r['country']] : []))->values()->all(),
                        'languages' => array_map(fn ($l) => BeachContentAi::LANGUAGE_NAMES[$l]." ({$l})", $locales),
                    ], JSON_UNESCAPED_UNICODE)]],
                    'outputConfig' => [
                        'effort' => $effort,
                        'format' => ['type' => 'json_schema', 'schema' => $this->schema($locales, $fieldNames)],
                    ],
                ],
            ];
        }

        return $requests;
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
You translate short labels for Beachrex, a beach guide published in 19 languages. Each item is an English label
from one list of the site (beach surface types, facilities, activities, badges, countries, islands, lakes, …).
For every item and every requested language, write the label the way a travel website in that language writes it:
- short and natural, same meaning; capitalise like a label in that language;
- country, island, lake and river names: the usual name in that language (Greek in Greek script, Ukrainian,
  Russian and Bulgarian in Cyrillic), e.g. Croatia -> "Hrvaška" (Slovenian), "Κροατία" (Greek), "Kroacia" (Albanian);
- numbers, units and measurements stay as they are ("3m" -> "3m");
- brand-like or international words that a language really uses unchanged (e.g. "WC", "Hotel", "Bistro") may stay.
Return every item key with every requested language.
TXT;
    }

    private function placePrompt(): string
    {
        return <<<'TXT'
You write place names for Beachrex, a beach guide published in 19 languages. Each item is the name of a beach or a
town as written locally (Latin script), with its country code. Write it in each requested language's script:
Greek in Greek script, Ukrainian, Russian and Bulgarian in Cyrillic.
- Use the established name in that language when there is one (Venice -> "Венеция" in Russian, "Βενετία" in Greek);
  otherwise transliterate the local pronunciation, following that language's usual rules for the country's language.
- Keep generic words in the name translated naturally when they are words, not names ("Plaža", "Plage", "Spiaggia",
  "Strand" -> the language's word for beach; "Mali", "Veli" stay part of the name).
- No added words, no explanations. Return every item key with every requested language.
TXT;
    }

    private function describe(string $table): string
    {
        return str_replace('_', ' ', substr($table, 0, -strlen('_translation')));
    }

    /** @param list<string> $locales @param list<string> $fields */
    private function schema(array $locales, array $fields): array
    {
        $value = ['type' => 'object', 'additionalProperties' => false, 'required' => $fields,
            'properties' => collect($fields)->mapWithKeys(fn ($f) => [$f => ['type' => 'string']])->all()];
        $item = ['type' => 'object', 'additionalProperties' => false, 'required' => array_merge(['key'], $locales),
            'properties' => ['key' => ['type' => 'string']] + collect($locales)->mapWithKeys(fn ($l) => [$l => $value])->all()];

        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['items'],
            'properties' => ['items' => ['type' => 'array', 'items' => $item]]];
    }

    // ------------------------------------------------------------------ apply

    /**
     * @return array{status: string, changes: list<string>, reason?: string}
     */
    public function apply(string $customId, string $json, bool $dryRun): array
    {
        $tables = collect(TranslationTables::all())->keyBy('table');
        if (! preg_match('/^tr-([a-z_]+_translation)-\d+$/', $customId, $m) || ! $tables->has($m[1])) {
            return ['status' => 'invalid', 'changes' => [], 'reason' => "unknown request id {$customId}"];
        }
        $data = json_decode($json, true);
        if (! is_array($data) || ! is_array($data['items'] ?? null)) {
            return ['status' => 'invalid', 'changes' => [], 'reason' => 'answer is not JSON'];
        }

        $t = $tables[$m[1]];
        $work = $this->work($t)->keyBy('key');
        $columns = Schema::getColumnListing($t['table']);
        $changes = [];
        DB::transaction(function () use ($t, $data, $work, $columns, $dryRun, &$changes) {
            foreach ($data['items'] as $item) {
                $key = (string) ($item['key'] ?? '');
                if (! $work->has($key)) {
                    continue; // already done, or not asked
                }
                $en = DB::table($t['table'])->where($t['fk'], $key)->where('locale', 'en')->first();
                foreach ($work[$key]['locales'] as $locale) {
                    $values = $this->clean($item[$locale] ?? null, array_keys($work[$key]['en']));
                    if ($values === null) {
                        continue;
                    }
                    $row = DB::table($t['table'])->where($t['fk'], $key)->where('locale', $locale)->first();
                    if ($row && trim((string) $row->name) !== '' && $row->name !== $en->name) {
                        continue; // a person translated it meanwhile
                    }
                    if ($row && $row->name === $values['name']) {
                        continue;
                    }
                    $changes[] = "{$t['table']} {$key} {$locale}: ".($row ? "\"{$row->name}\"" : '(new)')." -> \"{$values['name']}\"";
                    if ($dryRun) {
                        continue;
                    }
                    $now = now();
                    if ($row) {
                        $update = $values + (in_array('updated_at', $columns, true) ? ['updated_at' => $now] : []);
                        DB::table($t['table'])->where($t['fk'], $key)->where('locale', $locale)->update($update);
                    } else {
                        // Copy the English row (slug included, so URLs do not change).
                        $insert = array_merge((array) $en, $values, ['locale' => $locale]);
                        unset($insert['id']);
                        foreach (self::NOT_COPIED as $column) {
                            if (array_key_exists($column, $insert)) {
                                $insert[$column] = null;
                            }
                        }
                        foreach (['created_at', 'updated_at'] as $ts) {
                            if (in_array($ts, $columns, true)) {
                                $insert[$ts] = $now;
                            }
                        }
                        DB::table($t['table'])->insert($insert);
                    }
                }
            }
        });

        return ['status' => $changes === [] ? 'skipped' : 'written', 'changes' => $changes];
    }

    /** @param list<string> $fields @return array<string, string>|null */
    private function clean(mixed $value, array $fields): ?array
    {
        if (! is_array($value)) {
            return null;
        }
        $out = [];
        foreach ($fields as $field) {
            $text = is_string($value[$field] ?? null) ? trim(preg_replace('/\s+/u', ' ', $value[$field]) ?? '') : '';
            if ($text === '' || mb_strlen($text) > 150) {
                return null;
            }
            $out[$field] = $text;
        }

        return $out;
    }

    // ------------------------------------------------------------------ actions

    private function plan(): int
    {
        $rows = [];
        $total = 0;
        foreach ($this->tables() as $t) {
            $work = $this->work($t);
            if ($work->isEmpty()) {
                continue;
            }
            $pairs = $work->sum(fn ($r) => count($r['locales']));
            $total += $pairs;
            $rows[] = [$t['table'], $work->count(), $pairs, collect($work->pluck('locales')->flatten()->countBy())->keys()->sort()->implode(' ')];
        }
        $this->table(['Table', 'Rows', 'Names to write', 'Languages'], $rows);
        $this->line("Total names to write: {$total}. Nothing is sent and nothing is written.");

        return self::SUCCESS;
    }

    private function preview(): int
    {
        $tables = $this->tables();
        if (count($tables) !== 1) {
            $this->error('preview needs exactly one --table=<name>_translation.');

            return self::INVALID;
        }
        [$model, $effort] = $this->modelAndEffort();
        $requests = $this->requests($tables[0], $model, $effort);
        if ($requests === []) {
            $this->info('Nothing to translate in this table.');

            return self::SUCCESS;
        }
        $message = $this->client()->messages->create(...$requests[0]['params']);
        $this->report($requests[0]['customID'], $message, true);
        $this->info('Preview of the first chunk only: nothing was written.');

        return self::SUCCESS;
    }

    private function submit(): int
    {
        $pending = Cache::get(self::PENDING_KEY, []);
        if ($pending !== [] && ! $this->option('force')) {
            $this->error('Batches not applied yet: '.implode(', ', $pending).'. Run apply --write first (or add --force).');

            return self::FAILURE;
        }
        [$model, $effort] = $this->modelAndEffort();
        $requests = collect($this->tables())->flatMap(fn ($t) => $this->requests($t, $model, $effort))->values()->all();
        if ($requests === []) {
            $this->info('Nothing to send.');

            return self::SUCCESS;
        }
        $batch = $this->client()->messages->batches->create(requests: $requests);
        Cache::forever(self::PENDING_KEY, [...$pending, $batch->id]);
        $this->info('Batch '.$batch->id.': '.count($requests).' requests');
        $this->line('Check with: php artisan translations:ai status --batch=<id>');

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

    private function applyBatch(): int
    {
        $client = $this->client();
        $id = (string) $this->option('batch');
        $batch = $client->messages->batches->retrieve($id);
        if ($batch->processingStatus !== 'ended') {
            $this->error("Batch {$id} is {$batch->processingStatus}; wait until it has ended.");

            return self::FAILURE;
        }
        $dryRun = ! $this->option('write');
        $counts = ['written' => 0, 'skipped' => 0, 'invalid' => 0, 'failed' => 0];
        $names = 0;
        foreach ($client->messages->batches->resultsStream($id) as $entry) {
            if ($entry->result->type !== 'succeeded') {
                $counts['failed']++;
                $this->warn("{$entry->customID}: {$entry->result->type}");

                continue;
            }
            [$status, $n] = $this->report($entry->customID, $entry->result->message, $dryRun);
            $counts[$status]++;
            $names += $n;
        }
        $this->table(['Result', 'Requests'], collect($counts)->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->line("Names: {$names}");
        if ($dryRun) {
            $this->info('Dry run: nothing was written. Run again with --write to save.');
        } else {
            Cache::forever(self::PENDING_KEY, array_values(array_diff(Cache::get(self::PENDING_KEY, []), [$id])));
            $this->info('Saved. Now run: php artisan cache:clear-api --type=all');
        }

        return self::SUCCESS;
    }

    /** @return array{0: string, 1: int} */
    private function report(string $customId, object $message, bool $dryRun): array
    {
        if (($message->stopReason ?? null) !== 'end_turn') {
            $this->warn("{$customId}: stopped ({$message->stopReason})");

            return ['invalid', 0];
        }
        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }
        $result = $this->apply($customId, $text, $dryRun);
        if ($result['status'] === 'invalid') {
            $this->warn("{$customId}: not applied - {$result['reason']}");

            return ['invalid', 0];
        }
        foreach (array_slice($result['changes'], 0, (int) $this->option('show')) as $change) {
            $this->line("  {$change}");
        }

        return [$result['status'], count($result['changes'])];
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
