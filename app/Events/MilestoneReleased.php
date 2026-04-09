<?php

namespace App\Events;

use App\Models\PaymentMilestone;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MilestoneReleased
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PaymentMilestone $milestone) {}
}
