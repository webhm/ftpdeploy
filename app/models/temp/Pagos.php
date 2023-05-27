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
use Doctrine\DBAL\DriverManager;
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Odbc GEMA -> Pagos
 */

class Pagos extends Models implements IModels
{
    # Variables de clase
    private $sortField    = 'ROWNUM_';
    private $sortCategory = null;
    private $sortType     = 'desc'; # desc
    private $offset       = 1;
    private $limit        = 50;
    private $searchField  = null;
    private $startDate    = null;
    private $endDate      = null;

    # varaiables para pago web
    private $pago_fk_paciente            = '';
    private $pago_nombre_titular         = '';
    private $pago_identificacion_titular = '';
    private $pago_direccion              = '';
    private $pago_numero_tarjeta         = '';
    private $pago_numero_voucher         = '';
    private $pago_numero_autorizacion    = '';
    private $pago_fecha_vence            = '';
    private $pago_monto                  = '';

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

    }

    private function getAuthorizationn($value)
    {

        try {

            global $http;

            $token = $http->headers->get("Authorization");

            $auth = new Model\Auth;
            $key  = $auth->GetData($token);

            $this->pago_fk_paciente = $key->data[0]->$value;

        } catch (ModelsException $e) {
            throw new ModelsException($e->getMessage());
        }
    }

    private function errorsPagination()
    {

        if ($this->limit > 50) {
            throw new ModelsException('!Error! Solo se pueden mostrar 50 resultados por página.');
        }

        if ($this->limit == 0 or $this->limit < 0) {
            throw new ModelsException('!Error! {Limit} no puede ser 0 o negativo');
        }

        if ($this->offset == 0 or $this->offset < 0) {
            throw new ModelsException('!Error! {Offset} no puede ser 0 o negativo.¿');
        }
    }

    private function erroresPagoWeb()
    {

        # Verificar que no están vacíos los elenentos necesarios para le registro del pago
        if (Helper\Functions::e(
            # $this->pago_fk_paciente,
            $this->pago_nombre_titular,
            $this->pago_identificacion_titular,
            $this->pago_direccion,
            $this->pago_numero_tarjeta,
            $this->pago_numero_voucher,
            $this->pago_numero_autorizacion,
            $this->pago_fecha_vence,
            $this->pago_monto
        )) {
            throw new ModelsException('¡Error! Existen campos vacios para registrar el pago.', 4001);
        }
    }

    private function setParameters()
    {

        global $http;

        foreach ($http->request->all() as $key => $value) {
            $this->$key = $value;
        }

        if ($this->sortField == 'CATEGORIA') {
            $this->sortField = 'DESCRIPCION';
        }

        if ($this->sortField == 'ID') {
            $this->sortField = 'ARTI';
        }

        if ($this->sortCategory != null) {
            $this->sortCategory = mb_strtoupper($this->sortCategory);
        }

        if ($this->searchField != null) {
            $this->searchField = mb_strtoupper($this->searchField);
        }
    }

    private function setParametersPagoWeb(array $data)
    {

        foreach ($data as $key => $value) {
            $paramPagoWeb        = 'pago_' . $key;
            $this->$paramPagoWeb = $value;
        }

        if ($this->pago_fecha_vence != '') {
            $this->pago_fecha_vence = date('d-m-Y', strtotime($this->pago_fecha_vence));
        }

        if ($this->pago_monto == 0 or $this->pago_monto < 0) {
            throw new ModelsException('!Error! {Monto} no puede ser 0 o negativo');
        }

    }

    public function getPortafolioWeb(): array
    {

        try {

            global $http;

            # SETEAR VARIABLES DE CLASE
            $this->setParameters();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # CONULTA BDD GEMA
            if ($this->searchField != null and $this->sortCategory != null) {

                $sql = " SELECT WEB_VW_TARIFARIO_PMQ.*, ROWNUM AS ROWNUM_ FROM WEB_VW_TARIFARIO_PMQ WHERE (ARTI LIKE '%$this->searchField%' OR ARTICULO LIKE '%$this->searchField%') AND DESCRIPCION LIKE '%$this->sortCategory%' ORDER BY $this->sortField $this->sortType ";

            } elseif ($this->sortCategory != null) {

                if ($this->sortCategory == 'IMAGEN') {

                    $sql = " SELECT WEB_VW_TARIFARIO_PMQ.*, ROWNUM AS ROWNUM_ FROM WEB_VW_TARIFARIO_PMQ WHERE DESCRIPCION IN ('RADIOLOGIA', 'TOMOGRAFÍA') ORDER BY $this->sortField $this->sortType ";

                } else {

                    $sql = " SELECT WEB_VW_TARIFARIO_PMQ.*, ROWNUM AS ROWNUM_ FROM WEB_VW_TARIFARIO_PMQ WHERE DESCRIPCION LIKE '%$this->sortCategory%' ORDER BY $this->sortField $this->sortType ";
                }

            } else {

                $sql = "SELECT WEB_VW_TARIFARIO_PMQ.*, ROWNUM AS ROWNUM_ FROM WEB_VW_TARIFARIO_PMQ ORDER BY $this->sortField $this->sortType ";

            }

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # Cerrar conexion
            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # Ya no existe resultadso
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $articulos = array();

            # SETEO DE MEDICOS OBJERTO
            foreach ($data as $key) {

                $key['ID'] = $key['ARTI'];

                $key['CATEGORIA'] = $key['DESCRIPCION'];

                $key['PVP'] = number_format((float) $key['PVP'], 2, '.', '');

                unset($key['ARTI']);
                unset($key['GRUPO']);
                unset($key['TIPO_ARTICULO']);
                unset($key['DESCRIPCION']);
                unset($key['ROWNUM_']);

                $articulos[] = $key;

            }

            # Order by asc to desc
            $ARTICULOS = $this->get_Order_Pagination($articulos);

            # Devolver Información
            return array(
                'status' => true,
                'data'   => $this->get_page($ARTICULOS, $this->offset, $this->limit),
                'total'  => count($articulos),
                'limit'  => intval($this->limit),
                'offset' => intval($this->offset),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status'    => true,
                    'data'      => [],
                    'message'   => $e->getMessage(),
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
        $newRow   = array();
        foreach ($toOrderArray as $key => $row) {
            $position[$key] = $row[$field];
            $newRow[$key]   = $row;
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
                $arr[]      = $key;
                $NUM--;
            }

            return $arr;

        }

        # SI ES ASCENDENTE

        foreach ($arr_input as $key) {
            $key['NUM'] = $NUM;
            $arr[]      = $key;
            $NUM++;
        }

        return $arr;
    }

    private function get_page(array $input, $pageNum, $perPage)
    {
        $start = ($pageNum - 1) * $perPage;
        $end   = $start + $perPage;
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
