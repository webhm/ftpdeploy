<?php

/*
 * Hospital Metropolitano
 *
 */

namespace app\models\lisa;

use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Listar
 */
class Listar extends Models implements IModels
{

    # Variables de clase
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
    public $dirEnviados = '../v1/lisa/pedidos/enviados/';
    public $dirIngresados = '../v1/lisa/pedidos/ingresados/';
    public $dirRetenidos = '../v1/lisa/pedidos/retenidos/';
    public $dirErrores = '../v1//lisa/pedidos/errores/';
    private $ordenes = array();
    private $documento = array();

    public function listarOrdenes(): array
    {

        try {

            global $config, $http;

            $tipoFiltro = $http->query->get('type');

            if (isset($tipoFiltro)) {

                if ($tipoFiltro == 'ingresadas') {

                    $this->ordenes = $this->getIngresadas();

                }

                if ($tipoFiltro == 'filtradas') {

                    $this->ordenes = $this->getFiltradas();

                }

                if ($tipoFiltro == 'porenviar') {

                    $this->ordenes = $this->getPorEnviar();

                }

                if ($tipoFiltro == 'enviadas') {

                    $this->ordenes = $this->getEnviadas();

                }

                if ($tipoFiltro == 'errorFiltradas') {

                    $this->ordenes = $this->getErroresFiltradas();

                }

                if ($tipoFiltro == 'errorEnviadas') {

                    $this->ordenes = $this->getErroresEnviadas();

                }

                if ($tipoFiltro == 'reglas') {
                    $this->ordenes = $this->getReglas();
                }

                if ($tipoFiltro == 'reglas') {
                    $this->ordenes = $this->getReglas();
                }

                if ($tipoFiltro == 'rp') {
                    $this->ordenes = $this->getReprocesoFiltradas();
                }

            } else {

                throw new ModelsException('No existe data Envíe un tipo de filtro.');

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->ordenes,
                'total' => count($this->ordenes),
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getReglas()
    {

        global $config, $http;

        $query = $this->db->select('*', 'filtro_notificaciones_lab', null, "statusFiltro='1'");

        if (false !== $query) {

            return $query;

        }

        return array();

    }

    public function getReprocesoFiltradas()
    {

        global $config, $http;

        $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/ingresadas/');

        $i = 0;

        $ordenes = array();

        // Extraer ORDENES PARA FILTRAR
        foreach ($list as $key => $val) {

            $content = file_get_contents($val);
            $documento = json_decode($content, true);
            $documento['file'] = $val;
            $documento['_PDF'] = '';

            @unlink($documento['file']);
            unset($documento['file']);
            $documento['reglasFiltrosEnvio'] = array();
            $documento['reglasFiltrosNoEnvio'] = array();
            $documento['correosElectronicos'] = array();
            $documento['statusFiltro'] = 0;
            $documento['ultimoFiltrado'] = '';
            $file = 'ordenes/ingresadas/sc_' . $documento['sc'] . '_' . $documento['fechaExamen'] . '.json';
            $json_string = json_encode($documento);
            file_put_contents($file, $json_string);
            $ordenes[] = $documento;

            /*

            if (count($documento['dataClinica']) !== 0 && count($documento['dataMicro']) !== 0) {

            }

             */

            $ordenes[] = $documento;

        }

        return $ordenes;

    }

    public function getIngresadas()
    {

        try {

            global $config, $http;

            $typeFilter = $http->query->get('idFiltro');
            $fechaDesde = $http->query->get('fechaDesde');
            $fechaHasta = $http->query->get('fechaHasta');

            $list = Helper\Files::get_files_in_dir($this->dirIngresados);

            sleep(2);

            $i = 0;

            $ordenes = array();

            // Extraer ORDENES PARA FILTRAR
            foreach ($list as $key => $val) {

                $xml = file_get_contents($val);

                $xml_parser = xml_parser_create('UTF-8'); // UTF-8 or ISO-8859-1
                xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, 0);
                xml_parser_set_option($xml_parser, XML_OPTION_SKIP_WHITE, 1);
                xml_parse_into_struct($xml_parser, $xml, $aryXML, $index);
                xml_parser_free($xml_parser);

                $segundoApellido = (isset($aryXML[118]['value']) ? $aryXML[118]['value'] : '');
                $primerApellido = (isset($aryXML[117]['value']) ? $aryXML[117]['value'] : '');
                $primerNombre = (isset($aryXML[116]['value']) ? $aryXML[116]['value'] : '');
                $segundoNombre = (isset($aryXML[119]['value']) ? $aryXML[119]['value'] : '');

                $codigoPedido = (isset($aryXML[20]['value']) ? $aryXML[20]['value'] : '');

                $fechaPedido = (isset($aryXML[59]['value']) ? $aryXML[59]['value'] : '');
                $numeroHistoriaClinica = (isset($aryXML[96]['value']) ? $aryXML[96]['value'] : '');
                $codigoAtendimento = (isset($aryXML[26]['value']) ? $aryXML[26]['value'] : '');

                $sector = (isset($aryXML[35]['value']) ? $aryXML[35]['value'] : '');
                $tipoPedido = (isset($aryXML[23]['value']) ? $aryXML[23]['value'] : '');
                $descPrestadorSolicitante = (isset($aryXML[41]['value']) ? $aryXML[41]['value'] : '');

                $stsenviado = $this->dirEnviados . $codigoPedido . '.xml';
                $enviadoInfinity = 1;
                if (@file_get_contents($stsenviado, true) === false) {
                    $enviadoInfinity = 0;
                }

                # Pedidos de Hoy
                if ($typeFilter == 1) {

                    if (date('Y-m-d', strtotime($fechaPedido)) == date('Y-m-d')) {
                        $ordenes[] = array(
                            'codigoPedido' => $codigoPedido,
                            'fechaPedido' => $fechaPedido,
                            'at_mv' => $codigoAtendimento,
                            'numeroHistoriaClinica' => $numeroHistoriaClinica,
                            'paciente' => $primerApellido . ' ' . $segundoApellido . ' ' . $primerNombre . ' ' . $segundoNombre,
                            'sector' => $sector,
                            'tipoPedido' => $tipoPedido,
                            'descPrestadorSolicitante' => $descPrestadorSolicitante,
                            'enviadoInfinity' => $enviadoInfinity,
                        );
                    }

                }

                # Pedidos de Emergencia
                if ($typeFilter == 2) {

                    $timedesde = strtotime(date('Y-m-d', strtotime($fechaDesde)));
                    $timeHasta = strtotime(date('Y-m-d', strtotime($fechaHasta)));

                    if ($sector == 'EMERGENCIA' && (strtotime(date('Y-m-d', strtotime($fechaPedido))) >= $timedesde) && (strtotime(date('Y-m-d', strtotime($fechaPedido))) <= $timeHasta)) {
                        $ordenes[] = array(
                            'codigoPedido' => $codigoPedido,
                            'fechaPedido' => $fechaPedido,
                            'numeroHistoriaClinica' => $numeroHistoriaClinica,
                            'at_mv' => $codigoAtendimento,
                            'paciente' => $primerApellido . ' ' . $segundoApellido . ' ' . $primerNombre . ' ' . $segundoNombre,
                            'sector' => $sector,
                            'tipoPedido' => $tipoPedido,
                            'descPrestadorSolicitante' => $descPrestadorSolicitante,
                            'timeDesde' => strtotime($fechaPedido),
                            'enviadoInfinity' => $enviadoInfinity,

                        );
                    }

                }

                if ($typeFilter == 3) {

                    $timedesde = strtotime(date('Y-m-d', strtotime($fechaDesde)));
                    $timeHasta = strtotime(date('Y-m-d', strtotime($fechaHasta)));

                    if (strpos($sector, 'HOSPITALIZACION') !== false && (strtotime(date('Y-m-d', strtotime($fechaPedido))) >= $timedesde) && (strtotime(date('Y-m-d', strtotime($fechaPedido))) <= $timeHasta)) {
                        $ordenes[] = array(
                            'codigoPedido' => $codigoPedido,
                            'fechaPedido' => $fechaPedido,
                            'numeroHistoriaClinica' => $numeroHistoriaClinica,
                            'at_mv' => $codigoAtendimento,
                            'paciente' => $primerApellido . ' ' . $segundoApellido . ' ' . $primerNombre . ' ' . $segundoNombre,
                            'sector' => $sector,
                            'tipoPedido' => $tipoPedido,
                            'descPrestadorSolicitante' => $descPrestadorSolicitante,
                            'timeDesde' => strtotime($fechaPedido),
                            'enviadoInfinity' => $enviadoInfinity,

                        );
                    }

                }

                if ($typeFilter == 4) {

                    $timedesde = strtotime(date('Y-m-d', strtotime($fechaDesde)));
                    $timeHasta = strtotime(date('Y-m-d', strtotime($fechaHasta)));

                    if (strpos($sector, 'SERVICIOS AMBULATORIOS') !== false && (strtotime(date('Y-m-d', strtotime($fechaPedido))) >= $timedesde) && (strtotime(date('Y-m-d', strtotime($fechaPedido))) <= $timeHasta)) {
                        $ordenes[] = array(
                            'codigoPedido' => $codigoPedido,
                            'fechaPedido' => $fechaPedido,
                            'numeroHistoriaClinica' => $numeroHistoriaClinica,
                            'at_mv' => $codigoAtendimento,
                            'paciente' => $primerApellido . ' ' . $segundoApellido . ' ' . $primerNombre . ' ' . $segundoNombre,
                            'sector' => $sector,
                            'tipoPedido' => $tipoPedido,
                            'descPrestadorSolicitante' => $descPrestadorSolicitante,
                            'timeDesde' => strtotime($fechaPedido),
                            'enviadoInfinity' => $enviadoInfinity,

                        );
                    }

                }
            }

            return $ordenes;

        } catch (ModelsException $e) {

            return array();

        }

    }

    public function getFiltradas(): array
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/filtradas/');

            $i = 0;

            $ordenes = array();

            // Extraer ORDENES PARA FILTRAR
            foreach ($list as $key => $val) {

                $content = file_get_contents($val);
                $documento = json_decode($content, true);
                $ordenes[] = $documento;

            }

            $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/ingresadas/');

            // Extraer ORDENES PARA FILTRAR
            foreach ($list as $key => $val) {

                $content = file_get_contents($val);
                $documento = json_decode($content, true);

                $ordenes[] = $documento;

            }

            return $ordenes;

        } catch (ModelsException $e) {

            return array();

        }

    }

    public function getPorEnviar(): array
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/porenviar/');

            $i = 0;

            $ordenes = array();

            // Extraer ORDENES PARA FILTRAR
            foreach ($list as $key => $val) {

                $content = file_get_contents($val);
                $documento = json_decode($content, true);
                $ordenes[] = $documento;

            }

            return $ordenes;

        } catch (ModelsException $e) {

            return array();

        }

    }

    public function getEnviadas(): array
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/enviadas/');

            $i = 0;

            $ordenes = array();

            // Extraer ORDENES PARA FILTRAR
            foreach ($list as $key => $val) {

                $content = file_get_contents($val);
                $documento = json_decode($content, true);
                $documento['_PDF'] = '';
                $documento['file'] = $val;

                $ordenes[] = $documento;

            }

            return $ordenes;

        } catch (ModelsException $e) {

            return array();

        }

    }

    public function getErroresFiltradas(): array
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/errores/filtradas/');

            $i = 0;

            $ordenes = array();

            // Extraer ORDENES PARA FILTRAR
            foreach ($list as $key => $val) {

                $content = file_get_contents($val);
                $documento = json_decode($content, true);
                $documento['_PDF'] = '';
                $documento['file'] = $val;
                $ordenes[] = $documento;

            }

            return $ordenes;

        } catch (ModelsException $e) {

            return array();

        }

    }

    public function getErroresEnviadas(): array
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/errores/enviadas/');

            $i = 0;

            $ordenes = array();

            // Extraer ORDENES PARA FILTRAR
            foreach ($list as $key => $val) {

                $content = file_get_contents($val);
                $documento = json_decode($content, true);
                $documento['_PDF'] = '';
                $documento['file'] = $val;
                $ordenes[] = $documento;

            }

            return $ordenes;

        } catch (ModelsException $e) {

            return array();

        }

    }

    private function quitar_tildes($cadena)
    {
        $no_permitidas = array("%", "é", "í", "ó", "ú", "É", "Í", "Ó", "Ú", "ñ", "À", "Ã", "Ì", "Ò", "Ù", "Ã™", "Ã ", "Ã¨", "Ã¬", "Ã²", "Ã¹", "ç", "Ç", "Ã¢", "ê", "Ã®", "Ã´", "Ã»", "Ã‚", "ÃŠ", "ÃŽ", "Ã”", "Ã›", "ü", "Ã¶", "Ã–", "Ã¯", "Ã¤", "«", "Ò", "Ã", "Ã„", "Ã‹");
        $permitidas = array("", "e", "i", "o", "u", "E", "I", "O", "U", "n", "N", "A", "E", "I", "O", "U", "a", "e", "i", "o", "u", "c", "C", "a", "e", "i", "o", "u", "A", "E", "I", "O", "U", "u", "o", "O", "i", "a", "e", "U", "I", "A", "E");
        $texto = str_replace($no_permitidas, $permitidas, $cadena);
        return $texto;
    }

    private function sanear_string($string)
    {

        $string = trim($string);

        //Esta parte se encarga de eliminar cualquier caracter extraño
        $string = str_replace(
            array(">", "< ", ";", ",", ":", "%", "|", "-", "/"),
            ' ',
            $string
        );

        return trim($string);
    }

    # Ordenar array por campo
    public function orderMultiDimensionalArray($toOrderArray, $field, $inverse = 'desc')
    {
        $position = array();
        $newRow = array();
        foreach ($toOrderArray as $key => $row) {
            $position[$key] = $row[$field];
            $newRow[$key] = $row;
        }
        if ($inverse == 'desc') {
            arsort($position);
        } else {
            asort($position);
        }
        $returnArray = array();
        foreach ($position as $key => $pos) {
            $returnArray[] = $newRow[$key];
        }
        return $returnArray;
    }

    private function get_Order_Pagination(array $arr_input)
    {
        # SI ES DESCENDENTE

        $arr = array();
        $NUM = 1;

        if ($this->sortType == 'desc') {

            $NUM = count($arr_input);
            foreach ($arr_input as $key) {
                $key['NUM'] = $NUM;
                $arr[] = $key;
                $NUM--;
            }

            return $arr;

        }

        # SI ES ASCENDENTE

        foreach ($arr_input as $key) {
            $key['NUM'] = $NUM;
            $arr[] = $key;
            $NUM++;
        }

        return $arr; // 1000302446
    }

    private function get_page(array $input, $pageNum, $perPage)
    {
        $start = ($pageNum - 1) * $perPage;
        $end = $start + $perPage;
        $count = count($input);

        // Conditionally return results
        if ($start < 0 || $count <= $start) {
            // Page is out of range
            return array();
        } else if ($count <= $end) {
            // Partially-filled page
            return array_slice($input, $start);
        } else {
            // Full page
            return array_slice($input, $start, $end - $start);
        }
    }

    private function notResults(array $data)
    {
        if (count($data) == 0) {
            throw new ModelsException('No existe más resultados.', 4080);
        }
    }

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);

    }
}
