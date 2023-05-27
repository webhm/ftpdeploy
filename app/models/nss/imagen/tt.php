<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models\nss\imagen;

use app\models\nss\imagen as Model;
use Doctrine\DBAL\DriverManager;
use Exception;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;
use SoapClient;

/**
 * Modelo Extraer
 */

class Extraer extends Models implements IModels
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

    public function getTasksInformes()
    {

        try {

            global $config, $http;

            $time = new DateTime();

            $nuevaHora = strtotime('-30 minutes', $time->getTimestamp());

            # SETEAR FILTRO MENOS UNA HORA
            $menosHora = date('d/m/Y H:i', $nuevaHora);

            // $menosCuatroDias = date('d/m/Y H:i', strtotime('-4 days', $time->getTimestamp()));

            $sql = " SELECT *
            FROM VW_RESULTADOS_WEB_PTES
            WHERE REPORT_VERIFICATION_DATE >= TO_DATE('" . $menosHora . "', 'DD/MM/YYYY HH24:MI')
            AND REPORT_VERIFICATION_DATE <= TO_DATE('" . date('d/m/Y H:i') . "','DD/MM/YYYY HH24:MI')
            AND REPORT_STATUS = 'f' AND ADMISSION_TYPE NOT IN ('S','P') ";

            # Conectar base de datos
            $this->conectar_Medora();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $resultados = array();

            $time = time();

            foreach ($data as $key) {

                $hashReport = Helper\Strings::ocrend_encode($key['REPORT_KEY'], 'temp');
                $hashEstudio = Helper\Strings::ocrend_encode($key['PROCEDURE_KEY'], 'temp');
                $token = Helper\Strings::ocrend_encode(time(), 'temp');

                $k['NHC'] = $key['PAT_PID_NUMBER'];
                $k['FECHA'] = $key['PROCEDURE_START'];
                $k['ESTUDIO'] = $key['PROCEDURE_NAME'];
                $k['FECHA_RES'] = $key['PROCEDURE_START'];
                $k['ID_RESULTADO'] = $key['PROCEDURE_KEY'];

                $key['PAT_FIRST_NAME'] = strtoupper($key['PAT_FIRST_NAME']);
                $key['PAT_LAST_NAME'] = strtoupper($key['PAT_LAST_NAME']);

                if ($key['SECTION_CODE'] == 'HM-ES' && $key['ADMISSION_TYPE'] == 'S') {

                    // INSERTAR LOGS PTES INFORMES
                    $dataInforme = array(
                        'ID_STUDIO' => $key['PROCEDURE_KEY'],
                        'ID_REPORT' => $key['REPORT_KEY'],
                        'FECHA' => date('d/M/Y'),
                        'FECHA_ESTUDIO' => $key['PROCEDURE_START'],
                        'HC_PTE' => $key['PAT_PID_NUMBER'],
                        'PROCESADO' => ($key['REPORT_STATUS'] == 'f') ? '1' : '0',
                        'FECHA_PROCESADO' => time(),
                        'TIPO_RES' => $key['SECTION_CODE'],
                        'COD_MEDICO' => $key['SIGNER_CODE'],

                    );

                } else {

                    // INSERTAR LOGS PTES INFORMES
                    $dataInforme = array(
                        'ID_STUDIO' => $key['PROCEDURE_KEY'],
                        'ID_REPORT' => $key['REPORT_KEY'],
                        'FECHA' => date('d/M/Y'),
                        'FECHA_ESTUDIO' => $key['PROCEDURE_START'],
                        'HC_PTE' => $key['PAT_PID_NUMBER'],
                        'PROCESADO' => ($key['REPORT_STATUS'] == 'f') ? '1' : '0',
                        'FECHA_PROCESADO' => time(),
                        'TIPO_RES' => $key['SECTION_CODE'],
                        'COD_MEDICO' => $key['SIGNER_CODE'],

                    );

                }

                $registrado = $this->getRegistroInforme($key['PROCEDURE_KEY']);

                // No existe log procesado
                if (!$registrado) {
                    $this->insertarNuevoRegistroLogs($dataInforme);
                }

                $resultados[] = array_merge($k, $key);

            }

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $resultados,
                'total' => count($resultados),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status' => true,
                    'data' => [],
                    'message' => $e->getMessage(),
                    'errorCode' => 4080,

                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function extraerOrdenes(): array
    {

        try {

            global $config, $http;

            $this->wsLab_LOGIN();

            $client = new SoapClient(
                dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wOrders.xml',
                array(
                    'soap_version' => SOAP_1_1,
                    'exceptions' => true,
                    'trace' => 1,
                    'cache_wsdl' => WSDL_CACHE_NONE,
                )
            );

            $Preview = $client->GetList(
                array(
                    'pstrSessionKey' => $this->pstrSessionKey,
                    'pstrRegisterDateFrom' => "2022-09-29",
                    'pstrRegisterDateTo' => "2022-09-29",
                )
            );

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

            $Login = $client->Login(
                array(
                    "pstrUserName" => "CONSULTA",
                    "pstrPassword" => "CONSULTA1",
                )
            );

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

            $Logout = $client->Logout(
                array(
                    "pstrSessionKey" => $this->pstrSessionKey,
                )
            );

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
