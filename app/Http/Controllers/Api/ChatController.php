<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Order;
use App\Notifications\NewChatMessageNotification;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    use ApiResponse;

    public function rooms(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Order::query()->with(['customer:id,name,avatar_url', 'supplier.user:id,name,avatar_url']);

        if ($user->role === 'customer') {
            $query->where('customer_id', $user->id);
        } elseif ($user->role === 'supplier') {
            $supplier = $user->supplier;
            if (! $supplier) {
                return $this->success([]);
            }
            $query->where('supplier_id', $supplier->id);
        }

        // Only include orders that have at least one chat message
        $query->whereHas('chatMessages');

        $orders = $query->orderByDesc(
            ChatMessage::select('created_at')
                ->whereColumn('order_id', 'orders.id')
                ->orderByDesc('created_at')
                ->limit(1)
        )->get();

        $rooms = $orders->map(function (Order $order) use ($user) {
            $lastMessage = $order->chatMessages()->orderByDesc('created_at')->first();
            $unreadCount = $order->chatMessages()
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')
                ->count();

            $isSupplier = $user->role === 'supplier';

            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'supplier_name' => $isSupplier
                    ? ($order->customer->name ?? 'Cliente')
                    : ($order->supplier->user->name ?? $order->supplier->business_name ?? 'Fornecedor'),
                'supplier_logo_url' => $isSupplier
                    ? ($order->customer->avatar_url ?? null)
                    : ($order->supplier->logo_url ?? null),
                'last_message' => $lastMessage?->message,
                'last_message_at' => $lastMessage?->created_at?->toIso8601String(),
                'unread_count' => $unreadCount,
            ];
        });

        return $this->success($rooms->values());
    }

    public function index(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrderAccess($request, $order);

        $messages = ChatMessage::where('order_id', $order->id)
            ->with('sender:id,name,avatar_url')
            ->orderByDesc('created_at')
            ->paginate($request->input('limit', 50));

        $user = $request->user();

        // Mark unread messages as read
        ChatMessage::where('order_id', $order->id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Mark related chat notifications as read
        $user->notifications()
            ->whereNull('read_at')
            ->where('type', \App\Notifications\NewChatMessageNotification::class)
            ->whereJsonContains('data->data->order_id', (string) $order->id)
            ->update(['read_at' => now()]);

        return $this->success($messages);
    }

    public function store(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $this->authorizeOrderAccess($request, $order);

        $message = ChatMessage::create([
            'order_id' => $order->id,
            'sender_id' => $request->user()->id,
            'message' => $request->input('message'),
            'type' => 'text',
        ]);

        $message->load('sender:id,name,avatar_url');

        $this->notifyRecipient($request->user(), $order, $message);

        return $this->created($message);
    }

    public function sendImage(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|mimes:jpg,jpeg,png|max:5120',
        ]);

        $this->authorizeOrderAccess($request, $order);

        $path = $request->file('image')->store('chat/' . $order->id, 'public');

        $message = ChatMessage::create([
            'order_id' => $order->id,
            'sender_id' => $request->user()->id,
            'message' => '',
            'media_url' => Storage::url($path),
            'type' => 'image',
        ]);

        $message->load('sender:id,name,avatar_url');

        $this->notifyRecipient($request->user(), $order, $message);

        return $this->created($message);
    }

    protected function notifyRecipient($sender, Order $order, ChatMessage $message): void
    {
        $order->loadMissing(['customer', 'supplier.user']);

        // Determine recipient
        if ($sender->id === $order->customer_id) {
            $recipient = $order->supplier?->user;
        } else {
            $recipient = $order->customer;
        }

        if ($recipient) {
            $recipient->notify(new NewChatMessageNotification($message));
        }
    }

    protected function authorizeOrderAccess(Request $request, Order $order): void
    {
        $user = $request->user();

        if ($user->role === 'customer' && $order->customer_id !== $user->id) {
            abort(403, 'Acesso negado.');
        }

        if ($user->role === 'supplier') {
            $supplier = $user->supplier;
            if (! $supplier || $order->supplier_id !== $supplier->id) {
                abort(403, 'Acesso negado.');
            }
        }
    }
}
