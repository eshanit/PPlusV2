<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DistrictResource\Pages;
use App\Models\District;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DistrictResource extends Resource
{
    protected static ?string $model = District::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('facilities_count')
                    ->counts('facilities')
                    ->label('Facilities')
                    ->sortable(),
                Tables\Columns\TextColumn::make('synced_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDistricts::route('/'),
        ];
    }
}
