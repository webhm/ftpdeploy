<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models\touch;

use app\models\touch as Model;
use Doctrine\DBAL\DriverManager;
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Imagen
 */
class Imagen extends Models implements IModels
{
    # Variables de clase
    private $pstrSessionKey = 0;
    private $USER = null;
    private $sortField = 'ROWNUM_';
    private $sortType = 'desc'; # desc
    private $offset = 1;
    private $limit = 25;
    private $searchField = null;
    private $startDate = null;
    private $endDate = null;
    private $tresMeses = null;
    private $numeroHistoriaClinica = null;
    private $urlApiImagen = '//api.hospitalmetropolitano.org/v1/';
    private $urlApiViewer = '//api.imagen.hospitalmetropolitano.org/';

    private function conectar_Medora()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracleMedora'], $_config);

    }

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

    }

    private function setSpanishOracle()
    {

        $sql = "alter session set NLS_LANGUAGE = 'SPANISH'";
        # Execute
        $stmt = $this->_conexion->query($sql);

        $sql = "alter session set NLS_TERRITORY = 'SPAIN'";
        # Execute
        $stmt = $this->_conexion->query($sql);

        $sql = " alter session set NLS_DATE_FORMAT = 'DD-MM-YYYY' ";
        # Execute
        $stmt = $this->_conexion->query($sql);

    }

    private function getAuthorization()
    {

        try {

            global $http;

            $token = $http->headers->get("Authorization");

            $auth = new Model\Auth;
            $key = $auth->GetData($token);

            $this->USER = $key;

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function errorsPagination()
    {

        if ($this->limit > 25) {
            throw new ModelsException('!Error! Solo se pueden mostrar 25 resultados por página.');
        }

        if ($this->limit == 0 or $this->limit < 0) {
            throw new ModelsException('!Error! {Limit} no puede ser 0 o negativo');
        }

        if ($this->offset == 0 or $this->offset < 0) {
            throw new ModelsException('!Error! {Offset} no puede ser 0 o negativo.');
        }

    }

    private function setParameters()
    {

        global $http;

        foreach ($http->query->all() as $key => $value) {
            $this->$key = $value;
        }

    }

    public function getResultadosImg($nhc)
    {

        try {

            global $config, $http;

            $this->setParameters();

            $sql = " SELECT * FROM medora.VW_RESULTADOS_WEB_PTES WHERE PAT_PID_NUMBER = '$nhc'  ORDER BY PROCEDURE_KEY DESC ";

            # Conectar base de datos
            $this->conectar_Medora();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            if (count($data) === 0) {
                throw new ModelsException('El Paciente todavía no tiene resultados disponibles.');
            }

            # Datos de usuario cuenta activa
            $resultados = array();

            foreach ($data as $key) {

                $hashReport = Helper\Strings::ocrend_encode($key['REPORT_KEY'], 'temp');
                $hashEstudio = Helper\Strings::ocrend_encode($key['PROCEDURE_KEY'], 'temp');
                $token = Helper\Strings::ocrend_encode(time(), 'temp');

                $k['NHC'] = $key['PAT_PID_NUMBER'];
                $k['FECHA'] = $key['PROCEDURE_START'];
                $k['ESTUDIO'] = $key['PROCEDURE_NAME'];
                $k['URL_INFORME'] = $this->urlApiImagen . 'resultado/informe/' . $hashReport . '.pdf';
                $k['URL_IMAGEN'] = $this->urlApiViewer . 'viewer/' . $hashEstudio . '&key=' . $token;
                $resultados[] = $k;

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $resultados,
                'total' => count($resultados),
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

        }

    }

    public function getResultadosImagen()
    {

        try {

            global $config, $http;

            $this->setParameters();

            $numeroHistoriaClinica = $this->numeroHistoriaClinica;

            $sql = " SELECT * FROM medora.VW_RESULTADOS_WEB_PTES WHERE PAT_PID_NUMBER = '$numeroHistoriaClinica'  ORDER BY PROCEDURE_KEY DESC ";

            # Conectar base de datos
            $this->conectar_Medora();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $resultados = array();

            foreach ($data as $key) {

                $hashReport = Helper\Strings::ocrend_encode($key['REPORT_KEY'], 'temp');
                $hashEstudio = Helper\Strings::ocrend_encode($key['PROCEDURE_KEY'], 'temp');
                $token = Helper\Strings::ocrend_encode(time(), 'temp');

                $k['NHC'] = $key['PAT_PID_NUMBER'];
                $k['FECHA'] = $key['PROCEDURE_START'];
                $k['ESTUDIO'] = $key['PROCEDURE_NAME'];
                $k['URL_INFORME'] = $this->urlApiImagen . 'resultado/informe/' . $hashReport . '.pdf';
                $k['URL_IMAGEN'] = $this->urlApiViewer . 'viewer/' . $hashEstudio . '&key=' . $token;
                # $k['URL_INFORME'] = $config['build']['url'] . 'v1/resultado/informe/' . $hashReport . '.pdf';

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

            return array('status' => false, 'data' => [], 'message' => $e->getMessage() . $numeroHistoriaClinica);

        }

    }

    public function calcular_edad($fecha)
    {
        $dias = explode("-", $fecha, 3);
        $dias = mktime(0, 0, 0, $dias[1], $dias[0], $dias[2]);
        $edad = (int) ((time() - $dias) / 31556926);
        return $edad;
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

        return $arr;
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
