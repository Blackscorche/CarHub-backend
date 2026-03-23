<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Recibo do Pedido</title></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px">
    <h2 style="color:#18181B">CarHub - Recibo do Pedido</h2>
    <p>Olá, {{ $customer->name }}!</p>
    <p>Seu pedido <strong>#{{ $order->order_number }}</strong> foi confirmado.</p>
    <table style="width:100%;border-collapse:collapse;margin:20px 0">
        <tr style="background:#f9fafb"><th style="padding:10px;text-align:left;border-bottom:2px solid #e5e7eb">Item</th><th style="padding:10px;text-align:right;border-bottom:2px solid #e5e7eb">Valor</th></tr>
        @foreach($order->items as $item)
        <tr><td style="padding:8px;border-bottom:1px solid #eee">{{ $item->name }} × {{ $item->quantity }}</td><td style="padding:8px;border-bottom:1px solid #eee;text-align:right">R$ {{ number_format($item->total_price, 2, ',', '.') }}</td></tr>
        @endforeach
        <tr><td style="padding:10px;font-weight:bold">Total</td><td style="padding:10px;font-weight:bold;text-align:right">R$ {{ number_format($order->total, 2, ',', '.') }}</td></tr>
    </table>
    <p style="color:#666">Método: {{ strtoupper($order->payment_method) }}</p>
    <p style="margin-top:30px;color:#999;font-size:12px">CarHub - Sua plataforma automotiva</p>
</body>
</html>
