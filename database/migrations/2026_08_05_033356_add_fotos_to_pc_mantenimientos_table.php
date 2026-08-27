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
        if (Schema::hasTable('pc_mantenimientos')) {
            Schema::table('pc_mantenimientos', function (Blueprint $table) {
                if (!Schema::hasColumn('pc_mantenimientos', 'foto_antes')) {
                    $table->string('foto_antes')->nullable()->after('unidad_cd');
                }
                if (!Schema::hasColumn('pc_mantenimientos', 'foto_despues')) {
                    $table->string('foto_despues')->nullable()->after('foto_antes');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pc_mantenimientos')) {
            Schema::table('pc_mantenimientos', function (Blueprint $table) {
                $columns = ['foto_antes', 'foto_despues'];
                $existing = array_filter($columns, fn ($c) => Schema::hasColumn('pc_mantenimientos', $c));
                if (!empty($existing)) {
                    $table->dropColumn(array_values($existing));
                }
            });
        }
    }
};
