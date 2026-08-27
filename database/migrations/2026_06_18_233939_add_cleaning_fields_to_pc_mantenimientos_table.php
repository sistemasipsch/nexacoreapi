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
                if (!Schema::hasColumn('pc_mantenimientos', 'cpu')) {
                    $table->boolean('cpu')->default(false)->after('estado');
                }
                if (!Schema::hasColumn('pc_mantenimientos', 'pantalla')) {
                    $table->boolean('pantalla')->default(false)->after('cpu');
                }
                if (!Schema::hasColumn('pc_mantenimientos', 'teclado')) {
                    $table->boolean('teclado')->default(false)->after('pantalla');
                }
                if (!Schema::hasColumn('pc_mantenimientos', 'mouse')) {
                    $table->boolean('mouse')->default(false)->after('teclado');
                }
                if (!Schema::hasColumn('pc_mantenimientos', 'unidad_cd')) {
                    $table->boolean('unidad_cd')->default(false)->after('mouse');
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
                $columns = ['cpu', 'pantalla', 'teclado', 'mouse', 'unidad_cd'];
                $existing = array_filter($columns, fn ($c) => Schema::hasColumn('pc_mantenimientos', $c));
                if (!empty($existing)) {
                    $table->dropColumn(array_values($existing));
                }
            });
        }
    }
};
