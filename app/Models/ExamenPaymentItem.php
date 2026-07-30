<?php

namespace App\Models;

use App\Models\ExamenPayment;
use App\Models\ExamenCandidat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ExamenPaymentItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'examen_payment_id',
        'examen_candidat_id',
    ];

    public function examenPayment()
    {
        return $this->belongsTo(ExamenPayment::class);
    }

    public function examenCandidat()
    {
        return $this->belongsTo(ExamenCandidat::class);
    }
}
