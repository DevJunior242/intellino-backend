<?php

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
        Schema::create('affiliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->foreignUuid('saison_id')->constrained('saisons')->cascadeOnDelete();
            //tarif d'affiliation fixé par la fédération pour la saison, payé par chaque club
            $table->decimal('cotisation', 10, 2);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['federation_id', 'saison_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliations');
    }
};
