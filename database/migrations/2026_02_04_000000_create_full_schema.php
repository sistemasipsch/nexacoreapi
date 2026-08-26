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
        // Disable foreign key constraints to allow dropping tables in any order
        Schema::disableForeignKeyConstraints();

        // Drop all existing tables as requested
        $tables = [
            'actividades',
            'actualizaciones_web',
            'agenda_mantenimientos',
            'areas',
            'cp_centro_costo',
            'cp_cotizaciones_proveedores',
            'cp_dependencias',
            'cp_entrega_activos_fijos',
            'cp_entrega_activos_fijos_items',
            'cp_entrega_solicitud',
            'cp_items_pedidos',
            'cp_pedidos',
            'cp_productos',
            'cp_productos_servicios',
            'cp_proveedores',
            'cp_solicitud_descuento',
            'cp_tipo_solicitud',
            'datos_empresa',
            'dependencias_sedes',
            'inventario',
            'mantenimientos',
            'notif_notificaciones',
            'p_cargo',
            'pc_caracteristicas_tecnicas',
            'pc_config_cronograma',
            'pc_cronograma_mantenimientos',
            'pc_devuelto',
            'pc_entregas',
            'pc_equipos',
            'pc_historial_asignaciones',
            'pc_licencias_software',
            'pc_mantenimientos',
            'pc_perifericos_entregados',
            'permisos',
            'personal',
            'rf_reportes_comentarios',
            'rf_reportes_fallas',
            'rol',
            'rol_permisos',
            'sec_auditoria',
            'sec_codigo_verificacion',
            'sec_contrasenas_anteriores',
            'sec_intentos_login',
            'sec_ip_bloqueadas',
            'sedes',
            'usuarios',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        // ==========================================
        // CREATE TABLES
        // ==========================================

        Schema::create('actividades', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('usuario_id'); // FK
            $table->string('accion', 255);
            $table->string('tabla_afectada', 50)->nullable();
            $table->integer('registro_id')->nullable();
            $table->dateTime('fecha');

            $table->index('usuario_id');
        });

        Schema::create('actualizaciones_web', function (Blueprint $table) {
            $table->id('id');
            $table->string('titulo', 255);
            $table->text('descripcion');
            $table->date('mostrar_desde');
            $table->date('mostrar_hasta');
            $table->date('fecha_actualizacion');
            $table->integer('duracion_minutos');
            $table->enum('estado', ['pendiente', 'en progreso', 'finalizado'])->default('pendiente');
            $table->timestamp('creado_en')->useCurrent();
        });

        Schema::create('agenda_mantenimientos', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('mantenimiento_id')->nullable(); // FK
            $table->string('titulo', 255)->nullable();
            $table->text('descripcion')->nullable();
            $table->unsignedBigInteger('sede_id')->nullable(); // FK
            $table->dateTime('fecha_inicio')->nullable();
            $table->dateTime('fecha_fin')->nullable();
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->unsignedBigInteger('agendado_por')->nullable();
            $table->dateTime('fecha_creacion')->nullable();

            $table->index('mantenimiento_id');
            $table->index('sede_id');
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id('id');
            $table->string('nombre', 255)->nullable();
            $table->unsignedBigInteger('sede_id')->nullable(); // FK

            $table->index('sede_id');
        });

        Schema::create('cp_centro_costo', function (Blueprint $table) {
            $table->id('id');
            $table->integer('codigo')->nullable();
            $table->string('nombre', 160)->nullable();
        });

        Schema::create('cp_cotizaciones_proveedores', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('item_pedido_id')->nullable(); // FK
            $table->unsignedBigInteger('proveedor_id')->nullable(); // FK
            $table->date('fecha_solicitud_cotizacion')->nullable();
            $table->date('fecha_respuesta_cotizacion')->nullable();
            $table->decimal('precio', 12, 2)->nullable();
            $table->string('firma_aprobacion_oc', 255)->nullable();
            $table->date('fecha_envio_oc')->nullable();

            $table->unique('item_pedido_id', 'item_pedido_id_UNIQUE');
            $table->index('proveedor_id');
        });

        Schema::create('cp_dependencias', function (Blueprint $table) {
            $table->id('id');
            $table->integer('codigo')->nullable();
            $table->string('nombre', 160)->nullable();
            $table->unsignedBigInteger('sede_id')->nullable();
        });

        Schema::create('cp_entrega_activos_fijos', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('personal_id')->nullable(); // FK
            $table->unsignedBigInteger('sede_id')->nullable(); // FK
            $table->unsignedBigInteger('proceso_solicitante')->nullable(); // FK (dependencias_sedes)
            $table->unsignedBigInteger('coordinador_id')->nullable();
            $table->date('fecha_entrega')->nullable();
            $table->string('firma_quien_entrega', 260)->nullable();
            $table->string('firma_quien_recibe', 260)->nullable();

            $table->index('personal_id');
            $table->index('sede_id');
            $table->index('proceso_solicitante', 'cp_entrega_activos_fijos_idx');
        });

        Schema::create('cp_entrega_activos_fijos_items', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('item_id')->nullable(); // FK
            $table->tinyInteger('es_accesorio')->nullable();
            $table->text('accesorio_descripcion')->nullable();
            $table->unsignedBigInteger('entrega_activos_id'); // FK

            $table->index('item_id');
            $table->index('entrega_activos_id', 'cp_entrega_activos_fijos_items_ibfk_2');
        });

        Schema::create('cp_entrega_solicitud', function (Blueprint $table) {
            // "consecutivo_id" references "cp_pedidos"."consecutivo" (int UNIQUE)
            // So this must be integer to match.
            $table->integer('consecutivo_id')->primary();
            // 0 = pendiente, 1 = entregado
            $table->tinyInteger('estado')->default(0)->comment('0 = pendiente, 1 = entregado');
            $table->date('fecha')->nullable();
            $table->string('factura_proveedor', 260)->nullable();
            $table->string('firma_quien_recibe', 260)->nullable();
            $table->dateTime('created_at');
        });

        Schema::create('cp_items_pedidos', function (Blueprint $table) {
            $table->id('id');
            $table->string('nombre', 255);
            $table->integer('cantidad');
            $table->string('unidad_medida', 60)->nullable();
            $table->longText('referencia_items')->nullable();
            $table->unsignedBigInteger('cp_pedido'); // FK
            $table->unsignedBigInteger('productos_id')->nullable(); // FK
            $table->boolean('comprado')->default(0);

            $table->index('cp_pedido');
            $table->index('productos_id', 'fk_items_productos');
        });

        Schema::create('cp_pedidos', function (Blueprint $table) {
            $table->id('id');
            $table->enum('estado_compras', ['pendiente', 'aprobado', 'rechazado', 'en proceso'])->default('pendiente');
            $table->date('fecha_solicitud');
            $table->unsignedBigInteger('proceso_solicitante'); // FK
            $table->unsignedBigInteger('tipo_solicitud'); // FK
            $table->integer('consecutivo'); // Referenced by cp_entrega_solicitud
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('sede_id')->nullable(); // FK
            $table->unsignedBigInteger('elaborado_por'); // FK
            $table->string('elaborado_por_firma', 255)->nullable();
            $table->unsignedBigInteger('proceso_compra')->nullable(); // FK
            $table->string('proceso_compra_firma', 255)->nullable();
            $table->unsignedBigInteger('responsable_aprobacion')->nullable(); // FK
            $table->string('responsable_aprobacion_firma', 255)->nullable();
            $table->text('motivo_aprobacion')->nullable();
            $table->unsignedBigInteger('creador_por');
            $table->boolean('pedido_visto')->default(0);
            $table->text('observacion_diligenciado')->nullable();
            $table->enum('estado_gerencia', ['pendiente', 'aprobado', 'rechazado'])->default('pendiente');
            $table->text('observaciones_pedidos')->nullable();
            $table->string('adjunto_pdf', 255)->nullable();
            $table->date('fecha_compra')->nullable();
            $table->text('fecha_solicitud_cotizacion')->nullable();
            $table->dateTime('fecha_gerencia')->nullable();
            $table->text('observacion_gerencia')->nullable();
            $table->unique('consecutivo');
            $table->index('tipo_solicitud');
            $table->index('elaborado_por');
            $table->index('proceso_compra');
            $table->index('responsable_aprobacion');
            $table->index('sede_id', 'sede_ibfk_1_idx');
            $table->index('proceso_solicitante', 'cp_pedidos_ibfk_6_idx');
        });

        Schema::create('cp_productos', function (Blueprint $table) {
            $table->id('id');
            $table->string('codigo', 255)->nullable();
            $table->string('nombre', 255)->nullable();

            $table->unique('codigo');
        });

        Schema::create('cp_productos_servicios', function (Blueprint $table) {
            $table->id('id');
            $table->string('codigo_producto', 50);
            $table->string('nombre', 255);
        });

        Schema::create('cp_proveedores', function (Blueprint $table) {
            $table->id('id');
            $table->string('nombre', 255)->nullable();
            $table->string('nit', 50)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('correo', 100)->nullable();
            $table->string('direccion', 255)->nullable();
        });

        Schema::create('cp_solicitud_descuento', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('entrega_fijos_id')->nullable(); // FK
            $table->boolean('estado_solicitud')->default(0);
            $table->integer('consecutivo')->nullable();
            $table->date('fecha_solicitud')->nullable();
            $table->unsignedBigInteger('trabajador_id')->nullable(); // FK
            $table->enum('tipo_contrato', ['NOMINA', 'OPS'])->nullable();
            $table->string('firma_trabajador', 255)->nullable();
            $table->string('motivo_solicitud', 255)->nullable();
            $table->integer('valor_total_descontar')->nullable();
            $table->integer('numero_cuotas')->nullable();
            $table->integer('numero_cuotas_aprobadas')->nullable();
            $table->unsignedBigInteger('personal_responsable_aprobacion')->nullable(); // FK
            $table->string('firma_responsable_aprobacion', 255)->nullable();
            $table->unsignedBigInteger('jefe_inmediato_id')->nullable(); // FK
            $table->string('firma_jefe_inmediato', 255)->nullable();
            $table->unsignedBigInteger('personal_facturacion')->nullable(); // FK
            $table->string('firma_facturacion', 255)->nullable();
            $table->unsignedBigInteger('personal_gestion_financiera')->nullable(); // FK
            $table->string('firma_gestion_financiera', 255)->nullable();
            $table->unsignedBigInteger('personal_talento_humano')->nullable(); // FK
            $table->string('firma_talento_humano', 255)->nullable();
            $table->text('observaciones')->nullable();

            $table->index('entrega_fijos_id');
            $table->index('trabajador_id');
            $table->index('personal_responsable_aprobacion');
            $table->index('jefe_inmediato_id');
            $table->index('personal_facturacion');
            $table->index('personal_gestion_financiera');
            $table->index('personal_talento_humano');
        });

        Schema::create('cp_tipo_solicitud', function (Blueprint $table) {
            $table->id('id');
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
        });

        Schema::create('datos_empresa', function (Blueprint $table) {
            $table->id('id');
            $table->string('nombre', 255)->nullable();
            $table->string('nit', 255)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('representante_legal', 255)->nullable();
            $table->string('ciudad', 255)->nullable();
        });

        Schema::create('dependencias_sedes', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('sede_id')->nullable(); // FK
            $table->string('nombre', 255)->nullable();

            $table->index('sede_id');
        });

        Schema::create('inventario', function (Blueprint $table) {
            $table->id('id');
            $table->string('codigo', 50);
            $table->string('codigo2', 50);
            $table->string('activo', 1);
            $table->string('nombre', 100);
            $table->string('dependencia', 100)->nullable();
            $table->string('responsable', 100)->nullable();
            $table->unsignedBigInteger('responsable_id')->nullable(); // FK
            $table->unsignedBigInteger('coordinador_id')->nullable(); // FK
            $table->string('marca', 100)->nullable();
            $table->string('modelo', 100)->nullable();
            $table->string('serial', 100)->nullable();
            $table->unsignedBigInteger('proceso_id')->nullable(); // FK
            $table->unsignedBigInteger('sede_id')->nullable(); // FK
            $table->unsignedBigInteger('creado_por'); // FK
            $table->dateTime('fecha_creacion')->useCurrent();
            $table->string('codigo_barras', 160)->nullable();
            $table->string('num_factu', 60)->nullable();
            $table->string('grupo', 60)->nullable();
            $table->integer('vida_util')->nullable();
            $table->integer('vida_util_niff')->nullable();
            $table->string('centro_costo', 120)->nullable();
            $table->string('ubicacion', 60)->nullable();
            $table->string('proveedor', 60)->nullable();
            $table->date('fecha_compra')->nullable();
            $table->string('soporte', 160)->nullable();
            $table->string('soporte_adjunto', 260)->nullable();
            $table->string('descripcion', 160)->nullable();
            $table->string('estado', 160)->nullable();
            $table->string('escritura', 255)->nullable();
            $table->string('matricula', 10)->nullable();
            $table->double('valor_compra')->nullable();
            $table->string('salvamenta', 255)->nullable();
            $table->double('depreciacion')->nullable();
            $table->double('depreciacion_niif')->nullable();
            $table->string('meses', 7)->nullable();
            $table->string('meses_niif', 8)->nullable();
            $table->string('tipo_adquisicion', 60)->nullable();
            $table->date('calibrado')->nullable();
            $table->text('observaciones')->nullable();
            $table->dateTime('fecha_actualizacion')->nullable();
            $table->double('cuenta_inventario', 10, 2)->nullable();
            $table->double('cuenta_gasto', 10, 2)->nullable();
            $table->double('cuenta_salida', 10, 2)->nullable();
            $table->string('grupo_activos', 60)->nullable();
            $table->double('valor_actual', 10, 2)->nullable();
            $table->double('depreciacion_acumulada', 10, 2)->nullable();
            $table->string('tipo_bien', 60)->nullable();
            $table->string('tiene_accesorio', 10)->nullable();
            $table->text('descripcion_accesorio')->nullable();

            $table->index('sede_id');
            $table->index('creado_por', 'fk_creado_por');
            $table->index('coordinador_id', 'fk_inventario_coordinador');
            $table->index('responsable_id', 'fk_responsable_id_idx');
            $table->index('proceso_id', 'fk_proceso_idx');
        });

        Schema::create('mantenimientos', function (Blueprint $table) {
            $table->id('id');
            $table->string('titulo', 255);
            $table->string('codigo', 100)->nullable();
            $table->string('modelo', 100)->nullable();
            $table->string('dependencia', 255)->nullable();
            $table->unsignedBigInteger('sede_id')->nullable(); // FK
            $table->unsignedBigInteger('nombre_receptor')->nullable(); // FK
            $table->string('imagen', 255)->default('public/mantenimientos/default.jpg');
            $table->text('descripcion')->nullable();
            $table->unsignedBigInteger('revisado_por')->nullable(); // FK
            $table->dateTime('fecha_revisado')->nullable();
            $table->unsignedBigInteger('creado_por'); // FK
            $table->dateTime('fecha_creacion')->useCurrent();
            $table->boolean('esta_revisado')->default(0);
            $table->dateTime('fecha_ultima_actualizacion')->useCurrent();

            $table->index('sede_id');
            $table->index('nombre_receptor');
            $table->index('revisado_por');
            $table->index('creado_por');
        });

        Schema::create('notif_notificaciones', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('id_usuario'); // FK
            $table->string('tipo', 50)->nullable();
            $table->text('mensaje')->nullable();
            $table->dateTime('enviado')->useCurrent();
            $table->boolean('visto')->default(0);

            $table->index('id_usuario');
        });

        Schema::create('p_cargo', function (Blueprint $table) {
            $table->id('id');
            $table->string('nombre', 60)->nullable();
        });

        Schema::create('pc_caracteristicas_tecnicas', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('equipo_id')->nullable(); // FK
            $table->string('procesador', 255)->nullable();
            $table->string('memoria_ram', 255)->nullable();
            $table->string('disco_duro', 255)->nullable();
            $table->string('tarjeta_video', 255)->nullable();
            $table->string('tarjeta_red', 255)->nullable();
            $table->string('tarjeta_sonido', 255)->nullable();
            $table->string('usb', 35)->nullable();
            $table->string('unidad_cd', 35)->nullable();
            $table->string('parlantes', 35)->nullable();
            $table->string('drive', 35)->nullable();
            $table->string('monitor', 255)->nullable();
            $table->unsignedBigInteger('monitor_id')->nullable(); // FK
            $table->string('teclado', 255)->nullable();
            $table->unsignedBigInteger('teclado_id')->nullable(); // FK
            $table->string('mouse', 255)->nullable();
            $table->unsignedBigInteger('mouse_id')->nullable(); // FK
            $table->string('internet', 255)->nullable();
            $table->string('velocidad_red', 255)->nullable();
            $table->string('capacidad_disco', 255)->nullable();

            $table->index('equipo_id');
            $table->index('monitor_id', 'pc_monitor_id_idx');
            $table->index('teclado_id', 'pc_teclado_id_idx');
            $table->index('mouse_id', 'pc_mouse_id_idx');
        });

        Schema::create('pc_config_cronograma', function (Blueprint $table) {
            $table->id('id');
            $table->integer('dias_cumplimiento')->nullable();
            $table->integer('meses_cumplimiento')->nullable();
        });

        Schema::create('pc_cronograma_mantenimientos', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('equipo_id')->nullable(); // FK
            $table->date('fecha_programada')->nullable();
            $table->date('fecha_ejecucion')->nullable();
            $table->enum('estado_cumplimiento', ['pendiente', 'no_aplica', 'realizado'])->nullable();
            $table->date('fecha_ultimo_mantenimiento')->nullable();

            $table->index('equipo_id');
        });

        Schema::create('pc_devuelto', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('entrega_id'); // FK
            $table->dateTime('fecha_devolucion')->useCurrent();
            $table->text('firma_entrega');
            $table->text('firma_recibe');
            $table->text('observaciones')->nullable();

            $table->unique('entrega_id');
        });

        Schema::create('pc_entregas', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('equipo_id')->nullable(); // FK
            $table->unsignedBigInteger('funcionario_id')->nullable(); // FK
            $table->date('fecha_entrega')->nullable();
            $table->text('firma_entrega')->nullable();
            $table->text('firma_recibe')->nullable();
            $table->date('devuelto')->nullable();
            $table->enum('estado', ['entregado', 'devuelto'])->default('entregado');

            $table->index('funcionario_id');
            $table->index('equipo_id');
        });

        Schema::create('pc_equipos', function (Blueprint $table) {
            $table->id('id');
            $table->string('nombre_equipo', 255)->nullable();
            $table->string('marca', 255)->nullable();
            $table->string('modelo', 255)->nullable();
            $table->string('serial', 255);
            $table->string('tipo', 255)->nullable();
            $table->enum('propiedad', ['empleado', 'empresa'])->nullable();
            $table->string('ip_fija', 255)->nullable();
            $table->string('numero_inventario', 255)->nullable();
            $table->unsignedBigInteger('sede_id')->nullable(); // FK
            $table->unsignedBigInteger('area_id')->nullable(); // FK
            $table->unsignedBigInteger('responsable_id')->nullable(); // FK
            $table->string('estado', 255)->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->text('imagen_url')->nullable();
            $table->date('fecha_entrega')->nullable();
            $table->text('descripcion_general')->nullable();
            $table->integer('garantia_meses')->nullable();
            $table->enum('forma_adquisicion', ['compra', 'alquiler', 'donacion', 'comodato'])->nullable();
            $table->text('observaciones')->nullable();
            $table->text('repuestos_principales')->nullable();
            $table->text('recomendaciones')->nullable();
            $table->text('equipos_adicionales')->nullable();
            $table->unsignedBigInteger('creado_por')->default(1); // FK
            $table->dateTime('fecha_creacion')->useCurrent();

            $table->unique('serial', 'serial_UNIQUE');
            $table->unique('numero_inventario', 'numero_inventario_UNIQUE');
            $table->index('responsable_id');
            $table->index('sede_id');
            $table->index('area_id');
            $table->index('creado_por', 'fk_pc_equipos_creado_por');
        });

        Schema::create('pc_historial_asignaciones', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('equipo_id')->nullable(); // FK
            $table->unsignedBigInteger('personal_id')->nullable(); // FK
            $table->date('fecha_asignacion')->nullable();
            $table->date('fecha_devolucion')->nullable();
            $table->text('observaciones')->nullable();

            $table->index('personal_id');
            $table->index('equipo_id');
        });

        Schema::create('pc_licencias_software', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('equipo_id')->nullable(); // FK
            $table->string('windows', 10)->nullable();
            $table->string('office', 10)->nullable();
            $table->string('nitro', 10)->nullable();

            $table->index('equipo_id');
        });

        Schema::create('pc_mantenimientos', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('equipo_id')->nullable(); // FK
            $table->enum('tipo_mantenimiento', ['preventivo', 'correctivo'])->nullable();
            $table->text('descripcion')->nullable();
            $table->date('fecha')->nullable();
            $table->unsignedBigInteger('empresa_responsable_id')->nullable(); // FK
            $table->boolean('repuesto')->nullable();
            $table->integer('cantidad_repuesto')->nullable();
            $table->decimal('costo_repuesto', 10, 0)->nullable();
            $table->string('nombre_repuesto', 255)->nullable();
            $table->string('responsable_mantenimiento', 255)->nullable();
            $table->text('firma_personal_cargo')->nullable();
            $table->text('firma_sistemas')->nullable();
            $table->unsignedBigInteger('creado_por')->nullable(); // FK
            $table->dateTime('fecha_creacion')->useCurrent();
            $table->enum('estado', ['completado', 'pendiente'])->default('pendiente');

            $table->index('empresa_responsable_id');
            $table->index('equipo_id');
            $table->index('creado_por', 'fk_pc_mantenimientos_creado_por');
        });

        Schema::create('pc_perifericos_entregados', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('entrega_id')->nullable(); // FK
            $table->unsignedBigInteger('inventario_id')->nullable(); // FK
            $table->integer('cantidad')->nullable();
            $table->text('observaciones')->nullable();

            $table->index('entrega_id');
            $table->index('inventario_id', 'pc_perifericos_entregados_ibfk_2_idx');
        });

        Schema::create('permisos', function (Blueprint $table) {
            $table->id('id');
            $table->string('nombre', 50)->nullable();
            $table->text('descripcion')->nullable();

            $table->unique('nombre', 'unique_nombre');
        });

        Schema::create('personal', function (Blueprint $table) {
            $table->id('id');
            $table->string('nombre', 255);
            $table->string('cedula', 255)->nullable();
            $table->string('telefono', 255)->nullable();
            $table->unsignedBigInteger('cargo_id'); // FK

            $table->unique('cedula', 'cedula_UNIQUE');
            $table->index('cargo_id');
        });

        Schema::create('rf_reportes_comentarios', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('reporte_id'); // FK
            $table->unsignedBigInteger('usuario_id'); // FK
            $table->text('comentario');
            $table->dateTime('fecha')->useCurrent();

            $table->index('reporte_id');
            $table->index('usuario_id');
        });

        Schema::create('rf_reportes_fallas', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('usuario_id'); // FK
            $table->string('titulo', 150);
            $table->text('descripcion');
            $table->enum('estado', ['pendiente', 'en proceso', 'resuelto', 'rechazado'])->default('pendiente');
            $table->enum('prioridad', ['baja', 'media', 'alta'])->default('media');
            $table->dateTime('fecha_reporte')->useCurrent();
            $table->dateTime('fecha_actualizacion')->nullable();
            $table->unsignedBigInteger('responsable_id')->nullable(); // FK

            $table->index('usuario_id');
            $table->index('responsable_id');
        });

        Schema::create('rol', function (Blueprint $table) {
            $table->id('id');
            $table->string('nombre', 60)->nullable();
        });

        Schema::create('rol_permisos', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('rol_id')->nullable(); // FK
            $table->unsignedBigInteger('permiso_id')->nullable(); // FK

            $table->index('rol_id', 'usuario_id');
            $table->index('permiso_id');
            $table->index('rol_id');
        });

        Schema::create('sec_auditoria', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('id_usuario')->nullable(); // FK
            $table->string('accion', 100);
            $table->text('detalle')->nullable();
            $table->string('ip', 45)->nullable();
            $table->dateTime('fecha')->useCurrent();

            $table->index('id_usuario');
        });

        Schema::create('sec_codigo_verificacion', function (Blueprint $table) {
            $table->id('id');
            $table->string('codigo', 255);
            $table->unsignedBigInteger('id_usuario'); // FK
            $table->dateTime('creado')->useCurrent();
            $table->dateTime('fecha_activacion')->nullable();
            $table->dateTime('fecha_expiracion')->nullable();
            $table->tinyInteger('consumido')->nullable();

            $table->index('id_usuario');
        });

        Schema::create('sec_contrasenas_anteriores', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('id_usuario'); // FK
            $table->string('contrasena', 255);
            $table->dateTime('fecha_guardada')->useCurrent();

            $table->index('id_usuario');
        });

        Schema::create('sec_intentos_login', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('id_usuario')->nullable(); // FK
            $table->string('usuario_ingresado', 50)->nullable();
            $table->string('ip', 45);
            $table->boolean('exito')->default(0);
            $table->dateTime('fecha')->useCurrent();

            $table->index('id_usuario');
        });

        Schema::create('sec_ip_bloqueadas', function (Blueprint $table) {
            $table->id('id');
            $table->string('ip', 45);
            $table->dateTime('fecha_bloqueo');
            $table->dateTime('fecha_expiracion');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('sedes', function (Blueprint $table) {
            $table->id('id');
            $table->string('nombre', 100);
        });

        Schema::create('usuarios', function (Blueprint $table) {
            $table->id('id');
            $table->string('nombre_completo', 100)->nullable();
            $table->string('usuario', 50)->nullable();
            $table->string('contrasena', 255)->nullable();
            $table->unsignedBigInteger('rol_id')->nullable();
            $table->string('correo', 60)->nullable();
            $table->string('telefono', 15)->nullable();
            $table->boolean('estado')->default(0);
            $table->unsignedBigInteger('sede_id')->nullable();
            $table->string('firma_digital', 260)->nullable();

            $table->unique('usuario');
            $table->index('rol_id', 'fk_rol');
            $table->index('sede_id', 'fk_usuarios_sede');
        });


        // ==========================================
        // ADD FOREIGN KEYS
        // ==========================================

        Schema::table('actividades', function (Blueprint $table) {
            $table->foreign('usuario_id', 'actividades_ibfk_1')->references('id')->on('usuarios')->onDelete('cascade');
        });

        Schema::table('agenda_mantenimientos', function (Blueprint $table) {
            $table->foreign('mantenimiento_id', 'agenda_mantenimientos_ibfk_1')->references('id')->on('mantenimientos');
            $table->foreign('sede_id', 'agenda_mantenimientos_ibfk_2')->references('id')->on('sedes');
        });

        Schema::table('areas', function (Blueprint $table) {
            $table->foreign('sede_id', 'areas_ibfk_1')->references('id')->on('sedes');
        });

        Schema::table('cp_cotizaciones_proveedores', function (Blueprint $table) {
            $table->foreign('item_pedido_id', 'cp_cotizaciones_proveedores_ibfk_1')->references('id')->on('cp_items_pedidos');
            $table->foreign('proveedor_id', 'cp_cotizaciones_proveedores_ibfk_2')->references('id')->on('cp_proveedores');
        });

        Schema::table('cp_entrega_activos_fijos', function (Blueprint $table) {
            $table->foreign('proceso_solicitante', 'cp_entrega_activos_fijos')->references('id')->on('dependencias_sedes');
            $table->foreign('personal_id', 'cp_entrega_activos_fijos_ibfk_1')->references('id')->on('personal');
            $table->foreign('sede_id', 'cp_entrega_activos_fijos_ibfk_2')->references('id')->on('sedes');
        });

        Schema::table('cp_entrega_activos_fijos_items', function (Blueprint $table) {
            $table->foreign('item_id', 'cp_entrega_activos_fijos_items_ibfk_1')->references('id')->on('inventario');
            $table->foreign('entrega_activos_id', 'cp_entrega_activos_fijos_items_ibfk_2')->references('id')->on('cp_entrega_activos_fijos');
        });

        Schema::table('cp_entrega_solicitud', function (Blueprint $table) {
            // consecutivo is INT so consecutivo_id (FK) must be INT. This is correct as is.
            $table->foreign('consecutivo_id', 'cp_entrega_solicitud_ibfk_1')->references('consecutivo')->on('cp_pedidos');
        });

        Schema::table('cp_items_pedidos', function (Blueprint $table) {
            $table->foreign('cp_pedido', 'cp_items_pedidos_ibfk_1')->references('id')->on('cp_pedidos');
            $table->foreign('productos_id', 'fk_items_productos')->references('id')->on('cp_productos');
        });

        Schema::table('cp_pedidos', function (Blueprint $table) {
            $table->foreign('tipo_solicitud', 'cp_pedidos_ibfk_1')->references('id')->on('cp_tipo_solicitud');
            $table->foreign('elaborado_por', 'cp_pedidos_ibfk_2')->references('id')->on('usuarios');
            $table->foreign('proceso_compra', 'cp_pedidos_ibfk_3')->references('id')->on('usuarios');
            $table->foreign('responsable_aprobacion', 'cp_pedidos_ibfk_4')->references('id')->on('usuarios');
            $table->foreign('sede_id', 'cp_pedidos_ibfk_5')->references('id')->on('sedes')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('proceso_solicitante', 'cp_pedidos_ibfk_6')->references('id')->on('dependencias_sedes');
        });

        Schema::table('cp_solicitud_descuento', function (Blueprint $table) {
            $table->foreign('entrega_fijos_id', 'cp_solicitud_descuento_ibfk_1')->references('id')->on('cp_entrega_activos_fijos');
            $table->foreign('trabajador_id', 'cp_solicitud_descuento_ibfk_2')->references('id')->on('personal');
            $table->foreign('personal_responsable_aprobacion', 'cp_solicitud_descuento_ibfk_3')->references('id')->on('personal');
            $table->foreign('jefe_inmediato_id', 'cp_solicitud_descuento_ibfk_4')->references('id')->on('personal');
            $table->foreign('personal_facturacion', 'cp_solicitud_descuento_ibfk_5')->references('id')->on('personal');
            $table->foreign('personal_gestion_financiera', 'cp_solicitud_descuento_ibfk_6')->references('id')->on('personal');
            $table->foreign('personal_talento_humano', 'cp_solicitud_descuento_ibfk_7')->references('id')->on('personal');
        });

        Schema::table('dependencias_sedes', function (Blueprint $table) {
            $table->foreign('sede_id', 'dependencias_sedes_ibfk_1')->references('id')->on('sedes');
        });

        Schema::table('inventario', function (Blueprint $table) {
            $table->foreign('creado_por', 'fk_creado_por')->references('id')->on('usuarios');
            $table->foreign('coordinador_id', 'fk_inventario_coordinador')->references('id')->on('personal');
            $table->foreign('proceso_id', 'fk_proceso')->references('id')->on('dependencias_sedes');
            $table->foreign('responsable_id', 'fk_responsable_id')->references('id')->on('personal');
            $table->foreign('sede_id', 'inventario_ibfk_1')->references('id')->on('sedes');
        });

        Schema::table('mantenimientos', function (Blueprint $table) {
            $table->foreign('sede_id', 'mantenimientos_ibfk_1')->references('id')->on('sedes');
            $table->foreign('nombre_receptor', 'mantenimientos_ibfk_2')->references('id')->on('usuarios');
            $table->foreign('revisado_por', 'mantenimientos_ibfk_3')->references('id')->on('usuarios');
            $table->foreign('creado_por', 'mantenimientos_ibfk_4')->references('id')->on('usuarios');
        });

        Schema::table('notif_notificaciones', function (Blueprint $table) {
            $table->foreign('id_usuario', 'notif_notificaciones_ibfk_1')->references('id')->on('usuarios');
        });

        Schema::table('pc_caracteristicas_tecnicas', function (Blueprint $table) {
            $table->foreign('equipo_id', 'pc_caracteristicas_tecnicas_ibfk_1')->references('id')->on('pc_equipos');
            $table->foreign('monitor_id', 'pc_monitor_id')->references('id')->on('inventario');
            $table->foreign('mouse_id', 'pc_mouse_id')->references('id')->on('inventario');
            $table->foreign('teclado_id', 'pc_teclado_id')->references('id')->on('inventario');
        });

        Schema::table('pc_cronograma_mantenimientos', function (Blueprint $table) {
            $table->foreign('equipo_id', 'pc_cronograma_mantenimientos_ibfk_1')->references('id')->on('pc_equipos');
        });

        Schema::table('pc_devuelto', function (Blueprint $table) {
            $table->foreign('entrega_id', 'fk_pc_devuelto_entrega')->references('id')->on('pc_entregas')->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::table('pc_entregas', function (Blueprint $table) {
            $table->foreign('funcionario_id', 'pc_entregas_ibfk_1')->references('id')->on('personal');
            $table->foreign('equipo_id', 'pc_entregas_ibfk_3')->references('id')->on('pc_equipos');
        });

        Schema::table('pc_equipos', function (Blueprint $table) {
            $table->foreign('creado_por', 'fk_pc_equipos_creado_por')->references('id')->on('usuarios');
            $table->foreign('sede_id', 'pc_equipos_ibfk_2')->references('id')->on('sedes');
            $table->foreign('responsable_id', 'pc_equipos_ibfk_3')->references('id')->on('personal');
            $table->foreign('area_id', 'pc_equipos_ibfk_6')->references('id')->on('areas');
        });

        Schema::table('pc_historial_asignaciones', function (Blueprint $table) {
            $table->foreign('personal_id', 'pc_historial_asignaciones_ibfk_1')->references('id')->on('personal');
            $table->foreign('equipo_id', 'pc_historial_asignaciones_ibfk_2')->references('id')->on('pc_equipos');
        });

        Schema::table('pc_licencias_software', function (Blueprint $table) {
            $table->foreign('equipo_id', 'pc_licencias_software_ibfk_1')->references('id')->on('pc_equipos');
        });

        Schema::table('pc_mantenimientos', function (Blueprint $table) {
            $table->foreign('creado_por', 'fk_pc_mantenimientos_creado_por')->references('id')->on('usuarios');
            $table->foreign('empresa_responsable_id', 'pc_mantenimientos_ibfk_1')->references('id')->on('datos_empresa');
            $table->foreign('equipo_id', 'pc_mantenimientos_ibfk_2')->references('id')->on('pc_equipos');
        });

        Schema::table('pc_perifericos_entregados', function (Blueprint $table) {
            $table->foreign('entrega_id', 'pc_perifericos_entregados_ibfk_1')->references('id')->on('pc_entregas');
            $table->foreign('inventario_id', 'pc_perifericos_entregados_ibfk_2')->references('id')->on('inventario');
        });

        Schema::table('personal', function (Blueprint $table) {
            $table->foreign('cargo_id', 'personal_ibfk_1')->references('id')->on('p_cargo')->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::table('rf_reportes_comentarios', function (Blueprint $table) {
            $table->foreign('reporte_id', 'rf_reportes_comentarios_ibfk_1')->references('id')->on('rf_reportes_fallas');
            $table->foreign('usuario_id', 'rf_reportes_comentarios_ibfk_2')->references('id')->on('usuarios');
        });

        Schema::table('rf_reportes_fallas', function (Blueprint $table) {
            $table->foreign('usuario_id', 'rf_reportes_fallas_ibfk_1')->references('id')->on('usuarios');
            $table->foreign('responsable_id', 'rf_reportes_fallas_ibfk_2')->references('id')->on('usuarios');
        });

        Schema::table('rol_permisos', function (Blueprint $table) {
            $table->foreign('permiso_id', 'rol_permisos_ibfk_2')->references('id')->on('permisos');
            $table->foreign('rol_id', 'rol_permisos_ibfk_3')->references('id')->on('rol')->onDelete('cascade');
        });

        Schema::table('sec_auditoria', function (Blueprint $table) {
            $table->foreign('id_usuario', 'sec_auditoria_ibfk_1')->references('id')->on('usuarios');
        });

        Schema::table('sec_codigo_verificacion', function (Blueprint $table) {
            $table->foreign('id_usuario', 'sec_codigo_verificacion_ibfk_1')->references('id')->on('usuarios');
        });

        Schema::table('sec_contrasenas_anteriores', function (Blueprint $table) {
            $table->foreign('id_usuario', 'sec_contrasenas_anteriores_ibfk_1')->references('id')->on('usuarios');
        });

        Schema::table('sec_intentos_login', function (Blueprint $table) {
            $table->foreign('id_usuario', 'sec_intentos_login_ibfk_1')->references('id')->on('usuarios');
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->foreign('rol_id', 'fk_rol')->references('id')->on('rol');
            $table->foreign('sede_id', 'fk_usuarios_sede')->references('id')->on('sedes');
        });

        // Re-enable key checks
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            'actividades',
            'actualizaciones_web',
            'agenda_mantenimientos',
            'areas',
            'cp_centro_costo',
            'cp_cotizaciones_proveedores',
            'cp_dependencias',
            'cp_entrega_activos_fijos',
            'cp_entrega_activos_fijos_items',
            'cp_entrega_solicitud',
            'cp_items_pedidos',
            'cp_pedidos',
            'cp_productos',
            'cp_productos_servicios',
            'cp_proveedores',
            'cp_solicitud_descuento',
            'cp_tipo_solicitud',
            'datos_empresa',
            'dependencias_sedes',
            'inventario',
            'mantenimientos',
            'notif_notificaciones',
            'p_cargo',
            'pc_caracteristicas_tecnicas',
            'pc_config_cronograma',
            'pc_cronograma_mantenimientos',
            'pc_devuelto',
            'pc_entregas',
            'pc_equipos',
            'pc_historial_asignaciones',
            'pc_licencias_software',
            'pc_mantenimientos',
            'pc_perifericos_entregados',
            'permisos',
            'personal',
            'rf_reportes_comentarios',
            'rf_reportes_fallas',
            'rol',
            'rol_permisos',
            'sec_auditoria',
            'sec_codigo_verificacion',
            'sec_contrasenas_anteriores',
            'sec_intentos_login',
            'sec_ip_bloqueadas',
            'sedes',
            'usuarios',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }
};
