<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    use ApiResponse;

    public function upload(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'invoice' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $user = $request->user();
        $supplier = $user->supplier;

        if (! $supplier || $order->supplier_id !== $supplier->id) {
            return $this->forbidden('Acesso negado.');
        }

        $path = $request->file('invoice')->store('invoices/' . $order->id, 'public');
        $invoiceUrl = Storage::url($path);

        $order->update(['invoice_url' => $invoiceUrl]);

        // Send invoice to customer via email
        $customer = $order->customer;
        if ($customer->email) {
            try {
                Mail::send('emails.invoice', [
                    'customer' => $customer,
                    'order' => $order,
                    'invoiceUrl' => url($invoiceUrl),
                ], function ($message) use ($customer, $order) {
                    $message->to($customer->email)
                        ->subject("Nota Fiscal - Pedido #{$order->order_number}");
                });
            } catch (\Throwable $e) {
                \Log::error('Invoice email failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        return $this->success(['invoice_url' => $invoiceUrl], 'Nota fiscal enviada com sucesso.');
    }

    public function download(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'customer' && $order->customer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }
        if ($user->role === 'supplier') {
            $supplier = $user->supplier;
            if (! $supplier || $order->supplier_id !== $supplier->id) {
                return $this->forbidden('Acesso negado.');
            }
        }

        if (!$order->invoice_url) {
            return $this->notFound('Nota fiscal não encontrada.');
        }

        return $this->success(['invoice_url' => $order->invoice_url]);
    }
}
