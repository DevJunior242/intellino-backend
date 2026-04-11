<?php

namespace App\Models;

use App\Models\Club;
use App\Models\StudentPayment;
use App\Models\PaymentCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PricingPlan extends Model
{
    use HasUuids;
    protected $fillable = [
        'club_id',
        'duration_unit',
        'label',
        'price',
        'duration_value',
        'payment_category_id',
    ];


    public $incrementing = false;
    protected $keyType = 'string';

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function paymentCategory()
    {
        return $this->belongsTo(PaymentCategory::class);
    }



    // Scope pour récupérer les forfaits d'un club
    public function scopeForClub($query, $clubId)
    {
        return $query->where('club_id', $clubId);
    }

    public function paiements()
    {
        return $this->hasMany(StudentPayment::class);
    }
}
