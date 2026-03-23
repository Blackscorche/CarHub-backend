<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Usuários';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('email')->email()->required(),
            Forms\Components\TextInput::make('phone'),
            Forms\Components\TextInput::make('cpf'),
            Forms\Components\Select::make('role')
                ->options([
                    'customer' => 'Cliente',
                    'supplier' => 'Fornecedor',
                    'admin' => 'Admin',
                ])->required(),
            Forms\Components\Select::make('status')
                ->options([
                    'active' => 'Ativo',
                    'suspended' => 'Suspenso',
                    'pending_approval' => 'Aguardando Aprovação',
                ])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'supplier' => 'warning',
                        'customer' => 'success',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'danger',
                        'pending_approval' => 'warning',
                    }),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('created_at')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'customer' => 'Cliente',
                        'supplier' => 'Fornecedor',
                        'admin' => 'Admin',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Ativo',
                        'suspended' => 'Suspenso',
                        'pending_approval' => 'Aguardando Aprovação',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (User $record) => !$record->isAdmin()),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspender')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => $record->status === 'active' && !$record->isAdmin())
                    ->action(fn (User $record) => $record->update(['status' => 'suspended'])),
                Tables\Actions\Action::make('activate')
                    ->label('Ativar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => $record->status !== 'active' && !$record->isAdmin())
                    ->action(fn (User $record) => $record->update(['status' => 'active'])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
