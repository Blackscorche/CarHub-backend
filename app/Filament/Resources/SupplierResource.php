<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Notifications\SupplierApprovedNotification;
use App\Notifications\SupplierRejectedNotification;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationLabel = 'Fornecedores';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('business_name')->required()->label('Nome do Estabelecimento'),
            Forms\Components\TextInput::make('cnpj')->label('CNPJ'),
            Forms\Components\Select::make('category')
                ->options([
                    'mecanica' => 'Mecânica',
                    'eletrica' => 'Elétrica',
                    'funilaria' => 'Funilaria',
                    'pneus' => 'Pneus',
                    'estetica' => 'Estética',
                    'pecas' => 'Peças',
                    'outros' => 'Outros',
                ])->required(),
            Forms\Components\Textarea::make('description')->label('Descrição'),
            Forms\Components\Select::make('approval_status')
                ->options([
                    'pending' => 'Pendente',
                    'approved' => 'Aprovado',
                    'rejected' => 'Rejeitado',
                    'suspended' => 'Suspenso',
                ])->required(),
            Forms\Components\Toggle::make('is_verified')->label('Verificado'),
            Forms\Components\TextInput::make('service_radius_km')->numeric()->label('Raio de Atendimento (km)'),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Tabs::make('Supplier Details')
                ->tabs([
                    // Tab 1: Basic Info
                    Infolists\Components\Tabs\Tab::make('Informações')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Infolists\Components\Section::make('Dados do Estabelecimento')->schema([
                                Infolists\Components\TextEntry::make('business_name')->label('Estabelecimento'),
                                Infolists\Components\TextEntry::make('cnpj')->label('CNPJ'),
                                Infolists\Components\TextEntry::make('category')->badge()->label('Categoria'),
                                Infolists\Components\TextEntry::make('description')->label('Descrição'),
                                Infolists\Components\TextEntry::make('service_radius_km')->suffix(' km')->label('Raio'),
                                Infolists\Components\TextEntry::make('avg_rating')->label('Avaliação'),
                                Infolists\Components\TextEntry::make('total_ratings')->label('Total avaliações'),
                                Infolists\Components\TextEntry::make('pagarme_recipient_id')->label('Pagar.me Recipient'),
                            ])->columns(2),
                            Infolists\Components\Section::make('Responsável')->schema([
                                Infolists\Components\TextEntry::make('user.name')->label('Nome'),
                                Infolists\Components\TextEntry::make('user.email')->label('Email'),
                                Infolists\Components\TextEntry::make('user.phone')->label('Telefone'),
                            ])->columns(3),
                            Infolists\Components\Section::make('Status')->schema([
                                Infolists\Components\TextEntry::make('approval_status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'approved' => 'success',
                                        'pending' => 'warning',
                                        'rejected' => 'danger',
                                        'suspended' => 'gray',
                                        default => 'gray',
                                    })
                                    ->label('Status'),
                                Infolists\Components\IconEntry::make('is_verified')->boolean()->label('Verificado'),
                                Infolists\Components\TextEntry::make('approved_at')->dateTime('d/m/Y H:i')->label('Aprovado em'),
                                Infolists\Components\TextEntry::make('created_at')->dateTime('d/m/Y H:i')->label('Cadastrado em'),
                                Infolists\Components\TextEntry::make('rejected_at')->dateTime('d/m/Y H:i')->label('Rejeitado em')
                                    ->visible(fn (Supplier $record) => !empty($record->rejected_at)),
                                Infolists\Components\TextEntry::make('rejection_reason')->label('Motivo da rejeição')
                                    ->columnSpanFull()
                                    ->visible(fn (Supplier $record) => !empty($record->rejection_reason)),
                            ])->columns(4),
                        ]),

                    // Tab 2: KYC Documents
                    Infolists\Components\Tabs\Tab::make('KYC / Documentos')
                        ->icon('heroicon-o-document-check')
                        ->schema([
                            Infolists\Components\TextEntry::make('kyc_document_url')
                                ->label('Documento KYC')
                                ->url(fn (Supplier $record) => $record->kyc_document_url)
                                ->openUrlInNewTab()
                                ->placeholder('Nenhum documento enviado'),
                            Infolists\Components\ImageEntry::make('logo_url')->label('Logo')->circular(),
                            Infolists\Components\ImageEntry::make('cover_image_url')->label('Capa'),
                            Infolists\Components\Section::make('Dados Bancários')->schema([
                                Infolists\Components\TextEntry::make('bankAccount.bank_code')->label('Banco'),
                                Infolists\Components\TextEntry::make('bankAccount.agencia')->label('Agência'),
                                Infolists\Components\TextEntry::make('bankAccount.type')->label('Tipo'),
                                Infolists\Components\TextEntry::make('bankAccount.legal_name')->label('Titular'),
                            ])->columns(4),
                        ]),

                    // Tab 3: Orders
                    Infolists\Components\Tabs\Tab::make('Pedidos')
                        ->icon('heroicon-o-shopping-bag')
                        ->schema([
                            Infolists\Components\TextEntry::make('orders_count')
                                ->label('Total de pedidos')
                                ->getStateUsing(fn (Supplier $record) => $record->orders()->count()),
                            Infolists\Components\TextEntry::make('orders_total')
                                ->label('Faturamento total')
                                ->getStateUsing(fn (Supplier $record) => 'R$ ' . number_format($record->orders()->where('status', 'confirmed')->sum('total'), 2, ',', '.')),
                            Infolists\Components\RepeatableEntry::make('orders')
                                ->label('')
                                ->schema([
                                    Infolists\Components\TextEntry::make('order_number')->label('#'),
                                    Infolists\Components\TextEntry::make('total')->money('BRL')->label('Valor'),
                                    Infolists\Components\TextEntry::make('status')->badge()->label('Status'),
                                    Infolists\Components\TextEntry::make('created_at')->dateTime('d/m/Y')->label('Data'),
                                ])->columns(4)
                                ->getStateUsing(fn (Supplier $record) => $record->orders()->latest()->limit(20)->get()),
                        ]),

                    // Tab 4: Reviews
                    Infolists\Components\Tabs\Tab::make('Avaliações')
                        ->icon('heroicon-o-star')
                        ->schema([
                            Infolists\Components\TextEntry::make('avg_rating')->label('Média'),
                            Infolists\Components\TextEntry::make('total_ratings')->label('Total'),
                            Infolists\Components\RepeatableEntry::make('reviews')
                                ->label('')
                                ->schema([
                                    Infolists\Components\TextEntry::make('customer.name')->label('Cliente'),
                                    Infolists\Components\TextEntry::make('rating')->label('Nota'),
                                    Infolists\Components\TextEntry::make('comment')->label('Comentário')->limit(100),
                                    Infolists\Components\TextEntry::make('created_at')->dateTime('d/m/Y')->label('Data'),
                                ])->columns(4)
                                ->getStateUsing(fn (Supplier $record) => $record->reviews()->with('customer:id,name')->latest()->limit(20)->get()),
                        ]),

                    // Tab 5: Settlement
                    Infolists\Components\Tabs\Tab::make('Financeiro')
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Infolists\Components\TextEntry::make('settlement_held')
                                ->label('Retido')
                                ->getStateUsing(fn (Supplier $record) => 'R$ ' . number_format(
                                    $record->orders()->whereHas('payments', fn ($q) => $q->where('status', 'held'))->get()->sum(fn ($o) => $o->payments->where('status', 'held')->sum('supplier_amount')),
                                    2, ',', '.'
                                )),
                            Infolists\Components\TextEntry::make('settlement_released')
                                ->label('Liberado')
                                ->getStateUsing(fn (Supplier $record) => 'R$ ' . number_format(
                                    $record->orders()->whereHas('payments', fn ($q) => $q->where('status', 'released'))->get()->sum(fn ($o) => $o->payments->where('status', 'released')->sum('supplier_amount')),
                                    2, ',', '.'
                                )),
                            Infolists\Components\TextEntry::make('milestone_count')
                                ->label('Milestones ativos')
                                ->getStateUsing(fn (Supplier $record) => $record->orders()->whereHas('milestones')->withCount('milestones')->get()->sum('milestones_count')),
                        ]),
                ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('business_name')->searchable()->sortable()->label('Estabelecimento'),
                Tables\Columns\TextColumn::make('user.name')->searchable()->label('Responsável'),
                Tables\Columns\TextColumn::make('user.email')->label('Email'),
                Tables\Columns\TextColumn::make('cnpj'),
                Tables\Columns\TextColumn::make('category')->badge()->label('Categoria'),
                Tables\Columns\TextColumn::make('approval_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        'suspended' => 'gray',
                        default => 'gray',
                    })
                    ->label('Status'),
                Tables\Columns\TextColumn::make('avg_rating')->sortable()->label('Avaliação'),
                Tables\Columns\TextColumn::make('orders_count')
                    ->counts('orders')
                    ->sortable()
                    ->label('Pedidos'),
                Tables\Columns\IconColumn::make('is_verified')->boolean()->label('Verificado'),
                Tables\Columns\IconColumn::make('kyc_submitted')
                    ->label('KYC')
                    ->boolean()
                    ->getStateUsing(fn (Supplier $record) => !empty($record->kyc_document_url))
                    ->tooltip(fn (Supplier $record) => $record->kyc_document_url ? 'Documento enviado' : 'Sem documento'),
                Tables\Columns\TextColumn::make('created_at')->sortable()->dateTime('d/m/Y'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('approval_status')
                    ->options([
                        'pending' => 'Pendente',
                        'approved' => 'Aprovado',
                        'rejected' => 'Rejeitado',
                        'suspended' => 'Suspenso',
                    ])->label('Status'),
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'mecanica' => 'Mecânica',
                        'eletrica' => 'Elétrica',
                        'funilaria' => 'Funilaria',
                        'pneus' => 'Pneus',
                        'estetica' => 'Estética',
                        'pecas' => 'Peças',
                        'outros' => 'Outros',
                    ]),
                Tables\Filters\TernaryFilter::make('is_verified')->label('Verificado'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Supplier $record) => $record->approval_status === 'pending')
                    ->action(function (Supplier $record) {
                        $record->update(['approval_status' => 'approved', 'approved_at' => now()]);
                        $record->user->update(['status' => 'active']);
                        $record->user->notify(new SupplierApprovedNotification($record->user));
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Rejeitar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Motivo da rejeição')
                            ->required()
                            ->minLength(10)
                            ->rows(4)
                            ->placeholder('Explique por que o cadastro não foi aprovado (será enviado ao fornecedor).'),
                    ])
                    ->visible(fn (Supplier $record) => $record->approval_status === 'pending')
                    ->action(function (Supplier $record, array $data) {
                        $reason = $data['rejection_reason'];
                        $record->update([
                            'approval_status' => 'rejected',
                            'rejection_reason' => $reason,
                            'rejected_at' => now(),
                        ]);
                        $record->user->update(['status' => 'suspended']);
                        $record->user->notify(new SupplierRejectedNotification($record->user, $reason));
                    }),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspender')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Motivo da suspensão')
                            ->required()
                            ->minLength(10)
                            ->rows(4),
                    ])
                    ->visible(fn (Supplier $record) => $record->approval_status === 'approved')
                    ->action(function (Supplier $record, array $data) {
                        $reason = $data['rejection_reason'];
                        $record->update([
                            'approval_status' => 'suspended',
                            'rejection_reason' => $reason,
                            'rejected_at' => now(),
                        ]);
                        $record->user->update(['status' => 'suspended']);
                        $record->user->notify(new SupplierRejectedNotification($record->user, $reason));
                    }),
                Tables\Actions\Action::make('reactivate')
                    ->label('Reativar')
                    ->icon('heroicon-o-arrow-uturn-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Supplier $record) => in_array($record->approval_status, ['suspended', 'rejected']))
                    ->action(function (Supplier $record) {
                        $record->update([
                            'approval_status' => 'approved',
                            'approved_at' => $record->approved_at ?? now(),
                            'rejection_reason' => null,
                            'rejected_at' => null,
                        ]);
                        $record->user->update(['status' => 'active']);
                        $record->user->notify(new SupplierApprovedNotification($record->user));
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'view' => Pages\ViewSupplier::route('/{record}'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
