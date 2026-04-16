<?php

use App\Jobs\ExpireCashback;
use App\Jobs\ExpirePixPayments;
use App\Jobs\ExpireQuotes;
use App\Jobs\ReleaseHeldPayments;
use App\Jobs\RetryFailedWithdrawals;
use App\Jobs\SendScheduleReminders;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ReleaseHeldPayments)->hourly();
Schedule::job(new ExpirePixPayments)->everyFiveMinutes();
Schedule::job(new ExpireQuotes)->everySixHours();
Schedule::job(new SendScheduleReminders)->hourly();
Schedule::job(new ExpireCashback)->daily();
Schedule::job(new RetryFailedWithdrawals)->everyThirtyMinutes();
