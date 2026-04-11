<?php

namespace App\Models;

use App\Models\Club;
use App\Models\User;
use App\Models\Student;
use App\Models\PricingPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StudentPayment extends Model
{
    use HasUuids;
    protected $fillable = [
        'club_id',
        'student_id',
        'pricing_plan_id',
        'total_amount',
        'amount_paid',
        'balance',
        'parent_id',
        'payment_method',
        'starts_at',
        'ends_at',
        'recorded_by',
        'notes',
    ];
    public $incrementing = false;
    protected $keyType = 'string';
    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function pricingPlan()
    {
        return $this->belongsTo(PricingPlan::class);
    }
    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
