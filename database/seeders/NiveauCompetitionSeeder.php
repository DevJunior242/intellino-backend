<?php

namespace Database\Seeders;

use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use App\Models\NiveauxCompetition;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class NiveauCompetitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $niveaux = [
            'Regionale',
            'Nationale',
        ];
        foreach ($niveaux as $niveau) {
            NiveauxCompetition::firstOrCreate(
                ['nom' => $niveau]
            );
        }
    }
}
