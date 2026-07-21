<?php

namespace App\Console\Commands;

use App\Services\OrderExpirationService;
use Illuminate\Console\Command;

class ExpirePendingOrdersCommand extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'Expire pending orders past valid_until and release locked seats';

    public function handle(OrderExpirationService $service): int
    {
        $count = $service->expire();

        $this->info("Expired {$count} pending order(s).");

        return self::SUCCESS;
    }
}
