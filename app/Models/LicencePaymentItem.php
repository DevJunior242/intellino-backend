<?php

namespace App\Models;

use App\Models\Licence;
use App\Models\LicencePayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LicencePaymentItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'licence_payment_id',
        'licence_id',
    ];

    public function licencePayment()
    {
        return $this->belongsTo(LicencePayment::class);
    }

    public function licence()
    {
        return $this->belongsTo(Licence::class);
    }
}
