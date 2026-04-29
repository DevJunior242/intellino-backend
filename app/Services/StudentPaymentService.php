<?php

namespace App\Services;

use App\Models\StudentPayment;

class StudentPaymentService
{

    public function getStudentpaymentStats($activeId)
    {
        $totalPaid = StudentPayment::where('club_id', $activeId)->sum('amount_paid');
        $studentsCount = StudentPayment::where('club_id', $activeId)->distinct('student_id')->count();
        $monthPayments = StudentPayment::where('club_id', $activeId)->whereMonth('created_at', now()->month)->count();
        $totalTransactions = StudentPayment::where('club_id', $activeId)->count();
        $monthlyChart = StudentPayment::where('club_id', $activeId)->selectRaw("
                DATE_FORMAT(created_at,'%b') as month,
                SUM(amount_paid) as total
            ")
            ->groupBy('month')

            ->get();

        $recent = StudentPayment::with('student:id,fullname')
            ->where('club_id', $activeId)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($p) => [
                "id" => $p->id,
                "student" => $p->student->fullname,
                "amount_paid" => $p->amount_paid,
                "payment_method" => $p->payment_method,
                "created_at" => $p->created_at->format('d M Y'),
            ]);
        return [
            "total_paid" => $totalPaid,
            "students_count" => $studentsCount,
            "month_payments" => $monthPayments,
            "total_transactions" => $totalTransactions,

            "monthly_chart" =>  $monthlyChart,

            "recent" => $recent,
        ];
    }
}
