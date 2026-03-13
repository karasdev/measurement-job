<?php

namespace App\Filament\Widgets;

use App\Models\MeasurementJob;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $jobsByStatus = MeasurementJob::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            Stat::make('Total Users', number_format(User::count())),
            Stat::make('Total Jobs', number_format(MeasurementJob::count())),
            Stat::make('Completed Jobs', number_format((int) ($jobsByStatus['completed'] ?? 0)))
                ->color('success'),
            Stat::make('Failed Jobs', number_format((int) ($jobsByStatus['failed'] ?? 0)))
                ->color('danger'),
            Stat::make('In Progress Jobs', number_format(
                (int) ($jobsByStatus['generating'] ?? 0)
                + (int) ($jobsByStatus['processing'] ?? 0)
                + (int) ($jobsByStatus['aggregating'] ?? 0)
            ))->color('warning'),
        ];
    }
}
