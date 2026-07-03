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
        Schema::create('activation_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key_code')->unique();
            $table->string('type');
            $table->uuid('target_league_id')->nullable();
            $table->string('comment')->nullable();
            $table->boolean('is_used')->default(false);
            $table->string('used_by_user_id')->nullable();
            $table->string('used_by_organisation_id')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activation_keys');
    }
};
