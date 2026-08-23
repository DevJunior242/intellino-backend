<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\League;
use App\Models\Federation;
use App\Models\ActivationKey;
use Illuminate\Console\Command;

class DeactivateExpiredTrials extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:deactivate-expired-trials';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Désactive les clubs/ligues/fédérations créés sans clé d'activation dont le délai d'essai est écoulé";

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $trialDays = (int) config('app.trial_activation_days');
        $seuil = now()->subDays($trialDays);

        $activatedIds = ActivationKey::where('is_used', true)
            ->whereNotNull('used_by_organisation_id')
            ->pluck('used_by_organisation_id');

        $total = 0;

        foreach ([Club::class, League::class, Federation::class] as $model) {
            $count = $model::where('status', 1)
                ->where('created_at', '<=', $seuil)
                ->whereNotIn('id', $activatedIds)
                ->update(['status' => 0]);

            $total += $count;
        }

        $this->info("{$total} organisation(s) désactivée(s) (essai de {$trialDays} jours écoulé sans clé d'activation).");

        return self::SUCCESS;
    }
}
