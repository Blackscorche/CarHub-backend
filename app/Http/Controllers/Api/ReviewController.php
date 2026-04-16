<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Models\Supplier;
use App\Services\BadgeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    use ApiResponse;

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|uuid|exists:orders,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'media' => 'nullable|array|max:5',
            'media.*' => 'file|mimes:jpg,jpeg,png,mp4|max:10240',
        ]);

        $user = $request->user();
        $order = Order::findOrFail($request->input('order_id'));

        if ($order->customer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }

        if ($order->status !== 'confirmed') {
            return $this->error('Só é possível avaliar pedidos confirmados.', 422);
        }

        if ($order->review) {
            return $this->error('Este pedido já foi avaliado.', 422);
        }

        $mediaUrls = [];
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $path = $file->store('reviews/' . $order->id, 'public');
                $mediaUrls[] = Storage::url($path);
            }
        }

        $review = DB::transaction(function () use ($request, $user, $order, $mediaUrls) {
            $review = Review::create([
                'order_id' => $order->id,
                'customer_id' => $user->id,
                'supplier_id' => $order->supplier_id,
                'rating' => $request->input('rating'),
                'comment' => $request->input('comment'),
                'media_urls' => $mediaUrls ?: null,
            ]);

            // Recalculate supplier rating
            $supplier = Supplier::find($order->supplier_id);
            $stats = Review::where('supplier_id', $supplier->id)
                ->selectRaw('AVG(rating) as avg, COUNT(*) as total')
                ->first();

            $supplier->update([
                'avg_rating' => round($stats->avg, 1),
                'total_ratings' => $stats->total,
            ]);

            return $review;
        });

        // Invalidate badge cache so the new rating/volume is reflected on next fetch.
        app(BadgeService::class)->clearCache($order->supplier_id);

        return $this->created($review, 'Avaliação enviada com sucesso.');
    }

    public function supplierReviews(Request $request, Supplier $supplier): JsonResponse
    {
        $reviews = Review::where('supplier_id', $supplier->id)
            ->with('customer:id,name,avatar_url')
            ->orderByDesc('created_at')
            ->paginate($request->input('limit', 20));

        return $this->success($reviews);
    }
}
