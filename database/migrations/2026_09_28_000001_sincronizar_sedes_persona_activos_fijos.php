<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Agregar columna sede_id a la tabla personal si no existe
        if (!Schema::hasColumn('personal', 'sede_id')) {
            Schema::table('personal', function (Blueprint $table) {
                $table->integer('sede_id')->nullable()->after('cargo_id');
                $table->index('sede_id');
            });
        }

        // 2. Modificar sede_id y proceso_solicitante en cp_entrega_activos_fijos para que sean nullable
        try {
            DB::statement('ALTER TABLE cp_entrega_activos_fijos MODIFY COLUMN sede_id INT(11) NULL');
            DB::statement('ALTER TABLE cp_entrega_activos_fijos MODIFY COLUMN proceso_solicitante INT(11) NULL');
        } catch (\Exception $e) {
            // Si ya son nullable, continuar
        }

        // 3. Normalizar nombres y sincronizar sede_id desde usuarios hacia personal
        $usuariosConSede = DB::table('usuarios')->whereNotNull('sede_id')->get();
        foreach ($usuariosConSede as $usr) {
            $nombreUsuario = trim($usr->nombre_completo ?? '');
            if (!$nombreUsuario) continue;

            $cleanUser = mb_strtoupper($nombreUsuario, 'UTF-8');
            $userWords = array_values(array_filter(explode(' ', $cleanUser), fn($w) => strlen($w) >= 3));

            $personales = DB::table('personal')->whereNull('sede_id')->get();
            foreach ($personales as $p) {
                $pClean = mb_strtoupper(trim($p->nombre), 'UTF-8');
                if ($pClean === $cleanUser) {
                    DB::table('personal')->where('id', $p->id)->update(['sede_id' => $usr->sede_id]);
                    break;
                }
                $pWords = array_values(array_filter(explode(' ', $pClean), fn($w) => strlen($w) >= 3));
                $intersect = array_intersect($userWords, $pWords);
                if (count($intersect) >= 2 && count($intersect) >= count($userWords) * 0.5) {
                    DB::table('personal')->where('id', $p->id)->update(['sede_id' => $usr->sede_id]);
                    break;
                }
            }
        }

        // 4. Actualizar cp_entrega_activos_fijos.sede_id para que refleje la sede real de la persona (o NULL)
        $entregas = DB::table('cp_entrega_activos_fijos')->get();
        foreach ($entregas as $e) {
            if (!$e->personal_id) continue;

            $personal = DB::table('personal')->where('id', $e->personal_id)->first();
            $sedeId = $personal?->sede_id;

            // Si el personal no tiene sede_id, buscar en usuarios por nombre
            if (!$sedeId && $personal && !empty($personal->nombre)) {
                $pClean = mb_strtoupper(trim($personal->nombre), 'UTF-8');
                $pWords = array_values(array_filter(explode(' ', $pClean), fn($w) => strlen($w) >= 3));

                $allUsers = DB::table('usuarios')->whereNotNull('sede_id')->get();
                foreach ($allUsers as $u) {
                    $uClean = mb_strtoupper(trim($u->nombre_completo), 'UTF-8');
                    if ($uClean === $pClean) {
                        $sedeId = $u->sede_id;
                        break;
                    }
                    $uWords = array_values(array_filter(explode(' ', $uClean), fn($w) => strlen($w) >= 3));
                    $intersect = array_intersect($pWords, $uWords);
                    if (count($intersect) >= 2 && count($intersect) >= count($pWords) * 0.5) {
                        $sedeId = $u->sede_id;
                        break;
                    }
                }
            }

            // Actualizar la entrega con la sede real de la persona (o NULL si no tiene)
            DB::table('cp_entrega_activos_fijos')->where('id', $e->id)->update([
                'sede_id' => $sedeId ?: null
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('personal', 'sede_id')) {
            Schema::table('personal', function (Blueprint $table) {
                $table->dropColumn('sede_id');
            });
        }
    }
};
