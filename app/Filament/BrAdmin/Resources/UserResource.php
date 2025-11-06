<?php

namespace App\Filament\BrAdmin\Resources;

use App\Filament\BrAdmin\Resources\UserResource\Pages;
use App\Filament\BrAdmin\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Account Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('username')
                            ->maxLength(100)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn ($state) => filled($state))
                            ->maxLength(255),
                        Forms\Components\DateTimePicker::make('email_verified_at'),
                        Forms\Components\Toggle::make('is_admin')
                            ->label('Admin Access')
                            ->helperText('Allow this user to access the admin panel'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Personal Information')
                    ->schema([
                        Forms\Components\TextInput::make('firstname')
                            ->label('First Name')
                            ->maxLength(30),
                        Forms\Components\TextInput::make('lastname')
                            ->label('Last Name')
                            ->maxLength(30),
                        Forms\Components\DatePicker::make('date_of_birth')
                            ->label('Date of Birth')
                            ->maxDate(now()),
                        Forms\Components\Select::make('gender_id')
                            ->label('Gender')
                            ->relationship('gender', 'name')
                            ->preload(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Contact & Location')
                    ->schema([
                        Forms\Components\TextInput::make('mobile')
                            ->label('Mobile Phone')
                            ->tel()
                            ->maxLength(35),
                        Forms\Components\Select::make('country_code')
                            ->label('Country')
                            ->relationship('country', 'code')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->name . ' (' . $record->code . ')')
                            ->searchable(['code'])
                            ->preload(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status & Metadata')
                    ->schema([
                        Forms\Components\Select::make('status_id')
                            ->label('Status')
                            ->relationship('status', 'name')
                            ->required()
                            ->default(1),
                        Forms\Components\TextInput::make('register_type')
                            ->label('Registration Type')
                            ->maxLength(10)
                            ->helperText('e.g., mobile, web, etc.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('username')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('firstname')
                    ->label('First Name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('lastname')
                    ->label('Last Name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('mobile')
                    ->label('Mobile')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('gender.name')
                    ->label('Gender')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('country.name')
                    ->label('Country')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('date_of_birth')
                    ->label('Birth Date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_admin')
                    ->boolean()
                    ->label('Admin')
                    ->sortable(),
                Tables\Columns\TextColumn::make('register_type')
                    ->label('Registration')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('beaches_count')
                    ->label('Beaches')
                    ->counts('beaches')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_admin')
                    ->label('Admin Users')
                    ->placeholder('All users')
                    ->trueLabel('Only admins')
                    ->falseLabel('Only non-admins'),
                Tables\Filters\SelectFilter::make('status')
                    ->relationship('status', 'name'),
                Tables\Filters\SelectFilter::make('gender')
                    ->relationship('gender', 'name'),
                Tables\Filters\SelectFilter::make('country')
                    ->relationship('country', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name),
                Tables\Filters\SelectFilter::make('register_type')
                    ->options([
                        'mobile' => 'Mobile',
                        'web' => 'Web',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['gender', 'country', 'status'])
            ->withCount('beaches');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
