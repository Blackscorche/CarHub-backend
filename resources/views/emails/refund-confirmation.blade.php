<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Reembolso</title></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px">
    <h2 style="color:#18181B">CarHub - Confirmação de Reembolso</h2>
    <p>Olá, {{ $customer->name }}!</p>
    <p>O reembolso do pedido <strong>#{{ $order->order_number }}</strong> foi processado.</p>
    <table style="width:100%;border-collapse:collapse;margin:20px 0">
        <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666">Pedido</td><td style="padding:8px;border-bottom:1px solid #eee">#{{ $order->order_number }}</td></tr>
        <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666">Valor Reembolsado</td><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;color:#16a34a">R$ {{ number_format($amount, 2, ',', '.') }}</td></tr>
        <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666">Motivo</td><td style="padding:8px;border-bottom:1px solid #eee">{{ $reason }}</td></tr>
    </table>
    <p style="color:#666">O valor será devolvido ao método de pagamento original em até 5 dias úteis.</p>
    <p style="margin-top:30px;color:#999;font-size:12px">CarHub - Sua plataforma automotiva</p>
</body>
</html>
