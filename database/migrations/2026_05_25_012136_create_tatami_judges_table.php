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
            $table->string('ip_address');  
            $table->string('judge_token');  
            $table->tinyInteger('juge_numero'); 
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
