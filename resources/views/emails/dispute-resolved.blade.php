<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Disputa Resolvida</title></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px">
    <h2 style="color:#18181B">CarHub - Disputa Resolvida</h2>
    <p>Olá, {{ $user->name }}!</p>
    <p>A disputa referente ao pedido <strong>#{{ $order->order_number }}</strong> foi resolvida.</p>
    <table style="width:100%;border-collapse:collapse;margin:20px 0">
        <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666">Pedido</td><td style="padding:8px;border-bottom:1px solid #eee">#{{ $order->order_number }}</td></tr>
        <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666">Resolução</td><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold">{{ $resolution }}</td></tr>
        @if($adminNotes)
        <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666">Observações</td><td style="padding:8px;border-bottom:1px solid #eee">{{ $adminNotes }}</td></tr>
        @endif
    </table>
    <p style="color:#666">Se tiver dúvidas, entre em contato conosco pelo suporte@carhub.com.br</p>
    <p style="margin-top:30px;color:#999;font-size:12px">CarHub - Sua plataforma automotiva</p>
</body>
</html>
