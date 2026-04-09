<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Nota Fiscal</title></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px">
    <h2 style="color:#18181B">CarHub - Nota Fiscal</h2>
    <p>Olá, {{ $customer->name }}!</p>
    <p>A nota fiscal do seu pedido <strong>#{{ $order->order_number }}</strong> está disponível.</p>
    <table style="width:100%;border-collapse:collapse;margin:20px 0">
        <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666">Pedido</td><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold">#{{ $order->order_number }}</td></tr>
        <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666">Valor Total</td><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold">R$ {{ number_format($order->total, 2, ',', '.') }}</td></tr>
    </table>
    <a href="{{ $invoiceUrl }}" style="display:inline-block;background:#2563EB;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold">Baixar Nota Fiscal</a>
    <p style="margin-top:30px;color:#999;font-size:12px">Este e-mail foi enviado automaticamente pelo CarHub.</p>
</body>
</html>
