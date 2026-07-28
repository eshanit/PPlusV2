<?php

namespace App\Filament\Widgets;

use App\Models\SyncFailure;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentSyncFailuresWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent Sync Failures')
            ->query(SyncFailure::query())
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                Tables\Columns\TextColumn::make('db_name')
                    ->label('Database')
                    ->badge()
                    ->color('danger'),
                Tables\Columns\TextColumn::make('doc_id')
                    ->label('Document')
                    ->placeholder('—')
                    ->limit(24)
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('message')
                    ->label('Error')
                    ->wrap()
                    ->limit(120),
            ])
            ->emptyStateHeading('No sync failures')
            ->emptyStateDescription('Every processed document has synced cleanly.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
