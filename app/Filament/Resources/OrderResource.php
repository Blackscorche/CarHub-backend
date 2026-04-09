<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Pedidos';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('status')
                ->options([
                    'pending' => 'Pendente',
                    'accepted' => 'Aceito',
                    'paid' => 'Pago',
                    'partially_paid' => 'Parcialmente pago',
                    'in_progress' => 'Em Andamento',
                    'completed' => 'Concluído',
                    'confirmed' => 'Confirmado',
                    'cancelled' => 'Cancelado',
                ])->required()->label('Status'),
            Forms\Components\Textarea::make('notes')->label('Observações')->columnSpanFull(),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Tabs::make('Order Details')
                ->tabs([
                    // Tab 1: Timeline + Info
                    Infolists\Components\Tabs\Tab::make('Resumo')
                        ->icon('heroicon-o-clock')
                        ->schema([
                            Infolists\Components\Section::make('Informações do Pedido')->schema([
                                Infolists\Components\TextEntry::make('order_number')->label('Número'),
                                Infolists\Components\TextEntry::make('type')->badge()->label('Tipo'),
                                Infolists\Components\TextEntry::make('payment_model')->badge()
                                    ->color(fn (string $state): string => $state === 'milestone' ? 'warning' : 'success')
                                    ->label('Modelo'),
                                Infolists\Components\TextEntry::make('status')->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'confirmed' => 'success',
                                        'paid', 'completed' => 'info',
                                        'in_progress' => 'primary',
                                        'pending', 'accepted' => 'warning',
                                        'cancelled', 'rejected' => 'danger',
                                        default => 'gray',
                                    })
                                    ->label('Status'),
                                Infolists\Components\TextEntry::make('delivery_type')->label('Entrega'),
                                Infolists\Components\TextEntry::make('confirmation_code')->label('Código'),
                            ])->columns(3),
                            Infolists\Components\Section::make('Partes')->schema([
                                Infolists\Components\TextEntry::make('customer.name')->label('Cliente'),
                                Infolists\Components\TextEntry::make('customer.email')->label('Email'),
                                Infolists\Components\TextEntry::make('supplier.business_name')->label('Fornecedor'),
                                Infolists\Components\TextEntry::make('vehicle.brand')
                                    ->getStateUsing(fn (Order $record) => $record->vehicle ? "{$record->vehicle->brand} {$record->vehicle->model} ({$record->vehicle->year})" : '—')
                                    ->label('Veículo'),
                            ])->columns(2),
                            Infolists\Components\Section::make('Timeline')->schema([
                                Infolists\Components\TextEntry::make('created_at')->dateTime('d/m/Y H:i')->label('Criado'),
                                Infolists\Components\TextEntry::make('accepted_at')->dateTime('d/m/Y H:i')->label('Aceito')->placeholder('—'),
                                Infolists\Components\TextEntry::make('started_at')->dateTime('d/m/Y H:i')->label('Iniciado')->placeholder('—'),
                                Infolists\Components\TextEntry::make('completed_at')->dateTime('d/m/Y H:i')->label('Concluído')->placeholder('—'),
                                Infolists\Components\TextEntry::make('cancelled_at')->dateTime('d/m/Y H:i')->label('Cancelado')->placeholder('—'),
                                Infolists\Components\TextEntry::make('cancellation_reason')->label('Motivo cancelamento')->placeholder('—'),
                            ])->columns(3),
                            Infolists\Components\TextEntry::make('notes')->label('Observações')->placeholder('—')->columnSpanFull(),
                        ]),

                    // Tab 2: Items
                    Infolists\Components\Tabs\Tab::make('Itens')
                        ->icon('heroicon-o-list-bullet')
                        ->schema([
                            Infolists\Components\RepeatableEntry::make('items')
                                ->label('')
                                ->schema([
                                    Infolists\Components\TextEntry::make('name')->label('Item'),
                                    Infolists\Components\TextEntry::make('quantity')->label('Qtd'),
                                    Infolists\Components\TextEntry::make('unit_price')->money('BRL')->label('Preço unit.'),
                                    Infolists\Components\TextEntry::make('total_price')->money('BRL')->label('Total'),
                                ])->columns(4),
                            Infolists\Components\Section::make('Valores')->schema([
                                Infolists\Components\TextEntry::make('subtotal')->money('BRL')->label('Subtotal'),
                                Infolists\Components\TextEntry::make('platform_fee')->money('BRL')->label('Taxa plataforma'),
                                Infolists\Components\TextEntry::make('commission_rate')->suffix('%')->label('Comissão'),
                                Infolists\Components\TextEntry::make('total')->money('BRL')->label('Total'),
                            ])->columns(4),
                        ]),

                    // Tab 3: Payments
                    Infolists\Components\Tabs\Tab::make('Pagamentos')
                        ->icon('heroicon-o-credit-card')
                        ->schema([
                            Infolists\Components\RepeatableEntry::make('payments')
                                ->label('')
                                ->schema([
                                    Infolists\Components\TextEntry::make('pagarme_charge_id')->label('Pagar.me ID')->limit(15),
                                    Infolists\Components\TextEntry::make('amount')->money('BRL')->label('Valor'),
                                    Infolists\Components\TextEntry::make('method')->badge()->label('Método'),
                                    Infolists\Components\TextEntry::make('type')->label('Tipo'),
                                    Infolists\Components\TextEntry::make('status')->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'held', 'released' => 'success',
                                            'pending' => 'warning',
                                            'failed', 'refunded' => 'danger',
                                            default => 'gray',
                                        })
                                        ->label('Status'),
                                    Infolists\Components\TextEntry::make('created_at')->dateTime('d/m/Y H:i')->label('Data'),
                                ])->columns(6),
                        ]),

                    // Tab 4: Milestones (service orders)
                    Infolists\Components\Tabs\Tab::make('Milestones')
                        ->icon('heroicon-o-flag')
                        ->visible(fn (Order $record) => $record->payment_model === 'milestone')
                        ->schema([
                            Infolists\Components\RepeatableEntry::make('milestones')
                                ->label('')
                                ->schema([
                                    Infolists\Components\TextEntry::make('sequence')->label('#'),
                                    Infolists\Components\TextEntry::make('amount')->money('BRL')->label('Valor'),
                                    Infolists\Components\TextEntry::make('percentage')->suffix('%')->label('%'),
                                    Infolists\Components\TextEntry::make('status')->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'approved' => 'success',
                                            'paid' => 'warning',
                                            'delivered' => 'info',
                                            'declined', 'contested' => 'danger',
                                            'refunded' => 'gray',
                                            default => 'gray',
                                        })
                                        ->label('Status'),
                                    Infolists\Components\IconEntry::make('is_final')->boolean()->label('Final'),
                                    Infolists\Components\TextEntry::make('released_at')->dateTime('d/m/Y H:i')->label('Liberado')->placeholder('—'),
                                ])->columns(6),
                        ]),

                    // Tab 5: Chat
                    Infolists\Components\Tabs\Tab::make('Chat')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->schema([
                            Infolists\Components\RepeatableEntry::make('chatMessages')
                                ->label('')
                                ->schema([
                                    Infolists\Components\TextEntry::make('sender.name')->label('De'),
                                    Infolists\Components\TextEntry::make('message')->label('Mensagem')->limit(80),
                                    Infolists\Components\TextEntry::make('type')->badge()->label('Tipo'),
                                    Infolists\Components\TextEntry::make('created_at')->dateTime('d/m/Y H:i')->label('Data'),
                                ])->columns(4)
                                ->getStateUsing(fn (Order $record) => $record->chatMessages()->with('sender:id,name')->latest()->limit(50)->get()),
                        ]),
                ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->searchable()->sortable()->label('Número'),
                Tables\Columns\TextColumn::make('customer.name')->searchable()->label('Cliente'),
                Tables\Columns\TextColumn::make('supplier.business_name')->searchable()->label('Fornecedor'),
                Tables\Columns\TextColumn::make('total')->money('BRL')->sortable()->label('Total'),
                Tables\Columns\TextColumn::make('payment_model')->badge()
                    ->color(fn (string $state): string => $state === 'milestone' ? 'warning' : 'success')
                    ->label('Modelo'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'success',
                        'paid', 'completed' => 'info',
                        'in_progress' => 'primary',
                        'pending', 'accepted', 'partially_paid' => 'warning',
                        'cancelled', 'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->label('Status'),
                Tables\Columns\TextColumn::make('payment_method')->label('Pagamento'),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d/m/Y H:i')->sortable()->label('Criado em'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pendente',
                        'accepted' => 'Aceito',
                        'partially_paid' => 'Parcialmente pago',
                        'paid' => 'Pago',
                        'in_progress' => 'Em Andamento',
                        'completed' => 'Concluído',
                        'confirmed' => 'Confirmado',
                        'cancelled' => 'Cancelado',
                    ])->label('Status'),
                Tables\Filters\SelectFilter::make('payment_model')
                    ->options(['instant' => 'Instantâneo', 'milestone' => 'Por etapas'])
                    ->label('Modelo'),
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
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'business_name')
                    ->label('Fornecedor'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
