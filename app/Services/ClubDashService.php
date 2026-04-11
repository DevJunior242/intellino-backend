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
    public function getStatsByUserRole($user, $clubId, $role)
    {
        return match ($role) {
            'super_admin' => $this->superAdminStats(),
            'admin_club'  => $this->clubAdminStats($clubId),
            'instructeur' => $this->instructorStats($clubId),
            'secretaire'  => $this->secretaireStats($clubId),
            'parent'      => $this->parentStats($user),
            'karateka'    => $this->etudiantStats($clubId),
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


    private function clubAdminStats($clubId)
    {
        $instructorRoleId = Role::where('name', 'instructeur')->value('id');
        $secretaireRoleId = Role::where('name', 'secretaire')->value('id');
        $totalInstructors = $instructorRoleId
            ? DB::table('club_users')
            ->where('club_id', $clubId)
            ->where('role_id', $instructorRoleId)
            ->count()
            : 0;
        $totalSecretaries = $secretaireRoleId
            ? DB::table('club_users')
            ->where('club_id', $clubId)
            ->where('role_id', $secretaireRoleId)
            ->count()
            : 0;

        $month = now()->month;
        // Total encaissé ce mois-ci
        $total_collected = StudentPayment::where('club_id', $clubId)
            ->whereMonth('created_at', $month)
            ->sum('amount_paid');

        // Somme totale des dettes (tous mois confondus)
        $total_debts = StudentPayment::where('club_id', $clubId)
            ->sum('balance');

        // Répartition par mode de paiement
        $payment_methods = StudentPayment::where('club_id', $clubId)
            ->whereMonth('created_at', $month)
            ->select('payment_method', DB::raw('sum(amount_paid) as total'))
            ->groupBy('payment_method')
            ->get();

        return [
            'admin stats pour club' => $clubId,
            'total_students' => Student::where('club_id', $clubId)->count(),
            'total_instructors' =>  $totalInstructors,
            'total_parents' => ParentModel::whereHas('students', function ($q) use ($clubId) {
                $q->where('club_id', $clubId);
            })
                ->distinct()
                ->count(),
            'total_secretaries' => $totalSecretaries,
            'total_collected' => $total_collected,
            'total_debts' => $total_debts,
        ];
    }

    private function instructorStats($clubId)
    {
        $baseExamenQuery = Examen::where('club_id', $clubId);
        $today = now()->toDateString();


        $examenEncours = (clone $baseExamenQuery)->whereDate('date', $today)->count();
        $examenAVenir = (clone $baseExamenQuery)->where('date', '>', $today)->count();
        $examenTermines = (clone $baseExamenQuery)->where('date', '<', $today)->count();

        return [
            'role' => 'instructeur pour club ' . $clubId,
            'club_id' => $clubId,
            'total_students' => Student::where('club_id', $clubId)->count(),
            'total_courses' => Course::where('club_id', $clubId)->count(),
            'total_examens' => $baseExamenQuery->count(),
            'total_examens_encours' => $examenEncours,
            'total_examens_avenir' => $examenAVenir,
            'total_examens_termines' => $examenTermines,
        ];
    }


    private function parentStats($user)
    {
        $user = auth()->user();


        $parent = ParentModel::where('user_id', $user->id)
            ->with(['students' => function ($q) {
                $q->select('id', 'fullname', 'birthdate', 'sex', 'photo', 'status', 'club_id')
                    ->with('club:id,name,logo');
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






    private function etudiantStats($clubId)
    {
        $initExamen = Examen::where('club_id', $clubId);
        $today = now()->toDateString();
        $examenEncours = (clone $initExamen)
            ->where('date', '<=', $today)
            ->count();
        $examenAVenir = (clone $initExamen)
            ->where('date', '>', $today)
            ->count();
        $examenTermines = (clone $initExamen)
            ->where('date', '<', $today)
            ->count();
        return [
            'total_students' =>
            Student::where('club_id', $clubId)->count(),
            'total_courses' =>
            Course::where('club_id', $clubId)->count(),
            'toltals_examens' => $initExamen->count(),
            'total_examens_encours' => $examenEncours,
            'total_examens_avenir' => $examenAVenir,
            'total_examens_termines' => $examenTermines,
        ];
    }


    private function secretaireStats($clubId)
    {

        $initExamen = Examen::where('club_id', $clubId);
        $today = now()->toDateString();


        $examenEncours = (clone $initExamen)
            ->where('date', '<=', $today)
            ->count();

        $examenAVenir = (clone $initExamen)
            ->where('date', '>', $today)
            ->count();

        $examenTermines = (clone $initExamen)
            ->where('date', '<', $today)
            ->count();

        return [
            'total_students' =>
            Student::where('club_id', $clubId)->count(),

            'total_courses' =>
            Course::where('club_id', $clubId)->count(),
            'toltals_examens' => $initExamen->count(),
            'total_examens_encours' => $examenEncours,
            'total_examens_avenir' => $examenAVenir,
            'total_examens_termines' => $examenTermines,

        ];
    }
}
