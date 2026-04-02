<div class="space-y-4">
    @if($milestone->evidence_description)
        <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Descrição:</p>
            <p class="text-sm text-gray-900 dark:text-gray-100">{{ $milestone->evidence_description }}</p>
        </div>
    @endif

    @if($milestone->contest_reason)
        <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
            <p class="text-sm font-medium text-red-600 dark:text-red-400">Motivo da contestação:</p>
            <p class="text-sm text-red-900 dark:text-red-100">{{ $milestone->contest_reason }}</p>
        </div>
    @endif

    @if($milestone->evidence_urls && count($milestone->evidence_urls) > 0)
        <div class="grid grid-cols-3 gap-2">
            @foreach($milestone->evidence_urls as $url)
                <a href="{{ $url }}" target="_blank" class="block">
                    <img src="{{ $url }}" alt="Evidência" class="w-full h-32 object-cover rounded-lg border">
                </a>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-500">Nenhuma evidência enviada.</p>
    @endif
</div>
