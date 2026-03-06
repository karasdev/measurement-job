<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MeasurementJobResource\Pages;
use App\Models\MeasurementJob;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MeasurementJobResource extends Resource
{
    protected static ?string $model = MeasurementJob::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $modelLabel = 'Measurement Job';

    protected static ?string $pluralModelLabel = 'Measurement Jobs';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id'),
                TextEntry::make('user.email')->label('User'),
                TextEntry::make('requested_rows')->numeric(),
                TextEntry::make('status')->badge(),
                TextEntry::make('progress_percent')->suffix('%'),
                TextEntry::make('rows_processed')->numeric(),
                TextEntry::make('execution_time_ms')->label('Execution time (ms)'),
                TextEntry::make('memory_used_bytes')->label('Memory (bytes)')->formatStateUsing(fn (?int $state): string => $state !== null ? number_format($state) : '—'),
                TextEntry::make('error_message')->label('Error')->visible(fn ($record) => filled($record?->error_message)),
                TextEntry::make('completed_at')->dateTime(),
                TextEntry::make('created_at')->dateTime(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.email')->label('User')->searchable()->sortable(),
                TextColumn::make('requested_rows')->numeric()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('progress_percent')->suffix('%')->sortable(),
                TextColumn::make('rows_processed')->numeric()->sortable(),
                TextColumn::make('execution_time_ms')->label('Time (ms)')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'generating' => 'Generating',
                        'processing' => 'Processing',
                        'aggregating' => 'Aggregating',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMeasurementJobs::route('/'),
            'view' => Pages\ViewMeasurementJob::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('user:id,name,email');
    }
}
