<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kumite_formats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();   // poules, eliminatoire, poules_eliminatoire
            $table->string('libelle');          // "Poules uniquement"
            $table->string('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->integer('ordre')->default(0);
            $table->timestamps();
        });

        // Seeder
        DB::table('kumite_formats')->insert([
            [
                'id'          => Str::uuid(),
                'code'        => 'poules',
                'libelle'     => 'Poules uniquement',
                'description' => 'Combats en poules, classement par victoires',
                'ordre'       => 1,
                'actif'       => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'id'          => Str::uuid(),
                'code'        => 'eliminatoire',
                'libelle'     => 'Élimination directe',
                'description' => 'Tableau éliminatoire direct',
                'ordre'       => 2,
                'actif'       => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'id'          => Str::uuid(),
                'code'        => 'poules_eliminatoire',
                'libelle'     => 'Poules + Élimination',
                'description' => 'Phase de poules puis tableau éliminatoire',
                'ordre'       => 3,
                'actif'       => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kumute_formats');
    }
};
