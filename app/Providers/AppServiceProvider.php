<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface::class,
            \App\Modules\Shared\Infrastructure\Adapters\MicroserviceExcelToPdfConverter::class
        );

        $this->app->bind(
            \App\Modules\GestionCompras\Domain\Contracts\CpPedidoProgramadoRepositoryInterface::class,
            \App\Modules\GestionCompras\Infrastructure\Repositories\CpPedidoProgramadoRepository::class
        );

        $this->app->bind(
            \App\Modules\GestionSistemas\Domain\Repositories\ImagenMensualRepositoryInterface::class,
            \App\Modules\GestionSistemas\Infrastructure\Repositories\ImagenMensualRepository::class
        );

        $this->app->bind(
            \App\Modules\Gateway\Domain\Contracts\GatewayRepositoryInterface::class,
            \App\Modules\Gateway\Infrastructure\Repositories\KubappGatewayRepository::class
        );

        $this->app->bind(
            \App\Modules\Auditoria\Domain\Contracts\ActividadRepositoryInterface::class,
            \App\Modules\Auditoria\Infrastructure\Repositories\ActividadRepository::class
        );

        $this->app->bind(
            \App\Modules\Auditoria\Domain\Contracts\PedidoProgramadoAuditoriaRepositoryInterface::class,
            \App\Modules\Auditoria\Infrastructure\Repositories\PedidoProgramadoAuditoriaRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
