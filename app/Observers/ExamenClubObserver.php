<?php

namespace App\Observers;

use App\Models\Examen;
use App\Models\Activity;
use Illuminate\Support\Facades\Log;

class ExamenClubObserver
{
    /**
     * Handle the Examen "created" event.
     */
    public function created(Examen $examen): void
    {
        Activity::create([
            'user_id'        => auth()->id(),
            'organisateur_id' => $examen->club_id,
            'organisateur_type' => 'Club',
            'type'           => 'examen',
            'action'         => 'created',
            'description'    => "A créé l'examen pour l'obtention de " . $examen->currentGrade?->name,
        ]);
    }

    /**
     * Handle the Examen "updated" event.
     */
    public function updated(Examen $examen): void
    {
        Activity::create([
            'user_id'        => auth()->id(),
            'organisateur_id' => $examen->club_id,
            'organisateur_type' => get_class($examen->club),
            'type'           => 'examen',
            'action'         => 'updated',
            'description'    => "A mis à jour l'examen " . $examen->name,
        ]);
    }

    /**
     * Handle the Examen "deleted" event.
     */
    public function deleted(Examen $examen): void
    {
        Log::info('Observer Examen déclenché !');
        Activity::create([
            'user_id'        => auth()->id(),
            'organisateur_id' => $examen->club_id,
            'organisateur_type' => 'Club',
            'type'           => 'examen',
            'action'         => 'deleted',
            'description'    => "A supprimé l'examen " . $examen->name,
        ]);
    }

    /**
     * Handle the Examen "restored" event.
     */
    public function restored(Examen $examen): void
    {
        //
    }

    /**
     * Handle the Examen "force deleted" event.
     */
    public function forceDeleted(Examen $examen): void
    {
        //
    }
}
