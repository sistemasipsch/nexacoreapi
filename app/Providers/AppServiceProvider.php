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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
