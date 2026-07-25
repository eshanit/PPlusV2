<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Facility;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Clinicians';

    protected static ?string $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Profile')
                    ->schema([
                        Forms\Components\TextInput::make('firstname')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('lastname')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('username')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100),
                        Forms\Components\TextInput::make('profession')
                            ->maxLength(100),
                        Forms\Components\Select::make('role_id')
                            ->label('Role')
                            ->relationship('role', 'label')
                            ->required(),
                        Forms\Components\Select::make('district_id')
                            ->label('District')
                            ->relationship('district', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('facility_id', null)),
                        Forms\Components\Select::make('facility_id')
                            ->label('Facility')
                            ->options(fn (Get $get): array => Facility::query()
                                ->where('district_id', $get('district_id'))
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->disabled(fn (Get $get): bool => blank($get('district_id')))
                            ->helperText('Select a district first.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Panel Access')
                    ->description('Optional — only needed if this person should log into the reporting dashboard/admin.')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn (string $state): string => bcrypt($state))
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->maxLength(255)
                            ->helperText('Leave blank to keep the existing password.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                        fn (Builder $q) => $q
                            ->where('firstname', 'like', "%{$search}%")
                            ->orWhere('lastname', 'like', "%{$search}%")
                    ))
                    ->sortable(['lastname', 'firstname']),
                Tables\Columns\TextColumn::make('username')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role.label')
                    ->label('Role')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('profession')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('facility.name')
                    ->label('Facility')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('district.name')
                    ->label('District')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('password')
                    ->label('Panel Access')
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => filled($record->password))
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('synced_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('lastname')
            ->filters([
                SelectFilter::make('role')
                    ->relationship('role', 'label')
                    ->preload(),
                SelectFilter::make('district')
                    ->relationship('district', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('facility')
                    ->relationship('facility', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
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
