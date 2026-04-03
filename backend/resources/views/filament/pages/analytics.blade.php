<x-filament-panels::page>
    {{-- Period Selector --}}
    <div class="flex items-center gap-3 mb-6">
        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Período:</span>
        @foreach ([7 => '7 dias', 30 => '30 dias', 90 => '90 dias'] as $days => $label)
            <button
                wire:click="$set('period', '{{ $days }}')"
                class="px-4 py-2 text-sm rounded-lg border transition
                    {{ $this->period == $days
                        ? 'bg-primary-500 text-white border-primary-500'
                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50' }}"
            >
                {{ $label }}
            </button>
        @endforeach
        <button
            wire:click="exportCsv"
            class="ml-auto px-4 py-2 text-sm rounded-lg bg-green-600 text-white hover:bg-green-700 flex items-center gap-2"
        >
            <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
            Exportar CSV
        </button>
    </div>

    {{-- Stats Grid --}}
    @php $stats = $this->getStats(); @endphp
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        @foreach ([
            ['label' => 'Total Pedidos', 'value' => $stats['total_orders'], 'color' => 'blue'],
            ['label' => 'Concluídos', 'value' => $stats['completed_orders'], 'color' => 'green'],
            ['label' => 'Cancelados', 'value' => $stats['cancelled_orders'], 'color' => 'red'],
            ['label' => 'GMV', 'value' => 'R$ ' . number_format($stats['gmv'], 2, ',', '.'), 'color' => 'purple'],
            ['label' => 'Receita Plataforma', 'value' => 'R$ ' . number_format($stats['platform_revenue'], 2, ',', '.'), 'color' => 'emerald'],
            ['label' => 'Ticket Médio', 'value' => 'R$ ' . number_format($stats['avg_order_value'], 2, ',', '.'), 'color' => 'amber'],
            ['label' => 'Novos Clientes', 'value' => $stats['new_customers'], 'color' => 'cyan'],
            ['label' => 'Novos Fornecedores', 'value' => $stats['new_suppliers'], 'color' => 'indigo'],
            ['label' => 'Pedidos Milestone', 'value' => $stats['milestone_orders'], 'color' => 'orange'],
            ['label' => 'Pedidos Instant', 'value' => $stats['instant_orders'], 'color' => 'teal'],
        ] as $stat)
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">{{ $stat['label'] }}</p>
                <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Top Suppliers --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Top 10 Fornecedores</h3>
        </div>
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fornecedor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pedidos</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Faturamento</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avaliação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($this->getTopSuppliers() as $supplier)
                    <tr>
                        <td class="px-6 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $supplier['name'] }}</td>
                        <td class="px-6 py-3 text-sm text-gray-500">{{ $supplier['category'] }}</td>
                        <td class="px-6 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ $supplier['orders'] }}</td>
                        <td class="px-6 py-3 text-sm text-right text-gray-900 dark:text-gray-100">R$ {{ number_format($supplier['revenue'], 2, ',', '.') }}</td>
                        <td class="px-6 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ $supplier['rating'] }} ★</td>
                    </tr>
                @endforeach
                @if (empty($this->getTopSuppliers()))
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">Nenhum dado no período selecionado.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
