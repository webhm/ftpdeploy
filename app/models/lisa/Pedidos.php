<?php

/*
 * Hospital Metropolitano
 *
 */

namespace app\models\lisa;

use app\models\lisa as LisaModel;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Pedidos
 */
class Pedidos extends Models implements IModels
{
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
    public $tipoProceso = null;
    public $statusProcesado = 0;
    public $timestampLog = null;
    public $operacao = null;
    public $dirEnviados = '../../beta/v1/lisa/pedidos/enviados/';
    public $dirIngresados = '../../beta/v1/lisa/pedidos/ingresados/';
    public $dirRetenidos = '../../beta/v1/lisa/pedidos/retenidos/';
    public $dirErrores = '../../beta/v1//lisa/pedidos/errores/';

    # Inbound Interfaz de PEDIDOS
    public function in_nuevoPedido()
    {

        try {

            global $config;

            # Inicio
            $this->timestampLog = time();
            $this->pedido = $this->dataPedido->children('soap', true)->Body->children();
            $this->codigoPedido = $this->pedido->Mensagem->PedidoExameLab->codigoPedido;
            $this->nomeMaquina = $this->pedido->Mensagem->PedidoExameLab->atendimento->nomeMaquina;
            $this->usuarioSolicitante = $this->pedido->Mensagem->PedidoExameLab->atendimento->usuarioSolicitante;
            $this->strSolicitante = $this->pedido->Mensagem->PedidoExameLab->descSetorSolicitante;
            $this->fechaTomaMuestra = $this->pedido->Mensagem->PedidoExameLab->atendimento->dataColetaPedido;
            $this->fechaPedido = date("Y-m-d", strtotime($this->fechaTomaMuestra));

            if ($this->pedido == null) {
                throw new ModelsException('[Pedidos]: Error no se pudo generar el XML correctamente.', 0);
            }

            # Filtrar

            $filtro = new LisaModel\Filtrar;
            $filtro->pedido = $this->pedido;
            $filtro->filtrar();
            $this->tipoProceso = $filtro->tipoProceso;

            if ($filtro->statusFiltro == 0) {
                throw new ModelsException('[Filtrar]: Error no se pudo Filtrar correctamente este documento.', 0);
            }

            # Procesar

            $this->procesar();

            if ($this->statusProcesado == 0) {
                throw new ModelsException('[Pedidos]: Error no se pudo procesar correctamente ID PEDIDO:' . $this->codigoPedido, 0);
            }

            #Procesodo

            file_put_contents($this->dirIngresados . $this->codigoPedido . '_' . $this->timestampLog . '.xml', $this->documentoPedido, LOCK_EX);

            $log = new LisaModel\Logs;
            $log->typeLog = 2;
            $log->log = array(
                'idLog' => $this->codigoPedido . '_' . $this->timestampLog,
                'timestampLog' => time(),
                'typeLog' => $log->typeLog,
                'message' => '[Pedidos] ' . $this->codigoPedido . '_' . $this->timestampLog . ' Ingresado y procesado correctamente.',
            );
            $log->nuevoLog();

        } catch (\Exception $b) {

            file_put_contents($this->dirErrores . $this->codigoPedido . '_' . $this->timestampLog . '.xml', $this->documentoPedido, LOCK_EX);

            $log = new LisaModel\Logs;
            $log->typeLog = 0;
            $log->log = array(
                'idLog' => $this->codigoPedido . '_' . $this->timestampLog,
                'timestampLog' => time(),
                'typeLog' => $log->typeLog,
                'message' => '[Pedidos] Error de PHP in_nuevoPedido => ' . $b->getMessage(),
            );
            $log->nuevoLog();

        } catch (ModelsException $e) {

            file_put_contents($this->dirErrores . $this->codigoPedido . '_' . $this->timestampLog . '.xml', $this->documentoPedido, LOCK_EX);

            $log = new LisaModel\Logs;
            $log->typeLog = 1;
            $log->log = array(
                'idLog' => $this->codigoPedido . '_' . $this->timestampLog,

                'timestampLog' => time(),
                'typeLog' => $log->typeLog,
                'message' => '[Pedidos] Error de PHP in_nuevoPedido => ' . $e->getMessage(),
            );
            $log->nuevoLog();

        }

    }

    public function procesar()
    {

        try {

            global $config;

            # Passtrhu Enviar
            if ($this->tipoProceso == 1) {
                // $this->forrmatXML();
                $this->send();
            }

            # Retener
            if ($this->tipoProceso == 2) {
                $this->retener();
            }

        } catch (\Exception $b) {

            $log = new LisaModel\Logs;
            $log->typeLog = 0;
            $log->log = array(
                'idLog' => $this->codigoPedido . '_' . $this->timestampLog,

                'timestampLog' => time(),
                'typeLog' => $log->typeLog,
                'message' => '[Pedidos] Error de PHP. Procesar',
            );
            $log->nuevoLog();

        }

    }

    public function retener()
    {

        try {

            global $config;

            file_put_contents($this->dirRetenidos . $this->codigoPedido . '_' . $this->timestampLog . '.xml', $this->documentoPedido, LOCK_EX);

            $this->statusProcesado = 1;

            $log = new LisaModel\Logs;
            $log->typeLog = 3;
            $log->log = array(
                'idLog' => $this->codigoPedido . '_' . $this->timestampLog,
                'timestampLog' => time(),
                'typeLog' => $log->typeLog,
                'message' => '[Pedidos] Examne retenido',
            );
            $log->nuevoLog();

        } catch (\Exception $b) {

            $log = new LisaModel\Logs;
            $log->typeLog = 0;
            $log->log = array(
                'idLog' => $this->codigoPedido . '_' . $this->timestampLog,

                'timestampLog' => time(),
                'typeLog' => $log->typeLog,
                'message' => '[Pedidos] Error de PHP',
            );
            $log->nuevoLog();

        }

    }

    public function send()
    {

        try {

            global $config;

            $webservice_url = "http://172.16.253.11:8184/mv-api-hl7bus/proxySaidaMLLP";

            $headers = array(
                'Content-Type: text/xml; charset=utf-8',
                'Content-Length: ' . strlen($this->documentoPedido),
            );

            $ch = curl_init($webservice_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->documentoPedido);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $data = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code == 200) {

                file_put_contents($this->dirEnviados . 'log_response_' . $this->codigoPedido . '_' . $this->timestampLog . '.xml', $data, LOCK_EX);
                file_put_contents($this->dirEnviados . $this->codigoPedido . '_' . $this->timestampLog . '.xml', $this->documentoPedido, LOCK_EX);

                $log = new LisaModel\Logs;
                $log->typeLog = 4;
                $log->log = array(
                    'idLog' => $this->codigoPedido . '_' . $this->timestampLog,
                    'timestampLog' => time(),
                    'typeLog' => $log->typeLog,
                    'message' => '[Pedidos] ' . $this->codigoPedido . '_' . $this->timestampLog . ' Enviado correctamente.',
                );
                $log->nuevoLog();

                $this->statusProcesado = 1;

                return true;

            } else {

                file_put_contents($this->dirErrores . 'log_response_' . $this->codigoPedido . '_' . $this->timestampLog . '.xml', $data, LOCK_EX);

                $log = new LisaModel\Logs;
                $log->typeLog = 1;
                $log->log = array(
                    'idLog' => $this->codigoPedido . '_' . $this->timestampLog,
                    'timestampLog' => time(),
                    'typeLog' => $log->typeLog,
                    'message' => '[Pedidos] Error de PHP. Infinity error ',
                );
                $log->nuevoLog();

                $this->statusProcesado = 1;

                return false;

            }

        } catch (\Exception $b) {

            $log = new LisaModel\Logs;
            $log->typeLog = 0;
            $log->log = array(
                'idLog' => $this->codigoPedido . '_' . $this->timestampLog,
                'timestampLog' => time(),
                'typeLog' => $log->typeLog,
                'message' => '[Pedidos] Error de PHP. No se puede ejecutar el llamado CURL',
            );
            $log->nuevoLog();

        }

    }

/**
 * __construct()
 */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
    }
}
