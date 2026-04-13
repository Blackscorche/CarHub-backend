<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationLabel = 'Cupons';
    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->required()
                ->unique(ignoreRecord: true)
                ->label('Código'),
            Forms\Components\Select::make('discount_type')
                ->options([
                    'percent' => 'Percentual',
                    'fixed' => 'Valor Fixo',
                ])->required()->label('Tipo de Desconto'),
            Forms\Components\TextInput::make('discount_value')
                ->numeric()
                ->required()
                ->label('Valor do Desconto'),
            Forms\Components\TextInput::make('min_order_value')
                ->numeric()
                ->label('Valor Mínimo do Pedido'),
            Forms\Components\TextInput::make('max_uses')
                ->numeric()
                ->placeholder('Ilimitado')
                ->label('Máximo de Usos (total)'),
            Forms\Components\TextInput::make('max_per_user')
                ->numeric()
                ->placeholder('Ilimitado')
                ->label('Máximo por Usuário'),
            Forms\Components\TextInput::make('used_count')
                ->numeric()
                ->disabled()
                ->dehydrated(false)
                ->label('Vezes Usado'),
            Forms\Components\Select::make('category')
                ->options([
                    'mecanica' => 'Mecânica',
                    'eletrica' => 'Elétrica',
                    'funilaria' => 'Funilaria',
                    'pneus' => 'Pneus',
                    'estetica' => 'Estética',
                    'pecas' => 'Peças',
                    'outros' => 'Outros',
                ])
                ->placeholder('Todas as categorias')
                ->label('Categoria'),
            Forms\Components\Select::make('supplier_id')
                ->relationship('supplier', 'business_name')
                ->searchable()
                ->placeholder('Todos os fornecedores')
                ->label('Fornecedor Específico'),
            Forms\Components\Select::make('funded_by')
                ->options([
                    'platform' => 'Plataforma',
                    'supplier' => 'Fornecedor',
                ])
                ->default('platform')
                ->required()
                ->label('Custeado por'),
            Forms\Components\DateTimePicker::make('valid_from')
                ->label('Válido De'),
            Forms\Components\DateTimePicker::make('valid_until')
                ->label('Válido Até'),
            Forms\Components\Toggle::make('is_active')
                ->default(true)
                ->label('Ativo'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->label('Código'),
                Tables\Columns\TextColumn::make('discount_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'percent' => 'info',
                        'fixed' => 'success',
                        default => 'gray',
                    })
                    ->label('Tipo'),
                Tables\Columns\TextColumn::make('discount_value')
                    ->sortable()
                    ->label('Valor'),
                Tables\Columns\TextColumn::make('min_order_value')
                    ->money('BRL')
                    ->label('Pedido Mínimo'),
                Tables\Columns\TextColumn::make('max_uses')
                    ->label('Máx. Total'),
                Tables\Columns\TextColumn::make('max_per_user')
                    ->label('Máx./Usuário'),
                Tables\Columns\TextColumn::make('used_count')
                    ->label('Usado'),
                Tables\Columns\TextColumn::make('category')
                    ->label('Categoria'),
                Tables\Columns\TextColumn::make('supplier.business_name')
                    ->label('Fornecedor')
                    ->placeholder('Todos'),
                Tables\Columns\TextColumn::make('funded_by')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'platform' ? 'info' : 'warning')
                    ->label('Custeado por'),
                Tables\Columns\TextColumn::make('valid_from')
                    ->dateTime('d/m/Y H:i')
                    ->label('Válido De'),
                Tables\Columns\TextColumn::make('valid_until')
                    ->dateTime('d/m/Y H:i')
                    ->label('Válido Até'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Ativo'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Ativo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
