<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('personal') && !Schema::hasColumn('personal', 'estado')) {
            Schema::table('personal', function (Blueprint $table) {
                $table->tinyInteger('estado')->nullable()->default(1)->after('cargo_id')->comment('0: Inactivo, 1: Activo');
            });
        }
    }




    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
