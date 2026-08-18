<?php

namespace App\Models;

use App\Models\Club;
use App\Models\Saison;
use App\Models\Student;
use App\Models\Federation;
use App\Models\LicenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Licence extends Model
{
    use HasUuids, SoftDeletes;

    // Statuts (cohérents avec la colonne tinyInteger 'status') — purement
    // une donnée d'appartenance, l'argent lui-même vit dans `transactions`
    // (voir TransactionController::confirmer, qui met ce statut à jour).
    public const STATUS_EN_ATTENTE = 0;
    public const STATUS_PAYE = 1;

    protected $fillable = [
        'saison_id',
        'club_id',
        'student_id',
        'federation_id',
        'licence_type_id',
        'grade_au_moment',
        'montant_paye',
        'numero',
        'status',
    ];

    protected $casts = [
        'montant_paye' => 'decimal:2',
        'status' => 'integer',
    ];

    public function saison()
    {
        return $this->belongsTo(Saison::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function federation()
    {
        return $this->belongsTo(Federation::class);
    }

    public function licenceType()
    {
        return $this->belongsTo(LicenceType::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ((int) $this->status) {
            self::STATUS_PAYE => 'Payé',
            default => 'En attente',
        };
    }
}
