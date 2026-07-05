<?php

namespace App\Jobs;

use App\Models\ArbitreCompetition;
use App\Notifications\ArbitrePinNotification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Events\Dispatchable;

class SendArbitreAccessCodes
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    /**
     * Create a new job instance.
     */
    protected $evenementId;
    public function __construct($evenementId)
    {
        $this->evenementId = $evenementId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $arbitres = ArbitreCompetition::where('evenement_id', $this->evenementId)
            ->whereNotNull('code_acces')
            ->with('user')
            ->get();

        foreach ($arbitres as $arbitre) {
            if ($arbitre->user && $arbitre->user?->email) {
                $arbitre->user->notify(new ArbitrePinNotification($arbitre->code_acces));
            }
        }
    }
}
