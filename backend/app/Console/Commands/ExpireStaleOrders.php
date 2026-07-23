<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class ExpireStaleOrders extends Command
{
    protected $signature = 'mororide:expire-orders {--limit=200 : Maximum orders to expire per run}';

    protected $description = 'Expire open orders (searching/offered) whose expires_at has passed.';

    public function handle(OrderService $orders): int
    {
        $count = $orders->expireStaleOrders((int) $this->option('limit'));

        $this->info("Expired {$count} stale order(s).");

        return self::SUCCESS;
    }
}
