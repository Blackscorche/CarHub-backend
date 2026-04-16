<?php

namespace App\Filament\Widgets;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = now()->startOfDay();

        $todayOrders = Order::whereDate('created_at', $today)->count();

        $todayGmv = Order::whereDate('created_at', $today)->sum('total');

        $platformFees = Payment::whereDate('created_at', $today)
            ->where('status', 'paid')
            ->sum('platform_fee');

        $activeSuppliers = Supplier::where('approval_status', 'approved')->count();

        $newCustomers = User::where('role', 'customer')
            ->whereDate('created_at', $today)
            ->count();

        $openDisputes = Dispute::whereIn('status', ['open', 'under_review'])->count();

        // Take rate (30d): commission revenue / GMV
        $start30 = now()->subDays(30)->startOfDay();
        $gmv30 = (float) Order::where('created_at', '>=', $start30)->sum('total');
        $fees30 = (float) Payment::where('created_at', '>=', $start30)
            ->whereIn('status', ['paid', 'held', 'released'])
            ->sum('platform_fee');
        $takeRate = $gmv30 > 0 ? round(($fees30 / $gmv30) * 100, 2) : 0;

        return [
            Stat::make('Pedidos Hoje', $todayOrders)
                ->icon('heroicon-o-shopping-bag')
                ->color('primary'),
            Stat::make('GMV Hoje', 'R$ ' . number_format($todayGmv, 2, ',', '.'))
                ->icon('heroicon-o-currency-dollar')
                ->color('success'),
            Stat::make('Taxas Plataforma', 'R$ ' . number_format($platformFees, 2, ',', '.'))
                ->icon('heroicon-o-banknotes')
                ->color('info'),
            Stat::make('Take Rate (30d)', "{$takeRate}%")
                ->description('Comissão / GMV')
                ->icon('heroicon-o-chart-pie')
                ->color('info'),
            Stat::make('Fornecedores Ativos', $activeSuppliers)
                ->icon('heroicon-o-building-storefront')
                ->color('success'),
            Stat::make('Novos Clientes Hoje', $newCustomers)
                ->icon('heroicon-o-user-plus')
                ->color('primary'),
            Stat::make('Disputas Abertas', $openDisputes)
                ->icon('heroicon-o-exclamation-triangle')
                ->color($openDisputes > 0 ? 'danger' : 'success'),
        ];
    }
}
