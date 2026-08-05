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
        Schema::table('pc_mantenimientos', function (Blueprint $table) {
            $table->string('foto_antes')->nullable()->after('unidad_cd');
            $table->string('foto_despues')->nullable()->after('foto_antes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pc_mantenimientos', function (Blueprint $table) {
            $table->dropColumn(['foto_antes', 'foto_despues']);
        });
    }
};
