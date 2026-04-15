<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalController extends Controller
{
    use ApiResponse;

    public function terms(): JsonResponse
    {
        return $this->success([
            'version' => '1.0',
            'updated_at' => '2026-03-01',
            'content' => $this->getTermsContent(),
        ]);
    }

    public function privacy(): JsonResponse
    {
        return $this->success([
            'version' => '1.0',
            'updated_at' => '2026-03-01',
            'content' => $this->getPrivacyContent(),
        ]);
    }

    /**
     * LGPD Art. 18 — Data portability / export.
     * Returns all user data in a structured JSON.
     */
    public function exportData(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->loadMissing(['supplier', 'addresses', 'vehicles']);

        $data = [
            'exported_at' => now()->toIso8601String(),
            'legal_basis' => 'LGPD Art. 18, II — Portabilidade dos dados',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'cpf' => $user->cpf,
                'role' => $user->role,
                'avatar_url' => $user->avatar_url,
                'status' => $user->status,
                'lgpd_consent' => $user->lgpd_consent,
                'lgpd_consent_at' => $user->lgpd_consent_at,
                'terms_version' => $user->terms_version,
                'created_at' => $user->created_at,
            ],
            'supplier' => $user->supplier,
            'addresses' => $user->addresses,
            'vehicles' => $user->vehicles,
            'orders' => \App\Models\Order::where('customer_id', $user->id)
                ->orWhere('supplier_id', $user->supplier?->id)
                ->with(['items', 'payments', 'review'])
                ->get(),
            'chat_messages' => \App\Models\ChatMessage::where('sender_id', $user->id)->get(),
            'reviews' => \App\Models\Review::where('customer_id', $user->id)->get(),
            'cashback_transactions' => \App\Models\CashbackTransaction::whereHas(
                'wallet', fn($q) => $q->where('user_id', $user->id)
            )->get(),
            'notifications' => $user->notifications()->get(['id', 'type', 'data', 'read_at', 'created_at']),
            'audit_logs' => \App\Models\AuditLog::where('user_id', $user->id)
                ->orderByDesc('created_at')->limit(100)->get(),
        ];

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'lgpd_data_export',
            'entity_type' => 'users',
            'entity_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return $this->success($data, 'Dados exportados conforme LGPD Art. 18.');
    }

    public function deleteRequest(Request $request): JsonResponse
    {
        $user = $request->user();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'account_deletion_request',
            'entity_type' => 'users',
            'entity_id' => $user->id,
            'new_value' => ['requested_at' => now()->toIso8601String()],
            'ip_address' => $request->ip(),
        ]);

        // Soft-delete: mark user for deletion (LGPD 15-day grace period)
        $user->update(['status' => 'suspended']);

        // Revoke all tokens
        $user->tokens()->delete();

        return $this->success(null, 'Solicitação de exclusão registrada. Sua conta será removida em até 15 dias conforme LGPD.');
    }

    protected function getTermsContent(): string
    {
        return <<<'TERMS'
TERMOS DE USO - CARHUB

1. ACEITAÇÃO DOS TERMOS
Ao utilizar o aplicativo CarHub, você concorda com estes Termos de Uso.

2. DESCRIÇÃO DO SERVIÇO
O CarHub é uma plataforma que conecta proprietários de veículos a fornecedores de serviços automotivos.

3. CADASTRO
Para utilizar os serviços, é necessário criar uma conta com informações verdadeiras e atualizadas.

4. RESPONSABILIDADES DO USUÁRIO
- Manter dados cadastrais atualizados
- Não utilizar a plataforma para fins ilícitos
- Respeitar os direitos de outros usuários

5. PAGAMENTOS
Os pagamentos são processados via Pagar.me. A plataforma retém uma taxa de serviço sobre cada transação.

6. CANCELAMENTOS E REEMBOLSOS
Cancelamentos antes da aceitação do fornecedor geram reembolso integral. Após início do serviço, disputas devem ser abertas.

7. PROPRIEDADE INTELECTUAL
Todo o conteúdo do aplicativo é propriedade da CarHub.

8. LIMITAÇÃO DE RESPONSABILIDADE
A CarHub atua como intermediária e não se responsabiliza pela qualidade dos serviços prestados pelos fornecedores.

9. ALTERAÇÕES
Estes termos podem ser alterados a qualquer momento, com notificação prévia aos usuários.

10. CONTATO
suporte@carhub.com.br
TERMS;
    }

    protected function getPrivacyContent(): string
    {
        return <<<'PRIVACY'
POLÍTICA DE PRIVACIDADE - CARHUB (LGPD)

1. DADOS COLETADOS
- Dados pessoais: nome, e-mail, telefone, CPF/CNPJ
- Dados de localização (com consentimento)
- Dados de transações e pagamentos
- Dados de veículos

2. FINALIDADE DO TRATAMENTO
- Prestação do serviço de intermediação
- Processamento de pagamentos
- Comunicação sobre pedidos e serviços
- Melhoria da experiência do usuário

3. BASE LEGAL
O tratamento dos dados é baseado no consentimento do usuário e na execução de contrato (Art. 7, LGPD).

4. COMPARTILHAMENTO
Dados são compartilhados apenas com:
- Fornecedores de serviço (para execução do pedido)
- Pagar.me (processamento de pagamentos)
- Seguradoras (quando solicitado pelo usuário)

5. DIREITOS DO TITULAR
Conforme a LGPD, você tem direito a:
- Acessar seus dados pessoais
- Corrigir dados incompletos ou desatualizados
- Solicitar a exclusão dos seus dados
- Revogar o consentimento
- Solicitar portabilidade dos dados

6. RETENÇÃO
Dados são mantidos enquanto a conta estiver ativa. Após solicitação de exclusão, os dados são removidos em até 15 dias.

7. SEGURANÇA
Utilizamos criptografia e controles de acesso para proteger seus dados.

8. CONTATO DO DPO
dpo@carhub.com.br
PRIVACY;
    }
}
