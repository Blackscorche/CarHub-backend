<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DisputeResource\Pages;
use App\Models\Dispute;
use Filament\Forms;
use Filament\Forms\Form;
use App\Notifications\DisputeResolvedNotification;
use App\Services\PaymentService;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class DisputeResource extends Resource
{
    protected static ?string $model = Dispute::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationLabel = 'Disputas';
    protected static ?int $navigationSort = 6;

    protected static ?string $navigationBadgeTooltip = 'Disputas abertas';

    public static function getNavigationBadge(): ?string
    {
        $count = Dispute::whereIn('status', ['open', 'under_review'])->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('status')
                ->options([
                    'open' => 'Aberta',
                    'under_review' => 'Em Análise',
                    'resolved_refund' => 'Resolvida (Reembolso)',
                    'resolved_partial' => 'Resolvida (Parcial)',
                    'resolved_released' => 'Resolvida (Liberado)',
                    'closed' => 'Fechada',
                ])->required()->label('Status'),
            Forms\Components\Textarea::make('admin_notes')
                ->label('Notas do Admin')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->searchable()
                    ->sortable()
                    ->limit(8)
                    ->label('ID'),
                Tables\Columns\TextColumn::make('order.order_number')
                    ->searchable()
                    ->label('Pedido'),
                Tables\Columns\TextColumn::make('opener.name')
                    ->searchable()
                    ->label('Aberto por'),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->label('Categoria'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'danger',
                        'under_review' => 'warning',
                        'resolved_refund', 'resolved_partial', 'resolved_released' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    })
                    ->label('Status'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->label('Criado em'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Aberta',
                        'under_review' => 'Em Análise',
                        'resolved_refund' => 'Resolvida (Reembolso)',
                        'resolved_partial' => 'Resolvida (Parcial)',
                        'resolved_released' => 'Resolvida (Liberado)',
                        'closed' => 'Fechada',
                    ])->label('Status'),
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'quality' => 'Qualidade',
                        'incomplete' => 'Incompleto',
                        'overcharge' => 'Cobrança indevida',
                        'no_show' => 'Não compareceu',
                        'damage' => 'Dano',
                        'other' => 'Outro',
                    ])->label('Categoria'),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    })->label('Período'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('resolve')
                    ->label('Resolver')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Dispute $record) => in_array($record->status, ['open', 'under_review']))
                    ->form([
                        Forms\Components\Select::make('resolution')
                            ->options([
                                'resolved_refund' => 'Reembolso Total',
                                'resolved_partial' => 'Reembolso Parcial',
                                'resolved_released' => 'Liberado ao Fornecedor',
                                'closed' => 'Fechada',
                            ])
                            ->required()
                            ->label('Resolução'),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Notas do Admin'),
                        Forms\Components\TextInput::make('refund_amount')
                            ->numeric()
                            ->prefix('R$')
                            ->label('Valor do Reembolso'),
                    ])
                    ->action(function (Dispute $record, array $data) {
                        $paymentService = app(PaymentService::class);
                        $resolution = $data['resolution'];

                        DB::transaction(function () use ($record, $data, $resolution, $paymentService) {
                            $order = $record->order;

                            $record->update([
                                'status' => $resolution,
                                'resolution' => $resolution,
                                'admin_notes' => $data['admin_notes'] ?? null,
                                'resolved_at' => now(),
                            ]);

                            $heldPayments = $order->payments()->where('status', 'held')->get();

                            if ($resolution === 'resolved_refund') {
                                foreach ($heldPayments as $payment) {
                                    $paymentService->refund($payment);
                                }
                                $order->update(['status' => 'refunded']);
                            } elseif ($resolution === 'resolved_partial') {
                                $refundAmount = $data['refund_amount'] ?? null;
                                $firstPayment = $heldPayments->first();
                                if ($firstPayment && $refundAmount) {
                                    $paymentService->refund($firstPayment, (float) $refundAmount);
                                }
                                $order->update(['status' => 'refunded']);
                            } elseif ($resolution === 'resolved_released') {
                                foreach ($heldPayments as $payment) {
                                    $paymentService->releasePayment($payment);
                                }
                                $order->update(['status' => 'confirmed']);
                            } else {
                                // closed — no payment action, just settle the order state
                                $order->update(['status' => 'confirmed']);
                            }
                        });

                        // Notify both parties of the resolution.
                        $record->loadMissing(['order.customer', 'order.supplier.user']);
                        $fresh = $record->fresh();
                        if ($fresh->order?->customer) {
                            $fresh->order->customer->notify(new DisputeResolvedNotification($fresh));
                        }
                        if ($fresh->order?->supplier?->user) {
                            $fresh->order->supplier->user->notify(new DisputeResolvedNotification($fresh));
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDisputes::route('/'),
            'edit' => Pages\EditDispute::route('/{record}/edit'),
        ];
    }
}
