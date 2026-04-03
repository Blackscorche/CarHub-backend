<?php

namespace App\Filament\Resources\PlatformConfigResource\Pages;

use App\Filament\Resources\PlatformConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Cache;

class EditPlatformConfig extends EditRecord
{
    protected static string $resource = PlatformConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Clear cached platform configs so new values take effect immediately
        Cache::forget('platform:commission_rate');
        Cache::forget('platform:hold_period_hours');
        Cache::forget('platform:partial_payment_percent');
    }
}
