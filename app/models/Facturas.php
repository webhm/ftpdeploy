<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models;

use app\models as Model;
use DateTime;
use Doctrine\DBAL\DriverManager;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Odbc GEMA -> FACTURAS
 */

class Facturas extends Models implements IModels
{
    # Variables de clase
    private $USER = null;
    private $sortField = 'ROWNUM_';
    private $sortType = 'desc'; # desc
    private $offset = 1;
    private $limit = 25;
    private $searchField = null;
    private $startDate = null;
    private $endDate = null;
    private $tresMeses = null; # Se muestran resultados solo hasta los tres meses de la fecha actual
    private $_conexion = null;

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

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

        foreach ($http->request->all() as $key => $value) {
            $this->$key = strtoupper($value);
        }

        if ($this->startDate != null and $this->endDate != null) {

            $startDate = $this->startDate;
            $endDate = $this->endDate;

            $sd = new DateTime($startDate);
            $ed = new DateTime($endDate);

            if ($sd->getTimestamp() > $ed->getTimestamp()) {
                throw new ModelsException('!Error! Fecha inicial no puede ser mayor a fecha final.');
            }

        }

        $fecha = date('d-m-Y');
        $nuevafecha = strtotime('-13 month', strtotime($fecha));

        # SETEAR FILTRO HASTA TRES MESES
        $this->tresMeses = date('d-m-Y', $nuevafecha);

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

    public function getFacturas(): array
    {

        try {

            global $http, $config;

            # SETEAR VARIABLES DE CLASE
            $this->setParameters();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # EXTRAER VALOR DEL TOKEN PARA CONSULTA
            $this->getAuthorization();

            # setear codigos para query
            $codes = implode(',', $this->USER->CP_PTE);

            # NO EXITEN RESULTADOS
            $this->notResults($this->USER->CP_PTE);

            # CONULTA BDD GEMA
            if ($this->startDate != null and $this->endDate != null) {

                $sql = "SELECT WEB2_VW_FACTURAS.*, ROWNUM AS ROWNUM_ FROM WEB2_VW_FACTURAS WHERE COD_PERSONA IN ($codes)  AND FECHA_FACTURA >= TO_DATE('$this->startDate', 'dd-mm-yyyy') AND FECHA_FACTURA <= TO_DATE('$this->endDate', 'dd-mm-yyyy') AND FECHA_FACTURA >= TO_DATE('$this->tresMeses', 'dd-mm-yyyy') ORDER BY FECHA_FACTURA $this->sortType";

            } elseif ($this->sortField == 'FACT' and $this->searchField == '') {

                $sql = "SELECT WEB2_VW_FACTURAS.*, ROWNUM AS ROWNUM_ FROM WEB2_VW_FACTURAS WHERE COD_PERSONA IN ($codes) AND FECHA_FACTURA >= TO_DATE('$this->tresMeses', 'dd-mm-yyyy') ORDER BY ROWNUM_ $this->sortType";

            } elseif ($this->sortField == 'FACT' and $this->searchField != null) {

                $sql = "SELECT WEB2_VW_FACTURAS.*, ROWNUM AS ROWNUM_ FROM WEB2_VW_FACTURAS WHERE COD_PERSONA IN ($codes) AND (ORIGEN LIKE '%$this->searchField%' OR SERIE LIKE '%$this->searchField%' OR NUMERO LIKE '%$this->searchField%' OR TOTAL LIKE '%$this->searchField%' OR PAGADOR LIKE '%$this->searchField%'  OR ADMISION LIKE '%$this->searchField%') AND FECHA_FACTURA >= TO_DATE('$this->tresMeses', 'dd-mm-yyyy') ORDER BY ROWNUM_ $this->sortType ";

            } elseif ($this->searchField != null) {

                $sql = "SELECT WEB2_VW_FACTURAS.*, ROWNUM AS ROWNUM_ FROM WEB2_VW_FACTURAS WHERE COD_PERSONA IN ($codes) AND (ORIGEN LIKE '%$this->searchField%' OR SERIE LIKE '%$this->searchField%' OR NUMERO LIKE '%$this->searchField%' OR TOTAL LIKE '%$this->searchField%' OR PAGADOR LIKE '%$this->searchField%' OR ADMISION LIKE '%$this->searchField%') AND FECHA_FACTURA >= TO_DATE('$this->tresMeses', 'dd-mm-yyyy') ORDER BY $this->sortField $this->sortType ";

            } else {

                $sql = "SELECT WEB2_VW_FACTURAS.*, ROWNUM AS ROWNUM_ FROM WEB2_VW_FACTURAS WHERE COD_PERSONA IN ($codes) AND FECHA_FACTURA >= TO_DATE('$this->tresMeses', 'dd-mm-yyyy') ORDER BY $this->sortField $this->sortType";

            }

            # Conectar base de datos
            $this->conectar_Oracle();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $facturas = array();

            foreach ($data as $key) {

                $key['NUM'] = intval($key['ROWNUM_']);

                $key['ADM'] = $key['ADMISION'];

                $key['EST'] = substr($key['SERIE'], 0, -3);
                $key['PTO'] = substr($key['SERIE'], 3);
                $key['SEC'] = $key['NUMERO'];

                $key['FECHA_ADM'] = $key['FECHA_ADM'];
                $key['FECHA_ALTA'] = $key['FECHA_ALTA'];
                $key['FECHA_FACTURA'] = $key['FECHA_FACTURA'];
                $key['FECHA_REGISTRO'] = $key['FECHA_FACTURA'];

                switch ($key['TIPO']) {

                    case 'NC':
                        # NOTA DE CREDITO
                        $key['TIPO'] = '04';
                        break;

                    case 'AF':
                        # ANULACION DE FACTURA
                        $key['TIPO'] = '04';
                        break;

                    default:
                        # FACTURA
                        $key['TIPO'] = '01';
                        break;
                }

                $facturas[] = array(
                    'NUM' => $key['NUM'],
                    'TIPO' => $key['TIPO'],
                    'ORIGEN' => $key['ORIGEN'],
                    'FECHA_ADM' => $key['FECHA_ADM'],
                    'FECHA_ALTA' => $key['FECHA_ALTA'],
                    'FECHA_FACTURA' => $key['FECHA_FACTURA'],
                    'FECHA_REGISTRO' => $key['FECHA_REGISTRO'],
                    'TOTAL' => $key['TOTAL'],
                    'PAGADOR' => $key['PAGADOR'],
                    'NHC' => '',
                    'ADM' => $key['ADM'],
                    'FACT' => $key['EST'] . '-' . $key['PTO'] . '-' . $key['SEC'],
                );

            }

            // RESULTADO DE CONSULTA

            # Ya no existe resultadso
            $this->notResults($facturas);

            # Order by asc to desc
            $FACTURAS = $this->get_Order_Pagination($facturas);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->get_page($FACTURAS, $this->offset, $this->limit),
                'total' => count($facturas),
                'limit' => intval($this->limit),
                'offset' => intval($this->offset),
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
