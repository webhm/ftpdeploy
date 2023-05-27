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
 * Modelo Emergencia
 */

class Emergencia extends Models implements IModels
{
    # Variables de clase
    private $tipoBusqueda = null;
    private $start = 0;
    private $length = 10;
    private $searchField = null;
    private $startDate = null;
    private $endDate = null;
    private $_conexion = null;

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();

        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

    }

    private function conectar_Oracle_MV()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();

        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle_mv'], $_config);

    }

    private function getAuthorizationn()
    {

        try {

            global $http;

            $token = $http->headers->get("Authorization");

            $auth = new Model\Auth;
            $key = $auth->GetData($token);

            $this->user = $key;

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function setParameters()
    {

        global $http;

        foreach ($http->request->all() as $key => $value) {
            $this->$key = $value;
        }
    }

    public function getStatusPaciente_Emergencia()
    {

        try {

            global $http;

            $nhc = $http->request->get('numeroHistoriaClinica');

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Devolver todos los resultados
            $sql = " SELECT a.discriminante, TRUNC (a.fecha_admision) fecha_admision, a.pk_numero_admision nro_admision, a.pk_fk_paciente hc, e.fk_persona COD_PERSONA,

            fun_calcula_anios_a_fecha(f.fecha_nacimiento,TRUNC(a.fecha_admision)) edad,

            f.primer_apellido || ' ' || f.segundo_apellido || ' ' || f.primer_nombre || ' ' || f.segundo_nombre nombre_paciente,

            b.pk_fk_medico cod_medico, fun_busca_nombre_medico(b.pk_fk_medico) nombre_medico, d.descripcion especialidad,

            fun_busca_ubicacion_corta(1,a.pk_fk_paciente,a.pk_numero_admision) nro_habitacion,

            fun_busca_diagnostico(1,a.pk_fk_paciente, a.pk_numero_admision) dg_principal

            FROM cad_admisiones a, cad_medicos_admision b, edm_medicos_especialidad c, aas_especialidades d, cad_pacientes e, bab_personas f

            WHERE a.alta_clinica         IS NULL            AND

                  a.pre_admision         = 'N'              AND

                  a.anulado              = 'N'              AND

                  a.discriminante        IN ('EMA','HPN')   AND

                  a.pk_fk_paciente       = b.pk_fk_paciente AND

                  a.pk_numero_admision   = b.pk_fk_admision AND

                  b.clasificacion_medico = 'TRA'            AND

                  b.pk_fk_medico         = c.pk_fk_medico   AND

                  c.principal            = 'S'              AND

                  c.pk_fk_especialidad   = d.pk_codigo      AND

                  a.pk_fk_paciente       = e.pk_nhcl        AND

                  e.fk_persona           = f.pk_codigo      AND

                  e.pk_nhcl =  '$nhc' ";

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            if ($data === false) {
                throw new ModelsException('No existe información disponbile.');

            }

            return array(
                'status' => true,
                'data' => $data,
            );

        } catch (ModelsException $e) {
            return array('status' => false, 'data' => [], 'message' => $e->getMessage());
        }

    }

    public function getSVPaciente_Emergencia()
    {

        try {

            global $http;

            $nhc = $http->request->get('numeroHistoriaClinica');

            $nhc = substr($nhc, 0, -2);

            # Conectar base de datos
            $this->conectar_Oracle_MV();

            $this->setSpanishOracle();

            # Devolver todos los resultados
            $sql = " SELECT * FROM HMETRO.V_SIGNOSVITALES WHERE CD_PACIENTE = '$nhc' ORDER BY ATENCION DESC";

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            if ($data == false) {
                throw new ModelsException('No existe información disponbile.');
            }

            $numAtencion = $data['ATENCION'];

            $sql = " SELECT * FROM HMETRO.V_SIGNOSVITALES WHERE CD_PACIENTE = '$nhc' AND ATENCION = '$numAtencion' ORDER BY ATENCION DESC";

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            if (count($data) == 0) {
                throw new ModelsException('No existe información disponbile.');
            }

            # PRESION ARTERIAL SISTOLICA
            $pas = null;
            # PRESION ARTERIAL DIASTOLICA
            $pad = null;
            # FRECUENCIA CARDIACA
            $fc = null;
            # FRECUENCIA RESPIRATORIA
            $fr = null;
            # SATURACION OXIGENO
            $so = null;
            # FIO2
            $fio = null;
            # TEMPERATURA
            $tp = null;
            # LLENADO CAPILAR
            $llc = null;
            # TALLA
            $ta = null;

            $signosVitales = array();

            foreach ($data as $k) {

                if ($k['SIGNO'] == 'PRESION ARTERIAL SISTOLICA') {
                    $pas = $k;
                    $signosVitales[] = $pas;
                }

                if ($k['SIGNO'] == 'PRESION ARTERIAL DIASTOLICA') {
                    $pad = $k;
                    $signosVitales[] = $pad;
                }

                if ($k['SIGNO'] == 'FRECUENCIA CARDIACA') {
                    $fc = $k;
                    $signosVitales[] = $fc;
                }

                if ($k['SIGNO'] == 'FRECUENCIA RESPIRATORIA') {
                    $fr = $k;
                    $signosVitales[] = $fr;
                }

                if ($k['SIGNO'] == 'SATURACION OXIGENO') {
                    $so = $k;
                    $signosVitales[] = $so;
                }

                if ($k['SIGNO'] == 'TEMPERATURA') {
                    $tp = $k;
                    $signosVitales[] = $tp;
                }

                if ($k['SIGNO'] == 'LLENADO CAPILAR') {
                    $llc = $k;
                    $signosVitales[] = $llc;
                }

                if ($k['SIGNO'] == 'TALLA') {
                    $ta = $k;
                    $signosVitales[] = $ta;
                }

            }

            return array(
                'status' => true,
                'data' => $signosVitales,
            );

        } catch (ModelsException $e) {
            return array('status' => false, 'data' => [], 'message' => $e->getMessage());
        }

    }

    public function getFormularios_MV_005()
    {

        try {

            global $config, $http;

            $fecha = date('d-m-Y');

            $nhc = $http->request->get('numeroHistoriaClinica');

            $nhc = substr($nhc, 0, -2);

            $sql = " SELECT *
            FROM (
            SELECT b.*, ROWNUM AS NUM
            FROM (
                SELECT  at.cd_paciente AS NHCL, at.cd_atendimento as ADM, to_date(at.dt_atendimento, 'DD-MM-YYYY')  as FECHA_ADMISION, p.nm_paciente AS PACIENTE
                    from pw_editor_clinico a, -- enlace documento clinico-documento editor
                    pw_documento_clinico b, -- documento clinico
                    editor.editor_registro c, -- documento editor
                    paciente p, -- paciente
                    atendime at, -- atencion
                    prestador pr -- medico
                    where a.cd_documento=104
                    and a.cd_documento_clinico=b.cd_documento_clinico
                    and b.tp_status='FECHADO' and
                    a.cd_editor_registro=c.cd_registro and
                    b.cd_paciente=p.cd_paciente and
                    b.cd_atendimento=at.cd_atendimento and
                    b.cd_prestador=pr.cd_prestador and
                    to_date(at.dt_atendimento, 'DD-MM-YYYY') <= '$fecha' and
                    at.cd_paciente = '$nhc'
                    order by at.cd_atendimento  desc
            ) b
            WHERE ROWNUM <= 10
            )
            WHERE NUM > 0
            ";

            # Conectar base de datos
            $this->conectar_Oracle_MV();

            # Conectar base de datos
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            if ($data === false) {
                throw new ModelsException('No existe información disponbile.');
            }

            $data = array($data);

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $resultados = array();

            foreach ($data as $k) {
                $resultados[] = $k;
            }

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $resultados,
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

        }

    }

    public function getPaciente($nhc)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Devolver todos los resultados
            $sql = " SELECT a.discriminante, TRUNC (a.fecha_admision) fecha_admision, a.pk_numero_admision nro_admision, a.pk_fk_paciente hc, e.fk_persona COD_PERSONA,

            fun_calcula_anios_a_fecha(f.fecha_nacimiento,TRUNC(a.fecha_admision)) edad,

            f.primer_apellido || ' ' || f.segundo_apellido || ' ' || f.primer_nombre || ' ' || f.segundo_nombre nombre_paciente,

            b.pk_fk_medico cod_medico, fun_busca_nombre_medico(b.pk_fk_medico) nombre_medico, d.descripcion especialidad,

            fun_busca_ubicacion_corta(1,a.pk_fk_paciente,a.pk_numero_admision) nro_habitacion,

            fun_busca_diagnostico(1,a.pk_fk_paciente, a.pk_numero_admision) dg_principal

            FROM cad_admisiones a, cad_medicos_admision b, edm_medicos_especialidad c, aas_especialidades d, cad_pacientes e, bab_personas f

            WHERE a.alta_clinica         IS NULL            AND

                  a.pre_admision         = 'N'              AND

                  a.anulado              = 'N'              AND

                  a.discriminante        IN ('HPN','EMA')   AND

                  a.pk_fk_paciente       = b.pk_fk_paciente AND

                  a.pk_numero_admision   = b.pk_fk_admision AND

                  b.clasificacion_medico = 'TRA'            AND

                  b.pk_fk_medico         = c.pk_fk_medico   AND

                  c.principal            = 'S'              AND

                  c.pk_fk_especialidad   = d.pk_codigo      AND

                  a.pk_fk_paciente       = e.pk_nhcl        AND

                  e.fk_persona           = f.pk_codigo      AND

                  e.pk_nhcl =  '$nhc' ";

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            return array(
                'status' => true,
                'data' => $data,
            );

        } catch (ModelsException $e) {
            return array('status' => false, 'data' => [], 'message' => $e->getMessage());
        }

    }

    public function getMisPacientes()
    {

        try {

            global $config, $http;

            $this->getAuthorizationn();

            $oodMedico = $this->user->codMedico;

            # seteo de valores para paginacion
            $fecha = date('d-m-Y');

            $this->start = (int) $http->query->get('start');

            $this->length = (int) $http->query->get('length');

            if ($this->start >= 10) {
                $this->length = $this->start + $this->length;
            }

            $_searchField = (bool) $http->query->get('search')['value'];

            if ($_searchField != false && $this->isRange($http->query->get('search')['value']) === false) {

                $this->searchField = $this->quitar_tildes(mb_strtoupper($this->sanear_string($http->query->get('search')['value']), 'UTF-8'));

                $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (

                    SELECT a.discriminante, TRUNC (a.fecha_admision) fecha_admision, a.pk_numero_admision nro_admision, a.pk_fk_paciente hc,

fun_calcula_anios_a_fecha(f.fecha_nacimiento,TRUNC(a.fecha_admision)) edad,

f.primer_apellido || ' ' || f.segundo_apellido || ' ' || f.primer_nombre || ' ' || f.segundo_nombre nombre_paciente,

b.pk_fk_medico cod_medico, fun_busca_nombre_medico(b.pk_fk_medico) nombre_medico, d.descripcion especialidad,

fun_busca_ubicacion_corta(1,a.pk_fk_paciente,a.pk_numero_admision) nro_habitacion,

fun_busca_diagnostico(1,a.pk_fk_paciente, a.pk_numero_admision) dg_principal

FROM cad_admisiones a, cad_medicos_admision b, edm_medicos_especialidad c, aas_especialidades d, cad_pacientes e, bab_personas f

WHERE a.alta_clinica         IS NULL            AND

      a.pre_admision         = 'N'              AND

      a.anulado              = 'N'              AND

      a.discriminante        IN ('EMA')   AND

      a.pk_fk_paciente       = b.pk_fk_paciente AND

      a.pk_numero_admision   = b.pk_fk_admision AND

      b.clasificacion_medico = 'TRA'            AND

      b.pk_fk_medico         = c.pk_fk_medico   AND

      c.principal            = 'S'              AND

      c.pk_fk_especialidad   = d.pk_codigo      AND

      a.pk_fk_paciente       = e.pk_nhcl        AND

      e.fk_persona           = f.pk_codigo      AND

      b.pk_fk_medico = '996' AND

      (f.primer_apellido || ' ' || f.segundo_apellido || ' ' || f.primer_nombre || ' ' || f.segundo_nombre LIKE '%$this->searchField%' )

      ORDER BY a.fecha_admision DESC

                ) b
                WHERE ROWNUM <= " . $this->length . "
                )
                WHERE NUM > " . $this->start . "
                ";

            } elseif ($_searchField != false && $this->isRange($http->query->get('search')['value'])) {

                $this->searchField = explode('-', $http->query->get('search')['value']);

                $desde = $this->searchField[1]; // Valores para busqueda de desde rango de fechas
                $hasta = $this->searchField[2]; // valor de busqueda para hasta rango de fechas

                $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (
                    SELECT *
                    FROM WEB3_RESULTADOS_LAB NOLOCK WHERE TOT_SC != TOD_DC
                    AND FECHA >= '$desde'
                    AND FECHA <= '$hasta'
                    ORDER BY SC DESC
                ) b
                WHERE ROWNUM <= " . $this->length . "
                )
                WHERE NUM > " . $this->start . "
                ";

            } else {

                $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (
             SELECT a.discriminante, TRUNC (a.fecha_admision) fecha_admision, a.pk_numero_admision nro_admision, a.pk_fk_paciente hc,

            fun_calcula_anios_a_fecha(f.fecha_nacimiento,TRUNC(a.fecha_admision)) edad,

            f.primer_apellido || ' ' || f.segundo_apellido || ' ' || f.primer_nombre || ' ' || f.segundo_nombre nombre_paciente,

            b.pk_fk_medico cod_medico, fun_busca_nombre_medico(b.pk_fk_medico) nombre_medico, d.descripcion especialidad,

            fun_busca_ubicacion_corta(1,a.pk_fk_paciente,a.pk_numero_admision) nro_habitacion,

            fun_busca_diagnostico(1,a.pk_fk_paciente, a.pk_numero_admision) dg_principal

            FROM cad_admisiones a, cad_medicos_admision b, edm_medicos_especialidad c, aas_especialidades d, cad_pacientes e, bab_personas f

            WHERE a.alta_clinica         IS NULL            AND

                  a.pre_admision         = 'N'              AND

                  a.anulado              = 'N'              AND

                  a.discriminante        IN ('EMA')   AND

                  a.pk_fk_paciente       = b.pk_fk_paciente AND

                  a.pk_numero_admision   = b.pk_fk_admision AND

                  b.clasificacion_medico = 'TRA'            AND

                  b.pk_fk_medico         = c.pk_fk_medico   AND

                  c.principal            = 'S'              AND

                  c.pk_fk_especialidad   = d.pk_codigo      AND

                  a.pk_fk_paciente       = e.pk_nhcl        AND

                  e.fk_persona           = f.pk_codigo AND
                  b.pk_fk_medico = '996'    ORDER BY a.fecha_admision DESC

                ) b
                WHERE ROWNUM <= " . $this->length . "
                )
                WHERE NUM > " . $this->start . "
                ";

            }

            # Conectar base de datos
            $this->conectar_Oracle();

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
                $resultados[] = $key;
            }

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $resultados,
                'total' => count($resultados),
                'start' => intval($this->start),
                'length' => intval($this->length),
                'startDate' => $this->startDate,
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

        }

    }

    public function buscarPaciente()
    {

        try {

            # Set Parametrs
            $this->setParameters();

            # Verificar que no están vacíos
            if (Helper\Functions::e($this->pte, $this->tipoBusqueda)) {
                throw new ModelsException('Ingrese un valor de búsqueda.');
            }

            # Conectar base de datos
            $this->conectar_Oracle();

            if ($this->tipoBusqueda == 'cc') {

                if (!is_numeric($this->pte)) {
                    throw new ModelsException('Valor de búsqueda debe ser númerico.');
                }

                # Devolver todos los resultados
                $sql = "SELECT * FROM  WEB2_VW_LOGIN NOLOCK
                WHERE COD_PTE IS NOT NULL AND CC = '" . $this->pte . "'  OR PASAPORTE = '" . $this->pte . "' ";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $data = $stmt->fetch();

                if (false === $data) {
                    throw new ModelsException('No existe Información.');
                }

                $fk_persona = $data['COD_PTE'];

                # Conectar base de datos
                $this->conectar_Oracle();

                # Devolver todos los resultados
                $sql = " SELECT * FROM  GEMA.WEB_VW_PERSONAS NOLOCK  WHERE FK_PERSONA = '" . $fk_persona . "'   ";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $data = $stmt->fetch();

            } else if ($this->tipoBusqueda == 'nhc') {

                if (!is_numeric($this->pte)) {
                    throw new ModelsException('Valor de búsqueda debe ser númerico.');
                }

                # Devolver todos los resultados
                $sql = " SELECT * FROM  GEMA.WEB_VW_PERSONAS NOLOCK  WHERE PK_NHCL ='" . $this->pte . "'   ";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $data = $stmt->fetch();

                if (false === $data) {
                    throw new ModelsException('No existe Información.');

                }

            } else {

                if (!Helper\Strings::only_letters($this->pte)) {
                    throw new ModelsException('Valor de búsqueda debe ser texto.');
                }

                # Devolver todos los resultados
                $sql = " SELECT * FROM  gema.vw_web_pacientes NOLOCK  WHERE APELLIDOS || ' ' || NOMBRES LIKE '%" . $this->pte . "%'   ";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $data = $stmt->fetchAll();

                if (count($data) === 0) {
                    throw new ModelsException('No existe Información.');
                }
            }

            return array(
                'status' => true,
                'message' => 'Paciente encontrado.',
                'tipoBusqueda' => $this->tipoBusqueda,
                'data' => $data,
            );

        } catch (ModelsException $e) {
            return array('status' => false, 'data' => [], 'tipoBusqueda' => $this->tipoBusqueda,
                'message' => $e->getMessage());
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

    private function isRange($value)
    {

        $pos = strpos($value, 'fechas');

        if ($pos !== false) {
            return true;
        } else {
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
