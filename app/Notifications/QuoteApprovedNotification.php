<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuoteApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected $quote
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', FcmChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $orderNumber = $this->quote->order?->order_number ?? substr($this->quote->order_id, 0, 8);
        $total = number_format((float) ($this->quote->initial_price ?? 0), 2, ',', '.');

        return (new MailMessage)
            ->subject("Orçamento aprovado — Pedido #{$orderNumber}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("O cliente aprovou seu orçamento no valor de R$ {$total}.")
            ->line('Aguarde a confirmação do pagamento do sinal para começar o serviço.')
            ->salutation('Equipe CarHub');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quote_approved',
            'title' => 'Orçamento aprovado',
            'body' => 'O cliente aprovou seu orçamento. Aguarde o pagamento do sinal.',
            'data' => [
                'quote_id' => $this->quote->id,
                'order_id' => $this->quote->order_id,
            ],
        ];
    }
}
