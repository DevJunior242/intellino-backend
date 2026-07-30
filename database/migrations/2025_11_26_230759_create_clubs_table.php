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
        Schema::create('clubs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->string('logo')->nullable();
            $table->string('city');
            $table->string('region');
            $table->string('address')->nullable();
            $table->string('website')->nullable();
            $table->string('invitation_code')->unique()->nullable();
            // 1 = actif, 0 = désactivé par le super admin
            $table->tinyInteger('status')->default(1);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clubs');
    }
};
