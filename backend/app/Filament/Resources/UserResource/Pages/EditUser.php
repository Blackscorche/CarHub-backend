<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        if ($this->record->isAdmin()) {
            abort(403, 'Admin users cannot be edited.');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => !$this->record->isAdmin()),
        ];
    }
}
