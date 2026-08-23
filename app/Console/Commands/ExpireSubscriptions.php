<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-subscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Marque expirés les abonnements Intellino payés dont la période est terminée (sinon un paiement une fois protégerait l'organisation indéfiniment)";

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Subscription::where('status', Subscription::STATUS_PAID)
            ->whereDate('end_date', '<', today())
            ->update(['status' => Subscription::STATUS_EXPIRED]);

        return self::SUCCESS;
    }
}
