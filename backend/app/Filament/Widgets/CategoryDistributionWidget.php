<?php

namespace App\Filament\Widgets;

use App\Models\Supplier;
use Filament\Widgets\ChartWidget;

class CategoryDistributionWidget extends ChartWidget
{
    protected static ?string $heading = 'Fornecedores por categoria';
    protected static ?int $sort = 5;
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $categories = Supplier::approved()
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');

        $labels = [
            'mecanica' => 'Mecânica',
            'eletrica' => 'Elétrica',
            'funilaria' => 'Funilaria',
            'pneus' => 'Pneus',
            'estetica' => 'Estética',
            'pecas' => 'Peças',
            'outros' => 'Outros',
        ];

        $colors = ['#18181B', '#3B82F6', '#EF4444', '#F59E0B', '#22C55E', '#8B5CF6', '#6B7280'];

        return [
            'datasets' => [
                [
                    'data' => $categories->values()->toArray(),
                    'backgroundColor' => array_slice($colors, 0, $categories->count()),
                ],
            ],
            'labels' => $categories->keys()->map(fn ($k) => $labels[$k] ?? $k)->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
