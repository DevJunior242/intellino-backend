<?php

namespace App\Models;

use App\Models\Role;
use App\Models\User;
use App\Models\League;
use App\Models\Country;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Federation extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'code',
        'logo',
        'address',
        'phone',
        'email',
        'website',
        'country_id',
        'invitation_code'
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function leagues()
    {
        return $this->hasMany(League::class);
    }

    /**
     * Relation avec les Utilisateurs : Une fédération a plusieurs membres/admins
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'federation_users')
            ->withPivot('role_id', 'mandate_start_at', 'mandate_end_at', 'mandate_status')
            ->withTimestamps();
    }
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'federation_users')
            ->withPivot('role_id')
            ->withTimestamps();
    }
    protected static function booted()
    {
        static::creating(function ($federation) {
            if (empty($federation->invitation_code)) {
                $federation->invitation_code = self::generateUniqueInvitationCode();
            }
        });
    }

    /**
     * Génère un code unique et s'assure qu'il n'existe pas déjà en BDD
     */
    private static function generateUniqueInvitationCode(): string
    {
        do {
            $code = 'FED-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        } while (self::where('invitation_code', $code)->exists());

        return $code;
    }
}
