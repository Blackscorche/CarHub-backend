<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentMilestoneResource\Pages;
use App\Models\PaymentMilestone;
use App\Services\MilestoneService;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class PaymentMilestoneResource extends Resource
{
    protected static ?string $model = PaymentMilestone::class;
    protected static ?string $navigationIcon = 'heroicon-o-flag';
    protected static ?string $navigationLabel = 'Milestones';
    protected static ?string $navigationGroup = 'Pagamentos';
    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.order_number')
                    ->searchable()
                    ->label('Pedido'),
                Tables\Columns\TextColumn::make('sequence')
                    ->sortable()
                    ->label('#'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('BRL')
                    ->sortable()
                    ->label('Valor'),
                Tables\Columns\TextColumn::make('percentage')
                    ->suffix('%')
                    ->label('%'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'warning',
                        'delivered' => 'info',
                        'approved' => 'success',
                        'declined' => 'danger',
                        'contested' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    })
                    ->label('Status'),
                Tables\Columns\IconColumn::make('is_final')
                    ->boolean()
                    ->label('Final'),
                Tables\Columns\IconColumn::make('has_evidence')
                    ->boolean()
                    ->getStateUsing(fn (PaymentMilestone $record) => !empty($record->evidence_urls))
                    ->label('Evidência'),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Pago em'),
                Tables\Columns\TextColumn::make('released_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Liberado em')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('contested_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Contestado em')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'paid' => 'Aguardando entrega',
                        'delivered' => 'Entregue',
                        'approved' => 'Aprovado',
                        'declined' => 'Recusado',
                        'contested' => 'Contestado',
                        'refunded' => 'Reembolsado',
                    ])->label('Status'),
                Tables\Filters\TernaryFilter::make('is_final')
                    ->label('Pagamento final'),
                Tables\Filters\Filter::make('contested')
                    ->query(fn ($query) => $query->where('status', 'contested'))
                    ->label('Apenas contestados'),
            ])
            ->actions([
                Tables\Actions\Action::make('release')
                    ->label('Liberar')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Liberar pagamento ao fornecedor')
                    ->modalDescription(fn (PaymentMilestone $record) => "Liberar R$ {$record->amount} para o fornecedor?")
                    ->visible(fn (PaymentMilestone $record) => $record->status === 'contested' && $record->released_at === null)
                    ->action(function (PaymentMilestone $record) {
                        app(MilestoneService::class)->adminResolve($record, 'release');
                        Notification::make()->title('Pagamento liberado.')->success()->send();
                    }),
                Tables\Actions\Action::make('refund')
                    ->label('Reembolsar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reembolsar pagamento ao cliente')
                    ->modalDescription(fn (PaymentMilestone $record) => "Reembolsar R$ {$record->amount} ao cliente?")
                    ->visible(fn (PaymentMilestone $record) => $record->status === 'contested')
                    ->action(function (PaymentMilestone $record) {
                        app(MilestoneService::class)->adminResolve($record, 'refund');
                        Notification::make()->title('Reembolso processado.')->success()->send();
                    }),
                Tables\Actions\Action::make('view_evidence')
                    ->label('Ver evidência')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->visible(fn (PaymentMilestone $record) => !empty($record->evidence_urls))
                    ->modalHeading('Evidências')
                    ->modalContent(fn (PaymentMilestone $record) => view('filament.milestone-evidence', ['milestone' => $record]))
                    ->modalSubmitAction(false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePaymentMilestones::route('/'),
        ];
    }
}
