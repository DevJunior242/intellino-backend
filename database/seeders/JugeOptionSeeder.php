<?php

namespace Database\Seeders;

use App\Models\JugeOption;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class JugeOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $options = [
            [
                'valeur' => 5,
                'libelle' => '5 juges',
                'actif' => true,
            ],
            [
                'valeur' => 7,
                'libelle' => '7 juges',
                'actif' => true,
            ],
        ];
        foreach ($options as $option) {
            JugeOption::firstOrCreate(
                ['valeur' => $option['valeur']],
                [
                    'libelle' => $option['libelle'],
                    'actif' => $option['actif'],
                ]
            );
        }
    }
}
