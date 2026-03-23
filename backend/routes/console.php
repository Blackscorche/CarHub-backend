<?php

use App\Jobs\ExpirePixPayments;
use App\Jobs\ExpireQuotes;
use App\Jobs\ReleaseHeldPayments;
use Illuminate\Support\Facades\Schedule;

// Release held payments past their hold_until timestamp
Schedule::job(new ReleaseHeldPayments)->hourly();

// Expire unpaid Pix payments after 15 minutes
Schedule::job(new ExpirePixPayments)->everyFiveMinutes();

// Expire unanswered or unconfirmed quotes
Schedule::job(new ExpireQuotes)->everySixHours();
