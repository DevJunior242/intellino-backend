<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('equipment_category_id')->constrained()->onDelete('cascade');
            $table->string('name'); // ex: "Gants de Boxe Venum"
            $table->integer('total_quantity')->default(0); // Stock théorique total
            $table->integer('available_quantity')->default(0); // Ce qui est réellement en rayon
            $table->integer('min_stock_alert')->default(5);
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
