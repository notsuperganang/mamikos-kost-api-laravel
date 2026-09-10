<?php

namespace App\Console\Commands;

use App\Services\CreditService;
use Illuminate\Console\Command;

class RechargeCreditsCommand extends Command
{
    protected $signature = 'credits:recharge
                            {--dry-run : Report how many users would be recharged without changing anything}';

    protected $description = 'Reset regular and premium users to their monthly credit allowance';

    public function handle(CreditService $credits): int
    {
        if ($this->option('dry-run')) {
            foreach ($credits->eligibleForRecharge() as $role => $count) {
                $this->line("{$role}: {$count} user(s) would be recharged");
            }

            return self::SUCCESS;
        }

        $updated = $credits->rechargeAll();

        $this->info("Monthly credit recharge finished: {$updated} user(s) updated.");

        return self::SUCCESS;
    }
}
