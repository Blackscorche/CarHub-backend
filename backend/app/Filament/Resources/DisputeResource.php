<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DisputeResource\Pages;
use App\Models\Dispute;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DisputeResource extends Resource
{
    protected static ?string $model = Dispute::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationLabel = 'Disputas';
    protected static ?int $navigationSort = 6;

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
                        $record->update([
                            'status' => $data['resolution'],
                            'resolution' => $data['resolution'],
                            'admin_notes' => $data['admin_notes'],
                            'resolved_at' => now(),
                        ]);
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
