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
        Schema::create('mode_saisies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();   // centralise, tablettes
            $table->string('libelle');          // "Saisie centralisée"
            $table->string('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->integer('ordre')->default(0);
            $table->timestamps();
        });
        DB::table('mode_saisies')->insert([
            [
                'id'          => Str::uuid(),
                'code'        => 'centralise',
                'libelle'     => 'Saisie centralisée',
                'description' => 'Un informaticien saisit toutes les notes',
                'ordre'       => 1,
                'actif'       => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'id'          => Str::uuid(),
                'code'        => 'tablettes',
                'libelle'     => 'Tablettes individuelles',
                'description' => 'Chaque juge saisit depuis sa tablette',
                'ordre'       => 2,
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
        Schema::dropIfExists('mode_saisies');
    }
};
