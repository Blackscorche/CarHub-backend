<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlatformConfigResource\Pages;
use App\Models\PlatformConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlatformConfigResource extends Resource
{
    protected static ?string $model = PlatformConfig::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Configurações';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('key')
                ->required()
                ->unique(ignoreRecord: true)
                ->label('Chave'),
            Forms\Components\Textarea::make('value')
                ->required()
                ->json()
                ->label('Valor (JSON)')
                ->columnSpanFull(),
            Forms\Components\Textarea::make('description')
                ->label('Descrição')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->label('Chave'),
                Tables\Columns\TextColumn::make('value')
                    ->limit(50)
                    ->label('Valor'),
                Tables\Columns\TextColumn::make('description')
                    ->limit(60)
                    ->label('Descrição'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->label('Atualizado em'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformConfigs::route('/'),
            'create' => Pages\CreatePlatformConfig::route('/create'),
            'edit' => Pages\EditPlatformConfig::route('/{record}/edit'),
        ];
    }
}
