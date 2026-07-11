<?php

namespace App\Models;


use App\Models\Club;
use App\Models\Like;
use App\Models\Role;
use App\Models\League;
use App\Models\Student;
use App\Models\Activity;
use App\Models\Candidat;
use App\Models\ClubUser;
use App\Models\Federation;
use App\Models\ParentModel;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasUuids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'fullname',
        'phone',
        'email',
        'password',
        'current_club_id',
        'current_league_id',
        'current_federation_id',
        'global_role_id',

    ];
    protected $appends = ['photo_url'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    public function clubs()
    {
        return $this->belongsToMany(Club::class, 'club_users')
            ->using(ClubUser::class)
            ->withPivot('role_id')
            ->withTimestamps();
    }
    public function leagues()
    {
        return $this->belongsToMany(League::class, 'league_users')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function federations(): BelongsToMany
    {
        return $this->belongsToMany(Federation::class, 'federation_users')
            ->withPivot('role_id', 'mandate_start_at', 'mandate_end_at', 'mandate_status')
            ->withTimestamps();
    }

    public function activeLeagues()
    {
        $adminLeagueRoleId = Role::where('name', 'admin')->value('id');

        return $this->belongsToMany(League::class, 'league_users')
            ->withPivot('role_id', 'mandate_status', 'mandate_end_at')
            ->where(function ($query) use ($adminLeagueRoleId) {
                // On prend tout, SAUF les lignes qui sont à la fois 'admin' ET avec un mandat expiré (0)
                $query->whereNot(function ($q) use ($adminLeagueRoleId) {
                    $q->where('league_users.role_id', $adminLeagueRoleId)
                        ->where('league_users.mandate_status', 0);
                });
            })
            ->withTimestamps();
    }

    public function activeFederations()
    {
        $adminFedRoleId = Role::where('name', 'admin')->value('id');

        return $this->belongsToMany(Federation::class, 'federation_users')
            ->withPivot('role_id', 'mandate_status', 'mandate_end_at')
            ->where(function ($query) use ($adminFedRoleId) {
                $query->whereNot(function ($q) use ($adminFedRoleId) {
                    $q->where('federation_users.role_id', $adminFedRoleId)
                        ->where('federation_users.mandate_status', 0);
                });
            })
            ->withTimestamps();
    }

    /**
     * Relation Session Active : La fédération sur laquelle l'utilisateur travaille actuellement
     */
    public function currentFederation(): BelongsTo
    {
        return $this->belongsTo(Federation::class, 'current_federation_id');
    }
    public function children()
    {
        return $this->belongsToMany(
            Student::class,
            'parent_student',
            'parent_id',
            'student_id'
        );
    }

    public function parentProfile()
    {
        return $this->hasOne(ParentModel::class, 'user_id');
    }
    public function studentProfile()
    {
        return $this->hasOne(Student::class, 'user_id');
    }



    public function students()
    {
        return $this->hasMany(Student::class);
    }
    public function scopeParentsInClub($query, $clubId)
    {
        return $query->whereHas('clubs', function ($q) use ($clubId) {
            $q->where('club_id', $clubId)
                ->whereIn('role_id', Role::whereIn('name', ['parent'])->pluck('id'));
        });
    }

    public function globalRole()
    {
        return $this->belongsTo(Role::class, 'global_role_id');
    }

    public function getRoleInClub($clubId)
    {
        $club = $this->clubs()
            ->where('clubs.id', $clubId)
            ->first();

        return $club ? $club->pivot->role->name : null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->globalRole?->name === 'super_admin';
    }


    public function hasAccessTo(array $roles): bool
    {

        return in_array(request()->attributes->get('role'), $roles, true);
    }
    public function hasLeagueRole(array $roles): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        return in_array(request()->attributes->get('role'), $roles, true);
    }

    public function getPhotoUrlAttribute()
    {
        return $this->photo
            ? url('storage/' . $this->photo)
            : null;
    }
    //likes
    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function candidates()
    {
        return $this->hasMany(Candidat::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }
}
