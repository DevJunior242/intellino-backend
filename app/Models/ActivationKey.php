<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ActivationKey extends Model
{
    use HasUuids;
    protected $fillable = ['key_code', 'type', 'comment', 'is_used', 'used_at', 'target_league_id', 'used_by_user_id', 'used_by_organisation_id'];

    protected $keyType = 'string';
    public $incrementing = false;
}
