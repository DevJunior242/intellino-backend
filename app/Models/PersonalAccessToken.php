<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken as SanctumAccessToken;

class PersonalAccessToken extends SanctumAccessToken
{
    use HasUuids;
     

    protected $keyType = 'string';
    public $incrementing = false;
}
