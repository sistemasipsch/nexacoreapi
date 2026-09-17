<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Usuario;
use App\Models\Rol;
use App\Models\Permiso;
use App\Models\CpPedido;
use App\Models\CpItemPedido;
use App\Models\CpPedidoProgramado;
use App\Services\PermissionService;
use App\Modules\GestionCompras\Domain\Services\ValidarHorarioPedidoService;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\AsignarHorarioHabilTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class DummyHorarioHabil
{
    use AsignarHorarioHabilTrait;

    public function calcular(string $fecha): string
    {
        return $this->calcularFechaYHoraHabil($fecha);
    }
}

class PedidosPrioritariosYProgramadosTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset time mock
        parent::tearDown();
    }

    /**
     * Valida que roles de coordinadores, administradores y gerentes sean reconocidos como coordinadores.
     */
    public function test_coordinadores_detectados_correctamente(): void
    {
        $coordinador = new Usuario();
        $rolCoord = new Rol(['nombre' => 'COORDINADOR DE COMPRAS']);
        $coordinador->setRelation('rol', $rolCoord);
        $this->assertTrue($coordinador->isCoordinador());

        $coordinadora = new Usuario();
        $rolCoordFem = new Rol(['nombre' => 'Coordinadora Administrativa']);
        $coordinadora->setRelation('rol', $rolCoordFem);
        $this->assertTrue($coordinadora->isCoordinador());

        $admin = new Usuario();
        $rolAdmin = new Rol(['nombre' => 'ADMINISTRADOR']);
        $admin->setRelation('rol', $rolAdmin);
        $this->assertTrue($admin->isCoordinador());

        $gerente = new Usuario();
        $rolGerente = new Rol(['nombre' => 'GERENTE GENERAL']);
        $gerente->setRelation('rol', $rolGerente);
        $this->assertTrue($gerente->isCoordinador());

        $auxiliar = new Usuario();
        $rolAux = new Rol(['nombre' => 'AUXILIAR DE COMPRAS']);
        $auxiliar->setRelation('rol', $rolAux);
        $this->assertFalse($auxiliar->isCoordinador());
    }

    /**
     * Valida canCreatePriorityOrder para coordinadores y usuarios con permiso explícito.
     */
    public function test_can_create_priority_order(): void
    {
        $service = app(PermissionService::class);

        // Caso 1: Coordinador (sin permiso explícito necesario)
        $coordUser = new Usuario();
        $rolCoord = new Rol(['nombre' => 'COORDINADOR']);
        $rolCoord->setRelation('permisos', collect([]));
        $coordUser->setRelation('rol', $rolCoord);
        $this->assertTrue($service->canCreatePriorityOrder($coordUser));

        // Caso 2: Auxiliar con el permiso cp_pedido.realizar_pedido_prioritario
        $permisoPrioritario = new Permiso(['nombre' => 'cp_pedido.realizar_pedido_prioritario']);
        $rolConPermiso = new Rol(['nombre' => 'AUXILIAR ESPECIAL']);
        $rolConPermiso->setRelation('permisos', collect([$permisoPrioritario]));
        $userConPermiso = new Usuario();
        $userConPermiso->setRelation('rol', $rolConPermiso);
        $this->assertTrue($service->canCreatePriorityOrder($userConPermiso));

        // Caso 3: Auxiliar sin permiso
        $rolSinPermiso = new Rol(['nombre' => 'AUXILIAR REGULAR']);
        $rolSinPermiso->setRelation('permisos', collect([]));
        $userSinPermiso = new Usuario();
        $userSinPermiso->setRelation('rol', $rolSinPermiso);
        $this->assertFalse($service->canCreatePriorityOrder($userSinPermiso));
    }

    /**
     * Valida que ValidarHorarioPedidoService restrinja pedidos normales fuera de horario hábil
     * y los permita en horario hábil.
     */
    public function test_validacion_horario_pedidos_normales(): void
    {
        $service = new ValidarHorarioPedidoService();

        // Miércoles a las 8:00 AM (dentro de 7:30 - 8:30) -> Debe permitir
        Carbon::setTestNow(Carbon::parse('2026-09-16 08:00:00', 'America/Bogota'));
        try {
            $service->validar();
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("No debería fallar dentro de horario hábil: " . $e->getMessage());
        }

        // Miércoles a las 2:30 PM (dentro de 14:00 - 15:00) -> Debe permitir
        Carbon::setTestNow(Carbon::parse('2026-09-16 14:30:00', 'America/Bogota'));
        try {
            $service->validar();
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("No debería fallar dentro de horario de la tarde: " . $e->getMessage());
        }

        // Miércoles a las 11:00 AM (fuera de horario hábil) -> Debe lanzar excepción
        Carbon::setTestNow(Carbon::parse('2026-09-16 11:00:00', 'America/Bogota'));
        $exThrown = false;
        try {
            $service->validar();
        } catch (\Exception $e) {
            $exThrown = true;
            $this->assertStringContainsString('horario hábil', $e->getMessage());
        }
        $this->assertTrue($exThrown, "Debe rechazar pedidos normales fuera de horario hábil");

        // Domingo a las 8:00 AM (cerrado) -> Debe lanzar excepción
        Carbon::setTestNow(Carbon::parse('2026-09-20 08:00:00', 'America/Bogota'));
        $exThrownSunday = false;
        try {
            $service->validar();
        } catch (\Exception $e) {
            $exThrownSunday = true;
        }
        $this->assertTrue($exThrownSunday, "Debe rechazar pedidos normales los domingos");
    }

    /**
     * Valida que los pedidos programados en la mañana se programen para la tarde del mismo día (14:00:00).
     */
    public function test_asignar_horario_habil_manana_a_tarde_mismo_dia(): void
    {
        $dummy = new DummyHorarioHabil();

        // 1. Programado un Miércoles a las 7:00 AM (antes de la ventana de la mañana)
        Carbon::setTestNow(Carbon::parse('2026-09-16 07:00:00', 'America/Bogota'));
        $resultado = $dummy->calcular('2026-09-16');
        $this->assertEquals('2026-09-16 14:00:00', $resultado, 'Pedido a las 7:00 AM debe programarse para las 2:00 PM del mismo día');

        // 2. Programado un Miércoles a las 8:00 AM (durante la ventana de la mañana)
        Carbon::setTestNow(Carbon::parse('2026-09-16 08:00:00', 'America/Bogota'));
        $resultado2 = $dummy->calcular('2026-09-16');
        $this->assertEquals('2026-09-16 14:00:00', $resultado2, 'Pedido a las 8:00 AM debe programarse para las 2:00 PM del mismo día');

        // 3. Programado un Miércoles a las 10:30 AM (entre ventanas)
        Carbon::setTestNow(Carbon::parse('2026-09-16 10:30:00', 'America/Bogota'));
        $resultado3 = $dummy->calcular('2026-09-16');
        $this->assertEquals('2026-09-16 14:00:00', $resultado3, 'Pedido a las 10:30 AM debe programarse para las 2:00 PM del mismo día');

        // 4. Programado un Miércoles a las 13:59:59 (justo antes de las 14:00)
        Carbon::setTestNow(Carbon::parse('2026-09-16 13:59:59', 'America/Bogota'));
        $resultado4 = $dummy->calcular('2026-09-16');
        $this->assertEquals('2026-09-16 14:00:00', $resultado4, 'Pedido a las 13:59 debe programarse para las 2:00 PM del mismo día');

        // 5. Programado un Miércoles a las 16:00:00 (después de la tarde) -> Debe ir al Jueves 7:30 AM
        Carbon::setTestNow(Carbon::parse('2026-09-16 16:00:00', 'America/Bogota'));
        $resultado5 = $dummy->calcular('2026-09-16');
        $this->assertEquals('2026-09-17 07:30:00', $resultado5, 'Pedido en la tarde después de las 15:00 debe programarse para el día siguiente a las 7:30 AM');

        // 6. Programado un Viernes a las 16:00:00 -> Debe ir al Sábado 8:00 AM
        Carbon::setTestNow(Carbon::parse('2026-09-18 16:00:00', 'America/Bogota'));
        $resultado6 = $dummy->calcular('2026-09-18');
        $this->assertEquals('2026-09-19 08:00:00', $resultado6, 'Pedido el viernes en la tarde debe programarse para el sábado a las 8:00 AM');

        // 7. Programado un Sábado a las 10:00:00 -> Debe ir al Lunes 7:30 AM (saltando domingo)
        Carbon::setTestNow(Carbon::parse('2026-09-19 10:00:00', 'America/Bogota'));
        $resultado7 = $dummy->calcular('2026-09-19');
        $this->assertEquals('2026-09-21 07:30:00', $resultado7, 'Pedido el sábado tras su horario debe programarse para el lunes a las 7:30 AM');
    }

    /**
     * Prueba de ejecución del comando artisan pedidos:procesar-programados cuando no hay pendientes.
     */
    public function test_comando_cron_procesar_pedidos_programados_sin_pendientes(): void
    {
        $mockRepo = \Mockery::mock(\App\Modules\GestionCompras\Domain\Contracts\CpPedidoProgramadoRepositoryInterface::class);
        $mockRepo->shouldReceive('obtenerProgramadosPendientes')
            ->once()
            ->andReturn([]);
        $this->app->instance(\App\Modules\GestionCompras\Domain\Contracts\CpPedidoProgramadoRepositoryInterface::class, $mockRepo);

        $exitCode = Artisan::call('pedidos:procesar-programados');
        $this->assertEquals(0, $exitCode);
    }
}
