<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MeasurementJobsRelationManager extends RelationManager
{
    protected static string $relationship = 'measurementJobs';

    protected static ?string $title = 'Measurement Jobs';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('requested_rows')->label('Rows')->numeric()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('progress_percent')->label('Progress')->suffix('%')->sortable(),
                TextColumn::make('rows_processed')->numeric()->sortable(),
                TextColumn::make('execution_time_ms')->label('Time (ms)')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ])
            ->headerActions([])
            ->toolbarActions([])
            ->bulkActions([]);
    }
}
