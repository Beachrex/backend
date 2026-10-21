<?php

namespace App\Services\AI\Content;

use App\Http\Middleware\SetLocaleFromHeader;
use App\Models\Beach;
use App\Models\BeachTranslation;
use App\Models\Region;
use App\Models\RegionTranslation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AI data-quality pass (owner 2026-10-09, "uredi kakovost podatkov, z AI prevajaj").
 *
 * Four parts, each one Claude request per item (Message Batches API, see `content:ai`):
 * - names: drop the English "Beach" an import added to beach names ("Beach Slanica" → "Slanica");
 * - regions: translate region names ("Dalmatia - Šibenik" → "Dalmacija - Šibenik");
 * - descriptions: translate an existing description into the languages that have none;
 * - generate: write a search snippet, an intro and FAQs from database facts for beaches without a description;
 * - seo: write the search snippet and FAQs for beaches that already have a description (TASK-007).
 *
 * Writes never change slugs (URLs stay the same) and never overwrite text a person wrote:
 * a name is replaced only while it still has the English "Beach" or equals the source name,
 * a description, search snippet or FAQ only where the language has none.
 */
class BeachContentAi
{
    public const PARTS = ['names', 'regions', 'descriptions', 'generate', 'seo'];

    /** Locales written in Cyrillic or Greek script: proper names are transliterated there. */
    public const NON_LATIN = ['ua', 'ru', 'bg', 'el'];

    public const LANGUAGE_NAMES = [
        'en' => 'English', 'sl' => 'Slovenian', 'de' => 'German', 'hr' => 'Croatian', 'fr' => 'French',
        'es' => 'Spanish', 'it' => 'Italian', 'ua' => 'Ukrainian', 'ru' => 'Russian', 'pl' => 'Polish',
        'cs' => 'Czech', 'hu' => 'Hungarian', 'bg' => 'Bulgarian', 'sk' => 'Slovak', 'da' => 'Danish',
        'pt' => 'Portuguese', 'nl' => 'Dutch', 'el' => 'Greek', 'sq' => 'Albanian',
    ];

    /** Countries whose own beach names may contain the English word "Beach". */
    public const ENGLISH_NAME_COUNTRIES = ['GB', 'IE', 'US', 'AU', 'NZ', 'CA', 'ZA', 'MT'];

    private const NAME_MAX = 120;

    private const DESCRIPTION_MAX = 5000;

    /** We ask for 155 characters; a slightly longer snippet is kept, a much longer one is dropped. */
    private const META_MAX = 200;

    private const FAQ_QUESTION_MAX = 200;

    private const FAQ_ANSWER_MAX = 700;

    /** Nearby beaches for the prompt: radius and count. */
    private const NEARBY_KM = 10;

    private const NEARBY_COUNT = 3;

    /**
     * Opening angles, picked by beach id so neighbouring beaches do not read alike (owner 2026-10-21:
     * every text followed the same field order).
     */
    public const ANGLES = [
        'who the beach suits best and why (families, snorkellers, kiters, people looking for quiet)',
        'the setting and landscape around the beach (bay, pines, cliffs, wetlands, town promenade)',
        'the standout activity or experience the facts point to',
        'the water and the seabed: how it feels to get in and swim there',
    ];

    /** @var array<int, true>|null ids of the most viewed active beaches */
    private ?array $featuredIds = null;

    /** @return list<string> */
    public static function locales(): array
    {
        return SetLocaleFromHeader::SUPPORTED_LOCALES;
    }

    // ------------------------------------------------------------------ selection

    /** Active beaches with the English word "beach" in a name (any language). */
    public function beachesForNames(): Builder
    {
        return Beach::query()->where('status_id', 1)
            ->whereHas('translations', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%beach%']))
            ->orderBy('id');
    }

    /** Regions with active beaches that miss a language, or show the English name in another language. */
    public function regionsForTranslation(): Builder
    {
        return Region::query()
            ->whereHas('beaches', fn ($q) => $q->where('status_id', 1))
            ->where(function ($q) {
                $q->has('translations', '<', count(self::locales()))
                    ->orWhereExists(function ($sub) {
                        $sub->selectRaw('1')->from('region_translation as rt')
                            ->join('region_translation as rt_en', function ($j) {
                                $j->on('rt_en.region_id', '=', 'rt.region_id')->where('rt_en.locale', 'en');
                            })
                            ->whereColumn('rt.region_id', 'region.id')
                            ->where('rt.locale', '<>', 'en')
                            ->whereColumn('rt.name', 'rt_en.name');
                    });
            })
            ->orderBy('id');
    }

    /** Active beaches with a description in some language but not in all of them. */
    public function beachesForDescriptionTranslation(): Builder
    {
        $withText = fn ($q) => $q->whereNotNull('description')->whereRaw("TRIM(description) <> ''");

        return Beach::query()->where('status_id', 1)
            ->whereHas('translations', $withText)
            ->whereHas('translations', $withText, '<', count(self::locales()))
            ->orderBy('id');
    }

    /** Active beaches without a description in any language. */
    public function beachesForGeneration(): Builder
    {
        return Beach::query()->where('status_id', 1)
            ->whereDoesntHave('translations', fn ($q) => $q->whereNotNull('description')->whereRaw("TRIM(description) <> ''"))
            ->orderBy('id');
    }

    /** Active beaches with a description in some language and a language without a search snippet (TASK-007). */
    public function beachesForSeo(): Builder
    {
        $withText = fn ($q) => $q->whereNotNull('description')->whereRaw("TRIM(description) <> ''");
        $withMeta = fn ($q) => $q->whereNotNull('meta_description')->whereRaw("TRIM(meta_description) <> ''");

        return Beach::query()->where('status_id', 1)
            ->whereHas('translations', $withText)
            ->whereHas('translations', $withMeta, '<', count(self::locales()))
            ->orderBy('id');
    }

    public function query(string $part): Builder
    {
        return match ($part) {
            'names' => $this->beachesForNames(),
            'regions' => $this->regionsForTranslation(),
            'descriptions' => $this->beachesForDescriptionTranslation(),
            'generate' => $this->beachesForGeneration(),
            'seo' => $this->beachesForSeo(),
        };
    }

    // ------------------------------------------------------------------ requests

    /**
     * Messages API parameters (camelCase, as the PHP SDK takes them) for one item, or null when
     * there is nothing to send (e.g. no source text).
     *
     * @return array{customID: string, params: array<string, mixed>}|null
     */
    public function request(string $part, Model $item, string $model, string $effort): ?array
    {
        [$system, $user, $schema, $maxTokens] = match ($part) {
            'names' => $this->namesPrompt($item),
            'regions' => $this->regionsPrompt($item),
            'descriptions' => $this->descriptionsPrompt($item),
            'generate' => $this->generatePrompt($item),
            'seo' => $this->seoPrompt($item),
        } ?? [null, null, null, null];

        if ($system === null) {
            return null;
        }

        return [
            'customID' => "{$part}-{$item->getKey()}",
            'params' => [
                'model' => $model,
                'maxTokens' => $maxTokens,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $user]],
                'outputConfig' => [
                    'effort' => $effort,
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
            ],
        ];
    }

    /** @return array{0: string, 1: string, 2: array<string, mixed>, 3: int}|null */
    private function namesPrompt(Beach $beach): ?array
    {
        $beach->loadMissing(['translations', 'country.translations', 'region.translations', 'island.translations', 'city.translations']);
        $source = $this->sourceTranslation($beach);
        if (! $source) {
            return null;
        }

        $system = <<<'TXT'
You fix beach names for Beachrex, a beach guide published in 19 languages.
An import added the English word "Beach" to most names ("Beach Slanica", "Vela Beach", "beach Bučanje"), and that English label now shows in every language.
Return the beach's real name as people in its country write it on signs and maps:
- Drop the added word "Beach" when the remaining words are the proper name: "Beach Slanica" -> "Slanica", "Beach Zlatni Rat" -> "Zlatni Rat", "Beach Gradsko kupalište" -> "Gradsko kupalište".
- Keep a generic word only when it is part of the proper name, and then write it in the country's own language: "Vela Beach" in Croatia -> "Vela plaža".
- Keep the spelling, diacritics and capitalisation of the proper name. Never translate or invent a name; a name without the added "Beach" stays as it is.
Also give the same name in the script of Ukrainian (ua), Russian (ru) and Bulgarian (bg) - Cyrillic, with the usual transliteration of each language - and of Greek (el). A Greek beach keeps its own Greek name in el.
TXT;

        $user = json_encode([
            'name' => $source->name,
            'country' => $this->enName($beach->country),
            'region' => $this->enName($beach->region),
            'island' => $this->enName($beach->island),
            'town' => $this->enName($beach->city),
        ], JSON_UNESCAPED_UNICODE);

        $props = ['local_name' => ['type' => 'string', 'description' => 'The name in Latin script, as written locally']];
        foreach (self::NON_LATIN as $l) {
            $props[$l] = ['type' => 'string', 'description' => 'The name in '.self::LANGUAGE_NAMES[$l].' script'];
        }

        return [$system, $user, $this->objectSchema($props), 1500];
    }

    /** @return array{0: string, 1: string, 2: array<string, mixed>, 3: int}|null */
    private function regionsPrompt(Region $region): ?array
    {
        $region->loadMissing(['translations', 'country.translations']);
        $en = $region->translations->firstWhere('locale', 'en') ?? $region->translations->first();
        if (! $en) {
            return null;
        }

        $system = <<<'TXT'
You translate tourist region names for Beachrex, a beach guide published in 19 languages.
Names often combine a historical region and a town, e.g. "Dalmatia - Šibenik". Write each name the way travel guides in that language write it:
- translate the region word when the language has a usual form of it (Dalmatia -> "Dalmacija" in Slovenian and Croatian, "Dalmatien" in German, "Dalmazia" in Italian);
- keep town and island names in their local spelling; write them in Cyrillic for Ukrainian, Russian and Bulgarian and in Greek script for Greek;
- keep the "Region - Town" pattern with a spaced hyphen; add no words.
TXT;

        $user = json_encode(['name' => $en->name, 'country' => $this->enName($region->country)], JSON_UNESCAPED_UNICODE);

        return [$system, $user, $this->localeSchema(self::locales(), 'The region name in'), 2500];
    }

    /** @return array{0: string, 1: string, 2: array<string, mixed>, 3: int}|null */
    private function descriptionsPrompt(Beach $beach): ?array
    {
        $beach->loadMissing('translations');
        $source = $this->descriptionSource($beach);
        $targets = $this->localesWithoutDescription($beach);
        if (! $source || $targets === []) {
            return null;
        }

        $sourceLanguage = self::LANGUAGE_NAMES[$source->locale] ?? 'English';
        $system = <<<TXT
You translate beach descriptions for Beachrex, a beach guide. Translate the text from {$sourceLanguage} into each requested language.
Keep the meaning, the facts and the paragraph breaks; add nothing and leave nothing out. Use the beach name given for each language in name_by_language, and place names in that language's usual form. Plain text, no markdown.
TXT;

        $user = json_encode([
            'name_by_language' => array_intersect_key($this->namesByLanguage($beach), array_flip($targets)),
            'text' => $source->description,
            'languages' => array_map(fn ($l) => self::LANGUAGE_NAMES[$l], $targets),
        ], JSON_UNESCAPED_UNICODE);

        // ~1.5 tokens per character for the longest scripts, per language, plus room for thinking.
        $maxTokens = min(64000, 4000 + count($targets) * (int) ceil(mb_strlen((string) $source->description) * 1.5));

        return [$system, $user, $this->localeSchema($targets, 'The text in'), $maxTokens];
    }

    /** @return array{0: string, 1: string, 2: array<string, mixed>, 3: int}|null */
    private function generatePrompt(Beach $beach): ?array
    {
        $beach->loadMissing('translations');
        $targets = $this->localesWithoutDescription($beach);
        if ($targets === []) {
            return null;
        }
        $featured = $this->isFeatured($beach);
        $facts = $this->facts($beach) + ['opening_angle' => $this->angle($beach)];

        $intro = $featured
            ? '90-150 words in 3-6 sentences. This is one of the most visited beaches: give the town, island or area more room (what it is known for, what else is close), within the source rules.'
            : '50-80 words in 2-4 sentences: longer when there are many facts, shorter when there are few.';
        $faqCount = $featured ? '5' : '3-4';

        $system = self::SEO_INTRO.<<<TXT

You write three things for each language: a search snippet ("meta"), the intro text ("description") and frequently asked questions ("faq").

meta:
- One sentence of at most 155 characters (count them) for the search result. Name the beach, its town or island, and the word for "beach" in that language; say what kind of beach it is and who it suits. No quotes, no exclamation marks, no clickbait.

description:
- {$intro}
- Open with the angle given in opening_angle, written from the facts; never open with a generic "X is a beach in Y".
- Write about the character of the place: surface, setting, water, who it suits, what to do there. Use nearby facts (town centre distance, other beaches in the town or close by) when they help a visitor decide.
- Leave out logistics: parking, the road to the parking, means of transport and lists of facilities are shown on the page and go into the FAQ instead.
- Mention the area once, naturally ("in southern Dalmatia, near Dubrovnik"); do not copy region labels such as "Dalmatia - Dubrovnik".

faq:
- {$faqCount} questions a visitor would type into a search engine or ask an AI assistant about this beach, each answered from the facts. Good topics: small children and shallow water, how deep the water gets, shade, parking and access, dogs, food and drinks, snorkelling or water sports, how busy it is, beaches nearby. Ask only questions the facts answer; never answer "unknown" or guess.
- Every question names the beach. Every answer starts with the direct answer ("Yes, ...", "About 10 m from the shore, ...") in one or two sentences that make sense on their own, and names the beach again.
TXT.self::SEO_RULES;

        $schema = $this->localeObjectSchema($targets, withDescription: true);

        return [$system, json_encode($facts, JSON_UNESCAPED_UNICODE), $schema, 32000];
    }

    /** TASK-007: search snippet and FAQ for a beach whose description a person (or an earlier pass) wrote. */
    private function seoPrompt(Beach $beach): ?array
    {
        $beach->loadMissing('translations');
        $source = $this->descriptionSource($beach);
        $targets = $this->localesWithoutMeta($beach);
        if (! $source || $targets === []) {
            return null;
        }
        $faqCount = $this->isFeatured($beach) ? '5' : '3-4';
        $facts = $this->facts($beach) + ['description' => $source->description];

        $system = self::SEO_INTRO.<<<TXT

The beach already has a description (in "description"). You write two things for each language: a search snippet ("meta") and frequently asked questions ("faq"). The description and the facts are your only sources about the beach itself; when they disagree, use the facts.

meta:
- One sentence of at most 155 characters (count them) for the search result. Name the beach, its town or island, and the word for "beach" in that language; say what kind of beach it is and who it suits. No quotes, no exclamation marks, no clickbait.

faq:
- {$faqCount} questions a visitor would type into a search engine or ask an AI assistant about this beach, each answered from the facts or the description. Good topics: small children and shallow water, how deep the water gets, shade, parking and access, dogs, food and drinks, snorkelling or water sports, how busy it is, beaches nearby. Ask only questions the sources answer; never answer "unknown" or guess.
- Every question names the beach. Every answer starts with the direct answer ("Yes, ...", "About 10 m from the shore, ...") in one or two sentences that make sense on their own, and names the beach again.
TXT.self::SEO_RULES;

        return [$system, json_encode($facts, JSON_UNESCAPED_UNICODE), $this->localeObjectSchema($targets, withDescription: false), 24000];
    }

    private const SEO_INTRO = <<<'TXT'
You write beach texts for Beachrex, a beach guide published in 19 languages. The texts are read by people and by search engines and AI assistants that quote them, so every statement must be specific, correct and able to stand on its own. Each beach page already shows the facilities as icons.

Sources:
- The facts given for this beach, including the computed ones (town_centre, beaches_in_town, nearest_beaches). Add nothing about the beach itself that is not in them: no water colour, history, ratings, prices, opening times or seasons, and no distances other than the given ones.
- Well-known, stable facts about the town, island or area (for example what an island is known for), only when you are certain of them; when unsure, leave them out. Never present them as facts about the beach.
TXT;

    private const SEO_RULES = <<<'TXT'

All texts:
- shallow_water is already a finished statement about where the water becomes 1 m deep; keep its meaning exactly and never turn the distance into a depth.
- Avoid stock phrases such as "hidden gem", "perfect for", "nestled", "paradise", "crystal-clear", "something for everyone".
- Leave out trivial amenities such as rubbish bins.
- Use the beach name given for each language in name_by_language, and place names in that language's usual form; other beaches keep the names given in nearest_beaches (in that language's script when given).
- Write each language natively rather than word for word; every language carries the same facts. Plain text, no markdown.
TXT;

    /** Facts for `generate`, in English, empty ones left out. */
    public function facts(Beach $beach): array
    {
        $beach->loadMissing([
            'translations', 'country.translations', 'region.translations', 'island.translations', 'city.translations',
            'waterbody.translations', 'beachType.translations', 'accessibility.translations', 'occupancyRate.translations',
            'beachInclination.translations', 'swimmingDepthOfOneM.translations', 'parkingCapacity.translations',
            'culinaryOffers.translations', 'locationInfrastructures.translations', 'naturalLocationConditions.translations',
            'sportingActivities.translations', 'userTypes.translations', 'accommodationArounds.translations',
            'underwaterCharacteristics.translations', 'areaDescription.translations', 'locationLength.translations',
            'bayLength.translations', 'parkingBeachDistance.translations', 'roadToParkingType.translations',
            'recommendedTransportTypes.translations', 'beachLocationTypes.translations', 'waterBodyType.translations',
        ]);

        $list = fn ($items) => $items->map(fn ($m) => $this->enName($m))->filter()->values()->all();

        return array_filter([
            'name' => $this->displayName($this->sourceTranslation($beach)?->name ?? ''),
            'name_by_language' => $this->namesByLanguage($beach),
            'country' => $this->enName($beach->country),
            'region' => $this->enName($beach->region),
            'island' => $this->enName($beach->island),
            'town' => $this->enName($beach->city),
            'water' => $this->enName($beach->waterbody),
            'surface' => $this->enName($beach->beachType),
            'access' => $this->enName($beach->accessibility),
            'crowds' => $this->enName($beach->occupancyRate),
            'slope' => $this->enName($beach->beachInclination),
            'shallow_water' => $this->depthSentence($beach->swimmingDepthOfOneM?->code),
            'parking' => $this->enName($beach->parkingCapacity),
            'facilities' => $list($beach->locationInfrastructures),
            'food_and_drink' => $list($beach->culinaryOffers),
            'natural_conditions' => $list($beach->naturalLocationConditions),
            'activities' => $list($beach->sportingActivities),
            'suits' => $list($beach->userTypes),
            'accommodation_nearby' => $list($beach->accommodationArounds),
            'underwater' => $list($beach->underwaterCharacteristics),
            'area' => $this->enName($beach->areaDescription),
            'setting' => $list($beach->beachLocationTypes),
            'water_type' => $this->enName($beach->waterBodyType),
            'beach_length' => $this->enName($beach->locationLength),
            'bay_length' => $this->enName($beach->bayLength),
            'parking_distance' => $this->enName($beach->parkingBeachDistance),
            'road_to_parking' => $this->enName($beach->roadToParkingType),
            'best_reached_by' => $list($beach->recommendedTransportTypes),
            'town_centre' => $this->townCentre($beach),
            'beaches_in_town' => $this->beachesInTown($beach),
            'nearest_beaches' => $this->nearestBeaches($beach),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    // ------------------------------------------------------------------ computed context (TASK-007)

    /** "about 1.2 km from the centre of Povljana", from the beach and city coordinates (null when far or unknown). */
    public function townCentre(Beach $beach): ?string
    {
        $city = $beach->city;
        if (! $city || $city->latitude === null || $city->longitude === null || $beach->latitude === null || $beach->longitude === null) {
            return null;
        }
        $km = $this->km((float) $beach->latitude, (float) $beach->longitude, (float) $city->latitude, (float) $city->longitude);
        if ($km > 25) {
            return null; // the city point is probably wrong or the town is only the municipality
        }
        $town = $this->enName($city);

        return $km < 0.2
            ? "in the centre of {$town}"
            : 'about '.$this->distance($km)." from the centre of {$town}";
    }

    /** "one of 7 beaches in Povljana listed on Beachrex" (null for fewer than two). */
    public function beachesInTown(Beach $beach): ?string
    {
        if (! $beach->city_id) {
            return null;
        }
        $count = Beach::query()->where('status_id', 1)->where('city_id', $beach->city_id)->count();

        return $count >= 2 ? "one of {$count} beaches in ".$this->enName($beach->city).' listed on Beachrex' : null;
    }

    /**
     * The nearest active beaches within NEARBY_KM: name (also in Cyrillic/Greek when stored), distance and surface.
     *
     * @return list<array<string, mixed>>
     */
    public function nearestBeaches(Beach $beach): array
    {
        if ($beach->latitude === null || $beach->longitude === null) {
            return [];
        }
        [$lat, $lng] = [(float) $beach->latitude, (float) $beach->longitude];
        $dLat = self::NEARBY_KM / 111.0;
        $dLng = self::NEARBY_KM / max(1.0, 111.0 * cos(deg2rad($lat)));

        $candidates = Beach::query()
            ->with(['translations', 'beachType.translations'])
            ->where('status_id', 1)->whereKeyNot($beach->getKey())
            ->whereBetween('latitude', [$lat - $dLat, $lat + $dLat])
            ->whereBetween('longitude', [$lng - $dLng, $lng + $dLng])
            ->get();

        return $candidates
            ->map(fn (Beach $b) => [$b, $this->km($lat, $lng, (float) $b->latitude, (float) $b->longitude)])
            ->filter(fn ($pair) => $pair[1] <= self::NEARBY_KM)
            ->sortBy(fn ($pair) => $pair[1])
            ->take(self::NEARBY_COUNT)
            ->map(function ($pair) {
                [$b, $km] = $pair;
                $source = $this->sourceTranslation($b);
                $scripts = [];
                foreach (self::NON_LATIN as $l) {
                    $name = $b->translations->firstWhere('locale', $l)?->name;
                    if ($name !== null && $name !== $source?->name) {
                        $scripts[$l] = $this->displayName($name);
                    }
                }

                return array_filter([
                    'name' => $source ? $this->displayName($source->name) : null,
                    'name_in_script' => $scripts,
                    'distance' => 'about '.$this->distance($km),
                    'surface' => $this->enName($b->beachType),
                ], fn ($v) => $v !== null && $v !== []);
            })
            ->filter(fn ($b) => isset($b['name']))
            ->values()->all();
    }

    /** One of the most viewed active beaches (config ai.content.featured). */
    public function isFeatured(Beach $beach): bool
    {
        if ($this->featuredIds === null) {
            $count = max(0, (int) config('ai.content.featured', 200));
            $this->featuredIds = $count === 0 ? [] : array_fill_keys(
                Beach::query()->where('status_id', 1)->where('beach_views', '>', 0)
                    ->orderByDesc('beach_views')->orderBy('id')->limit($count)->pluck('id')->all(),
                true,
            );
        }

        return isset($this->featuredIds[$beach->getKey()]);
    }

    /** The opening angle for a beach: stable per beach, varied across neighbours. */
    public function angle(Beach $beach): string
    {
        return self::ANGLES[$beach->getKey() % count(self::ANGLES)];
    }

    private function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    /** 0.43 -> "400 m", 1.26 -> "1.3 km", 12.4 -> "12 km". */
    private function distance(float $km): string
    {
        if ($km < 1) {
            return (string) max(100, (int) round($km * 10) * 100).' m';
        }

        return $km < 10 ? number_format($km, 1, '.', '').' km' : (string) (int) round($km).' km';
    }

    // ------------------------------------------------------------------ apply

    /**
     * Validate one model answer and write it (or, with $dryRun, only describe the change).
     *
     * @return array{status: 'written'|'skipped'|'invalid', changes: list<string>, reason?: string}
     */
    public function apply(string $customId, string $json, bool $dryRun, int $previewChars = 80): array
    {
        if (! preg_match('/^('.implode('|', self::PARTS).')-(\d+)$/', $customId, $m)) {
            return ['status' => 'invalid', 'changes' => [], 'reason' => "unknown request id {$customId}"];
        }
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return ['status' => 'invalid', 'changes' => [], 'reason' => 'answer is not JSON'];
        }

        [$part, $id] = [$m[1], (int) $m[2]];
        $changes = [];
        $result = DB::transaction(function () use ($part, $id, $data, $dryRun, $previewChars, &$changes) {
            return match ($part) {
                'names' => $this->applyNames($id, $data, $dryRun, $changes),
                'regions' => $this->applyRegions($id, $data, $dryRun, $changes),
                'descriptions' => $this->applyDescriptions($id, $data, $dryRun, $changes, $previewChars),
                'generate' => $this->applySeo($id, $data, $dryRun, $changes, $previewChars, withDescription: true),
                'seo' => $this->applySeo($id, $data, $dryRun, $changes, $previewChars, withDescription: false),
            };
        });

        if ($result !== null) {
            return ['status' => 'invalid', 'changes' => [], 'reason' => $result];
        }

        return ['status' => $changes === [] ? 'skipped' : 'written', 'changes' => $changes];
    }

    private function applyNames(int $id, array $data, bool $dryRun, array &$changes): ?string
    {
        $beach = Beach::with('translations')->find($id);
        $source = $beach ? $this->sourceTranslation($beach) : null;
        if (! $source) {
            return 'beach or its name not found';
        }

        $local = $this->cleanLine($data['local_name'] ?? null);
        if ($local === null || ! $this->sharesWord($local, $source->name)) {
            return 'name "'.($data['local_name'] ?? '').'" does not match "'.$source->name.'"';
        }
        // "Beach" stays only in the proper names of English-speaking countries ("Bondi Beach").
        if (preg_match('/\bbeach\b/i', $local) && ! in_array($beach->country_code, self::ENGLISH_NAME_COUNTRIES, true)) {
            return "name \"{$local}\" still has the English word Beach";
        }

        $names = [];
        foreach (self::locales() as $locale) {
            $names[$locale] = in_array($locale, self::NON_LATIN, true)
                ? ($this->cleanLine($data[$locale] ?? null) ?? $local)
                : $local;
        }

        foreach ($names as $locale => $name) {
            $row = $beach->translations->firstWhere('locale', $locale);
            if ($row) {
                $replaceable = preg_match('/\bbeach\b/i', $row->name) || $row->name === $source->name;
                if (! $replaceable || $row->name === $name) {
                    continue;
                }
                $changes[] = "{$locale}: \"{$row->name}\" -> \"{$name}\"";
                if (! $dryRun) {
                    $row->update(['name' => $name]);
                }
            } else {
                $changes[] = "{$locale}: (new) \"{$name}\"";
                if (! $dryRun) {
                    BeachTranslation::create([
                        'beach_id' => $beach->id, 'locale' => $locale, 'name' => $name,
                        'slug' => $source->slug, 'description' => null,
                    ]);
                }
            }
        }

        return null;
    }

    private function applyRegions(int $id, array $data, bool $dryRun, array &$changes): ?string
    {
        $region = Region::with('translations')->find($id);
        $en = $region?->translations->firstWhere('locale', 'en') ?? $region?->translations->first();
        if (! $en) {
            return 'region or its English name not found';
        }

        foreach (self::locales() as $locale) {
            if ($locale === 'en') {
                continue;
            }
            $name = $this->cleanLine($data[$locale] ?? null);
            if ($name === null) {
                return "no {$locale} name";
            }
            $row = $region->translations->firstWhere('locale', $locale);
            if ($row) {
                if ($row->name !== $en->name || $row->name === $name) {
                    continue; // a person already translated it
                }
                $changes[] = "{$locale}: \"{$row->name}\" -> \"{$name}\"";
                if (! $dryRun) {
                    $row->update(['name' => $name]);
                }
            } else {
                $changes[] = "{$locale}: (new) \"{$name}\"";
                if (! $dryRun) {
                    // The English slug, so region URLs do not change.
                    RegionTranslation::create(['region_id' => $region->id, 'locale' => $locale, 'name' => $name, 'slug' => $en->slug]);
                }
            }
        }

        return null;
    }

    private function applyDescriptions(int $id, array $data, bool $dryRun, array &$changes, int $previewChars = 80): ?string
    {
        $beach = Beach::with('translations')->find($id);
        $source = $beach ? $this->sourceTranslation($beach) : null;
        if (! $source) {
            return 'beach or its name not found';
        }

        foreach ($this->localesWithoutDescription($beach) as $locale) {
            $text = isset($data[$locale]) && is_string($data[$locale]) ? trim($data[$locale]) : '';
            if ($text === '') {
                continue;
            }
            if (mb_strlen($text) > self::DESCRIPTION_MAX) {
                return "{$locale} description is too long";
            }
            $row = $beach->translations->firstWhere('locale', $locale);
            $changes[] = "{$locale}: ".Str::limit($text, $previewChars);
            if ($dryRun) {
                continue;
            }
            if ($row) {
                $row->update(['description' => $text]);
            } else {
                BeachTranslation::create([
                    'beach_id' => $beach->id, 'locale' => $locale, 'name' => $source->name,
                    'slug' => $source->slug, 'description' => $text,
                ]);
            }
        }

        return null;
    }

    /**
     * TASK-007: per language {meta, description?, faq}. Each field is written only where that language has none;
     * a snippet that is far too long or an FAQ with fewer than two valid items is left out (the `seo` part retries it).
     */
    private function applySeo(int $id, array $data, bool $dryRun, array &$changes, int $previewChars, bool $withDescription): ?string
    {
        $beach = Beach::with('translations')->find($id);
        $source = $beach ? $this->sourceTranslation($beach) : null;
        if (! $source) {
            return 'beach or its name not found';
        }

        foreach (self::locales() as $locale) {
            $answer = $data[$locale] ?? null;
            if (! is_array($answer)) {
                continue;
            }
            $row = $beach->translations->firstWhere('locale', $locale);
            $update = [];

            if ($withDescription && trim((string) $row?->description) === '') {
                $text = is_string($answer['description'] ?? null) ? trim($answer['description']) : '';
                if (mb_strlen($text) > self::DESCRIPTION_MAX) {
                    return "{$locale} description is too long";
                }
                if ($text !== '') {
                    $update['description'] = $text;
                    $changes[] = "{$locale}: ".Str::limit($text, $previewChars);
                }
            }

            if (trim((string) $row?->meta_description) === '') {
                $meta = is_string($answer['meta'] ?? null) ? trim(preg_replace('/\s+/u', ' ', $answer['meta']) ?? '') : '';
                if (mb_strlen($meta) > self::META_MAX) {
                    $changes[] = "{$locale}: [meta left out: ".mb_strlen($meta).' characters]';
                } elseif ($meta !== '') {
                    $update['meta_description'] = $meta;
                    $changes[] = "{$locale}: [meta] ".Str::limit($meta, $previewChars);
                }
            }

            if (empty($row?->faq)) {
                $faq = $this->cleanFaq($answer['faq'] ?? null);
                if (count($faq) >= 2) {
                    $update['faq'] = $faq;
                    foreach ($faq as $item) {
                        $changes[] = "{$locale}: [faq] {$item['q']} — ".Str::limit($item['a'], $previewChars);
                    }
                } elseif (isset($answer['faq'])) {
                    $changes[] = "{$locale}: [faq left out: fewer than two valid questions]";
                }
            }

            if ($update === [] || $dryRun) {
                continue;
            }
            if ($row) {
                $row->update($update);
            } else {
                BeachTranslation::create([
                    'beach_id' => $beach->id, 'locale' => $locale, 'name' => $source->name, 'slug' => $source->slug,
                    'description' => null,
                ] + $update);
            }
        }

        return null;
    }

    /** @return list<array{q: string, a: string}> */
    public function cleanFaq(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }
        $clean = [];
        foreach ($items as $item) {
            $q = is_array($item) && is_string($item['q'] ?? null) ? trim($item['q']) : '';
            $a = is_array($item) && is_string($item['a'] ?? null) ? trim($item['a']) : '';
            if ($q === '' || $a === '' || mb_strlen($q) > self::FAQ_QUESTION_MAX || mb_strlen($a) > self::FAQ_ANSWER_MAX) {
                continue;
            }
            $clean[] = ['q' => $q, 'a' => $a];
        }

        return array_slice($clean, 0, 6);
    }

    // ------------------------------------------------------------------ helpers

    /** The English row, else Croatian, else any: the name and slug new rows copy. */
    public function sourceTranslation(Beach $beach): ?BeachTranslation
    {
        return $beach->translations->firstWhere('locale', 'en')
            ?? $beach->translations->firstWhere('locale', 'hr')
            ?? $beach->translations->first();
    }

    /** The description to translate from: English if it has one, else the longest. */
    public function descriptionSource(Beach $beach): ?BeachTranslation
    {
        $withText = $beach->translations->filter(fn ($t) => trim((string) $t->description) !== '');

        return $withText->firstWhere('locale', 'en') ?? $withText->sortByDesc(fn ($t) => mb_strlen($t->description))->first();
    }

    /** @return list<string> */
    public function localesWithoutDescription(Beach $beach): array
    {
        return array_values(array_filter(self::locales(), function ($locale) use ($beach) {
            return trim((string) $beach->translations->firstWhere('locale', $locale)?->description) === '';
        }));
    }

    /** @return list<string> */
    public function localesWithoutMeta(Beach $beach): array
    {
        return array_values(array_filter(self::locales(), function ($locale) use ($beach) {
            $row = $beach->translations->firstWhere('locale', $locale);

            return trim((string) $row?->meta_description) === '' || empty($row?->faq);
        }));
    }

    /**
     * `swimming_depth_of_one_m` codes ("0m", "5m", "0m_10m", "more_100m") as a sentence: the model read
     * the bare "5m" as a depth (owner preview 2026-10-09), it is the distance from the shore to 1 m depth.
     */
    public function depthSentence(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }
        if ($code === '0m') {
            return 'The water is about 1 m deep right at the shore (it gets deep straight away).';
        }
        if ($code === 'more_100m') {
            return 'The water stays shallower than 1 m for more than 100 m from the shore (very shallow, good for small children).';
        }
        if (preg_match('/^0m_(\d+)m(_plus)?$/', $code, $m)) {
            return "The water becomes 1 m deep somewhere within the first {$m[1]} m from the shore.";
        }
        if (preg_match('/^(\d+)m$/', $code, $m)) {
            $shallow = (int) $m[1] >= 30 ? ' (a long shallow stretch, good for small children)' : '';

            return "The water becomes 1 m deep about {$m[1]} m from the shore{$shallow}.";
        }

        return null;
    }

    /** The beach name per language as stored (names part first), without the import's "Beach". */
    public function namesByLanguage(Beach $beach): array
    {
        $source = $this->sourceTranslation($beach);
        $names = [];
        foreach (self::locales() as $locale) {
            $name = $beach->translations->firstWhere('locale', $locale)?->name ?? $source?->name;
            if ($name !== null) {
                $names[$locale] = $this->displayName($name);
            }
        }

        return $names;
    }

    /** "Beach Slanica" -> "Slanica" for prompts that only need to mention the beach. */
    public function displayName(string $name): string
    {
        $clean = trim(preg_replace('/^\s*beach\s+|\s+beach\s*$/iu', '', $name) ?? $name);

        return $clean !== '' ? $clean : $name;
    }

    private function enName(?Model $model): ?string
    {
        if (! $model) {
            return null;
        }
        $translations = $model->relationLoaded('translations') ? $model->translations : $model->translations()->get();

        return $translations->firstWhere('locale', 'en')?->name ?? $translations->first()?->name ?? ($model->code ?? null);
    }

    private function cleanLine(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' || mb_strlen($value) > self::NAME_MAX ? null : $value;
    }

    /** At least one word of 3+ letters in common, ignoring case and diacritics (guards against a wrong name). */
    private function sharesWord(string $a, string $b): bool
    {
        $words = fn (string $s) => array_filter(
            preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($s))) ?: [],
            fn ($w) => strlen($w) >= 3 && $w !== 'beach',
        );
        $sourceWords = $words($b);

        return $sourceWords === [] || array_intersect($words($a), $sourceWords) !== [];
    }

    /** @param list<string> $locales */
    private function localeSchema(array $locales, string $what): array
    {
        $props = [];
        foreach ($locales as $l) {
            $props[$l] = ['type' => 'string', 'description' => "{$what} ".self::LANGUAGE_NAMES[$l]];
        }

        return $this->objectSchema($props);
    }

    /** TASK-007: per language an object {meta, description?, faq: [{q, a}]}. */
    private function localeObjectSchema(array $locales, bool $withDescription): array
    {
        $faq = [
            'type' => 'array',
            'items' => $this->objectSchema([
                'q' => ['type' => 'string', 'description' => 'The question'],
                'a' => ['type' => 'string', 'description' => 'The answer, starting with the direct answer'],
            ]),
        ];
        $props = [];
        foreach ($locales as $l) {
            $name = self::LANGUAGE_NAMES[$l];
            $fields = ['meta' => ['type' => 'string', 'description' => "Search snippet in {$name}, at most 155 characters"]];
            if ($withDescription) {
                $fields['description'] = ['type' => 'string', 'description' => "Intro text in {$name}"];
            }
            $fields['faq'] = $faq + ['description' => "Questions and answers in {$name}"];
            $props[$l] = $this->objectSchema($fields);
        }

        return $this->objectSchema($props);
    }

    private function objectSchema(array $props): array
    {
        return ['type' => 'object', 'properties' => $props, 'required' => array_keys($props), 'additionalProperties' => false];
    }
}
