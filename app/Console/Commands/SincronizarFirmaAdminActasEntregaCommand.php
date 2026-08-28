<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\GestionSistemas\Application\UseCases\ActasEntrega\SincronizarFirmaAdminActasEntregaUseCase;

class SincronizarFirmaAdminActasEntregaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'actas:sincronizar-firma-emisor {--user_id= : ID opcional del usuario administrador}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revisa todas las actas de entrega de PC y asigna la firma del perfil admin a las que no les carga la firma de entrega, conservando intactas las que ya la tienen.';

    /**
     * Execute the console command.
     */
    public function handle(SincronizarFirmaAdminActasEntregaUseCase $useCase)
    {
        $this->info('Iniciando revisión y sincronización de firmas de emisor en actas de entrega...');

        $userId = $this->option('user_id') ? (int) $this->option('user_id') : null;

        try {
            $resultado = $useCase->execute($userId);

            $this->newLine();
            $this->info("==================================================");
            $this->info("  RESUMEN DE SINCRONIZACIÓN DE FIRMAS DE ENTREGA ");
            $this->info("==================================================");
            $this->line("👤 Usuario Admin Utilizado: <comment>{$resultado['admin_usuario']['nombre']} (@{$resultado['admin_usuario']['usuario']})</comment>");
            $this->line("🖊️  Ruta Firma Admin:       <comment>{$resultado['admin_usuario']['firma_asignada']}</comment>");
            $this->line("📋 Total Actas Evaluadas:   <info>{$resultado['total_actas']}</info>");
            $this->line("✅ Actas Intactas (Válidas):<info>{$resultado['actas_intactas']}</info>");
            $this->line("🔄 Actas Actualizadas:      <comment>{$resultado['actas_actualizadas']}</comment>");
            $this->info("==================================================");

            if (!empty($resultado['detalles_actualizadas'])) {
                $this->newLine();
                $this->warn("Actas a las que se les asignó la firma del perfil admin:");
                $tableData = [];
                foreach ($resultado['detalles_actualizadas'] as $det) {
                    $tableData[] = [
                        'Acta #' => $det['acta_id'],
                        'Equipo ID' => $det['equipo_id'],
                        'Funcionario ID' => $det['funcionario_id'],
                        'Motivo' => $det['motivo']
                    ];
                }
                $this->table(['Acta #', 'Equipo ID', 'Funcionario ID', 'Motivo'], $tableData);
            }

            $this->newLine();
            $this->info('¡Proceso completado exitosamente!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error durante la sincronización: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
