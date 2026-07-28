<?php

namespace App\Filament\BrAdmin\Resources;

use App\Filament\BrAdmin\Resources\NewsletterSubscriberResource\Pages;
use App\Models\NewsletterSubscriber;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Newsletter Subscribers';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Subscriber Details')->schema([
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->disabled(fn ($operation) => $operation === 'edit')
                    ->helperText('Email is locked after creation. To change it, delete and let the user re-subscribe.'),
                Forms\Components\Select::make('status_id')
                    ->label('Status')
                    ->relationship('status', 'name')
                    ->required()
                    ->default(1)
                    ->helperText('Active = subscribed, Disabled = unsubscribed')
                    ->searchable(),
                Forms\Components\Toggle::make('is_synced')
                    ->label('Synced to ESP (Klaviyo)')
                    ->default(false)
                    ->helperText('Mark after syncing to the email service provider'),
                Forms\Components\Toggle::make('consent_accepted')
                    ->label('GDPR Consent Accepted')
                    ->disabled()
                    ->helperText('Set by the subscribe endpoint when the user accepts the privacy policy.'),
                Forms\Components\TextInput::make('source')
                    ->label('Source')
                    ->maxLength(50)
                    ->helperText('Where the subscription came from (footer, popup, etc.)'),
                Forms\Components\TextInput::make('locale')
                    ->label('Subscription Locale')
                    ->maxLength(5)
                    ->disabled(),
            ])->columns(2),

            Forms\Components\Section::make('Audit Information')->schema([
                Forms\Components\Placeholder::make('subscribed_at')
                    ->label('Subscribed At')
                    ->content(fn ($record) => $record?->subscribed_at?->format('Y-m-d H:i:s') ?? '—'),
                Forms\Components\Placeholder::make('synced_at')
                    ->label('Synced At')
                    ->content(fn ($record) => $record?->synced_at?->format('Y-m-d H:i:s') ?? '—'),
                Forms\Components\Placeholder::make('ip_address')
                    ->label('IP Address')
                    ->content(fn ($record) => $record?->ip_address ?? '—'),
                Forms\Components\Placeholder::make('created_at')
                    ->label('Created At')
                    ->content(fn ($record) => $record?->created_at?->format('Y-m-d H:i:s') ?? '—'),
            ])->columns(2)->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Email copied')
                    ->limit(40),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Active' => 'success',
                        'Disabled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_synced')
                    ->label('Synced')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),
                Tables\Columns\IconColumn::make('consent_accepted')
                    ->label('GDPR')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->color('gray')
                    ->toggleable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('locale')
                    ->label('Locale')
                    ->badge()
                    ->color('info')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('subscribed_at')
                    ->label('Subscribed')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('synced_at')
                    ->label('Synced')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->relationship('status', 'name'),
                Tables\Filters\TernaryFilter::make('is_synced')
                    ->label('Synced to ESP')
                    ->trueLabel('Synced')
                    ->falseLabel('Not synced'),
                Tables\Filters\TernaryFilter::make('consent_accepted')
                    ->label('GDPR Consent'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Mark as synced (for after a Klaviyo bulk sync)
                    Tables\Actions\BulkAction::make('markSynced')
                        ->label('Mark as Synced')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update([
                            'is_synced' => true,
                            'synced_at' => now(),
                        ]))
                        ->deselectRecordsAfterCompletion()
                        ->successNotification(
                            fn ($records) => \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Marked as Synced')
                                ->body(count($records) . ' subscriber(s) marked as synced.')
                        ),
                    // Unsubscribe (set status to disabled)
                    Tables\Actions\BulkAction::make('unsubscribe')
                        ->label('Unsubscribe')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['status_id' => 2]))
                        ->deselectRecordsAfterCompletion()
                        ->successNotification(
                            fn ($records) => \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Unsubscribed')
                                ->body(count($records) . ' subscriber(s) unsubscribed.')
                        ),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['status']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsletterSubscribers::route('/'),
            'edit' => Pages\EditNewsletterSubscriber::route('/{record}/edit'),
        ];
    }
}
