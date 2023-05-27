<?php

/*
 * Hospital Metropolitano
 *
 */

namespace app\models\lisa;

use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\Traits\DBModel;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Filtrar
 */
class Filtrar extends Models implements IModels
{

    use DBModel;

    # Variables de clase
    public $dataPedido = null;
    public $documentoPedido = null;
    public $pedido = null;
    public $codigoPedido = null;
    public $nomeMaquina = null;
    public $usuarioSolicitante = null;
    public $fechaPedido = null;
    public $strSolicitante = null;
    public $fechaTomaMuestra = null;
    public $tipoProceso = 0;
    public $sectores = [];
    public $statusFiltro = 0;
    public $operacao = null;

    # Inbound Interfaz de PEDIDOS
    public function filtrar()
    {

        # Filtrar

        $this->operacao = $this->pedido->Mensagem->PedidoExameLab->operacao;

        if ($this->operacao == 'I') {
            $this->getFiltroDia();
        } else {
            $this->tipoProceso = 1;
        }

        $this->statusFiltro = 1;

    }

    public function getFiltroDia()
    {

        # Filtrar

        $this->fechaTomaMuestra = $this->pedido->Mensagem->PedidoExameLab->atendimento->dataColetaPedido;
        $this->fechaPedido = date("Y-m-d", strtotime($this->fechaTomaMuestra));

        if ($this->fechaPedido !== date("Y-m-d")) {
            $this->tipoProceso = 2;
        } else {
            $this->tipoProceso = 1;
        }

    }

/**
 * __construct()
 */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
        $this->startDBConexion();

    }
}
