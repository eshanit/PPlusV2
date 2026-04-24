<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
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
                Tables\Actions\EditAction::make()
                    ->label('Manage Access'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
