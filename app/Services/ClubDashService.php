<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Club;
use App\Models\Role;
use App\Models\Course;
use App\Models\Examen;
use App\Models\Student;
use App\Models\ParentModel;
use App\Models\Subscription;
use App\Models\StudentPayment;
use Illuminate\Support\Facades\DB;

class ClubDashService
{
    public function   getStatsByUserRole($user, $activeId, $activeType, $role)
    {
        return match ($role) {
            'super_admin' => $this->superAdminStats(),
            'admin'  => $this->clubAdminStats($activeId),
            'instructeur' => $this->instructorStats($activeId, $activeType),
            'secretaire'  => $this->secretaireStats($activeId, $activeType),
            'parent'      => $this->parentStats($user),
            'karateka'    => $this->etudiantStats($activeId, $activeType),
            default       => ['stats' => [], 'message' => 'Aucun rôle associé'],
        };
    }
    private function superAdminStats()
    {
        $totalInstructors = Role::where('name', 'instructeur')->value('id')
            ? DB::table('club_users')
            ->where('role_id', Role::where('name', 'instructeur')->value('id'))
            ->count()
            : 0;
        $revenuTotal = Subscription::join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->where('subscriptions.status', 'paid')
            ->sum('plans.amount');

        $revenuMensuel = Subscription::join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->where('subscriptions.status', 'paid')
            ->whereYear('subscriptions.created_at', Carbon::now()->year)
            ->whereMonth('subscriptions.created_at', Carbon::now()->month)
            ->sum('plans.amount');
        $revenuAnnuel = Subscription::join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->where('subscriptions.status', 'paid')
            ->whereYear('subscriptions.created_at', Carbon::now()->year)
            ->sum('plans.amount');

        return [
            'total_clubs' => Club::count(),
            'total_students' => Student::count(),
            'total_instructors' => $totalInstructors,
            'total_parents' => ParentModel::count(),
            'active_subscriptions' => Subscription::where('status', 'paid')->count(),
            'total_subscriptions' => Subscription::count(),
            'total_revenue_mensuel' => $revenuMensuel,
            'total_revenue_annuel' => $revenuAnnuel,
            'total_revenue' => $revenuTotal,
        ];
    }


    private function clubAdminStats($activeId)
    {
        $instructorRoleId = Role::where('name', 'instructeur')->value('id');
        $secretaireRoleId = Role::where('name', 'secretaire')->value('id');
        $totalInstructors = $instructorRoleId
            ? DB::table('club_users')
            ->where('club_id', $activeId)
            ->where('role_id', $instructorRoleId)
            ->count()
            : 0;
        $totalSecretaries = $secretaireRoleId
            ? DB::table('club_users')
            ->where('club_id', $activeId)
            ->where('role_id', $secretaireRoleId)
            ->count()
            : 0;

        $totalStudents = Student::whereHas('clubs', function ($q) use ($activeId) {
            $q->where('club_id', $activeId);
        })->count();

        $month = now()->month;
        // Total encaissé ce mois-ci
        $total_collected = StudentPayment::where('club_id', $activeId)
            ->whereMonth('created_at', $month)
            ->sum('amount_paid');

        // Somme totale des dettes (tous mois confondus)
        $total_debts = StudentPayment::where('club_id', $activeId)
            ->sum('balance');

        return [
            'total_students' => $totalStudents,
            'total_instructors' => $totalInstructors,
            'total_secretaries' => $totalSecretaries,
            'total_collected' => $total_collected,
            'total_debts' => $total_debts,
        ];
    }

    /**
     * Stats club (élèves/cours/examens) communes aux rôles instructeur/secrétaire/karateka.
     * Student n'a pas de colonne club_id (relation many-to-many via club_students),
     * Course/Examen sont scopés par organisateur_id/organisateur_type (pas club_id),
     * et Examen n'a pas de colonne "date" (seulement start_date/end_date).
     */
    private function studentCourseExamStats($activeId, $activeType)
    {
        $examenQuery = Examen::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType);
        $today = now()->toDateString();

        return [
            'total_students' => Student::whereHas('clubs', function ($q) use ($activeId) {
                $q->where('club_id', $activeId);
            })->count(),
            'total_courses' => Course::where('organisateur_id', $activeId)
                ->where('organisateur_type', $activeType)
                ->count(),
            'total_examens' => (clone $examenQuery)->count(),
            'total_examens_encours' => (clone $examenQuery)
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->count(),
            'total_examens_avenir' => (clone $examenQuery)
                ->whereDate('start_date', '>', $today)
                ->count(),
            'total_examens_termines' => (clone $examenQuery)
                ->whereDate('end_date', '<', $today)
                ->count(),
        ];
    }

    private function instructorStats($activeId, $activeType)
    {
        $stats = $this->studentCourseExamStats($activeId, $activeType);

        return [
            'role' => 'instructeur pour club ' . $activeId,
            'club_id' => $activeId,
            'total_students' => $stats['total_students'],
            'total_courses' => $stats['total_courses'],
            'total_examens' => $stats['total_examens'],
            'total_examens_encours' => $stats['total_examens_encours'],
            'total_examens_avenir' => $stats['total_examens_avenir'],
            'total_examens_termines' => $stats['total_examens_termines'],
        ];
    }


    private function parentStats($user)
    {
        $user = auth()->user();


        $parent = ParentModel::where('user_id', $user->id)
            ->with(['students' => function ($q) {
                $q->select('id', 'fullname', 'birthdate', 'sex', 'photo', 'status')
                    ->with('clubs:id,name,logo');
            }])
            ->first();

        if (!$parent) {
            return [
                'actif_students' => 0,
                'inactive_students' => 0,
                'total_students' => 0,
                'students' => [],
            ];
        }

        $students = $parent->students;

        return [
            'actif_students' => $students->where('status', 'actif')->count(),
            'inactive_students' => $students->where('status', 'inactif')->count(),
            'total_students' => $students->count(),
            'students' => $students,

        ];
    }






    private function etudiantStats($activeId, $activeType)
    {
        $stats = $this->studentCourseExamStats($activeId, $activeType);

        return [
            'total_students' => $stats['total_students'],
            'total_courses' => $stats['total_courses'],
            'toltals_examens' => $stats['total_examens'],
            'total_examens_encours' => $stats['total_examens_encours'],
            'total_examens_avenir' => $stats['total_examens_avenir'],
            'total_examens_termines' => $stats['total_examens_termines'],
        ];
    }


    private function secretaireStats($activeId, $activeType)
    {
        // Mêmes clés que etudiantStats() — préservées telles quelles (y compris
        // la faute de frappe historique "toltals_examens") pour ne pas casser le front.
        return $this->etudiantStats($activeId, $activeType);
    }
}
