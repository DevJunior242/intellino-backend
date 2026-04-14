<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Student;
use App\Models\ParentModel;
use App\Models\PricingPlan;
use Illuminate\Http\Request;
use App\Models\StudentPayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\StudentGradeService;
use App\Services\StudentPaymentService;
use App\Http\Requests\StorePaymentRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class StudentPaymentController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request)
    {
        $clubId = $request->validated_club_id;
        $role = $request->validated_role_name;
        $user = auth()->user();

        if ($role == 'parent') {
            $parent = ParentModel::where('user_id', $user->id)
                ->with([
                    'students' => function ($q) {
                        $q->with('club:id,name,logo')

                            ->select('students.id', 'fullname', 'birthdate', 'sex', 'photo', 'status', 'club_id');
                    }
                ])
                ->first();

            if (!$parent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas la permission d\'accéder à cette page',
                ], 403);
            }
            $studentId = $parent->students()->pluck('students.id');
            $Payments = StudentPayment::with(['pricingPlan.paymentCategory', 'student'])
                ->whereIn('student_id', $studentId)
                ->where('club_id', $clubId)
                ->latest()
                ->paginate(8);
            return response()->json([
                'success' => true,
                'paiements' => $Payments,
            ]);
        } elseif ($role === 'karateka') {
            $student = Student::where('user_id', $user->id)->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'etes pas inscrit à ce club',
                ], 403);
            }
            $payments = $student->payments()
                ->with(['pricingPlan.paymentCategory', 'student'])
                ->latest()
                ->paginate(2);

            return response()->json([
                'success' => true,
                'paiements' => $payments,
            ]);
        } else {

            $Payments = StudentPayment::with(['pricingPlan.paymentCategory', 'student'])
                ->where('club_id', $clubId)
                ->latest()
                ->paginate(4);
            return response()->json([
                'success' => true,
                'paiements' => $Payments,
            ]);

            return response()->json([
                'success' => true,
                'paiements' => $Payments,
            ]);
        }
    }
    public function store(StorePaymentRequest $request)
    {
        $validated = $request->validated();
        $clubId = $request->validated_club_id;
        $plan = PricingPlan::with('paymentCategory')->findOrFail($validated['pricing_plan_id']);
        $student = Student::findOrFail($validated['student_id']);

        // 1. RÉCUPÉRATION DE LA DETTE RÉELLE (Basée sur le dernier paiement)
        $lastPayment = StudentPayment::where('student_id', $student->id)
            ->where('pricing_plan_id', $plan->id)
            ->where('club_id', $clubId)
            ->latest() // On prend le tout dernier
            ->first();

        // Si pas de paiement précédent, la dette est le prix du plan. 
        // Sinon, c'est la balance de la dernière ligne.
        $isNewPurchase = !$lastPayment;
        $currentDebt = $isNewPurchase ? $plan->price : $lastPayment->balance;

        $amountPaid = $validated['amount_paid'];

        // 2. VÉRIFICATION DE SÉCURITÉ
        if ($currentDebt <= 0) {
            return response()->json([
                'success' => false,
                'message' => "Ce forfait a déjà été entièrement réglé.",
            ], 422);
        }

        if ($amountPaid > $currentDebt) {
            return response()->json([
                'success' => false,
                'message' => "Le montant saisi dépasse le reste à payer (" . number_format($currentDebt, 0, ',', ' ') . " XOF).",
            ], 422);
        }

        // 3. LOGIQUE DES DATES (Uniquement si c'est un nouvel achat)
        $startsAt = Carbon::parse($validated['starts_at']);
        $endsAt = null;

        if ($isNewPurchase) {
            $currentExpiry = $student->subscription_expires_at;
            // Prolongation si abonnement déjà actif
            if ($currentExpiry && $currentExpiry->isFuture() && $plan->paymentCategory->slug === 'subscription') {
                $startsAt = $currentExpiry->copy()->addDay();
            }

            if ($plan->duration_value && $plan->duration_unit) {
                $method = 'add' . ucfirst($plan->duration_unit) . 's';
                $endsAt = $startsAt->copy()->$method($plan->duration_value);
            }
        } else {
            // Si c'est un remboursement de dette, on garde les dates du premier paiement
            $startsAt = $lastPayment->starts_at;
            $endsAt = $lastPayment->ends_at ? Carbon::parse($lastPayment->ends_at)->addDay() : null;
        }

        // 4. CALCUL FINANCIER
        $newBalance = $currentDebt - $amountPaid;
        $status = ($newBalance <= 0) ? 'paid' : 'partial';

        $payment = StudentPayment::create([
            'club_id' => $clubId,
            'student_id' => $student->id,
            'pricing_plan_id' => $plan->id,
            'total_amount' => $plan->price,
            'amount_paid' => $amountPaid,
            'balance' => $newBalance,
            'status' => $status,
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'] ?? null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt ? $endsAt->copy()->subDay() : null,
            'recorded_by' => Auth::id(),
            'notes' => $validated['notes'] ?? null,
            'parent_id' => $lastPayment ? ($lastPayment->parent_id ?? $lastPayment->id) : null,
        ]);

        // 5. MISE À JOUR DE LA VALIDITÉ (Seulement si c'est le premier versement)
        if ($isNewPurchase && $endsAt && ($plan->paymentCategory->affects_validity || $plan->paymentCategory->slug === 'registration')) {
            $student->update(['subscription_expires_at' => $endsAt]);
        }

        return response()->json([
            'success' => true,
            'message' => $newBalance <= 0 ? 'Paiement soldé avec succès' : 'Tranche enregistrée. Reste : ' . $newBalance . ' XOF',
            'data' => $payment
        ], 201);
    }
    public function getStudentHistory($studentId, Request $request)
    {
        $clubId = $request->validated_club_id;
        $payments = StudentPayment::with(['pricingPlan.paymentCategory', 'recorder'])
            ->where('student_id', $studentId)
            ->where('club_id', $clubId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($payments);
    }

    public function downloadInvoice(Request $request, $id)
    {
        $clubId = $request->validated_club_id;
        $role = $request->validated_role_name;
        Log::info('clubId', ['clubId' => $clubId]);

        $payment = StudentPayment::with(['student', 'pricingPlan', 'club'])
            ->where('club_id', $clubId)
            ->findOrFail($id);

        $pdf = Pdf::loadView('invoices.payment', compact('payment'));

        return $pdf->download("Facture_{$payment->student->lastname}_{$payment->created_at->format('d-m-Y')}.pdf");
    }
    public function revenueStats(Request $request)
    {
        $clubId = $request->validated_club_id;
        $role = $request->validated_role_name;
        $stats = StudentPayment::where('club_id', $clubId)
            ->selectRaw('SUM(amount_paid) as total, DATE_FORMAT(created_at, "%b") as month')
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($stats);
    }


    public function stats(StudentPaymentService $StudentPaymentService, Request $request)
    {
        $this->authorize('viewStats', StudentPayment::class);
        $clubId = $request->validated_club_id;
        Log::info('club_id', ['clubId' => $clubId]);

        $stats = $StudentPaymentService->getStudentpaymentStats($clubId);

        return response()->json([
            'success' => true,
            'message' => 'Statistiques des paiements',
            'data' => $stats,
        ]);
    }


    public function getRemainingDebt(Request $request, Student $student, PricingPlan $pricingPlan)
    {
        $clubId = $request->validated_club_id;
        $lastPayment = StudentPayment::where('student_id', $student->id)
            ->where('pricing_plan_id', $pricingPlan->id)
            ->where('club_id', $clubId)
            ->latest()
            ->first();


        $debt = $lastPayment ? $lastPayment->balance : $pricingPlan->price;

        return response()->json([
            'success' => true,
            'debt' => (float) $debt,
            'is_new_purchase' => $lastPayment ? false : true
        ]);
    }

    public function getDebtors(Request $request)
    {
        $clubId = $request->validated_club_id;

        $debts = StudentPayment::where('club_id', $clubId)
            ->where('balance', '>', 0)
            ->with(['student.user:id,fullname,phone', 'pricingPlan:id,label', 'student.parents.user:id,fullname,phone', 'student.club:id,name,logo'])
            ->select('student_id', 'pricing_plan_id', 'balance', 'updated_at', 'total_amount')
            ->whereIn('id', function ($query) {
                $query->selectRaw('max(id)')
                    ->from('student_payments')
                    ->groupBy('student_id', 'pricing_plan_id');
            })
            ->orderBy('balance', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $debts,
            'total_unpaid' => $debts->sum('balance')
        ]);
    }


    public function getParentDebts(Request $request)
    {
        $user = auth()->user();
        $clubId = $request->validated_club_id;
        $parent = ParentModel::where('user_id', $user->id)->first();
        $studentIds = $parent->students()->pluck('students.id');
        $debts = StudentPayment::where('club_id', $clubId)
            ->where('balance', '>', 0)
            ->whereIn('student_id', $studentIds)
            ->with(['student.user:id,fullname', 'pricingPlan:id,label'])
            ->select('student_id', 'pricing_plan_id', 'balance', 'updated_at', 'total_amount')
            ->whereIn('id', function ($query) {
                $query->selectRaw('max(id)')
                    ->from('student_payments')
                    ->groupBy('student_id', 'pricing_plan_id');
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $debts,
            'total_family_debt' => $debts->sum('balance')
        ]);
    }
}
