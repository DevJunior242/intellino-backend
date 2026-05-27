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
        Schema::create('tatami_judges', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('config_notation_id')->constrained('config_notations')->cascadeOnDelete();
            $table->string('ip_address'); // L'IP unique de la tablette
            $table->string('judge_token'); // Un token unique pour chaque tablette
            $table->tinyInteger('juge_numero'); // 1, 2, 3 ou 4
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['config_notation_id', 'juge_numero']);
            $table->unique(['config_notation_id', 'judge_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tatami_judges');
    }
};
