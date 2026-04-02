<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Filament\Widgets\ChartWidget;

class ConversionFunnelWidget extends ChartWidget
{
    protected static ?string $heading = 'Funil de conversão (30 dias)';
    protected static ?int $sort = 6;
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $since = now()->subDays(30);

        $customers = User::where('role', 'customer')->where('created_at', '>=', $since)->count();
        $orders = Order::where('created_at', '>=', $since)->count();
        $accepted = Order::where('created_at', '>=', $since)->whereNotNull('accepted_at')->count();
        $paid = Payment::where('created_at', '>=', $since)->whereIn('status', ['held', 'released'])->distinct('order_id')->count('order_id');
        $completed = Order::where('created_at', '>=', $since)->whereNotNull('completed_at')->count();
        $confirmed = Order::where('created_at', '>=', $since)->where('status', 'confirmed')->count();

        return [
            'datasets' => [
                [
                    'data' => [$customers, $orders, $accepted, $paid, $completed, $confirmed],
                    'backgroundColor' => ['#18181B', '#3B82F6', '#F59E0B', '#22C55E', '#8B5CF6', '#10B981'],
                ],
            ],
            'labels' => ['Cadastros', 'Pedidos', 'Aceitos', 'Pagos', 'Concluídos', 'Confirmados'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
