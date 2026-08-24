<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Evenement;
use Illuminate\Support\Facades\DB;

class ArbitreVerificationService
{
    private array $config = [
        'Ligue' => [
            'table' => 'league_users',
            'fk'    => 'league_id',
            'role'  => 'arbitre',
        ],
        'Federation' => [
            'table' => 'federation_users',
            'fk'    => 'federation_id',
            'role'  => 'arbitre',
        ],
    ];
    public function estArbitre(Evenement $evenement, int|string $userId): bool
    {
        $type = $evenement->organisateur_type;

        if (!isset($this->config[$type])) {
            return false;
        }

        $cfg         = $this->config[$type];
        $roleArbitre = Role::where('name', $cfg['role'])->first();

        if (!$roleArbitre) return false;

        return DB::table($cfg['table'])
            ->where('user_id',   $userId)
            ->where($cfg['fk'],  $evenement->organisateur_id)
            ->where('role_id',   $roleArbitre->id)
            ->exists();
    }
}
