<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GapEntryResource\Pages;
use App\Models\GapEntry;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GapEntryResource extends Resource
{
    protected static ?string $model = GapEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Gaps';

    protected static ?string $navigationGroup = 'Clinical Data';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['mentee', 'tool', 'evaluator']);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Gap Details')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('mentee.full_name')
                            ->label('Mentee'),
                        Infolists\Components\TextEntry::make('evaluator.full_name')
                            ->label('Identified By'),
                        Infolists\Components\TextEntry::make('tool.label')
                            ->label('Tool'),
                        Infolists\Components\TextEntry::make('identified_at')
                            ->label('Identified On')
                            ->date(),
                        Infolists\Components\TextEntry::make('supervision_level')
                            ->label('Supervision Level')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'intensive_mentorship' => 'Intensive Mentorship',
                                'ongoing_mentorship' => 'Ongoing Mentorship',
                                'independent_practice' => 'Independent Practice',
                                default => $state ?? '—',
                            }),
                        Infolists\Components\TextEntry::make('timeline')
                            ->label('Timeline')
                            ->placeholder('Not specified.'),
                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('domains')
                            ->label('Domains')
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state)))
                            ->columnSpanFull(),
                    ]),
                Infolists\Components\Section::make('Mentorship Follow-Up')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\IconEntry::make('covered_in_mentorship')
                            ->label('Covered in Mentorship')
                            ->boolean(),
                        Infolists\Components\IconEntry::make('covering_later')
                            ->label('Covering Later')
                            ->boolean(),
                    ]),
                Infolists\Components\Section::make('Resolution')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('resolved_at')
                            ->label('Resolved On')
                            ->date()
                            ->placeholder('Not yet resolved.'),
                        Infolists\Components\TextEntry::make('resolution_note')
                            ->label('Resolution Note')
                            ->placeholder('No note.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('mentee.full_name')
                    ->label('Mentee')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'mentee',
                        fn (Builder $q) => $q
                            ->where('firstname', 'like', "%{$search}%")
                            ->orWhere('lastname', 'like', "%{$search}%")
                    )),
                Tables\Columns\TextColumn::make('tool.label')
                    ->label('Tool')
                    ->sortable(),
                Tables\Columns\TextColumn::make('identified_at')
                    ->label('Identified')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(60)
                    ->tooltip(fn (GapEntry $record): string => $record->description),
                Tables\Columns\TextColumn::make('supervision_level')
                    ->label('Level')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'intensive_mentorship' => 'Intensive',
                        'ongoing_mentorship' => 'Ongoing',
                        'independent_practice' => 'Independent',
                        default => '—',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'intensive_mentorship' => 'danger',
                        'ongoing_mentorship' => 'warning',
                        'independent_practice' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('resolved_at')
                    ->label('Resolved')
                    ->boolean()
                    ->getStateUsing(fn (GapEntry $record): bool => $record->resolved_at !== null)
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('domains')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state)))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('identified_at', 'desc')
            ->filters([
                SelectFilter::make('tool')
                    ->relationship('tool', 'label')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('resolved_at')
                    ->label('Resolved')
                    ->nullable()
                    ->trueLabel('Resolved only')
                    ->falseLabel('Unresolved only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGapEntries::route('/'),
        ];
    }
}
