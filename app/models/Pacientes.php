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
 * Modelo Pacientes
 */

class Pacientes extends Models implements IModels
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

    private function errorsValidacion()
    {

        $dni = new Model\ValidacionDNI;
        # si es cédula
        if (ctype_digit(Helper\Strings::remove_spaces($this->val))) {

            #validar si es ruc o si es cedula normal
            if (strlen(Helper\Strings::remove_spaces($this->val)) == 10) {
                if (!$dni->validarCedula($this->val)) {
                    throw new ModelsException('!Error! Cédula ingresada no es válida.', 4003);
                }
            }

            # si documento estrangero
            if ((strlen(Helper\Strings::remove_spaces($this->val)) > 13 and
                strlen(Helper\Strings::remove_spaces($this->val)) > 25)) {
                throw new ModelsException('!Error! Documento extrangero no puede ser mayor que 25 caracteres.', 4005);
            }

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

    public function getHistorialPaciente(): array
    {

        try {

            # SETEAR VARIABLES DE CLASE
            $this->setParameters();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # EXTRAER VALOR DEL TOKEN PARA CONSULTA
            $this->getAuthorizationn();

            # eXTRAER CODIGOS DE cp_pte
            $codes = implode(',', $this->USER->CP_PTE);

            # NO EXITEN RESULTADOS
            $this->notResults($this->USER->CP_PTE);

            # CONULTA BDD GEMA
            if ($this->startDate != null and $this->endDate != null and $this->sortField === 'FECHA_ADMISION') {

                $sql = "SELECT WEB_VW_ATENCIONES.*, ROWNUM AS ROWNUM_ FROM WEB_VW_ATENCIONES WHERE COD_PERSONA IN ($codes) AND FECHA_ADMISION >= TO_DATE('$this->startDate', 'dd-mm-yyyy') AND FECHA_ADMISION <= TO_DATE('$this->endDate', 'dd-mm-yyyy') ORDER BY $this->sortField $this->sortType ";

            } elseif ($this->startDate != null and $this->endDate != null and $this->sortField === 'FECHA_ALTA') {

                $sql = "SELECT WEB_VW_ATENCIONES.*, ROWNUM AS ROWNUM_ FROM WEB_VW_ATENCIONES WHERE COD_PERSONA IN ($codes) AND FECHA_ALTA >= TO_DATE('$this->startDate', 'dd-mm-yyyy') AND FECHA_ALTA <= TO_DATE('$this->endDate', 'dd-mm-yyyy') ORDER BY $this->sortField $this->sortType ";

            } elseif ($this->searchField != null) {

                $sql = "SELECT WEB_VW_ATENCIONES.*, ROWNUM AS ROWNUM_ FROM WEB_VW_ATENCIONES WHERE COD_PERSONA IN ($codes) AND (ORIGEN_ATENCION LIKE '%$this->searchField%' OR ESPECIALIDAD LIKE '%$this->searchField%' OR MEDICO_TRATANTE LIKE '%$this->searchField%') ORDER BY ROWNUM_ $this->sortType ";

            } else {

                $sql = "SELECT WEB_VW_ATENCIONES.*, ROWNUM AS ROWNUM_ FROM WEB_VW_ATENCIONES WHERE COD_PERSONA IN ($codes) ORDER BY FECHA_ADMISION  $this->sortType ";

            }

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # Cerrar conexion
            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $historial = array();

            $NUM = 1; // ITERADO

            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            foreach ($data as $key) {

                $key['HCU'] = $key['HCL'];
                $key['NUM'] = $key['ROWNUM_'];
                $key['ADM'] = $key['ADMISION'];

                unset($key['ADMISION']);
                unset($key['ROWNUM_']);
                unset($key['HCL']);
                unset($key['COD_PERSONA']);

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
                    'status' => false,
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

            # paciente ya tiene cuenta electrónica
            $sql = "SELECT AAS_CLAVES_WEB.*, ROWNUM as ROWNUM_ FROM AAS_CLAVES_WEB WHERE PK_FK_PERSONA='$fk_persona'";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # Cerrar conexion
            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $pte = $stmt->fetch();

            if (false === $pte) {
                throw new ModelsException('!Error! Usuario ya tiene una cuenta electrónica registrada.', 4010);
            }

            # valor final
            return $pte['CORREO'];

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
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
