<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EvaluationSessionResource\Pages;
use App\Models\EvaluationSession;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EvaluationSessionResource extends Resource
{
    protected static ?string $model = EvaluationSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Sessions';

    protected static ?string $navigationGroup = 'Clinical Data';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['mentee', 'evaluator', 'tool', 'district', 'facility']);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Session Details')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('mentee.full_name')
                            ->label('Mentee'),
                        Infolists\Components\TextEntry::make('evaluator.full_name')
                            ->label('Evaluator'),
                        Infolists\Components\TextEntry::make('tool.label')
                            ->label('Tool'),
                        Infolists\Components\TextEntry::make('eval_date')
                            ->label('Evaluation Date')
                            ->date(),
                        Infolists\Components\TextEntry::make('district.name')
                            ->label('District'),
                        Infolists\Components\TextEntry::make('facility.name')
                            ->label('Facility'),
                        Infolists\Components\TextEntry::make('phase')
                            ->label('Phase')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => self::formatPhase($state))
                            ->color(fn (?string $state): string => self::phaseColor($state)),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No notes recorded.')
                            ->columnSpanFull(),
                    ]),
                Infolists\Components\Section::make('Item Scores')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('itemScores')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('item.category.name')
                                    ->label('Category'),
                                Infolists\Components\TextEntry::make('item.number')
                                    ->label('#'),
                                Infolists\Components\TextEntry::make('item.title')
                                    ->label('Competency'),
                                Infolists\Components\TextEntry::make('mentee_score')
                                    ->label('Score')
                                    ->placeholder('N/A'),
                            ])
                            ->columns(4),
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
                    ))
                    ->sortable(['mentee_id']),
                Tables\Columns\TextColumn::make('evaluator.full_name')
                    ->label('Evaluator')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'evaluator',
                        fn (Builder $q) => $q
                            ->where('firstname', 'like', "%{$search}%")
                            ->orWhere('lastname', 'like', "%{$search}%")
                    ))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tool.label')
                    ->label('Tool')
                    ->sortable(),
                Tables\Columns\TextColumn::make('eval_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('district.name')
                    ->label('District')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('facility.name')
                    ->label('Facility')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('phase')
                    ->label('Phase')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::formatPhase($state))
                    ->color(fn (?string $state): string => self::phaseColor($state)),
            ])
            ->defaultSort('eval_date', 'desc')
            ->filters([
                SelectFilter::make('tool')
                    ->relationship('tool', 'label')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('district')
                    ->relationship('district', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('phase')
                    ->options([
                        'initial_intensive' => 'Initial Intensive',
                        'ongoing' => 'Ongoing',
                        'supervision' => 'Supervision',
                    ]),
                Filter::make('eval_date')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn (Builder $q, string $d): Builder => $q->whereDate('eval_date', '>=', $d))
                        ->when($data['until'], fn (Builder $q, string $d): Builder => $q->whereDate('eval_date', '<=', $d))
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvaluationSessions::route('/'),
            'view' => Pages\ViewEvaluationSession::route('/{record}'),
        ];
    }

    private static function formatPhase(?string $phase): string
    {
        return match ($phase) {
            'initial_intensive' => 'Initial Intensive',
            'ongoing' => 'Ongoing',
            'supervision' => 'Supervision',
            default => $phase ?? '—',
        };
    }

    private static function phaseColor(?string $phase): string
    {
        return match ($phase) {
            'initial_intensive' => 'info',
            'ongoing' => 'success',
            'supervision' => 'warning',
            default => 'gray',
        };
    }
}
