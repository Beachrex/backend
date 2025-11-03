<?php

namespace App\Exports;

use App\Models\Beach;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Database\Eloquent\Builder;

class BeachesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $query;

    public function __construct($query = null)
    {
        $this->query = $query;
    }

    public function query()
    {
        $query = $this->query ?? Beach::query();
        
        return $query->with([
            'translations',
            'status',
            'beachType',
            'accessibility',
            'occupancyRate',
            'waterbody',
            'country.translations',
            'region.translations',
            'city.translations',
            'user',
        ]);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Beach Name (EN)',
            'Folder Name',
            'Country',
            'Region',
            'City',
            'Latitude',
            'Longitude',
            'Status',
            'Beach Type',
            'Accessibility',
            'Occupancy Rate',
            'Waterbody',
            'User',
            'Translations Count',
            'Has GPS',
            'Has Media',
            'Created At',
            'Updated At',
        ];
    }

    public function map($beach): array
    {
        $beachName = $beach->translations()->where('locale', 'en')->first()?->name 
            ?? $beach->translations()->first()?->name 
            ?? '';
        
        $countryName = $beach->country 
            ? ($beach->country->translations()->where('locale', 'en')->first()?->name ?? $beach->country->code)
            : '';
        
        $regionName = $beach->region 
            ? ($beach->region->translations()->where('locale', 'en')->first()?->name ?? $beach->region->code)
            : '';
        
        $cityName = $beach->city 
            ? ($beach->city->translations()->where('locale', 'en')->first()?->name ?? $beach->city->code)
            : '';

        return [
            $beach->id,
            $beachName,
            $beach->folder_name,
            $countryName,
            $regionName,
            $cityName,
            $beach->latitude,
            $beach->longitude,
            $beach->status?->name ?? '',
            $beach->beachType?->name ?? '',
            $beach->accessibility?->name ?? '',
            $beach->occupancyRate?->name ?? '',
            $beach->waterbody?->name ?? '',
            $beach->user?->name ?? '',
            $beach->translations()->count() . '/14',
            $beach->latitude && $beach->longitude ? 'Yes' : 'No',
            $beach->media()->count() > 0 ? 'Yes' : 'No',
            $beach->created_at?->format('Y-m-d H:i:s'),
            $beach->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

