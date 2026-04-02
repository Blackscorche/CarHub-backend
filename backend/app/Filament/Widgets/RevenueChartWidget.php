<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Receita (30 dias)';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $data = collect(range(29, 0))->map(function ($daysAgo) {
            $date = Carbon::today()->subDays($daysAgo);
            return [
                'date' => $date->format('d/m'),
                'revenue' => Payment::whereDate('created_at', $date)
                    ->whereIn('status', ['held', 'released'])
                    ->sum('amount') / 100,
                'fees' => Payment::whereDate('created_at', $date)
                    ->whereIn('status', ['held', 'released'])
                    ->sum('platform_fee') / 100,
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Receita total',
                    'data' => $data->pluck('revenue')->toArray(),
                    'borderColor' => '#22C55E',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Taxa plataforma',
                    'data' => $data->pluck('fees')->toArray(),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $data->pluck('date')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
