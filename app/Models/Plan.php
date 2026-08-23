<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Plan extends Model
{

    use HasUuids;
    protected $fillable = [
        'name',
        'description',
        'amount',
        'organisateur_type',
        'min_users',
        'max_users',
    ];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    // Le palier applicable à une organisation d'un type donné pour un
    // nombre d'utilisateurs actifs donné — max_users nul = pas de plafond
    // (dernier palier "sur-mesure" d'une échelle).
    public static function pourEffectif(string $organisateurType, int $nbUsers): ?self
    {
        return static::where('organisateur_type', $organisateurType)
            ->where('min_users', '<=', $nbUsers)
            ->where(function ($q) use ($nbUsers) {
                $q->whereNull('max_users')->orWhere('max_users', '>=', $nbUsers);
            })
            ->orderBy('min_users')
            ->first();
    }
}
