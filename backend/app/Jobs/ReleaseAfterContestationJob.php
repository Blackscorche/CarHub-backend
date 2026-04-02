<?php

namespace App\Jobs;

use App\Models\PaymentMilestone;
use App\Services\MilestoneService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReleaseAfterContestationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public PaymentMilestone $milestone
    ) {}

    public function handle(MilestoneService $milestoneService): void
    {
        $this->milestone = $this->milestone->fresh();

        if (!$this->milestone) {
            return;
        }

        // If customer contested within 48h, do nothing — admin handles it
        if ($this->milestone->isContested()) {
            Log::info('Milestone contested, skipping auto-release', [
                'milestone_id' => $this->milestone->id,
            ]);
            return;
        }

        // If already released, skip
        if ($this->milestone->released_at !== null) {
            return;
        }

        // No contestation → auto-release to supplier
        Log::info('48h passed, auto-releasing milestone', [
            'milestone_id' => $this->milestone->id,
            'amount' => $this->milestone->amount,
        ]);

        $milestoneService->releaseMilestone($this->milestone);
    }
}
