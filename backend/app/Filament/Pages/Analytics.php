<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Analytics extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Análises';
    protected static ?int $navigationSort = 10;
    protected static string $view = 'filament.pages.analytics';

    public string $period = '30';

    public function mount(): void
    {
        $this->period = '30';
    }

    public function getStats(): array
    {
        $since = Carbon::today()->subDays((int) $this->period);

        $orders = Order::where('created_at', '>=', $since);
        $payments = Payment::where('created_at', '>=', $since)->whereIn('status', ['held', 'released']);

        return [
            'total_orders' => (clone $orders)->count(),
            'completed_orders' => (clone $orders)->where('status', 'confirmed')->count(),
            'cancelled_orders' => (clone $orders)->where('status', 'cancelled')->count(),
            'gmv' => round((clone $payments)->sum('amount'), 2),
            'platform_revenue' => round((clone $payments)->sum('platform_fee'), 2),
            'avg_order_value' => round((clone $orders)->avg('total') ?? 0, 2),
            'new_customers' => User::where('role', 'customer')->where('created_at', '>=', $since)->count(),
            'new_suppliers' => Supplier::where('created_at', '>=', $since)->count(),
            'milestone_orders' => (clone $orders)->where('payment_model', 'milestone')->count(),
            'instant_orders' => (clone $orders)->where('payment_model', 'instant')->count(),
        ];
    }

    public function getTopSuppliers(): array
    {
        $since = Carbon::today()->subDays((int) $this->period);

        return Supplier::approved()
            ->withCount(['orders' => fn ($q) => $q->where('created_at', '>=', $since)])
            ->withSum(['orders' => fn ($q) => $q->where('created_at', '>=', $since)->where('status', 'confirmed')], 'total')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get()
            ->map(fn ($s) => [
                'name' => $s->business_name,
                'category' => $s->category,
                'orders' => $s->orders_count,
                'revenue' => round($s->orders_sum_total ?? 0, 2),
                'rating' => $s->avg_rating,
            ])
            ->toArray();
    }

    public function exportCsv(): StreamedResponse
    {
        $since = Carbon::today()->subDays((int) $this->period);

        $orders = Order::with(['customer:id,name', 'supplier:id,business_name'])
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->get();

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Pedido', 'Cliente', 'Fornecedor', 'Total', 'Status', 'Modelo', 'Data']);

            foreach ($orders as $order) {
                fputcsv($handle, [
                    $order->order_number,
                    $order->customer->name ?? '',
                    $order->supplier->business_name ?? '',
                    $order->total,
                    $order->status,
                    $order->payment_model,
                    $order->created_at->format('d/m/Y H:i'),
                ]);
            }

            fclose($handle);
        }, "carhub-relatorio-{$this->period}dias.csv");
    }
}
