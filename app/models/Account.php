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
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Account
 */

class Account extends Models implements IModels
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
    private $_conexion = null;
    private $proveedor = null;

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

        try {

            global $http;

            foreach ($http->request->all() as $key => $value) {
                $this->$key = $value;
            }

            return false;
        } catch (ModelsException $e) {
            throw new ModelsException($e->getMessage(), 0);
        }
    }

    public function getAccount()
    {

        try {

            # EXTRAER VALOR CEDULA DEL TOKEN PARA CONSULTA
            $this->getAuthorizationn();

            if ($this->USER->PRO == 1) {

                # Conectar base de datos
                $this->conectar_Oracle();

                $COD_PERSONA = $this->USER->COD_PERSONA;

                # Query
                $sql = " SELECT * FROM CP_VW_DATOS_PROV
                WHERE CODIGO_PERSONA = '$COD_PERSONA'  ";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                # Datos de usuario cuenta activa
                $data = $stmt->fetch();

                # SI ES PROVEEDOR
                $pte['APELLIDOS'] = '.';
                $pte['NOMBRES'] = $data['NOMBRE'];
                $pte['DIRECCIONES'] = array($data['DIRECCION']);
                $pte['TELEFONOS'] = array($data['TELEFONO1'], $data['TELEFONO2']);
                $pte['CELULARES'] = array($data['TELEFONO1'], $data['TELEFONO2']);
                $pte['EMAIL_ACCOUNT'] = $this->getEmailAccount($this->USER->COD_PERSONA);

                $pte['FACTURACION'] = array(
                    'DNI' => $this->USER->DNI,
                    'USER' => $data['NOMBRE'],
                    'EMAIL' => $pte['EMAIL_ACCOUNT'],
                    'DIR' => $pte['DIRECCIONES'][0],
                    'CELULAR' => $pte['CELULARES'][0],
                    'CIUDAD' => '',
                );

                # vERIFICAR SI ES MÉDICO
                if ($this->USER->MED == 1) {

                    $PERFIL_MEDICO = new Model\Medicos;
                    $PERFIL = $PERFIL_MEDICO->getPerfil_Medico($this->USER->CP_MED[0]);

                    # Si es médico devolver los datos del medico
                    if ($PERFIL['status']) {
                        $pte['PERFIL_MEDICO'] = $PERFIL['data'];
                    }

                }

                unset($data['PK_NHCL']);
                unset($data['FK_PERSONA']);
                unset($data['ROWNUM_']);
                unset($data['EMAILS']);

                # RESULTADO OBJETO
                return array(
                    'status' => true,
                    'data' => $pte,
                );

            } else {

                # Conectar base de datos
                $this->conectar_Oracle();

                # Setear valores
                $COD_PERSONA = $this->USER->COD_PERSONA;

                # Query

                $sql = " SELECT * FROM WEB_VW_PERSONAS
                WHERE FK_PERSONA = '$COD_PERSONA'  ";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                # Datos de usuario cuenta activa
                $pte = $stmt->fetch();

                if (false === $pte) {
                    throw new ModelsException('Error No existen elementos.', 4080);
                }

                # PARSEO DE INFORMACION

                unset($pte['PK_NHCL']);
                unset($pte['FK_PERSONA']);
                unset($pte['EMAILS']);

                $DIRECCIONES = array();

                foreach (explode(';', $pte['DIRECCIONES']) as $key => $value) {

                    $FIELD = explode(' ', $value);
                    unset($FIELD[0]);

                    $DIRECCIONES[] = array(
                        'ID' => intval(explode(' ', $value)[0]),
                        'FIELD' => implode(' ', $FIELD),
                    );
                }

                $pte['DIRECCIONES'] = $DIRECCIONES;

                $TELEFONOS = array();

                foreach (explode(';', $pte['TELEFONOS']) as $key => $value) {

                    $FIELD = explode(' ', $value);
                    unset($FIELD[0]);

                    $TELEFONOS[] = array(
                        'ID' => intval(explode(' ', $value)[0]),
                        'FIELD' => implode(' ', $FIELD),
                    );
                }

                $pte['TELEFONOS'] = $TELEFONOS;

                $CELULARES = array();

                foreach (explode(';', $pte['CELULARES']) as $key => $value) {

                    $FIELD = explode(' ', $value);
                    unset($FIELD[0]);

                    $CELULARES[] = array(
                        'ID' => intval(explode(' ', $value)[0]),
                        'FIELD' => implode(' ', $FIELD),
                    );
                }

                $pte['CELULARES'] = $CELULARES;

                $pte['EMAIL_ACCOUNT'] = $this->getEmailAccount($this->USER->COD_PERSONA);

                $pte['FACTURACION'] = array(
                    'DNI' => $this->USER->DNI,
                    'USER' => $pte['APELLIDOS'] . ' ' . $pte['NOMBRES'],
                    'EMAIL' => $pte['EMAIL_ACCOUNT'],
                    'DIR' => $pte['DIRECCIONES'][0]['FIELD'],
                    'CELULAR' => $pte['CELULARES'][0]['FIELD'],
                    'CIUDAD' => '',
                );

                # vERIFICAR SI ES MÉDICO

                if ($this->USER->MED == 1) {

                    $PERFIL_MEDICO = new Model\Medicos;
                    $PERFIL = $PERFIL_MEDICO->getPerfil_Medico($this->USER->CP_MED[0]);

                    # Si es médico devolver los datos del medico
                    if ($PERFIL['status']) {
                        $pte['PERFIL_MEDICO'] = $PERFIL['data'];
                    }

                }

                # RESULTADO OBJETO
                return array(
                    'status' => true,
                    'data' => $pte,
                );
            }

        } catch (Exception $e) {

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

    public function getHistorialPaciente(): array
    {

        try {

            # SETEAR VARIABLES DE CLASE
            $this->setParameters();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # EXTRAER VALOR DEL TOKEN PARA CONSULTA
            $this->getAuthorizationn();

            # Setear valores
            $codes = array_merge($this->CP_PTE, $this->CP_MED, $this->CP_PRO);
            $codes = implode(',', $codes);

            # CONULTA BDD GEMA
            if ($this->startDate != null and $this->endDate != null and $this->sortField === 'FECHA_ADMISION') {

                $sql = "SELECT WEB_VW_ATENCIONES.*, ROWNUM AS ROWNUM_ FROM WEB_VW_ATENCIONES WHERE HCL='$this->USER' AND FECHA_ADMISION >= TO_DATE('$this->startDate', 'dd-mm-yyyy') AND FECHA_ADMISION <= TO_DATE('$this->endDate', 'dd-mm-yyyy') ORDER BY $this->sortField $this->sortType ";

            } elseif ($this->startDate != null and $this->endDate != null and $this->sortField === 'FECHA_ALTA') {

                $sql = "SELECT WEB_VW_ATENCIONES.*, ROWNUM AS ROWNUM_ FROM WEB_VW_ATENCIONES WHERE HCL='$this->USER' AND FECHA_ALTA >= TO_DATE('$this->startDate', 'dd-mm-yyyy') AND FECHA_ALTA <= TO_DATE('$this->endDate', 'dd-mm-yyyy') ORDER BY $this->sortField $this->sortType ";

            } elseif ($this->searchField != null) {

                $sql = "SELECT WEB_VW_ATENCIONES.*, ROWNUM AS ROWNUM_ FROM WEB_VW_ATENCIONES WHERE HCL='$this->USER' AND (ORIGEN_ATENCION LIKE '%$this->searchField%' OR ESPECIALIDAD LIKE '%$this->searchField%' OR MEDICO_TRATANTE LIKE '%$this->searchField%') ORDER BY ROWNUM_ $this->sortType ";

            } else {

                $sql = "SELECT WEB_VW_ATENCIONES.*, ROWNUM AS ROWNUM_ FROM WEB_VW_ATENCIONES WHERE HCL='$this->USER' ORDER BY FECHA_ADMISION  $this->sortType ";

            }

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # Cerrar conexion
            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $historial = array();

            $NUM = 1; // ITERADO

            foreach ($stmt->fetchAll() as $key) {

                $key['NUM'] = $key['ROWNUM_'];
                $key['NHC'] = $key['HCL'];
                $key['ADM'] = $key['ADMISION'];

                $key['FECHA_ADMISION'] = date('d-m-Y', strtotime($key['FECHA_ADMISION']));
                $key['FECHA_ALTA'] = date('d-m-Y', strtotime($key['FECHA_ALTA']));

                unset($key['HCL']);
                unset($key['ADMISION']);
                unset($key['ROWNUM_']);

                $historial[] = $key;

            }

            // RESULTADO DE CONSULTA

            # Order by asc to desc
            $ATENCIONES = $this->get_Order_Pagination($historial);

            # Ya no existe resultadso
            if (count($historial) == 0) {
                throw new ModelsException('No existe más resultados.', 4080);
            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->get_page($ATENCIONES, $this->offset, $this->limit),
                'total' => count($historial),
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

    public function getEmailAccount($fk_persona)
    {

        try {

            # Devolver valores si es proveedor para regsitrar

            # Conectar base de datos
            $this->conectar_Oracle();

            # Query
            $sql = " SELECT EMAILS AS EMAILS
                FROM WEB_VW_PERSONAS
                WHERE FK_PERSONA = '$fk_persona' ";

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $pacientes = $stmt->fetch();

            # Conectar base de datos
            $this->conectar_Oracle();

            # Query
            $sql = "SELECT EMAIL_EMP AS EMAIL_EMP,
                EMAIL_PERS AS EMAIL_PERS
                FROM CP_VW_DATOS_PROV
                WHERE CODIGO_PERSONA = '$fk_persona' ";

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $proveedores = $stmt->fetch();

            if ((false === $pacientes || is_null($pacientes['EMAILS'])) && false === $proveedores) {
                throw new ModelsException($this->errors['notEmailRegister']['message'], $this->errors['notEmailRegister']['code']);
            }

            $emails_proveedores = array($proveedores['EMAIL_EMP'], $proveedores['EMAIL_PERS']);

            $emails_pacientes = explode(';', $pacientes['EMAILS']);

            $_emails_pacientes = array();

            foreach ($emails_pacientes as $key => $value) {

                if (empty($value)) {
                    unset($key);
                } else {
                    $_emails_pacientes[] = explode(' ', $value)[1];
                }
            }

            $emails = array_merge($emails_proveedores, $_emails_pacientes);

            # SETEAR VALORES DE CORREO ELECTRONICO PARA PROVEEDORES
            $data_emails_for_register = array();

            # Extraer correos electrónicos para porterior registro de cuenta electrónica
            foreach ($emails as $key) {

                if (empty($key)) {
                    unset($key);
                } else {
                    $data_emails_for_register[] = $key;
                }

            }

            return array_values(array_unique($data_emails_for_register));

        } catch (ModelsException $e) {
            return false;
        }
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

/**
 * __construct()
 */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
    }
}
