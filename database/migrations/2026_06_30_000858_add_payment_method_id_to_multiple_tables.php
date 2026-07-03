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
        $tables = ['student_payments', 'stage_payments', 'licence_payments'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignUuid('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['student_payments', 'stage_payments', 'licence_payments'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['payment_method_id']);
                $table->dropColumn('payment_method_id');
            });
        }
    }
};
