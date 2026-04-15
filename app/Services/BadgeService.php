<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Supplier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BadgeService
{
    /**
     * Compute badges for a supplier.
     * Returns array of badge keys like ['top_region', 'fastest', 'most_hired']
     */
    public function getBadges(Supplier $supplier): array
    {
        return Cache::remember("supplier:badges:{$supplier->id}", 3600, function () use ($supplier) {
            $badges = [];

            // Top da Região — top 3 by avg_rating in same state
            if ($this->isTopInRegion($supplier)) {
                $badges[] = 'top_region';
            }

            // Melhor Prazo — fastest avg completion time (within top 5 in category)
            if ($this->isFastestInCategory($supplier)) {
                $badges[] = 'fastest';
            }

            // Mais Contratado — highest order volume (top 5 overall)
            if ($this->isMostHired($supplier)) {
                $badges[] = 'most_hired';
            }

            // Verificado — admin-approved with verified documents
            if ($supplier->is_verified) {
                $badges[] = 'verified';
            }

            return $badges;
        });
    }

    protected function isTopInRegion(Supplier $supplier): bool
    {
        if (!$supplier->address_id || $supplier->total_ratings < 3) return false;

        $supplier->loadMissing('address');
        $state = $supplier->address?->state;
        if (!$state) return false;

        $topIds = Supplier::query()
            ->whereHas('address', fn($q) => $q->where('state', $state))
            ->where('approval_status', 'approved')
            ->where('total_ratings', '>=', 3)
            ->orderByDesc('avg_rating')
            ->orderByDesc('total_ratings')
            ->limit(3)
            ->pluck('id')
            ->toArray();

        return in_array($supplier->id, $topIds);
    }

    protected function isFastestInCategory(Supplier $supplier): bool
    {
        $completedCount = Order::where('supplier_id', $supplier->id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->count();
        if ($completedCount < 5) return false;

        $avgHours = $this->getAverageCompletionHours($supplier->id);
        if ($avgHours === null) return false;

        // Get top 5 fastest in same category
        $category = $supplier->category;
        $fasterSuppliers = Supplier::where('category', $category)
            ->where('approval_status', 'approved')
            ->where('id', '!=', $supplier->id)
            ->get()
            ->filter(function ($s) use ($avgHours) {
                $otherAvg = $this->getAverageCompletionHours($s->id);
                return $otherAvg !== null && $otherAvg < $avgHours;
            })
            ->count();

        return $fasterSuppliers < 5;
    }

    protected function getAverageCompletionHours(string $supplierId): ?float
    {
        $result = DB::table('orders')
            ->where('supplier_id', $supplierId)
            ->whereIn('status', ['confirmed', 'completed'])
            ->whereNotNull('accepted_at')
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, accepted_at, completed_at)) as avg_hours')
            ->first();

        return $result?->avg_hours ? (float) $result->avg_hours : null;
    }

    protected function isMostHired(Supplier $supplier): bool
    {
        $orderCount = Order::where('supplier_id', $supplier->id)
            ->whereIn('status', ['confirmed', 'completed', 'in_progress'])
            ->count();
        if ($orderCount < 10) return false;

        $topIds = DB::table('orders')
            ->whereIn('status', ['confirmed', 'completed', 'in_progress'])
            ->select('supplier_id', DB::raw('COUNT(*) as total'))
            ->groupBy('supplier_id')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('supplier_id')
            ->toArray();

        return in_array($supplier->id, $topIds);
    }

    public function clearCache(string $supplierId): void
    {
        Cache::forget("supplier:badges:{$supplierId}");
    }
}
