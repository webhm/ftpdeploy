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
use Ocrend\Kernel\Models\Traits\DBModel;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Odbc GEMA -> Medicos
 */

class Proveedores extends Models implements IModels
{
    use DBModel;

    # Variables de clase
    private $USER                = null;
    private $anio                = null;
    private $sortCategory        = null;
    private $sortField           = 'ROWNUM_';
    private $filterField         = null;
    private $downloadAction      = false;
    private $sortType            = 'desc'; # desc
    private $offset              = 1;
    private $limit               = 25;
    private $searchField         = null;
    private $startDate           = null;
    private $endDate             = null;
    private $foto_dummy          = 'assets/doctores/doc.jpg';
    private $_conexion           = null;
    private $_medicos_first_load = array();

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

    }

    private function getAuthorizationn()
    {

        try {

            global $http;

            $token = $http->headers->get("Authorization");

            $auth = new Model\Auth;
            $key  = $auth->GetData($token);

            $this->USER = $key;

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function errorsPagination()
    {

        try {

            if ($this->limit > 25) {
                throw new ModelsException('!Error! Solo se pueden mostrar 100 resultados por página.');
            }

            if ($this->limit == 0 or $this->limit < 0) {
                throw new ModelsException('!Error! {Limit} no puede ser 0 o negativo');
            }

            if ($this->offset == 0 or $this->offset < 0) {
                throw new ModelsException('!Error! {Offset} no puede ser 0 o negativo.');
            }

            return false;
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function setParameters()
    {

        try {

            global $http;

            foreach ($http->request->all() as $key => $value) {
                $this->$key = $value;
            }

            if ($this->startDate != null and $this->endDate != null) {

                $startDate = $this->startDate;
                $endDate   = $this->endDate;

                $sd = new DateTime($startDate);
                $ed = new DateTime($endDate);

                if ($sd->getTimestamp() > $ed->getTimestamp()) {
                    throw new ModelsException('!Error! Fecha inicial no puede ser mayor a fecha final.');
                }

            }

            $fecha      = date('d-m-Y');
            $nuevafecha = strtotime('-12 month', strtotime($fecha));

            # SETEAR FILTRO HASTA 12 MESES
            $this->anio = date('d-m-Y', $nuevafecha);

            if ($this->sortCategory != null) {
                $this->sortCategory = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->sortCategory), 'UTF-8'));
            }

            if ($this->searchField != null) {
                $this->searchField = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->searchField), 'UTF-8'));
            }

            return false;
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    public function getNotas_Credito()
    {

        try {

            global $http;

            # SETEAR VARIABLES DE CLASE
            $this->setParameters();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # EXTRAER VALOR DEL TOKEN PARA CONSULTA
            $this->getAuthorizationn();

            # SETEAR CODIGOS DE MEDICOS
            $codes = implode(',', $this->USER->CP_PRO);

            # NO EXITEN RESULTADOS
            $this->notResults($this->USER->CP_PRO);

            # CONULTA BDD GEMA
            if ($this->sortField == 'FECHA_REGISTRO' and $this->startDate != null and $this->endDate != null) {

                $sql = "SELECT cp_vw_nc_prov.*, ROWNUM AS ROWNUM_ FROM cp_vw_nc_prov WHERE CODIGO_PERSONA IN ($codes) AND $this->sortField >= TO_DATE('$this->startDate', 'dd-mm-yyyy') AND $this->sortField <= TO_DATE('$this->endDate', 'dd-mm-yyyy') ORDER BY ROWNUM_  $this->sortType ";

            } elseif ($this->searchField != null) {

                $sql = "SELECT cp_vw_nc_prov.*, ROWNUM AS ROWNUM_ FROM cp_vw_nc_prov WHERE CODIGO_PERSONA IN ($codes) AND (SERIE LIKE '%$this->searchField%' OR FACTURA LIKE '%$this->searchField%' OR MONTO LIKE '%$this->searchField%') ORDER BY ROWNUM_ $this->sortType ";

            } else {

                $sql = "SELECT cp_vw_nc_prov.*, ROWNUM AS ROWNUM_ FROM cp_vw_nc_prov WHERE CODIGO_PERSONA IN ($codes) ORDER BY ROWNUM_ $this->sortType";

            }

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $notas = array();

            foreach ($data as $key) {
                $key['FACT']           = $key['FACTURA'];
                $key['FECHA_REGISTRO'] = date('d-m-Y', strtotime($key['FECHA_REGISTRO']));
                unset($key['ROWNUM_']);
                unset($key['NO_PROVE']);
                unset($key['RUC_CED']);
                unset($key['CODIGO_PERSONA']);
                unset($key['FACTURA']);
                $notas[] = $key;
            }

            // RESULTADO DE CONSULTA

            # Order by asc to desc
            $NOTAS_DE_CREDITO = $this->get_Order_Pagination($notas);

            # Devolver Información
            return array(
                'status' => true,
                'data'   => $this->get_page($NOTAS_DE_CREDITO, $this->offset, $this->limit),
                'total'  => count($notas),
                'limit'  => intval($this->limit),
                'offset' => intval($this->offset),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status'    => true,
                    'data'      => [],
                    'total'     => 0,
                    'message'   => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getPagos()
    {

        try {

            global $http;

            # SETEAR VARIABLES DE CLASE
            $this->setParameters();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # EXTRAER VALOR DEL TOKEN PARA CONSULTA
            $this->getAuthorizationn();

            # SETEAR CODIGOS DE MEDICOS
            $codes = implode(',', $this->USER->CP_PRO);

            # NO EXITEN RESULTADOS
            $this->notResults($this->USER->CP_PRO);

            # Si es para descaragr infomarcion o no
            if (false == $this->downloadAction) {

                # CONULTA BDD GEMA
                if ($this->startDate != null and $this->endDate != null) {

                    $sql = "SELECT
                m.tipo_doc as tipo_documento,
                m.no_fisico as documento,
                m.fecha_documento as fecha_documento,
                m.saldo as debito,
                NVL(r.MONTO,0) as credito,
                D.TIPO_DOC as tipo_pago,
                D.NO_FISICO as id_pago,
                D.FECHA as fecha_pago,
                NVL(r.MONTO,0) as abono,
                m.saldo as saldo,
                ROWNUM AS ROWNUM_
                FROM ARCPMD D, ARCPRD r, arcptd td, arcpmd m,arcptd t, arcpmp a
                WHERE D.IND_ACT != 'P'
                AND td.tipo_doc = d.tipo_doc
                AND t.tipo_doc = m.tipo_doc
                AND D.NO_DOCU   = r.NO_DOCU
                AND r.no_refe = m.no_docu
                AND d.anulado = 'N'
                AND m.anulado = 'N'
                AND A.CODIGO_PERSONA IN ($codes)
                AND m.fecha_documento >= TO_DATE('$this->startDate', 'dd-mm-yyyy')
                AND m.fecha_documento <= TO_DATE('$this->endDate', 'dd-mm-yyyy')
                and d.no_prove = a.no_prove
                ORDER BY FECHA_DOCUMENTO $this->sortType";

                } elseif ($this->searchField != null) {

                    $sql = "SELECT
                m.tipo_doc as tipo_documento,
                m.no_fisico as documento,
                m.fecha_documento as fecha_documento,
                m.saldo as debito,
                NVL(r.MONTO,0) as credito,
                D.TIPO_DOC as tipo_pago,
                D.NO_FISICO as id_pago,
                D.FECHA as fecha_pago,
                NVL(r.MONTO,0) as abono,
                m.saldo as saldo,
                ROWNUM AS ROWNUM_
                FROM ARCPMD D, ARCPRD r, arcptd td, arcpmd m,arcptd t, arcpmp a
                WHERE D.IND_ACT != 'P'
                AND td.tipo_doc = d.tipo_doc
                AND t.tipo_doc = m.tipo_doc
                AND D.NO_DOCU   = r.NO_DOCU
                AND r.no_refe = m.no_docu
                AND d.anulado = 'N'
                AND m.anulado = 'N'
                AND A.CODIGO_PERSONA IN ($codes)
                AND D.FECHA >= TO_DATE('$this->anio', 'dd-mm-yyyy')
                and d.no_prove = a.no_prove
                AND (
                m.tipo_doc LIKE '%$this->searchField%'
                OR m.no_fisico LIKE '%$this->searchField%'
                OR  m.saldo LIKE '%$this->searchField%'
                OR NVL(r.MONTO,0) LIKE '%$this->searchField%'
                OR D.TIPO_DOC  LIKE '%$this->searchField%'
                OR D.NO_FISICO LIKE '%$this->searchField%'
                OR NVL(r.MONTO,0) LIKE '%$this->searchField%'
                OR m.saldo LIKE '%$this->searchField%'
                )
                ORDER BY FECHA_DOCUMENTO $this->sortType";

                } else {

                    $sql = "SELECT
                m.tipo_doc as tipo_documento,
                m.no_fisico as documento,
                m.fecha_documento as fecha_documento,
                m.saldo as debito,
                NVL(r.MONTO,0) as credito,
                D.TIPO_DOC as tipo_pago,
                D.NO_FISICO as id_pago,
                D.FECHA as fecha_pago,
                NVL(r.MONTO,0) as abono,
                m.saldo as saldo,
                ROWNUM AS ROWNUM_
                FROM ARCPMD D, ARCPRD r, arcptd td, arcpmd m,arcptd t, arcpmp a
                WHERE D.IND_ACT != 'P'
                AND td.tipo_doc = d.tipo_doc
                AND t.tipo_doc = m.tipo_doc
                AND D.NO_DOCU   = r.NO_DOCU
                AND r.no_refe = m.no_docu
                AND d.anulado = 'N'
                AND m.anulado = 'N'
                AND A.CODIGO_PERSONA IN ($codes)
                AND D.FECHA >= TO_DATE('$this->anio', 'dd-mm-yyyy')
                and d.no_prove = a.no_prove
                ORDER BY FECHA_DOCUMENTO $this->sortType";

                }

                # Conectar base de datos
                $this->conectar_Oracle();

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $data = $stmt->fetchAll();

                # NO EXITEN RESULTADOS
                $this->notResults($data);

                # Datos de usuario cuenta activa
                $pagos = array();

                foreach ($data as $key) {

                    $key['FECHA_PAGO']      = date('d-m-Y', strtotime($key['FECHA_PAGO']));
                    $key['FECHA_DOCUMENTO'] = date('d-m-Y', strtotime($key['FECHA_DOCUMENTO']));

                    $key['DOCUMENTO'] = $key['TIPO_DOCUMENTO'] . '-' . $key['DOCUMENTO'];

                    # cluster pagos
                    if ($key['TIPO_PAGO'] == 'ND') {
                        $key['TIPO_PAGO'] = 'NC';
                        $key['CREDITO']   = -$key['CREDITO'];
                        $key['ABONO']     = -$key['ABONO'];
                    }

                    if ($key['TIPO_PAGO'] == 'CK' || $key['TIPO_PAGO'] == 'TR') {
                        $key['CREDITO'] = -$key['CREDITO'];
                        $key['ABONO']   = -$key['ABONO'];
                    }

                    $pagos[] = $key;
                }

                // RESULTADO DE CONSULTA

                # Order by asc to desc
                $_PAGOS = $this->get_Order_Pagination($pagos);

                # Devolver Información
                return array(
                    'status' => true,
                    'data'   => $this->get_page($_PAGOS, $this->offset, $this->limit),
                    'total'  => count($pagos),
                    'limit'  => intval($this->limit),
                    'offset' => intval($this->offset),
                );

            } else {

                $sql = "SELECT
                m.tipo_doc as tipo_documento,
                m.no_fisico as documento,
                m.fecha_documento as fecha_documento,
                m.saldo as debito,
                NVL(r.MONTO,0) as credito,
                D.TIPO_DOC as tipo_pago,
                D.NO_FISICO as id_pago,
                D.FECHA as fecha_pago,
                NVL(r.MONTO,0) as abono,
                m.saldo as saldo,
                ROWNUM AS ROWNUM_
                FROM ARCPMD D, ARCPRD r, arcptd td, arcpmd m,arcptd t, arcpmp a
                WHERE D.IND_ACT != 'P'
                AND td.tipo_doc = d.tipo_doc
                AND t.tipo_doc = m.tipo_doc
                AND D.NO_DOCU   = r.NO_DOCU
                AND r.no_refe = m.no_docu
                AND d.anulado = 'N'
                AND m.anulado = 'N'
                AND A.CODIGO_PERSONA IN ($codes)
                AND D.FECHA >= TO_DATE('$this->anio', 'dd-mm-yyyy')
                and d.no_prove = a.no_prove
                ORDER BY FECHA_DOCUMENTO DESC";

                # Conectar base de datos
                $this->conectar_Oracle();

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $data = $stmt->fetchAll();

                # NO EXITEN RESULTADOS
                $this->notResults($data);

                # Datos de usuario cuenta activa
                $pagos = array();

                foreach ($data as $key) {

                    $key['FECHA_PAGO']      = date('d-m-Y', strtotime($key['FECHA_PAGO']));
                    $key['FECHA_DOCUMENTO'] = date('d-m-Y', strtotime($key['FECHA_DOCUMENTO']));

                    $pagos[] = $key;
                }

                // RESULTADO DE CONSULTA

                # Order by asc to desc
                $_PAGOS = $this->get_Order_Pagination($pagos);

                $total_pagos = count($pagos);

                # Devolver Información
                return array(
                    'status' => true,
                    'data'   => $_PAGOS,
                    'total'  => $total_pagos,
                    // 'limit'  => $total_pagos,
                    //  'offset' => 1,
                );

            }

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status'    => true,
                    'data'      => [],
                    'total'     => 0,
                    'message'   => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getFacturas()
    {

        try {

            global $http;

            # SETEAR VARIABLES DE CLASE
            $this->setParameters();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # EXTRAER VALOR DEL TOKEN PARA CONSULTA
            $this->getAuthorizationn();

            # SETEAR CODIGOS DE MEDICOS
            $codes = implode(',', $this->USER->CP_PRO);

            # NO EXITEN RESULTADOS
            $this->notResults($this->USER->CP_PRO);

            # CONULTA BDD GEMA
            if (($this->sortField == 'FECHA_REGISTRO' or $this->sortField == 'FECHA_PAGO') and $this->startDate != null and $this->endDate != null) {

                $sql = "SELECT cp_vw_facturas_prov.*, ROWNUM AS ROWNUM_ FROM cp_vw_facturas_prov WHERE CODIGO_PERSONA IN ($codes) AND $this->sortField >= TO_DATE('$this->startDate', 'dd-mm-yyyy') AND $this->sortField <= TO_DATE('$this->endDate', 'dd-mm-yyyy') AND FECHA_PAGO IS NOT NULL ORDER BY ROWNUM_  $this->sortType ";

            } elseif ($this->searchField != null) {

                $sql = "SELECT cp_vw_facturas_prov.*, ROWNUM AS ROWNUM_ FROM cp_vw_facturas_prov WHERE
                CODIGO_PERSONA IN ($codes) AND
                (
                SERIE LIKE '%$this->searchField%'
                OR FACTURA LIKE '%$this->searchField%'
                OR NOMBRE LIKE '%$this->searchField%'
                OR SALDO_FACTURA LIKE '%$this->searchField%'
                OR MONTO LIKE '%$this->searchField%'
                )
                AND FECHA_PAGO IS NOT NULL ORDER BY ROWNUM_  $this->sortType ";

            } else {

                $sql = "SELECT cp_vw_facturas_prov.*, ROWNUM AS ROWNUM_ FROM cp_vw_facturas_prov WHERE CODIGO_PERSONA IN ($codes) AND FECHA_PAGO IS NOT NULL ORDER BY ROWNUM_ $this->sortType";

            }

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $notas = array();

            foreach ($data as $key) {
                $key['FACT']           = $key['FACTURA'];
                $key['FECHA_REGISTRO'] = date('d-m-Y', strtotime($key['FECHA_REGISTRO']));
                $key['FECHA_PAGO']     = ($key['FECHA_PAGO'] == null) ? '' : date('d-m-Y', strtotime($key['FECHA_PAGO']));

                unset($key['ROWNUM_']);
                unset($key['NO_PROVE']);
                unset($key['RUC_CED']);
                unset($key['CODIGO_PERSONA']);
                unset($key['FACTURA']);
                $notas[] = $key;
            }

            // RESULTADO DE CONSULTA

            # Order by asc to desc
            $NOTAS_DE_CREDITO = $this->get_Order_Pagination($notas);

            # Devolver Información
            return array(
                'status' => true,
                'data'   => $this->get_page($NOTAS_DE_CREDITO, $this->offset, $this->limit),
                'total'  => count($notas),
                'limit'  => intval($this->limit),
                'offset' => intval($this->offset),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status'    => true,
                    'data'      => [],
                    'total'     => 0,
                    'message'   => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getRetenciones()
    {

        try {

            global $config, $http;

            # SETEAR VARIABLES DE CLASE
            $this->setParameters();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # EXTRAER VALOR DEL TOKEN PARA CONSULTA
            $this->getAuthorizationn();

            # SETEAR CODIGOS DE MEDICOS
            $codes = implode(',', $this->USER->CP_PRO);

            # NO EXITEN RESULTADOS
            $this->notResults($this->USER->CP_PRO);

            # CONULTA BDD GEMA
            if ($this->searchField != null) {

                $sql = "SELECT cp_vw_retenciones_prov.*, ROWNUM AS ROWNUM_ FROM cp_vw_retenciones_prov WHERE
                COD_PERSONA IN ($codes) AND
                (
                NOMBRE LIKE '%$this->searchField%'
                OR NO_FISICO LIKE '%$this->searchField%'
                OR NUMERO_RETENCION LIKE '%$this->searchField%'
                OR CLAVE LIKE '%$this->searchField%'
                OR BASE LIKE '%$this->searchField%'
                OR PORCENTAJE LIKE '%$this->searchField%'
                OR MONTO LIKE '%$this->searchField%'
                ) ORDER BY ROWNUM_  $this->sortType ";

            } else {

                $sql = "SELECT cp_vw_retenciones_prov.*, ROWNUM AS ROWNUM_ FROM cp_vw_retenciones_prov WHERE COD_PERSONA IN ($codes) ORDER BY ROWNUM_ $this->sortType";

            }

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $notas = array();

            foreach ($data as $key) {

                $serie_retencion = str_pad($key['NUMERO_RETENCION'], 9, "0", STR_PAD_LEFT);

                $filename = '001-142-' . $serie_retencion . '.pdf';

                $key['_PDF'] = $config['api']['url'] . 'documentos/proveedores/' . $filename;

                unset($key['ROWNUM_']);
                unset($key['NO_PROVE']);
                unset($key['RUC_CED']);
                unset($key['CODIGO_PERSONA']);

                $notas[] = $key;
            }

            // RESULTADO DE CONSULTA

            # Order by asc to desc
            $NOTAS_DE_CREDITO = $this->get_Order_Pagination($notas);

            # Devolver Información
            return array(
                'status' => true,
                'data'   => $this->get_page($NOTAS_DE_CREDITO, $this->offset, $this->limit),
                'total'  => count($notas),
                'limit'  => intval($this->limit),
                'offset' => intval($this->offset),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status'    => true,
                    'data'      => [],
                    'total'     => 0,
                    'message'   => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    private function getRetencionProv($filename = '001-143-000056876.pdf')
    {

        global $config;

        $doc = $config['build']['ducumentosRetencionesProv'] . $filename;

        # Verificar si existe documento
        $doc = file_exists($doc);

        if (false === $doc) {
            return false;
        }

        # retornar valores de pdf final
        return $config['api']['url'] . 'documentos/proveedores/' . $filename;

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

    public function shuffle_assoc($array)
    {
        $keys = array_keys($array);

        shuffle($keys);

        foreach ($keys as $key) {
            $new[$key] = $array[$key];
        }

        $array = $new;

        return $array;
    }

    private function quitar_tildes($cadena)
    {
        $no_permitidas = array("%", "é", "í", "ó", "ú", "É", "Í", "Ó", "Ú", "ñ", "À", "Ã", "Ì", "Ò", "Ù", "Ã™", "Ã ", "Ã¨", "Ã¬", "Ã²", "Ã¹", "ç", "Ç", "Ã¢", "ê", "Ã®", "Ã´", "Ã»", "Ã‚", "ÃŠ", "ÃŽ", "Ã”", "Ã›", "ü", "Ã¶", "Ã–", "Ã¯", "Ã¤", "«", "Ò", "Ã", "Ã„", "Ã‹");
        $permitidas    = array("", "e", "i", "o", "u", "E", "I", "O", "U", "n", "N", "A", "E", "I", "O", "U", "a", "e", "i", "o", "u", "c", "C", "a", "e", "i", "o", "u", "A", "E", "I", "O", "U", "u", "o", "O", "i", "a", "e", "U", "I", "A", "E");
        $texto         = str_replace($no_permitidas, $permitidas, $cadena);
        return $texto;
    }

    private function sanear_string($string)
    {

        $string = trim($string);

        //Esta parte se encarga de eliminar cualquier caracter extraño
        $string = str_replace(
            array(">", "< ", ";", ",", ":", " ", "%"),
            ' ',
            $string
        );

        return trim($string);
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
        $this->startDBConexion();

    }
}
