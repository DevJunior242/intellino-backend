<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\SessionModel;

class SessionObserver
{
    /**
     * Handle the SessionModel "created" event.
     */
    public function created(SessionModel $sessionModel): void
    {
        $course = $sessionModel->course;

        if (!$course) {
            return;
        }
        Activity::create([
            'organisateur_id'   => $course->organisateur_id,
            'organisateur_type' => $course->organisateur_type,
            'type'           => 'session',
            'action'         => 'created',
            'description'    => "A créé la session " . $sessionModel->title,
            'user_id' => auth()->id() ?? $sessionModel->created_by,
        ]);
    }

    /**
     * Handle the SessionModel "updated" event.
     */
    public function updated(SessionModel $sessionModel): void
    {
        //
    }

    /**
     * Handle the SessionModel "deleted" event.
     */
    public function deleted(SessionModel $sessionModel): void
    {
        Activity::create([
            'user_id'        => auth()->id(),
            'organisateur_id' => $sessionModel->course->organisateur_id,
            'organisateur_type' => 'Club',
            'type'           => 'session',
            'action'         => 'deleted',
            'description'    => "A supprimé la session " . $sessionModel->title,
        ]);
    }

    /**
     * Handle the SessionModel "restored" event.
     */
    public function restored(SessionModel $sessionModel): void
    {
        //
    }

    /**
     * Handle the SessionModel "force deleted" event.
     */
    public function forceDeleted(SessionModel $sessionModel): void
    {
        //
    }
}
