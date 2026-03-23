<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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
                ])->required(),
            Forms\Components\Toggle::make('is_verified')->label('Verificado'),
            Forms\Components\TextInput::make('service_radius_km')->numeric()->label('Raio de Atendimento (km)'),
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
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->label('Categoria'),
                Tables\Columns\TextColumn::make('approval_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                    })
                    ->label('Status'),
                Tables\Columns\TextColumn::make('avg_rating')->sortable()->label('Avaliação'),
                Tables\Columns\IconColumn::make('is_verified')->boolean()->label('Verificado'),
                Tables\Columns\TextColumn::make('created_at')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('approval_status')
                    ->options([
                        'pending' => 'Pendente',
                        'approved' => 'Aprovado',
                        'rejected' => 'Rejeitado',
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
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Supplier $record) => $record->approval_status === 'pending')
                    ->action(function (Supplier $record) {
                        $record->update([
                            'approval_status' => 'approved',
                            'approved_at' => now(),
                        ]);
                        $record->user->update(['status' => 'active']);
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Rejeitar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Supplier $record) => $record->approval_status === 'pending')
                    ->action(function (Supplier $record) {
                        $record->update(['approval_status' => 'rejected']);
                        $record->user->update(['status' => 'suspended']);
                    }),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspender')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Supplier $record) => $record->approval_status === 'approved')
                    ->action(function (Supplier $record) {
                        $record->update(['approval_status' => 'rejected']);
                        $record->user->update(['status' => 'suspended']);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
