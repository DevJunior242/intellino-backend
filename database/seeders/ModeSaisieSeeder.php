<?php

namespace Database\Seeders;

use App\Models\ModeSaisie;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ModeSaisieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modes = [
            [
                'code' => 'centralise',
                'libelle' => 'Saisie centralisée',
                'actif' => true,
            ],
            [
                'code' => 'tablettes',
                'libelle' => 'Tablettes individuelles',
                'actif' => true,
            ],
        ];
        foreach ($modes as $mode) {
            ModeSaisie::firstOrCreate(
                ['code' => $mode['code']],
                [
                    'libelle' => $mode['libelle'],
                    'actif' => $mode['actif'],
                ]
            );
        }
    }
}
