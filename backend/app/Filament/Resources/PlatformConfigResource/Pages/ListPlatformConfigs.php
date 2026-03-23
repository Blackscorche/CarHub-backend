<?php

namespace App\Filament\Resources\PlatformConfigResource\Pages;

use App\Filament\Resources\PlatformConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlatformConfigs extends ListRecords
{
    protected static string $resource = PlatformConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
