<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\StudentPayment;

class StudentPayObserver
{
    /**
     * Handle the StudentPayment "created" event.
     */
    public function created(StudentPayment $studentPayment): void
    {
        Activity::create([
            'user_id'        => auth()->id(),
            'organisateur_id' => $studentPayment->club_id,
            'organisateur_type' => get_class($studentPayment->club),
            'type'           => 'student_payment',
            'action'         => 'created',
            'description'    => "A créé la facture de paiement pour l'étudiant " . $studentPayment->student->fullname,
        ]);
    }

    /**
     * Handle the StudentPayment "updated" event.
     */
    public function updated(StudentPayment $studentPayment): void
    {
        //
    }

    /**
     * Handle the StudentPayment "deleted" event.
     */
    public function deleted(StudentPayment $studentPayment): void
    {
        Activity::create([
            'user_id'        => auth()->id(),
            'organisateur_id' => $studentPayment->club_id,
            'organisateur_type' => 'Club',
            'type'           => 'student_payment',
            'action'         => 'deleted',
            'description'    => "A supprimé la facture de paiement pour l'étudiant " . $studentPayment->student->fullname,
        ]);
    }

    /**
     * Handle the StudentPayment "restored" event.
     */
    public function restored(StudentPayment $studentPayment): void
    {
        //
    }

    /**
     * Handle the StudentPayment "force deleted" event.
     */
    public function forceDeleted(StudentPayment $studentPayment): void
    {
        //
    }
}
