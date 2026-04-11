<?php

namespace App\Models;

use App\Models\User;
use App\Models\Student;
use App\Models\Equipment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EquipmentLoan extends Model
{
    use HasUuids;
    protected $table = 'equipment_loans';
    protected $fillable = [
        'equipment_id',
        'user_id',
        'club_id',
        'club_id',
        'to_club_id',
        'quantity_loaned',
        'loaned_at',
        'returned_at',
        'quantity_returned',
        'quantity_lost',
        'quantity_damaged',
        'status',
        'type',
        'beneficiary',
    ];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $attributes = ['status' => 'active'];
    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }
    public function toClub()
    {
        return $this->belongsTo(Club::class, 'to_club_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function calculateStatus(
        int $returned,
        int $lost,
        int $damaged,
        int $total
    ): string {
        // Cas parfait : tout est retourné
        if ($returned === $total && $lost === 0 && $damaged === 0) {
            return 'returned';
        }

        // Tout est perdu
        if ($lost === $total && $returned === 0 && $damaged === 0) {
            return 'lost';
        }

        // Tout est endommagé
        if ($damaged === $total && $returned === 0 && $lost === 0) {
            return 'damaged';
        }

        // Tous les autres cas (mix)
        return 'partial';
    }
}
