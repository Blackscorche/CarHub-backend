<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Services\PaymentService;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Pagamentos';
    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pagarme_charge_id')
                    ->searchable()
                    ->limit(20)
                    ->label('ID Pagar.me'),
                Tables\Columns\TextColumn::make('order.order_number')
                    ->searchable()
                    ->label('Pedido'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('BRL')
                    ->sortable()
                    ->label('Valor'),
                Tables\Columns\TextColumn::make('platform_fee')
                    ->money('BRL')
                    ->label('Taxa Plataforma'),
                Tables\Columns\TextColumn::make('supplier_amount')
                    ->money('BRL')
                    ->label('Valor Fornecedor'),
                Tables\Columns\TextColumn::make('method')
                    ->badge()
                    ->label('Método'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid', 'released' => 'success',
                        'pending', 'held' => 'warning',
                        'failed', 'refunded' => 'danger',
                        default => 'gray',
                    })
                    ->label('Status'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->label('Criado em'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pendente',
                        'paid' => 'Pago',
                        'held' => 'Retido',
                        'released' => 'Liberado',
                        'failed' => 'Falhou',
                        'refunded' => 'Reembolsado',
                    ])->label('Status'),
                Tables\Filters\SelectFilter::make('method')
                    ->options([
                        'pix' => 'PIX',
                        'credit_card' => 'Cartão de Crédito',
                        'debit_card' => 'Cartão de Débito',
                        'boleto' => 'Boleto',
                    ])->label('Método'),
            ])
            ->actions([
                Tables\Actions\Action::make('release')
                    ->label('Liberar')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Payment $record) => "Liberar R$ {$record->supplier_amount} ao fornecedor?")
                    ->visible(fn (Payment $record) => $record->status === 'held')
                    ->action(function (Payment $record) {
                        app(PaymentService::class)->releasePayment($record);
                        Notification::make()->title('Pagamento liberado.')->success()->send();
                    }),
                Tables\Actions\Action::make('refund')
                    ->label('Reembolsar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Payment $record) => "Reembolsar R$ {$record->amount} ao cliente?")
                    ->visible(fn (Payment $record) => in_array($record->status, ['held', 'released']))
                    ->action(function (Payment $record) {
                        app(PaymentService::class)->refund($record);
                        Notification::make()->title('Reembolso processado.')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}
