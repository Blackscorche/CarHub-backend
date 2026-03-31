<?php

namespace App\Providers;

use App\Events\OrderAccepted;
use App\Events\OrderCancelled;
use App\Events\OrderCompleted;
use App\Events\OrderConfirmed;
use App\Events\OrderCreated;
use App\Events\OrderRejected;
use App\Events\OrderPaid;
use App\Events\OrderStarted;
use App\Listeners\HandleOrderAccepted;
use App\Listeners\HandleOrderCancelled;
use App\Listeners\HandleOrderCompleted;
use App\Listeners\HandleOrderConfirmed;
use App\Listeners\HandleOrderCreated;
use App\Listeners\HandleOrderPaid;
use App\Listeners\HandleOrderRejected;
use App\Listeners\HandleOrderStarted;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Rate limiting: login 5/min per IP
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Rate limiting: general API 60/min per user
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiting: payment 10/min per user
        RateLimiter::for('payment', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Register event listeners
        Event::listen(OrderCreated::class, HandleOrderCreated::class);
        Event::listen(OrderAccepted::class, HandleOrderAccepted::class);
        Event::listen(OrderRejected::class, HandleOrderRejected::class);
        Event::listen(OrderPaid::class, HandleOrderPaid::class);
        Event::listen(OrderStarted::class, HandleOrderStarted::class);
        Event::listen(OrderCompleted::class, HandleOrderCompleted::class);
        Event::listen(OrderConfirmed::class, HandleOrderConfirmed::class);
        Event::listen(OrderCancelled::class, HandleOrderCancelled::class);
    }
}
