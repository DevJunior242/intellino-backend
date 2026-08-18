<?php

namespace Database\Seeders;

use App\Models\KumiteFormat;
use Illuminate\Database\Seeder;

class KumiteFormatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $formats = [
            [
                'code' => 'poules',
                'libelle' => 'Poules uniquement',
                'description' => 'Combats en poules, classement par victoires',
                'ordre' => 1,
                'actif' => true,
            ],
            [
                'code' => 'eliminatoire',
                'libelle' => 'Élimination directe',
                'description' => 'Tableau éliminatoire direct',
                'ordre' => 2,
                'actif' => true,
            ],
            [
                'code' => 'poules_eliminatoire',
                'libelle' => 'Poules + Élimination',
                'description' => 'Phase de poules puis tableau éliminatoire',
                'ordre' => 3,
                'actif' => true,
            ],
        ];

        foreach ($formats as $format) {
            KumiteFormat::firstOrCreate(
                ['code' => $format['code']],
                [
                    'libelle' => $format['libelle'],
                    'description' => $format['description'],
                    'ordre' => $format['ordre'],
                    'actif' => $format['actif'],
                ]
            );
        }
    }
}
