<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\Equipment;

class EquipmentObserver
{
    /**
     * Handle the Equipment "created" event.
     */
    public function created(Equipment $equipment): void
    {
        Activity::create([
            'user_id'        => auth()->id(),
            'organisateur_id' => $equipment->club_id,
            'organisateur_type' => 'Club',
            'type'           => 'equipment',
            'action'         => 'created',
            'description'    => "A créé l'équipement " . $equipment->name,
        ]);
    }

    /**
     * Handle the Equipment "updated" event.
     */
    public function updated(Equipment $equipment): void {}

    /**
     * Handle the Equipment "deleted" event.
     */
    public function deleted(Equipment $equipment): void
    {
        Activity::create([
            'user_id'        => auth()->id(),
            'organisateur_id' => $equipment->club_id,
            'organisateur_type' => 'Club',
            'type'           => 'equipment',
            'action'         => 'deleted',
            'description'    => "A supprimé l'équipement " . $equipment->name,
        ]);
    }

    /**
     * Handle the Equipment "restored" event.
     */
    public function restored(Equipment $equipment): void
    {
        //
    }

    /**
     * Handle the Equipment "force deleted" event.
     */
    public function forceDeleted(Equipment $equipment): void
    {
        //
    }
}
