<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected $user,
        protected ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', FcmChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $msg = (new MailMessage)
            ->subject('Sua solicitação de cadastro foi rejeitada')
            ->greeting("Olá, {$this->user->name}")
            ->line('Após análise, sua solicitação de cadastro de fornecedor não foi aprovada.');

        if ($this->reason) {
            $msg->line("Motivo: {$this->reason}");
        }

        return $msg
            ->line('Se quiser ajustar seu cadastro e tentar novamente, entre em contato com suporte@carhubrasil.com.br.')
            ->salutation('Atenciosamente, Equipe CarHub');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'supplier_rejected',
            'title' => 'Cadastro Rejeitado',
            'body' => $this->reason
                ? 'Seu cadastro foi rejeitado: ' . $this->reason
                : 'Seu cadastro não foi aprovado. Verifique seu e-mail para detalhes.',
            'data' => [
                'user_id' => $this->user->id,
                'reason' => $this->reason,
            ],
        ];
    }
}
