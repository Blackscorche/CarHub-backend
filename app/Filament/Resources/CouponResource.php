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
                ->label('Máximo de Usos'),
            Forms\Components\TextInput::make('used_count')
                ->numeric()
                ->disabled()
                ->dehydrated(false)
                ->label('Vezes Usado'),
            Forms\Components\TextInput::make('category')
                ->label('Categoria'),
            Forms\Components\TextInput::make('region')
                ->label('Região'),
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
                    ->label('Máx. Usos'),
                Tables\Columns\TextColumn::make('used_count')
                    ->label('Usado'),
                Tables\Columns\TextColumn::make('category')
                    ->label('Categoria'),
                Tables\Columns\TextColumn::make('region')
                    ->label('Região'),
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
