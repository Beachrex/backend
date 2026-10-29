<?php

namespace App\Filament\BrAdmin\Resources\PlaceWebcamResource\Pages;

use App\Filament\BrAdmin\Resources\PlaceWebcamResource;
use App\Services\Webcams\PlaceWebcamReview;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;

class ListPlaceWebcams extends ListRecords
{
    protected static string $resource = PlaceWebcamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('add')->label('Add webcam by hand')->icon('heroicon-o-plus')
                ->form([
                    Forms\Components\Select::make('place_id')->label('Place')->required()->searchable()
                        ->getSearchResultsUsing(fn (string $search) => \DB::table('beach')->where('name', 'ilike', "%{$search}%")
                            ->orderBy('name')->limit(30)->get(['id', 'name', 'country_code'])
                            ->mapWithKeys(fn ($b) => [$b->id => "{$b->name} ({$b->country_code}, #{$b->id})"])->all())
                        ->getOptionLabelUsing(fn ($value) => \DB::table('beach')->where('id', $value)->value('name')),
                    Forms\Components\Textarea::make('input')->label('Windy webcam id, link or embed code')->required()->rows(3),
                ])
                ->action(fn (array $data) => PlaceWebcamResource::run(
                    fn (PlaceWebcamReview $s) => $s->addManual((int) $data['place_id'], $data['input'], auth()->id()),
                    'Webcam added and approved'
                )),
        ];
    }
}
