<?php

namespace App\Filament\BrAdmin\Resources;

use App\Filament\BrAdmin\Resources\PlaceWebcamResource\Pages;
use App\Models\PlaceWebcam;
use App\Services\Webcams\PlaceWebcamReview;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * TASK-006 §7: owner review of Windy webcam assignments. No Windy API calls here, except one verify call
 * when a webcam is added by hand.
 */
class PlaceWebcamResource extends Resource
{
    protected static ?string $model = PlaceWebcam::class;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Webcam review';

    protected static ?string $modelLabel = 'webcam assignment';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['place:id,name,country_code,latitude,longitude,waterbody_id', 'webcam']))
            ->defaultSort('match_score', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('place.name')->label('Place')->searchable()
                    ->description(fn (PlaceWebcam $r) => "#{$r->place_id} · {$r->place?->country_code}")
                    ->url(fn (PlaceWebcam $r) => $r->place ? "https://www.openstreetmap.org/?mlat={$r->place->latitude}&mlon={$r->place->longitude}#map=16/{$r->place->latitude}/{$r->place->longitude}" : null, true),
                Tables\Columns\TextColumn::make('webcam.title')->label('Webcam')->wrap()->limit(70)
                    ->description(fn (PlaceWebcam $r) => (string) $r->windy_id)
                    ->url(fn (PlaceWebcam $r) => $r->webcam?->publicUrl(), true),
                Tables\Columns\TextColumn::make('distance_m')->label('Distance')->suffix(' m')->sortable(),
                Tables\Columns\TextColumn::make('match_score')->label('Score')->sortable()->badge()
                    ->color(fn (int $state) => $state >= 70 ? 'success' : ($state >= 40 ? 'warning' : 'gray')),
                Tables\Columns\TextColumn::make('score_breakdown')->label('Why')->wrap()
                    ->formatStateUsing(fn ($state, PlaceWebcam $r) => self::why($r->score_breakdown ?? [])),
                Tables\Columns\TextColumn::make('review_status')->label('Status')->badge(),
                Tables\Columns\TextColumn::make('source')->label('Source')->badge()->color('gray'),
                Tables\Columns\IconColumn::make('is_primary')->label('Primary')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('review_status')->label('Status')->default(PlaceWebcam::PENDING)->options([
                    PlaceWebcam::PENDING => 'Pending', PlaceWebcam::AUTO_APPROVED => 'Auto-approved',
                    PlaceWebcam::APPROVED => 'Approved', PlaceWebcam::REJECTED => 'Rejected',
                ]),
                Tables\Filters\SelectFilter::make('country')->label('Country')
                    ->options(fn () => \DB::table('beach')->whereNotNull('country_code')->distinct()->orderBy('country_code')->pluck('country_code', 'country_code')->all())
                    ->query(fn (Builder $query, array $data) => $data['value'] ? $query->whereHas('place', fn ($p) => $p->where('country_code', $data['value'])) : $query),
                Tables\Filters\SelectFilter::make('waterbody')->label('Place type')
                    ->options(fn () => \DB::table('waterbody')->orderBy('id')->pluck('code', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $data['value'] ? $query->whereHas('place', fn ($p) => $p->where('waterbody_id', $data['value'])) : $query),
                Tables\Filters\SelectFilter::make('source')->options(['legacy' => 'Legacy', 'matcher' => 'Matcher', 'manual' => 'Manual']),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')->label('Preview')->icon('heroicon-o-play')->color('gray')
                    ->modalHeading(fn (PlaceWebcam $r) => $r->webcam?->title ?? 'Webcam')
                    ->modalContent(fn (PlaceWebcam $r) => view('filament.webcams.preview', ['id' => $r->windy_id, 'url' => $r->webcam?->publicUrl()]))
                    ->modalSubmitAction(false),
                Tables\Actions\Action::make('approve')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (PlaceWebcam $r) => $r->review_status !== PlaceWebcam::APPROVED)
                    ->action(fn (PlaceWebcam $r) => self::run(fn (PlaceWebcamReview $s) => $s->approve($r, auth()->id()), 'Approved')),
                Tables\Actions\Action::make('reject')->icon('heroicon-o-x-mark')->color('danger')->requiresConfirmation()
                    ->visible(fn (PlaceWebcam $r) => $r->review_status !== PlaceWebcam::REJECTED)
                    ->action(fn (PlaceWebcam $r) => self::run(fn (PlaceWebcamReview $s) => $s->reject($r, auth()->id()), 'Rejected')),
                Tables\Actions\Action::make('primary')->label('Set primary')->icon('heroicon-o-star')
                    ->visible(fn (PlaceWebcam $r) => ! $r->is_primary && in_array($r->review_status, PlaceWebcam::VISIBLE, true))
                    ->action(fn (PlaceWebcam $r) => self::run(fn (PlaceWebcamReview $s) => $s->makePrimary($r, auth()->id()), 'Primary webcam set')),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('approve_all')->label('Approve')->icon('heroicon-o-check')
                    ->action(fn ($records) => $records->each(fn (PlaceWebcam $r) => self::run(fn (PlaceWebcamReview $s) => $s->approve($r, auth()->id()), null))),
                Tables\Actions\BulkAction::make('reject_all')->label('Reject')->icon('heroicon-o-x-mark')->color('danger')->requiresConfirmation()
                    ->action(fn ($records) => $records->each(fn (PlaceWebcam $r) => self::run(fn (PlaceWebcamReview $s) => $s->reject($r, auth()->id()), null))),
            ]);
    }

    /** One readable line from the score breakdown. */
    public static function why(array $b): string
    {
        $parts = ["distance +" . ($b['distance'] ?? 0)];
        if (! empty($b['category'])) {
            $parts[] = 'category +' . $b['category'];
        }
        if (! empty($b['category_penalty'])) {
            $parts[] = 'category ' . $b['category_penalty'];
        }
        if (! empty($b['name'])) {
            $parts[] = 'name +' . $b['name'] . ' (' . ($b['name_ratio'] ?? '') . ')';
        }
        if (! empty($b['town'])) {
            $parts[] = 'town +' . $b['town'];
        }
        if (array_key_exists('runner_up', $b) && $b['runner_up'] !== null) {
            $parts[] = 'runner-up ' . $b['runner_up'];
        }
        foreach ($b['notes'] ?? [] as $note) {
            $parts[] = $note;
        }

        return implode(' · ', $parts);
    }

    public static function run(\Closure $fn, ?string $ok): void
    {
        try {
            $fn(app(PlaceWebcamReview::class));
            if ($ok) {
                Notification::make()->title($ok)->success()->send();
            }
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPlaceWebcams::route('/')];
    }
}
