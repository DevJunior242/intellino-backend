<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\League;
use App\Models\Federation;
use App\Models\ActivationKey;
use App\Models\Subscription;
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
    protected $description = "Désactive les clubs/ligues/fédérations dont le délai d'essai est écoulé sans clé d'activation ni abonnement payé en cours";

    /**
     * Association type morph (string utilisé par Subscription.organisateur_type)
     * <-> classe Eloquent, dans le même ordre que la boucle de désactivation.
     */
    private const TYPES = [
        Club::class => 'Club',
        League::class => 'Ligue',
        Federation::class => 'Federation',
    ];

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

        foreach (self::TYPES as $model => $organisateurType) {
            $subscribedIds = Subscription::where('organisateur_type', $organisateurType)
                ->where('status', Subscription::STATUS_PAID)
                ->where('end_date', '>=', now()->toDateString())
                ->pluck('organisateur_id');

            $excludedIds = $activatedIds->merge($subscribedIds)->unique();

            $count = $model::where('status', 1)
                ->where('created_at', '<=', $seuil)
                ->whereNotIn('id', $excludedIds)
                ->update(['status' => 0]);

            $total += $count;
        }

        $this->info("{$total} organisation(s) désactivée(s) (essai de {$trialDays} jours écoulé, sans clé d'activation ni abonnement payé en cours).");

        return self::SUCCESS;
    }
}
