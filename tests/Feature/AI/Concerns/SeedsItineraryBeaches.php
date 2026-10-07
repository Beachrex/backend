<?php

namespace Tests\Feature\AI\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Seeds 12 real-looking Croatian beaches in 3 areas (Istria, Split, Dubrovnik — 150-400 km apart,
 * beaches within an area a few km apart) with beach types, facilities, user types, activities and
 * en + sl translations. Returns beach ids keyed by name.
 */
trait SeedsItineraryBeaches
{
    /** @var array<string,int> */
    protected array $beachIds = [];

    /** @var array<string,string> beach name => area */
    protected array $beachArea = [];

    protected function seedItineraryBeaches(): array
    {
        DB::table('status')->insertOrIgnore([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        DB::table('countries')->insertOrIgnore(['code' => 'HR']);

        $type = fn (string $code, string $en, string $sl) => tap(DB::table('beach_type')->insertGetId(['code' => $code]), fn ($id) =>
            DB::table('beach_type_translation')->insert([
                ['beach_type_id' => $id, 'locale' => 'en', 'name' => $en],
                ['beach_type_id' => $id, 'locale' => 'sl', 'name' => $sl],
            ]));
        $types = [
            'sand' => $type('sand', 'Sand', 'Pesek'),
            'small_stones' => $type('small_stones', 'Small stones', 'Drobni kamenčki'),
            'rock_beach' => $type('rock_beach', 'Rock', 'Skale'),
        ];

        $infra = [];
        foreach (['lifeguard' => ['Lifeguard', 'Reševalec'], 'parking' => ['Parking', 'Parkirišče'],
                  'children_playground' => ['Children playground', 'Otroško igrišče'],
                  'access_for_disabled' => ['Access for disabled', 'Dostop za invalide']] as $code => [$en, $sl]) {
            $id = DB::table('location_infrastructure')->insertGetId(['code' => $code, 'status_id' => 1]);
            DB::table('location_infrastructure_translation')->insert([
                ['location_infrastructure_id' => $id, 'locale' => 'en', 'name' => $en],
                ['location_infrastructure_id' => $id, 'locale' => 'sl', 'name' => $sl],
            ]);
            $infra[$code] = $id;
        }
        $family = DB::table('user_type')->insertGetId(['code' => 'family']);
        $snorkeling = DB::table('sporting_activities')->insertGetId(['code' => 'snorkeling']);

        $areas = [
            'istria' => ['region' => 'istria', 'city' => 'Rovinj', 'lat' => 45.08, 'lon' => 13.64],
            'split' => ['region' => 'split-dalmatia', 'city' => 'Split', 'lat' => 43.51, 'lon' => 16.44],
            'dubrovnik' => ['region' => 'dubrovnik-neretva', 'city' => 'Dubrovnik', 'lat' => 42.65, 'lon' => 18.09],
        ];
        // name => [area, offset index, type, facilities, family?, snorkeling?, views]
        $beaches = [
            'Mulini' => ['istria', 0, 'small_stones', ['parking'], false, true, 900],
            'Cuvi' => ['istria', 1, 'rock_beach', [], false, true, 400],
            'Lone' => ['istria', 2, 'small_stones', ['parking', 'lifeguard'], true, false, 700],
            'Borik' => ['istria', 3, 'rock_beach', [], false, false, 100],
            'Bacvice' => ['split', 0, 'sand', ['lifeguard', 'children_playground', 'parking'], true, false, 1000],
            'Znjan' => ['split', 1, 'sand', ['lifeguard', 'children_playground'], true, false, 800],
            'Kasjuni' => ['split', 2, 'small_stones', ['parking'], false, true, 600],
            'Trstenik' => ['split', 3, 'small_stones', [], false, false, 300],
            'Banje' => ['dubrovnik', 0, 'small_stones', ['lifeguard', 'access_for_disabled'], true, false, 950],
            'Lapad' => ['dubrovnik', 1, 'small_stones', ['parking', 'access_for_disabled'], true, false, 650],
            'Sveti Jakov' => ['dubrovnik', 2, 'small_stones', [], false, true, 500],
            'Copacabana' => ['dubrovnik', 3, 'small_stones', ['parking', 'lifeguard'], true, false, 450],
        ];
        $offsets = [[0, 0], [0.02, 0.01], [-0.015, 0.02], [0.01, -0.025]];

        $regionIds = $cityIds = [];
        foreach ($areas as $key => $a) {
            $regionIds[$key] = DB::table('region')->insertGetId(['code' => 'HR-' . strtoupper($key)]);
            DB::table('region_translation')->insert(['region_id' => $regionIds[$key], 'locale' => 'en', 'name' => ucfirst($a['region']), 'slug' => $a['region']]);
            $cityIds[$key] = DB::table('city')->insertGetId(['code' => strtolower($a['city'])]);
            DB::table('city_translation')->insert(['city_id' => $cityIds[$key], 'locale' => 'en', 'name' => $a['city'], 'slug' => strtolower($a['city'])]);
        }

        foreach ($beaches as $name => [$area, $i, $typeCode, $facilities, $isFamily, $hasSnorkeling, $views]) {
            $a = $areas[$area];
            $id = DB::table('beach')->insertGetId([
                'name' => $name,
                'latitude' => $a['lat'] + $offsets[$i][0],
                'longitude' => $a['lon'] + $offsets[$i][1],
                'country_code' => 'HR',
                'region_id' => $regionIds[$area],
                'city_id' => $cityIds[$area],
                'beach_type_id' => $types[$typeCode],
                'beach_views' => $views,
                'status_id' => 1,
            ]);
            $slug = strtolower(str_replace(' ', '-', $name));
            DB::table('beach_translation')->insert([
                ['beach_id' => $id, 'locale' => 'en', 'name' => "Beach {$name}", 'slug' => "beach-{$slug}", 'description' => "<p>{$name} is a beach in {$a['city']}.</p>"],
                ['beach_id' => $id, 'locale' => 'sl', 'name' => "Plaža {$name}", 'slug' => "plaza-{$slug}", 'description' => null],
            ]);
            foreach ($facilities as $code) {
                DB::table('location_infrastructure_beach')->insert(['beach_id' => $id, 'location_infrastructure_id' => $infra[$code]]);
            }
            if ($isFamily) {
                DB::table('user_type_beach')->insert(['beach_id' => $id, 'user_type_id' => $family]);
            }
            if ($hasSnorkeling) {
                DB::table('sporting_activities_beach')->insert(['beach_id' => $id, 'sporting_activities_id' => $snorkeling]);
            }
            $this->beachIds[$name] = $id;
            $this->beachArea[$name] = $area;
        }

        return $this->beachIds;
    }

    protected function areaOfBeachId(int $id): string
    {
        return $this->beachArea[array_search($id, $this->beachIds, true)];
    }
}
