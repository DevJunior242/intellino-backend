<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        $tables = ['examens', 'courses', 'attendances'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignUuid('saison_id')
                    ->nullable()
                    ->constrained('saisons')
                    ->nullOnDelete();
            });
        }
    }

    public function down()
    {
        $tables = ['examens', 'courses', 'attendances', 'affiliations'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropForeign(['saison_id']);
                $table->dropColumn('saison_id');
            });
        }
    }
};
