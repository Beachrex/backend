<?php

namespace App\Console\Commands;

use App\Models\BeachTranslation;
use App\Services\AI\Agent\AgentModel;
use App\Services\AI\Agent\AnthropicAgentModel;
use App\Services\AI\Agent\ChatAgent;
use App\Services\AI\Agent\OpenAIAgentModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * AI-2 evaluation (owner 2026-10-09): runs the fixed question set (resources/ai-eval/chat-questions.json)
 * through the tool-calling chat agent with one provider/model and writes a report with automatic checks
 * (links point to real active beaches, links present when beaches are expected, right country, no foreign
 * URLs) plus time and cost per question. Every run calls the paid AI API.
 */
class AiChatEval extends Command
{
    protected $signature = 'ai:chat-eval
        {--provider=openai : openai or anthropic}
        {--model= : model id (default: config ai.chat.*_model)}
        {--effort= : Claude effort (default: config ai.chat.effort)}
        {--only= : comma-separated question ids}
        {--limit= : first N questions only}';

    protected $description = 'Evaluate the AI chat agent on the fixed question set (calls the paid AI API)';

    public function handle(ChatAgent $agent): int
    {
        $provider = (string) $this->option('provider');
        $model = (string) ($this->option('model') ?: config($provider === 'anthropic' ? 'ai.chat.anthropic_model' : 'ai.chat.openai_model'));
        $effort = (string) ($this->option('effort') ?: config('ai.chat.effort'));
        if ($provider === 'anthropic' && ! config('ai.providers.anthropic.api_key') && ! app()->bound(AgentModel::class)) {
            $this->error('ANTHROPIC_API_KEY is not set in this environment.');

            return self::FAILURE;
        }
        if (! app()->bound(AgentModel::class)) { // tests bind a fake model
            app()->instance(AgentModel::class, $provider === 'anthropic'
                ? new AnthropicAgentModel($model, $effort)
                : new OpenAIAgentModel($model));
        }

        $questions = json_decode((string) file_get_contents(resource_path('ai-eval/chat-questions.json')), true);
        if ($only = $this->option('only')) {
            $ids = array_map('trim', explode(',', (string) $only));
            $questions = array_values(array_filter($questions, fn ($q) => in_array($q['id'], $ids, true)));
        }
        if ($limit = (int) $this->option('limit')) {
            $questions = array_slice($questions, 0, $limit);
        }

        $rows = [];
        $details = [];
        foreach ($questions as $q) {
            $this->line("… {$q['id']}");
            $start = microtime(true);
            $turns = [$q['message']];
            if (! empty($q['followup'])) {
                $turns[] = $q['followup'];
            }
            $history = [];
            $cost = 0.0;
            $tools = 0;
            $answer = null;
            $error = null;
            try {
                foreach ($turns as $turn) {
                    $answer = $agent->answer($turn, $history, $this->apiLocale($q['locale']), $q['location'] ?? null);
                    $history[] = ['role' => 'user', 'content' => $turn];
                    $history[] = ['role' => 'assistant', 'content' => $answer['message']];
                    $cost += $answer['result']->costUsd;
                    $tools += count($answer['result']->toolCalls);
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
            $seconds = round(microtime(true) - $start, 1);

            [$checks, $notes] = $error ? [['error' => false], ["error: {$error}"]] : $this->check($q, $answer);
            $passed = ! in_array(false, $checks, true);
            $rows[] = [$q['id'], $passed ? 'PASS' : 'FAIL', $seconds, $tools, number_format($cost, 4), implode('; ', $notes)];
            $details[] = "### {$q['id']} — ".($passed ? 'PASS' : 'FAIL')."\n\n**Q ({$q['locale']}):** ".implode(' → ', $turns)
                ."\n\n**A:** ".($answer['message'] ?? '(no answer)')."\n\n".($notes ? '_'.implode('; ', $notes)."_\n" : '');
        }

        $passed = count(array_filter($rows, fn ($r) => $r[1] === 'PASS'));
        $total = count($rows);
        $sumCost = array_sum(array_map(fn ($r) => (float) str_replace(',', '', $r[4]), $rows));
        $avgSec = $total ? round(array_sum(array_column($rows, 2)) / $total, 1) : 0;

        $this->table(['question', 'result', 's', 'tools', 'USD', 'notes'], $rows);
        $summary = "{$provider} / {$model}".($provider === 'anthropic' ? " (effort {$effort})" : '')
            .": {$passed}/{$total} passed, avg {$avgSec} s, total \$".number_format($sumCost, 4)
            .' (\$'.number_format($total ? $sumCost / $total : 0, 4).' per question)';
        $this->info($summary);

        $file = 'ai-eval/'.now()->format('Ymd-His')."-{$provider}-".preg_replace('/[^a-z0-9.-]/i', '_', $model).'.md';
        Storage::disk('local')->put($file, "# Chat evaluation\n\n{$summary}\n\n| question | result | s | tools | USD | notes |\n|---|---|---|---|---|---|\n"
            .implode("\n", array_map(fn ($r) => '| '.implode(' | ', array_map(fn ($c) => str_replace('|', '/', (string) $c), $r)).' |', $rows))
            ."\n\n".implode("\n", $details));
        $this->info('Report: '.Storage::disk('local')->path($file));

        return self::SUCCESS;
    }

    private function apiLocale(string $webLocale): string
    {
        return $webLocale === 'si' ? 'sl' : $webLocale;
    }

    /** @return array{0: array<string,bool>, 1: string[]} */
    private function check(array $q, array $answer): array
    {
        $text = $answer['message'];
        $front = rtrim((string) config('app.frontend_url'), '/');
        preg_match_all('#https?://[^\s)\]>"]+#', $text, $m);
        $urls = array_unique($m[0]);
        $beachUrls = array_values(array_filter($urls, fn ($u) => str_starts_with($u, $front.'/')));

        $checks = [];
        $notes = [];

        $foreign = array_values(array_diff($urls, $beachUrls));
        $checks['no_foreign_urls'] = $foreign === [];
        if ($foreign) {
            $notes[] = 'foreign URLs: '.implode(', ', $foreign);
        }

        $invalid = [];
        foreach ($beachUrls as $url) {
            $slug = basename(parse_url($url, PHP_URL_PATH) ?: '');
            $ok = BeachTranslation::query()->where('slug', $slug)
                ->whereHas('beach', fn ($b) => $b->where('status_id', 1))->exists();
            if (! $ok) {
                $invalid[] = $url;
            }
        }
        $checks['links_valid'] = $invalid === [];
        if ($invalid) {
            $notes[] = 'broken links: '.implode(', ', $invalid);
        }

        $expectBeaches = (bool) ($q['expect']['beaches'] ?? false);
        $checks['beach_links_when_expected'] = ! $expectBeaches || $beachUrls !== [];
        if ($expectBeaches && ! $beachUrls) {
            $notes[] = 'no beach link';
        }

        if (! empty($q['expect']['country']) && $answer['beaches']) {
            $wrong = array_filter($answer['beaches'], fn ($b) => strtoupper((string) ($b['country_code'] ?? '')) !== $q['expect']['country']);
            $checks['right_country'] = $wrong === [];
            if ($wrong) {
                $notes[] = 'other country: '.implode(', ', array_column($wrong, 'name'));
            }
        }
        $notes[] = count($beachUrls).' beach link(s)';

        return [$checks, $notes];
    }
}
