<?php

namespace App\Observers;

use App\Models\Course;
use App\Models\Activity;
use Illuminate\Support\Facades\Log;

class CourseObserver
{
    /**
     * Handle the Course "created" event.
     */

    public function created(Course $course): void
    {
        $user = auth()->user();
        Activity::create([
            'user_id'        => $user->id,
            'organisateur_id' => $course->club_id,
            'organisateur_type' => 'Club',
            'type'           => 'course',
            'action'         => 'created',
            'description'    => "A créé le cours " . $course->name,
        ]);
    }

    /**
     * Handle the Course "updated" event.
     */
    public function updated(Course $course): void
    {
        //
    }

    /**
     * Handle the Course "deleted" event.
     */
    public function deleted(Course $course): void
    {
        Log::info('Observer Course déclenché !');
        Activity::create([
            'user_id'        => auth()->id(),
            'organisateur_id' => $course->club_id,
            'organisateur_type' => 'Club',
            'type'           => 'course',
            'action'         => 'deleted',
            'description'    => "A supprimé le cours " . $course->name,
        ]);
    }

    /**
     * Handle the Course "restored" event.
     */
    public function restored(Course $course): void
    {
        //
    }

    /**
     * Handle the Course "force deleted" event.
     */
    public function forceDeleted(Course $course): void
    {
        //
    }
}
