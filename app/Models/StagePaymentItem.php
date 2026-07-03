<?php

namespace App\Models;

use App\Models\StagePayment;
use App\Models\StageRegistration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StagePaymentItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'stage_payment_id',
        'stage_registration_id',
    ];

    public function stagePayment()
    {
        return $this->belongsTo(StagePayment::class);
    }

    public function stageRegistration()
    {
        return $this->belongsTo(StageRegistration::class);
    }
}
