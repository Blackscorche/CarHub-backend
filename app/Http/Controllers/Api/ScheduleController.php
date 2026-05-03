<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Supplier;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    use ApiResponse;

    public function availability(Request $request, Supplier $supplier): JsonResponse
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today',
        ]);

        $date = $request->input('date');
        $dayOfWeek = strtolower(date('l', strtotime($date)));

        $openingHours = $supplier->opening_hours[$dayOfWeek] ?? null;
        if (! $openingHours || empty($openingHours['open']) || empty($openingHours['close'])) {
            return $this->success(['slots' => [], 'message' => 'Fornecedor fechado neste dia.']);
        }

        $open = strtotime($date . ' ' . $openingHours['open']);
        $close = strtotime($date . ' ' . $openingHours['close']);

        // Get existing bookings for this date
        $booked = Schedule::where('supplier_id', $supplier->id)
            ->whereDate('scheduled_date', $date)
            ->whereIn('status', ['confirmed', 'completed'])
            ->pluck('scheduled_time')
            ->map(fn ($t) => substr($t, 0, 5))
            ->toArray();

        $slots = [];
        for ($time = $open; $time < $close; $time += 1800) { // 30-min intervals
            $slot = date('H:i', $time);
            $slots[] = [
                'time' => $slot,
                'available' => ! in_array($slot, $booked),
            ];
        }

        return $this->success(['date' => $date, 'slots' => $slots]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'supplier_id' => 'required|uuid|exists:suppliers,id',
            'order_id' => 'nullable|uuid|exists:orders,id',
            'scheduled_date' => 'required|date|after_or_equal:today',
            'scheduled_time' => 'required|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
        ]);

        $user = $request->user();

        // Check slot availability
        $exists = Schedule::where('supplier_id', $request->input('supplier_id'))
            ->whereDate('scheduled_date', $request->input('scheduled_date'))
            ->where('scheduled_time', $request->input('scheduled_time'))
            ->whereIn('status', ['confirmed'])
            ->exists();

        if ($exists) {
            return $this->error('Horário indisponível.', 422);
        }

        $schedule = Schedule::create([
            'supplier_id' => $request->input('supplier_id'),
            'order_id' => $request->input('order_id'),
            'customer_id' => $user->id,
            'scheduled_date' => $request->input('scheduled_date'),
            'scheduled_time' => $request->input('scheduled_time'),
            'duration_minutes' => $request->input('duration_minutes', 60),
            'status' => 'pending',
            'reminder_sent' => false,
        ]);

        // Notify Supplier
        $supplierUser = $schedule->supplier->user;
        if ($supplierUser && $supplierUser->fcm_token) {
            app(\App\Services\NotificationService::class)->sendPush(
                $supplierUser->fcm_token,
                'Novo Agendamento solicitado',
                "{$user->name} solicitou um horário para o dia " . date('d/m', strtotime($schedule->scheduled_date)),
                ['type' => 'schedule', 'schedule_id' => $schedule->id]
            );
        }

        return $this->created($schedule, 'Agendamento solicitado. Aguardando confirmação.');
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Schedule::with(['supplier.user:id,name', 'customer:id,name', 'order:id,order_number']);

        if ($user->role === 'customer') {
            $query->where('customer_id', $user->id);
        } elseif ($user->role === 'supplier') {
            $supplier = $user->supplier;
            if (! $supplier) {
                return $this->success([]);
            }
            $query->where('supplier_id', $supplier->id);
        }

        if ($request->has('date')) {
            $query->whereDate('scheduled_date', $request->input('date'));
        }

        return $this->success(
            $query->orderBy('scheduled_date')
                ->orderBy('scheduled_time')
                ->paginate($request->input('limit', 30))
                ->through(fn ($s) => [
                    'id' => $s->id,
                    'order_id' => $s->order_id,
                    'customer_name' => $s->customer?->name,
                    'supplier_name' => $s->supplier?->business_name,
                    'service_name' => $s->order?->items?->first()?->name ?? 'Serviço',
                    'date' => $s->scheduled_date->format('Y-m-d'),
                    'start_time' => substr($s->scheduled_time, 0, 5),
                    'end_time' => date('H:i', strtotime($s->scheduled_time . " +{$s->duration_minutes} minutes")),
                    'status' => $s->status,
                ])
        );
    }

    public function accept(Request $request, Schedule $schedule): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'supplier' || $schedule->supplier_id !== $user->supplier->id) {
            return $this->forbidden('Acesso negado.');
        }

        if ($schedule->status !== 'pending') {
            return $this->error('Apenas agendamentos pendentes podem ser aceitos.', 422);
        }

        $schedule->update(['status' => 'confirmed']);

        // Notify Customer
        $customer = $schedule->customer;
        if ($customer && $customer->fcm_token) {
            app(\App\Services\NotificationService::class)->sendPush(
                $customer->fcm_token,
                'Agendamento Confirmado! ✅',
                "Seu agendamento com {$schedule->supplier->business_name} foi aceito.",
                ['type' => 'schedule', 'schedule_id' => $schedule->id]
            );
        }

        return $this->success($schedule->fresh(), 'Agendamento aceito com sucesso.');
    }

    public function reject(Request $request, Schedule $schedule): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'supplier' || $schedule->supplier_id !== $user->supplier->id) {
            return $this->forbidden('Acesso negado.');
        }

        if ($schedule->status !== 'pending') {
            return $this->error('Apenas agendamentos pendentes podem ser rejeitados.', 422);
        }

        $schedule->update(['status' => 'rejected']);

        // Notify Customer
        $customer = $schedule->customer;
        if ($customer && $customer->fcm_token) {
            app(\App\Services\NotificationService::class)->sendPush(
                $customer->fcm_token,
                'Agendamento Recusado ❌',
                "Infelizmente o fornecedor {$schedule->supplier->business_name} não poderá te atender no horário solicitado.",
                ['type' => 'schedule', 'schedule_id' => $schedule->id]
            );
        }

        return $this->success($schedule->fresh(), 'Agendamento rejeitado.');
    }

    public function cancel(Request $request, Schedule $schedule): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'customer' && $schedule->customer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }
        if ($user->role === 'supplier') {
            $supplier = $user->supplier;
            if (! $supplier || $schedule->supplier_id !== $supplier->id) {
                return $this->forbidden('Acesso negado.');
            }
        }

        if ($schedule->status !== 'confirmed') {
            return $this->error('Agendamento não pode ser cancelado.', 422);
        }

        $schedule->update(['status' => 'cancelled']);

        return $this->success($schedule->fresh(), 'Agendamento cancelado.');
    }
}
