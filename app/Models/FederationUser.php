<?php

namespace App\Models;

use App\Models\Role;
use App\Models\User;
use App\Models\Federation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class FederationUser extends Model
{
    use HasUuids;
    protected $fillable = ['federation_id', 'user_id', 'role_id', 'mandate_start_at', 'mandate_end_at', 'mandate_status'];
    protected $keyType = 'string';
    public $incrementing = false;

    public function federation()
    {
        return $this->belongsTo(Federation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
