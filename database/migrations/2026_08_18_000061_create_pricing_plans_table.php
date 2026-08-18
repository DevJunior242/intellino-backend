<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->onDelete('cascade');
            $table->foreignUuid('payment_category_id')->constrained('payment_categories')->onDelete('cascade');
            $table->string('label');
            $table->decimal('price', 10, 2);
            $table->integer('duration_value')->nullable();
            $table->enum('duration_unit', ['day', 'month', 'year'])->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_plans');
    }
};
