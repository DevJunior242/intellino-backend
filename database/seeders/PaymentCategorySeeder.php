<?php

namespace Database\Seeders;

use Illuminate\Support\Str;
use App\Models\PaymentCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Inscription / Licence',
                'slug' => 'registration',
                'description' => 'Frais d\'adhésion annuelle et licence fédérale',
                'affects_validity' => false,
                'is_system' => true,
            ],
            [
                'name' => 'Abonnement (Mensualité)',
                'slug' => 'subscription',
                'description' => 'Cotisation périodique donnant accès aux cours',
                'affects_validity' => true,
                'is_system' => true,
            ],
            [
                'name' => 'Réinscription',
                'slug' => 're_registration',
                'description' => 'Frais de renouvellement pour ancienne saison',
                'affects_validity' => false,
                'is_system' => true,
            ],
            [
                'name' => 'Passage de Grade',
                'slug' => 'belt_exam',
                'description' => 'Frais de participation aux examens de ceintures',
                'affects_validity' => false,
                'is_system' => true,
            ],
            [
                'name' => 'Équipement (Kimono/Protections)',
                'slug' => 'equipment',
                'description' => 'Achat de matériel de karaté (Dogi, gants, etc.)',
                'affects_validity' => false,
                'is_system' => true,
            ],
            [
                'name' => 'Évènement / Stage / Compétition',
                'slug' => 'event',
                'description' => 'Frais de participation aux activités hors club',
                'affects_validity' => false,
                'is_system' => true,
            ],
            [
                'name' => 'Sanction / Amende',
                'slug' => 'penalty',
                'description' => 'Frais suite à un retard ou manquement',
                'affects_validity' => false,
                'is_system' => true,
            ],
        ];
        foreach ($categories as $category) {
            PaymentCategory::firstOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'affects_validity' => $category['affects_validity'],
                    'is_system' => $category['is_system'],
                ]
            );
        }
    }
}
