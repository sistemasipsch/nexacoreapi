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
        if (!Schema::hasTable('pc_prestamos')) {
            Schema::create('pc_prestamos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('equipo_id')->nullable();
                $table->string('descripcion_equipo', 500);
                $table->string('serial', 150)->nullable();
                $table->string('titular_nombre', 255)->comment('A nombre de quién está el equipo originalmente o Stock Sistemas');
                $table->unsignedBigInteger('titular_persona_id')->nullable();
                $table->string('prestatario_nombre', 255)->comment('A quién se le prestó');
                $table->unsignedBigInteger('prestatario_persona_id')->nullable();
                $table->unsignedBigInteger('sede_id')->nullable();
                $table->dateTime('fecha_prestamo');
                $table->dateTime('fecha_devolucion_esperada')->nullable();
                $table->dateTime('fecha_devolucion_real')->nullable();
                $table->unsignedBigInteger('prestado_por')->comment('Usuario de sistemas que realizó el préstamo');
                $table->unsignedBigInteger('recibido_por')->nullable()->comment('Usuario de sistemas que recibió el equipo devuelto');
                $table->string('estado', 50)->default('vigente')->comment('vigente, devuelto');
                $table->text('observaciones')->nullable();
                $table->text('observaciones_devolucion')->nullable();
                $table->timestamps();

                $table->index('equipo_id');
                $table->index('estado');
                $table->index('prestado_por');
                $table->index('fecha_prestamo');
            });
        }

        // Registrar permisos para el módulo de préstamos
        $permisosPrestamos = [
            'pc_prestamo.listar' => 'Listar préstamos de sistemas',
            'pc_prestamo.crear' => 'Crear préstamos de equipos en sistemas',
            'pc_prestamo.actualizar' => 'Actualizar y gestionar devoluciones de préstamos',
            'pc_prestamo.eliminar' => 'Eliminar registros de préstamos de sistemas',
        ];

        $permisosIds = [];
        foreach ($permisosPrestamos as $nombre => $desc) {
            $permiso = DB::table('permisos')->where('nombre', $nombre)->first();
            if (!$permiso) {
                $permisosIds[] = DB::table('permisos')->insertGetId([
                    'nombre' => $nombre,
                    'descripcion' => $desc,
                ]);
            } else {
                $permisosIds[] = $permiso->id;
            }
        }

        // Asignar los permisos de préstamos únicamente a roles de Sistemas y Administrador
        $rolesSistemas = DB::table('rol')
            ->where(function ($query) {
                $query->where('nombre', 'LIKE', '%SISTEMA%')
                      ->orWhere('nombre', 'LIKE', '%ADMINISTRADOR%');
            })
            ->get();

        foreach ($rolesSistemas as $rol) {
            foreach ($permisosIds as $pId) {
                $exists = DB::table('rol_permisos')
                    ->where('rol_id', $rol->id)
                    ->where('permiso_id', $pId)
                    ->exists();

                if (!$exists) {
                    DB::table('rol_permisos')->insert([
                        'rol_id' => $rol->id,
                        'permiso_id' => $pId,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pc_prestamos');
    }
};
