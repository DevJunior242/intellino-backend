<?php

namespace App\Observers;

use App\Models\Student;
use App\Models\Activity;

class StudentObserver
{
    /**
     * Handle the Student "created" event.
     */
    public function created(Student $student): void
    {
        Activity::create([
            'user_id'        => auth()->id(),
            'organisateur_id' => $student->club_id,
            'organisateur_type' => get_class($student->club),
            'type'           => 'student',
            'action'         => 'created',
            'description'    => "A créé le étudiant " . $student->fullname,
        ]);
    }

    /**
     * Handle the Student "updated" event.
     */
    public function updated(Student $student): void
    {
        Activity::create([
            'user_id'        => auth()->id(),
            'organisateur_id' => $student->club_id,
            'organisateur_type' => 'Club',
            'type'           => 'student',
            'action'         => 'updated',
            'description'    => "A mis à jour le étudiant " . $student->fullname,
        ]);
    }

    /**
     * Handle the Student "deleted" event.
     */
    public function deleted(Student $student): void
    {
        Activity::create([
            'user_id'        => auth()->id(),
            'organisateur_id' => $student->club_id,
            'organisateur_type' => 'Club',
            'type'           => 'student',
            'action'         => 'deleted',
            'description'    => "A supprimé le étudiant " . $student->fullname,
        ]);
    }

    /**
     * Handle the Student "restored" event.
     */
    public function restored(Student $student): void
    {
        //
    }

    /**
     * Handle the Student "force deleted" event.
     */
    public function forceDeleted(Student $student): void
    {
        //
    }
}
