<?php

namespace App\Models;

use App\Models\ConfigNotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class JugeCompetition extends Model
{
    use HasUuids;
    protected $fillable = [
        'config_notation_id',
        'numero_juge',
        'user_id',
        'nom_affichage',
        'code_acces',
        'connecte',
    ];

    protected $casts = [
        'connecte' => 'boolean',
    ];

    // Cacher le code PIN dans les réponses API par défaut
    protected $hidden = ['code_acces'];
    protected  $keyType = 'string';
    public $incrementing = false;

    public function configNotation()
    {
        return $this->belongsTo(ConfigNotation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Connexion du juge via son code PIN
    public static function connecterViaPIN(
        string $pin,
        string $configId
    ): self|null {
        $juge = self::where('config_notation_id', $configId)
            ->where('code_acces', $pin)
            ->first();

        if (!$juge) return null;

        $juge->update(['connecte' => true]);
        return $juge;
    }
}
