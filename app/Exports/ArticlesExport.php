<?php

namespace App\Exports;

use App\Models\Article;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ArticlesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $query;

    public function __construct($query = null)
    {
        $this->query = $query;
    }

    public function query()
    {
        $query = $this->query ?? Article::query();
        
        return $query->with([
            'translations',
            'status',
            'user',
        ]);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Code',
            'Title (EN)',
            'Excerpt (EN)',
            'Category',
            'Status',
            'User',
            'Translations Count',
            'Created At',
            'Updated At',
        ];
    }

    public function map($article): array
    {
        $title = $article->translations()->where('locale', 'en')->first()?->title 
            ?? $article->translations()->first()?->title 
            ?? '';
        
        $excerpt = $article->translations()->where('locale', 'en')->first()?->excerpt 
            ?? $article->translations()->first()?->excerpt 
            ?? '';

        return [
            $article->id,
            $article->code,
            $title,
            $excerpt,
            $article->category,
            $article->status?->name ?? '',
            $article->user?->name ?? '',
            $article->translations()->count() . '/14',
            $article->created_at?->format('Y-m-d H:i:s'),
            $article->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

