<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models\nss;

use app\models\nss as Model;
use Doctrine\DBAL\DriverManager;
use Exception;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;
use SoapClient;

/**
 * Modelo Ordenes
 */
class Ordenes extends Models implements IModels
{
    # Variables de clase
    private $pstrSessionKey = 0;
    private $USER = null;
    private $sortField = 'ROWNUM_';
    private $sortType = 'desc'; # desc
    private $start = 1;
    private $length = 10;
    private $searchField = null;
    private $startDate = null;
    private $endDate = null;
    private $tresMeses = null;
    private $urlPDF = 'https://api.hospitalmetropolitano.org/t/v1/rslocal/lab/';

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

    }

    private function conectar_Oracle_MV()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle_mv'], $_config);

    }

    public function extraerOrdenes(): array
    {

        try {

            global $config, $http;

            $this->wsLab_LOGIN();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wOrders.xml', array(
                'soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ));

            $Preview = $client->GetList(array(
                'pstrSessionKey' => $this->pstrSessionKey,
                'pstrRegisterDateFrom' => "2022-09-29",
                'pstrRegisterDateTo' => "2022-09-29",
            ));

            $this->wsLab_LOGOUT();

            $xml = simplexml_load_string($Preview->GetListResult->any);

            $json = json_encode($xml);

            $array = json_decode($json, true);

            # Ya no existe resultadso
            $this->notResults($array);

            $ordenes = array();

            foreach ((array) $array['DefaultDataSet']['SQL'] as $key) {

                $documento = array(
                    'numeroHistoriaClinica' => $key['PatientID1'],
                    'apellidosPaciente' => $key['LastName'],
                    'nombresPaciente' => $key['FirstName'],
                    'sc' => $key['SampleID'],
                    'fechaExamen' => $key['RegisterDate'],
                    'horaExamen' => $key['RegisterHour'],
                    'origen' => (isset($key['Origin']) ? $key['Origin'] : ""),
                    'servicio' => (isset($key['Service']) ? $key['Service'] : ""),
                    'medico' => (isset($key['Doctor']) ? $key['Doctor'] : ""),
                    'motivo' => (isset($key['Motive']) ? $key['Motive'] : ""),
                    'ultimaValidacion' => '',
                    'ultimaFiltrado' => '',
                    'tipoValidacion' => 0,
                    'validacionClinica' => 0,
                    'validacionMicro' => 0,
                    'procesoValidacion' => 0,
                    'dataClinica' => array(),
                    'dataMicro' => array(),
                    'reglasFiltrosEnvio' => array(),
                    'reglasFiltrosNoEnvio' => array(),
                    'formatoQR' => array(),
                    'logsEnvio' => array(),
                    'correosElectronicos' => array(),
                    'statusFiltro' => 0,
                    'statusEnvio' => 0,
                    '_PDF' => '',
                    '_PCR' => 0,

                );

                $this->crearOrden($documento);
                $ordenes[] = $documento;

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $ordenes,
                'total' => count($ordenes),
            );

        } catch (\Exception $b) {

            $this->wsLab_LOGOUT();

            return array('status' => false, 'data' => [], 'message' => $b->getMessage(), 'errorCode' => $b->getCode());

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function crearOrden($documento)
    {

        $stsLogPen = "ordenes/logs/sc_" . $documento['sc'] . "_" . $documento['fechaExamen'] . ".json";

        if (@file_get_contents($stsLogPen, true) === false) {

            file_put_contents('ordenes/logs/sc_' . $documento['sc'] . '_' . $documento['fechaExamen'] . '.json', json_encode($documento), LOCK_EX);
            file_put_contents('ordenes/ingresadas/sc_' . $documento['sc'] . '_' . $documento['fechaExamen'] . '.json', json_encode($documento), LOCK_EX);

        }

    }

    public function verificarOrden($documento)
    {

        $estaProcesado = true;

        $stsLogPen = "ordenes/logs/sc_" . $documento['sc'] . "_" . $documento['fechaExamen'] . ".json";

        if (@file_get_contents($stsLogPen, true) === false) {

            file_put_contents('ordenes/logs/sc_' . $documento['sc'] . '_' . $documento['fechaExamen'] . '.json', json_encode($documento), LOCK_EX);
            file_put_contents('ordenes/ingresadas/sc_' . $documento['sc'] . '_' . $documento['fechaExamen'] . '.json', json_encode($documento), LOCK_EX);

        }

    }

    # Metodo LOGIN webservice laboratorio ROCHE
    public function wsLab_LOGIN()
    {

        try {

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'zdk.ws.wSessions.wsdl.xml');

            $Login = $client->Login(array(
                "pstrUserName" => "CONSULTA",
                "pstrPassword" => "CONSULTA1",
            ));

            # Guaradar  KEY de session WS
            $this->pstrSessionKey = $Login->LoginResult;

            # Retorna KEY de session WS
            return $Login->LoginResult;

        } catch (SoapFault $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }

    }

    # Metodo LOGOUT webservice laboratorio ROCHE
    public function wsLab_LOGOUT()
    {

        try {

            # INICIAR SESSION
            # $this->wsLab_LOGIN();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'zdk.ws.wSessions.wsdl.xml');

            $Logout = $client->Logout(array(
                "pstrSessionKey" => $this->pstrSessionKey,
            ));

            return $Logout->LogoutResult;

        } catch (SoapFault $e) {
            return array('success' => false, 'message' => $e->getMessage());
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

/**
 * __construct()
 */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
    }
}
