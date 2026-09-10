<?php

use App\Console\Commands\RechargeCreditsCommand;
use Illuminate\Support\Facades\Schedule;

/*
| Reset regular / premium balances to their allowance on the first day of every month.
| onOneServer() needs a shared cache store (database / redis) when the scheduler runs on
| several machines; the job itself is idempotent within a month as an extra safety net.
*/
Schedule::command(RechargeCreditsCommand::class)
    ->monthlyOn(1, '00:00')
    ->timezone(config('credits.timezone'))
    ->onOneServer()
    ->withoutOverlapping();
