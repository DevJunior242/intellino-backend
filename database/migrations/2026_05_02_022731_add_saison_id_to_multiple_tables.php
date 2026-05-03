<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        $tables = ['examens', 'competitions', 'licences', 'affiliations'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignUuid('saison_id')
                    ->nullable()
                    ->constrained('saisons')
                    ->onullOnDelete();
            });
        }
    }

    public function down()
    {
        $tables = ['examens', 'courses', 'attendances', 'licences', 'affiliations'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropForeign([$tableName . '_saison_id_foreign']);
                $table->dropColumn('saison_id');
            });
        }
    }
};
