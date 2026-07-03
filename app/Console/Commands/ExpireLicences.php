<?php

namespace App\Console\Commands;

use App\Models\Licence;
use Illuminate\Console\Command;

class ExpireLicences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-licences';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Licence::where('status', Licence::STATUS_ACTIVE)
            ->whereDate('date_expiration', '<', today())
            ->update([
                'status' => Licence::STATUS_EXPIRED
            ]);

        return self::SUCCESS;
    }
}
