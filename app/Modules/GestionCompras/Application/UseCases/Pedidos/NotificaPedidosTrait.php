<?php

namespace App\Modules\GestionCompras\Application\UseCases\Pedidos;

use App\Models\CpPedido;

trait NotificaPedidosTrait
{
    /**
     * Notificación desactivada: Los pedidos no deben enviar correos.
     */
    protected function sendGerenciaApprovedNotification(CpPedido $pedido)
    {
        return;
    }

    /**
     * Notificación desactivada: Los pedidos no deben enviar correos.
     */
    protected function sendGerenciaRejectedNotification(CpPedido $pedido, $motivo)
    {
        return;
    }

    /**
     * Notificación desactivada: Los pedidos no deben enviar correos.
     */
    protected function sendOrderApprovedNotification(CpPedido $pedido)
    {
        return;
    }

    /**
     * Notificación desactivada: Los pedidos no deben enviar correos.
     */
    protected function sendOrderRejectedNotification(CpPedido $pedido, $motivo)
    {
        return;
    }

    /**
     * Notificación desactivada: Los pedidos no deben enviar correos.
     */
    protected function sendNewOrderNotification(CpPedido $pedido)
    {
        return;
    }
}
