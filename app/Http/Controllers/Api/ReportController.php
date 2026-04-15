<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Withdrawal;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ApiResponse;

    /**
     * Export billing report as CSV.
     * Query params: start_date, end_date, type (orders|commissions|repasses)
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'type' => 'required|in:orders,commissions,repasses',
        ]);

        $startDate = $request->input('start_date', now()->subDays(30)->startOfDay());
        $endDate = $request->input('end_date', now()->endOfDay());
        $type = $request->input('type');

        $filename = "carhub-{$type}-" . now()->format('Y-m-d') . '.csv';

        $callback = function () use ($type, $startDate, $endDate) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            match ($type) {
                'orders' => $this->writeOrdersCsv($handle, $startDate, $endDate),
                'commissions' => $this->writeCommissionsCsv($handle, $startDate, $endDate),
                'repasses' => $this->writeRepassesCsv($handle, $startDate, $endDate),
            };

            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function writeOrdersCsv($handle, $startDate, $endDate): void
    {
        fputcsv($handle, [
            'Pedido', 'Data', 'Cliente', 'Fornecedor', 'Status', 'Subtotal', 'Taxa Plataforma', 'Total', 'Método Pagamento',
        ], ';');

        Order::with(['customer:id,name', 'supplier:id,business_name'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at')
            ->chunk(500, function ($orders) use ($handle) {
                foreach ($orders as $o) {
                    fputcsv($handle, [
                        $o->order_number,
                        $o->created_at->format('Y-m-d H:i'),
                        $o->customer?->name ?? '',
                        $o->supplier?->business_name ?? '',
                        $o->status,
                        number_format((float) $o->subtotal, 2, ',', '.'),
                        number_format((float) $o->platform_fee, 2, ',', '.'),
                        number_format((float) $o->total, 2, ',', '.'),
                        $o->payment_method ?? '',
                    ], ';');
                }
            });
    }

    protected function writeCommissionsCsv($handle, $startDate, $endDate): void
    {
        fputcsv($handle, [
            'Pedido', 'Data', 'Fornecedor', 'Valor Bruto', 'Taxa Plataforma', 'Valor Líquido', 'Status',
        ], ';');

        Payment::with('order.supplier:id,business_name')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['held', 'released', 'confirmed'])
            ->orderBy('created_at')
            ->chunk(500, function ($payments) use ($handle) {
                foreach ($payments as $p) {
                    fputcsv($handle, [
                        $p->order?->order_number ?? '',
                        $p->created_at->format('Y-m-d H:i'),
                        $p->order?->supplier?->business_name ?? '',
                        number_format((float) $p->amount, 2, ',', '.'),
                        number_format((float) $p->platform_fee, 2, ',', '.'),
                        number_format((float) $p->supplier_amount, 2, ',', '.'),
                        $p->status,
                    ], ';');
                }
            });
    }

    protected function writeRepassesCsv($handle, $startDate, $endDate): void
    {
        fputcsv($handle, [
            'Saque', 'Data Solicitada', 'Data Concluída', 'Fornecedor', 'Valor', 'Taxa', 'Valor Líquido', 'Tipo', 'Status',
        ], ';');

        Withdrawal::with('supplier:id,business_name')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at')
            ->chunk(500, function ($withdrawals) use ($handle) {
                foreach ($withdrawals as $w) {
                    fputcsv($handle, [
                        substr($w->id, 0, 8),
                        $w->requested_at?->format('Y-m-d H:i') ?? '',
                        $w->completed_at?->format('Y-m-d H:i') ?? '',
                        $w->supplier?->business_name ?? '',
                        number_format((float) $w->amount, 2, ',', '.'),
                        number_format((float) $w->fee, 2, ',', '.'),
                        number_format((float) $w->net_amount, 2, ',', '.'),
                        $w->type,
                        $w->status,
                    ], ';');
                }
            });
    }
}
