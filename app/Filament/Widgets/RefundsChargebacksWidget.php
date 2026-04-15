<?php

namespace App\Filament\Widgets;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RefundsChargebacksWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        $start = now()->subDays(30)->startOfDay();

        // Total refunded amount (30 days)
        $refundedAmount = Payment::where('status', 'refunded')
            ->where('refunded_at', '>=', $start)
            ->sum('amount');

        // Refund count
        $refundedCount = Payment::where('status', 'refunded')
            ->where('refunded_at', '>=', $start)
            ->count();

        // Cancelled orders (30 days)
        $cancelledOrders = Order::where('status', 'cancelled')
            ->where('cancelled_at', '>=', $start)
            ->count();

        // Open disputes
        $openDisputes = Dispute::whereIn('status', ['open', 'under_review'])->count();

        // Resolved disputes with full refund (30 days)
        $refundedDisputes = Dispute::where('status', 'resolved_refund')
            ->where('resolved_at', '>=', $start)
            ->count();

        // Dispute rate (last 30 days): disputes / paid orders
        $paidOrders30d = Order::whereIn('status', ['paid', 'in_progress', 'completed', 'confirmed'])
            ->where('created_at', '>=', $start)
            ->count();
        $disputedOrders30d = Dispute::where('created_at', '>=', $start)->count();
        $disputeRate = $paidOrders30d > 0 ? round(($disputedOrders30d / $paidOrders30d) * 100, 2) : 0;

        return [
            Stat::make('Reembolsos (30d)', 'R$ ' . number_format($refundedAmount, 2, ',', '.'))
                ->description("{$refundedCount} reembolso(s)")
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->icon('heroicon-o-currency-dollar')
                ->color('warning'),

            Stat::make('Pedidos Cancelados (30d)', $cancelledOrders)
                ->icon('heroicon-o-x-circle')
                ->color('danger'),

            Stat::make('Disputas Abertas', $openDisputes)
                ->description("{$refundedDisputes} resolvidas com reembolso (30d)")
                ->icon('heroicon-o-scale')
                ->color($openDisputes > 0 ? 'danger' : 'success'),

            Stat::make('Taxa de Disputas', "{$disputeRate}%")
                ->description("30 dias")
                ->icon('heroicon-o-chart-bar')
                ->color($disputeRate > 5 ? 'danger' : ($disputeRate > 2 ? 'warning' : 'success')),
        ];
    }
}
