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
use DateTime;
use Doctrine\DBAL\DriverManager;
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Models\Traits\DBModel;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Admisiones
 */

class Admisiones extends Models implements IModels
{

    use DBModel;

    # Variables de clase
    private $pte = null;
    private $tipoBusqueda = null;
    private $sortField = 'ROWNUM_';
    private $sortType = 'desc'; # desc
    private $start = 1;
    private $length = 25;
    private $searchField = null;
    private $startDate = null;
    private $endDate = null;
    private $_conexion = null;
    private $user = null;

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

    public function getUltimasConsultas()
    {

        try {
            global $config, $http;

            $consultas = $this->db->select(
                '*',
                "dataConsultasPacientes",
                null,
                null
            );

            if (false === $consultas) {
                throw new ModelsException('No existe información.');
            }

            foreach ($consultas as $key) {
                $consultas[$key]['data'] = json_decode($consultas[$key]['data'], true);
            }

            return array(
                'status' => true,
                'data' => $consultas,
            );

        } catch (ModelsException $e) {
            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage()
            );
        }

    }

    public function getControlCamas()
    {

        try {

            global $config, $http;

            # seteo de valores para paginacion
            $fecha = date('d-m-Y');

            $this->start = (int) $http->query->get('start');

            $this->length = (int) $http->query->get('length');

            $_searchField = (bool) $http->query->get('search')['value'];

            if ($_searchField != false && $this->isRange($http->query->get('search')['value']) === false) {

                $this->searchField = $this->quitar_tildes(mb_strtoupper($this->sanear_string($http->query->get('search')['value']), 'UTF-8'));

                $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (
                    SELECT * FROM  tmp_camas_gema_mv WHERE (HABITACION_GEMA LIKE'%$this->searchField%' OR NHCL LIKE '%$this->searchField%')
                    order by HABITACION_GEMA DESC
                ) b
                WHERE ROWNUM <= 500
                )
                WHERE NUM > 0
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
                    SELECT * FROM  tmp_camas_gema_mv order by HABITACION_GEMA DESC
                ) b
                WHERE ROWNUM <= 500
                )
                WHERE NUM > 0
                ";

            }

            # Conectar base de datos
            $this->conectar_Oracle();

            # Conectar base de datos
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

                if (!is_null($key['DIFERENCIA']) && $key['DIFERENCIA'] == 'X') {
                    $key['DIFERENCIA'] = 1;
                } else {
                    $key['DIFERENCIA'] = 0;
                }

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

            return array('status' => false, 'data' => [], 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getPreAdmisiones()
    {

        try {

            global $config, $http;

            # seteo de valores para paginacion
            $fecha = date('d-m-Y');

            $this->start = (int) $http->query->get('start');

            $this->length = (int) $http->query->get('length');

            $_searchField = (bool) $http->query->get('search')['value'];

            if ($_searchField != false && $this->isRange($http->query->get('search')['value']) === false) {

                $this->searchField = $this->quitar_tildes(mb_strtoupper($this->sanear_string($http->query->get('search')['value']), 'UTF-8'));

                $this->searchField = str_replace(" ", "%", $this->searchField);

                $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (
                    select * from cad_vw_preadmisiones WHERE (hc LIKE'%$this->searchField%' OR nombre_pte LIKE '%$this->searchField%')
                ) b
                WHERE ROWNUM <= 5000
                )
                WHERE NUM > 0
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
                    select * from cad_vw_preadmisiones
                ) b
                WHERE ROWNUM <= 5000
                )
                WHERE NUM > 0
                ";

            }

            # Conectar base de datos
            $this->conectar_Oracle();

            # Conectar base de datos
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

            return array('status' => false, 'data' => [], 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getPedidosTR()
    {

        try {

            global $config, $http;

            # seteo de valores para paginacion
            $fecha = date('d-m-Y');

            $this->start = (int) $http->query->get('start');

            $this->length = (int) $http->query->get('length');

            $_searchField = (bool) $http->query->get('search')['value'];

            if ($_searchField != false && $this->isRange($http->query->get('search')['value']) === false) {

                $this->searchField = $this->quitar_tildes(mb_strtoupper($this->sanear_string($http->query->get('search')['value']), 'UTF-8'));

                $this->searchField = str_replace(" ", "%", $this->searchField);

                $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (
                    select * from cad_vw_preadmisiones WHERE (hc LIKE'%$this->searchField%' OR nombre_pte LIKE '%$this->searchField%')
                ) b
                WHERE ROWNUM <= 5000
                )
                WHERE NUM > 0
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
                    SELECT DISTINCT t7.ds_tip_esq, t5.cd_paciente, t6.nm_paciente, t1.cd_atendimento, t1.cd_pre_med, t1.hr_pre_med
                    FROM   pre_med t1, itpre_med t3, tip_presc t4, atendime t5, paciente t6, tip_esq t7
                    WHERE  TRUNC(t1.hr_pre_med) = TRUNC(SYSDATE) AND
                        t1.cd_objeto IN (420,436) AND
                        t1.fl_impresso = 'S' AND
                        t1.cd_pre_med = t3.cd_pre_med AND
                        t3.cd_tip_presc = t4.cd_tip_presc AND
                        t4.cd_tip_esq IN ('GAS','NFG','HEM') AND
                        t1.cd_atendimento = t5.cd_atendimento AND
                        t5.cd_paciente = t6.cd_paciente AND
                        t4.cd_tip_esq = t7.cd_tip_esq
                    ORDER BY t1.cd_pre_med
                ) b
                WHERE ROWNUM <= 5000
                )
                WHERE NUM > 0
                ";

            }

            # Conectar base de datos
            $this->conectar_Oracle_MV();

            # Conectar base de datos
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

            return array('status' => false, 'data' => [], 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function buscarPaciente_MV($nhc = '')
    {

        $nhc = substr($nhc, 0, -2);

        # Conectar base de datos
        $this->conectar_Oracle_MV();

        $this->setSpanishOracle();

        # Devolver todos los resultados
        $sql = "SELECT p.cd_paciente,p.ds_primeiro_sobrenome,p.ds_segundo_sobrenome,p.ds_primeiro_nome,p.ds_segundo_nome,
            p.dt_nascimento,p.tp_sexo,p.tp_estado_civil,ct.atenciones
            FROM paciente p,
            (SELECT cd_paciente, count (cd_atendimento) atenciones
            FROM atendime
            group by cd_paciente) ct
            WHERE p.cd_paciente = ct.cd_paciente AND p.cd_paciente = '$nhc' ";

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetch();

        if ($data === false) {
            return 0;
        }

        return 1;

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

            $this->setSpanishOracle();

            if ($this->tipoBusqueda == 'cc') {

                if (!is_numeric($this->pte)) {
                    throw new ModelsException('Valor de búsqueda debe ser númerico.');
                }

                # Devolver todos los resultados
                $sql = "SELECT b.pk_nhcl hc, a.primer_apellido, a.segundo_apellido, a.primer_nombre, a.segundo_nombre, a.fecha_nacimiento, a.sexo, a.estado_civil,
                (select count(d.pk_numero_admision) from cad_admisiones d where d.pk_fk_paciente = b.pk_nhcl and d.anulado = 'N') total_adm
                FROM   bab_personas a, cad_pacientes b
                WHERE  a.cedula = '" . $this->pte . "' AND
                a.pk_codigo = b.fk_persona";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $data = $stmt->fetch();

                if (false === $data) {
                    throw new ModelsException('No existe Información.');

                }

                $data['STS_MV'] = 0;

                $data = array($data);

            } else if ($this->tipoBusqueda == 'pas') {

                # Devolver todos los resultados
                $sql = "SELECT b.pk_nhcl hc, a.primer_apellido, a.segundo_apellido, a.primer_nombre, a.segundo_nombre, a.fecha_nacimiento, a.sexo, a.estado_civil,
                (select count(d.pk_numero_admision) from cad_admisiones d where d.pk_fk_paciente = b.pk_nhcl and d.anulado = 'N') total_adm
                FROM   bab_personas a, cad_pacientes b
                WHERE  a.pasaporte = '" . $this->pte . "' AND
                a.pk_codigo = b.fk_persona";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $data = $stmt->fetch();

                if (false === $data) {
                    throw new ModelsException('No existe Información.');

                }

                $data = array($data);

            } else {

                if (!Helper\Strings::only_letters($this->pte)) {
                    throw new ModelsException('Valor de búsqueda debe ser texto.');
                }

                # Devolver todos los resultados
                $sql = "SELECT b.pk_nhcl hc, a.primer_apellido, a.segundo_apellido, a.primer_nombre, a.segundo_nombre, a.fecha_nacimiento, a.sexo, a.estado_civil,
                (select count(d.pk_numero_admision) from cad_admisiones d where d.pk_fk_paciente = b.pk_nhcl and d.anulado = 'N') total_adm
                FROM   bab_personas a, cad_pacientes b
                WHERE  (a.primer_apellido || ' ' || a.segundo_apellido || ' ' || a.primer_nombre || ' ' || a.segundo_nombre LIKE '%" . $this->pte . "%') AND
                a.pk_codigo = b.fk_persona";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $data = $stmt->fetchAll();

                if (count($data) === 0) {
                    throw new ModelsException('No existe Información.');
                }
            }

            $res = array();

            foreach ($data as $k) {

                //  $k['STS_MV'] = $this->buscarPaciente_MV($k['HC']);

                $k['STS_MV'] = 1;

                $res[] = $k;

            }

            return array(
                'status' => true,
                'message' => 'Paciente encontrado.',
                'tipoBusqueda' => $this->tipoBusqueda,
                'data' => $res,
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

    // Get Task Inbound Trx
    public function _getTaskInbound_HGE(): array
    {

        try {

            global $config, $http;

            $scs = Helper\Files::get_files_in_dir('../../v1/higienizacion/event/call/request/', 'json');

            $i = 0;
            $validaciones = array();

            foreach ($scs as $key => $val) {

                $datos = file_get_contents($val);
                $_setData = json_decode($datos, true);

                $validaciones[] = $_setData;
            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->orderMultiDimensionalArray($validaciones, 'timestamp'),
                'total' => count($validaciones),
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
            );

        }

    }

    public function _getTaskOutbound_HGE(): array
    {

        try {

            global $config, $http;

            $scs = Helper\Files::get_files_in_dir('../../v1/higienizacion/event/call/out/', 'json');
            $scs = array_reverse($scs);

            $i = 0;
            $validaciones = array();

            foreach ($scs as $key => $val) {

                $datos = file_get_contents($val);
                $_setData = json_decode($datos, true);
                $url = str_replace("../../", "", $val);
                $_setData['URL_LOG'] = 'https://api.hospitalmetropolitano.org/' . $url;
                $validaciones[] = $_setData;

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $validaciones,
                'total' => count($validaciones),
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
            );

        }

    }

    // Get tASK PROCES CAMBIA ESTADO EN GEMA -> CENTRA TELEFONOCIA
    public function _processTaskInbound_HGES()
    {

        try {

            global $config, $http;

            $scs = Helper\Files::get_files_in_dir('../../v1/higienizacion/event/call/request/', 'json');

            $i = 0;
            $validaciones = array();

            $time = new DateTime();
            $nuevaHora = strtotime('-2 minutes', $time->getTimestamp());
            $menosHora = date('Y-m-d H:i:s', $nuevaHora);
            $tiempoControl = strtotime($menosHora);

            foreach ($scs as $key => $val) {

                $datos = file_get_contents($val);
                $_setData = json_decode($datos, true);

                if ($_setData['status'] == 'AA' || $_setData['status'] == 'CC') {

                    if ($_setData['status'] == 'AA') {
                        $mvHab = $_setData['hab'];
                    }

                    if ($_setData['status'] == 'CC') {
                        $mvHab = $_setData['habAnt'];
                    }

                    $hab = $this->getHabMV($mvHab);

                    $sts_mv = $this->_processTaskOutbound_HGES($hab, $mvHab);

                    if ($sts_mv['status']) {

                        @unlink($val);

                    } else {
                        $validaciones[] = array($_setData, $sts_mv, $hab);
                    }

                    $i++;
                }

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $validaciones,
                'total' => count($validaciones),
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
            );

        }

    }

    public function _processTaskInbound_HGES_GEMA()
    {

        try {

            global $config, $http;

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Devolver todos los resultados
            $sql = "SELECT b.hora_final, a.pk_fk_habitacion cod_hab, a.pk_codigo cod_cama, b.pk_fk_paciente hc,
            fun_busca_nombre_pte(b.pk_fk_paciente) nombre, b.pk_fk_admision adm
            from aas_camas a, cad_habitaciones_paciente b, mv_itg_admision c
            where estado = 'LO' and
            a.pk_fk_habitacion = b.pk_fk_habitacion and
            a.pk_codigo = b.pk_fk_cama and
            b.fecha_fin is not null and
            b.fecha_fin >= sysdate - (5/1440) and
            b.pk_fk_paciente = c.hc_mv || '01' and
            b.pk_fk_admision = c.adm_gema and
            c.desc_error is null ";

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            $time = new DateTime();
            $nuevaHora = strtotime('-2 minutes', $time->getTimestamp());
            $menosHora = date('Y-m-d H:i:s', $nuevaHora);
            $tiempoControl = strtotime($menosHora);

            $validaciones = array();

            foreach ($data as $key) {

                if (!is_null($key['COD_HAB'])) {

                    $hab = $this->getHabMV($key['COD_HAB']);

                    $sts_mv = $this->_processTaskOutbound_HGES($key['COD_HAB'], $hab);

                    if ($sts_mv['status']) {

                        $validaciones[] = array(1, $sts_mv, $key['COD_HAB'], $hab);

                    } else {
                        $validaciones[] = array(0, $sts_mv, $key['COD_HAB'], $hab);
                    }

                    $i++;
                }

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $validaciones,
                'total' => count($validaciones),
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
            );

        }

    }

    public function getHab($hab)
    {

        $d = array(
            '0112' => '45',
            '0410' => '96',
            '0402' => '97',
            '0403' => '98',
            '0404' => '99',
            '0405' => '100',
            '0406' => '101',
            '0407' => '102',
            '0408' => '103',
            '0409' => '104',
            '0410' => '105',
            '0411' => '106',
            '0412' => '107',
            '0413' => '108',
            '0414' => '109',
            '0415' => '110',
            '0416' => '111',
            '0417' => '112',
            '0418' => '113',
            '0419' => '114',
            '0420' => '115',
            '0421' => '116',
            '0422' => '117',
            '0423' => '118',
            '0424' => '119',
        );

        return $d[$hab];

    }

    public function getHabMV($hab)
    {

        # Conectar base de datos
        $this->conectar_Oracle_MV();

        $this->setSpanishOracle();

        # Devolver todos los resultados
        $sql = " SELECT DS_LEITO  from leito where cd_leito = '$hab'";

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetch();

        if ($data === false) {
            return 0;
        }

        $piso = explode(" ", $data['DS_LEITO']);

        return $piso[0];

    }

    // Escucha cambios en tabla aas_camas y envia estados MV
    public function _processTaskOutbound_HGES($habGema, $habMV)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Devolver todos los resultados
            $sql = " SELECT * FROM aas_camas WHERE PK_FK_HABITACION = '$habGema' AND PK_CODIGO = '1' AND ESTADO = 'LO' ";

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            if ($data === false) {
                throw new ModelsException('No existe Información.');
            }

            if ($data['ESTADO'] == 'LO') {

                $sts = $this->sendCambiaStatusCama_v2($habMV, 'EH');
                $data['_MV_EH'] = $sts;
                sleep(0.5);

                $sts_PL = $this->sendCambiaStatusCama_v2($habMV, 'PL');
                $data['_MV_PL'] = $sts_PL;
                sleep(0.5);

                $sts_V = $this->sendCambiaStatusCama_v2($habMV, 'V');
                $data['_MV_V'] = $sts_V;

            }

            $data['timestamp'] = date("Y-m-d H:i:s");

            $t_log = date("Y_m_d_H_i_s_");

            if ($data['_MV_V']['status']) {
                $file = '../../v1/higienizacion/event/call/out/task_200_' . $t_log . '.json';
                $json_string = json_encode($data, true);
                file_put_contents($file, $json_string);
            }

            return array(
                'status' => true,
                'message' => 'Paciente encontrado.',
                'data' => $data,
            );

        } catch (ModelsException $e) {
            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage()
            );
        }

    }

    private function updateCAMA_IN_MV($hab = '')
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        $this->setSpanishOracle();

        # Execute queryBuilder
        $queryBuilder = $this->_conexion->createQueryBuilder();

        # Insertar nuevo registro de cuenta electrónica.
        $queryBuilder
            ->update('AAS_CAMAS', 'u')
            ->set('u.ESTADO', '?')
            ->where('u.pk_fk_habitacion = ?')
            ->andWhere('u.pk_codigo = ?')
            ->setParameter(0, 'RS')
            ->setParameter(1, (string) $hab)
            ->setParameter(2, '1')

        ;

        $stmt = $queryBuilder->execute();

        $this->_conexion->close();

    }

    private function updateCAMA_OUT_MV()
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        $this->setSpanishOracle();

        # Execute queryBuilder
        $queryBuilder = $this->_conexion->createQueryBuilder();

        # Insertar nuevo registro de cuenta electrónica.
        $queryBuilder
            ->update('AAS_CAMAS', 'u')
            ->set('u.ESTADO', '?')
            ->where('u.pk_fk_habitacion = ?')
            ->andWhere('u.pk_codigo = ?')
            ->setParameter(0, 'LE')
            ->setParameter(1, '0038')
            ->setParameter(2, '1')

        ;

        $stmt = $queryBuilder->execute();

        $this->_conexion->close();

    }

    # Send Estado Cambio de Cama => MV => v2
    public function sendCambiaStatusCama_v2($habitacion = "", $stado = "")
    {

        try {

            global $config, $http;

            $code_reg = date("YmdHis");

            $fechaHora = date("Y-m-d H:i:s");

            $webservice_url = "http://172.16.253.17:8084/jintegra-core/services/WebservicePadrao";

            $request_param = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:core="http://core.integracao.mv.com.br">
              <soapenv:Header/>
              <soapenv:Body>
                 <core:mensagem>
                 <![CDATA[
                 <Mensagem>
                   <Cabecalho>
                       <mensagemID>' . $code_reg . '</mensagemID>
                       <versaoXML/>
                       <identificacaoCliente>1</identificacaoCliente>
                       <servico>ATUALIZA_STATUS_LEITO</servico>
                       <dataHora>' . $fechaHora . '</dataHora>
                       <empresaOrigem>1</empresaOrigem>
                       <sistemaOrigem>COMPETENCIA</sistemaOrigem>
                       <empresaDestino>1</empresaDestino>
                       <sistemaDestino>MV</sistemaDestino>
                       <usuario/>
                       <senha/>
                   </Cabecalho>
                   <atualizaLeito>
                       <unidade>1</unidade>
                       <unidadeDePara/>
                       <descUnidade/>
                       <leito>' . $habitacion . '</leito>
                       <leitoDePara/>
                       <descLeito/>
                       <descLeitoResumido/>
                       <statusLeito>' . $stado . '</statusLeito>
                       <statusLeitoDePara/>
                       <ramal>0</ramal>
                   </atualizaLeito>
               </Mensagem>
                 ]]>
                 </core:mensagem>
              </soapenv:Body>
           </soapenv:Envelope>';

            $headers = array(
                'Content-Type: text/xml; charset=utf-8',
                'Content-Length: ' . strlen($request_param),
            );

            $ch = curl_init($webservice_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request_param);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $data = curl_exec($ch);

            $result = $data;

            if ($result === false) {

                return array(
                    'status' => false,
                    'data' => [],
                    'message' => "CURL error (#%d): %s<br>\n" . curl_errno($ch) . htmlspecialchars(curl_error($ch))
                );

            }

            curl_close($ch);

            return array(
                'status' => true,
                'data' => $result,
                'message' => 'Proceso realizado con éxito.',
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

        }

    }

    public function TEST_HL7_LIS()
    {

        try {

            global $config, $http;

            $code_reg = date("YmdHis");

            $fechaHora = date("Y-m-d H:i:s");

            $webservice_url = "http://172.16.253.11:8184/mv-api-hl7bus/proxySaidaMLLP";

            $request_param = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:core="http://core.integracao.mv.com.br">
            <soapenv:Header/>
            <soapenv:Body>
               <core:mensagem>
               <![CDATA[
               <Mensagem>
               <PedidoExameLab idEntidade="181" elementoLista="N" isRoot="S" lista="N" tipoRegistro="004" idEntidadePai="0" sequencial="1">
            <idIntegracao idElemento="9984" idEntidadePai="181" nomeColuna="CD_IMV_SOLICITACAO_SADT" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="2">405470</idIntegracao>
            <operacao idElemento="9985" idEntidadePai="181" nomeColuna="TP_MOVIMENTO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="3">I</operacao>
            <codigoPedido idElemento="9986" idEntidadePai="181" nomeColuna="CD_PEDIDO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="4">22006692</codigoPedido>
            <codigoPedidoDePara idElemento="9987" idEntidadePai="181" nomeColuna="CD_PEDIDO_INTEGRA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="5" />
            <atendimento idElemento="9988" idEntidadePai="181" nomeColuna="" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="6">
              <tipoSolicitacao idElemento="9989" idEntidadePai="181" nomeColuna="TP_SOLICITACAO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="7">U</tipoSolicitacao>
              <dataPedido idElemento="9990" idEntidadePai="181" nomeColuna="DT_PEDIDO" mascara="YYYY-MM-DD" valorFixo="" snMensagemFormatada="S" sequencial="8">2022-08-05</dataPedido>
              <tipoAtendimento idElemento="9991" idEntidadePai="181" nomeColuna="TP_ATENDIMENTO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="9">U</tipoAtendimento>
              <codigoAtendimento idElemento="9992" idEntidadePai="181" nomeColuna="CD_ATENDIMENTO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="10">16008</codigoAtendimento>
              <dataAtendimento idElemento="9993" idEntidadePai="181" nomeColuna="HR_ATENDIMENTO" mascara="YYYYMMDDHH24miss" valorFixo="" snMensagemFormatada="S" sequencial="11">20220805130411</dataAtendimento>
              <horaPedido idElemento="9994" idEntidadePai="181" nomeColuna="HR_PEDIDO" mascara="YYYY-MM-DD HH24:mi:ss" valorFixo="" snMensagemFormatada="S" sequencial="12">2022-08-05 13:27:00</horaPedido>
              <codigoAtendimentoDePara idElemento="9995" idEntidadePai="181" nomeColuna="" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="13" />
              <observacao idElemento="9996" idEntidadePai="181" nomeColuna="DS_OBSERVACAO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="14" />
              <enfermaria idElemento="9997" idEntidadePai="181" nomeColuna="DS_ENFERMARIA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="15">NO</enfermaria>
              <setorSolicitante idElemento="9998" idEntidadePai="181" nomeColuna="CD_SETOR_SOLICITANTE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="16">75</setorSolicitante>
              <setorSolicitanteDePara idElemento="9999" idEntidadePai="181" nomeColuna="CD_SETOR_SOLIC_INTEGRA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="17" />
              <leito idElemento="10000" idEntidadePai="181" nomeColuna="CD_LEITO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="18">131</leito>
              <descSetorSolicitante idElemento="10001" idEntidadePai="181" nomeColuna="NM_SETOR_SOLICITANTE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="19">EMERGENCIA</descSetorSolicitante>
              <leitoDePara idElemento="10002" idEntidadePai="181" nomeColuna="" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="20" />
              <descLeito idElemento="10003" idEntidadePai="181" nomeColuna="DS_LEITO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="21">CUBICULO 10</descLeito>
              <prestadorSolicitante idElemento="10004" idEntidadePai="181" nomeColuna="CD_PRESTADOR_SOLICITANTE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="22">2121</prestadorSolicitante>
              <descLeitoResumido idElemento="10005" idEntidadePai="181" nomeColuna="DS_LEITO_RESUMIDO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="23">CUB 10</descLeitoResumido>
              <prestadorSolicitanteDePara idElemento="10006" idEntidadePai="181" nomeColuna="CD_PRESTADOR_SOLIC_INTEGRA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="24" />
              <descPrestadorSolicitante idElemento="10007" idEntidadePai="181" nomeColuna="NM_PRESTADOR_SOLICITANTE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="25">POLANCO PAEZ CARLOS XAVIER</descPrestadorSolicitante>
              <numeroConselhoSolicitante idElemento="10008" idEntidadePai="181" nomeColuna="NR_CONSELHO_SOLICITANTE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="26">0602575995</numeroConselhoSolicitante>
              <tipoConselhoSolicitante idElemento="10009" idEntidadePai="181" nomeColuna="TP_CONSELHO_SOLICITANTE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="27">COLEGIO MEDICO</tipoConselhoSolicitante>
              <numeroControle idElemento="10010" idEntidadePai="181" nomeColuna="NR_CONTROLE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="28" />
              <descLocalExame idElemento="10011" idEntidadePai="181" nomeColuna="DS_LOCAL_EXAME" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="29" />
              <convenio idElemento="10012" idEntidadePai="181" nomeColuna="CD_CONVENIO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="30">4</convenio>
              <convenioDePara idElemento="10013" idEntidadePai="181" nomeColuna="CD_CONVENIO_INTEGRA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="31" />
              <descConvenio idElemento="10014" idEntidadePai="181" nomeColuna="NM_CONVENIO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="32">HM - PARTICULAR</descConvenio>
              <plano idElemento="10015" idEntidadePai="181" nomeColuna="CD_PLANO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="33">2</plano>
              <planoDePara idElemento="10016" idEntidadePai="181" nomeColuna="CD_PLANO_INTEGRA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="34" />
              <descPlano idElemento="10017" idEntidadePai="181" nomeColuna="DS_PLANO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="35">HM-PLAN NORMAL PARTICULAR INTE</descPlano>
              <guia idElemento="10018" idEntidadePai="181" nomeColuna="CD_GUIA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="36" />
              <dataValidadeGuia idElemento="10019" idEntidadePai="181" nomeColuna="DT_VALIDADE_GUIA" mascara="YYYY-MM-DD" valorFixo="" snMensagemFormatada="S" sequencial="37" />
              <senha idElemento="10020" idEntidadePai="181" nomeColuna="CD_SENHA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="38" />
              <usuarioSolicitante idElemento="10021" idEntidadePai="181" nomeColuna="CD_USUARIO_PEDIDO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="39">CPOLANCO</usuarioSolicitante>
              <tipoEntrega idElemento="10022" idEntidadePai="181" nomeColuna="CD_TIPO_ENTREGA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="40" />
              <descTipoEntrega idElemento="10023" idEntidadePai="181" nomeColuna="DS_TIPO_ENTREGA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="41" />
              <dataExame idElemento="10030" idEntidadePai="181" nomeColuna="DT_EXAME" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="42">2022-08-05 13:27:00</dataExame>
              <dataColetaPedido idElemento="10031" idEntidadePai="181" nomeColuna="DT_COLETA_PEDIDO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="43">2022-08-05 13:27:31</dataColetaPedido>
              <gemelaridade idElemento="10032" idEntidadePai="181" nomeColuna="QT_GEMELARIDADE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="44" />
              <transfusao idElemento="10033" idEntidadePai="181" nomeColuna="DS_TRANSFUSAO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="45" />
              <nutricao idElemento="10034" idEntidadePai="181" nomeColuna="SN_NUTRICAO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="46" />
              <idadeGestacional idElemento="10035" idEntidadePai="181" nomeColuna="QT_SEMANA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="47" />
              <codigoEspecialidade idElemento="10036" idEntidadePai="181" nomeColuna="CD_ESPECIALID" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="48">60</codigoEspecialidade>
              <nomeMaquina idElemento="10037" idEntidadePai="181" nomeColuna="NM_MAQUINA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="49">HMERPSISTPROD1</nomeMaquina>
              <descEspecialid idElemento="10038" idEntidadePai="181" nomeColuna="DS_ESPECIALID" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="50">EMERGENCIA</descEspecialid>
            </atendimento>
            <diagnostico idElemento="10039" idEntidadePai="181" nomeColuna="" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="51">
              <codigoDiagnostico idElemento="10040" idEntidadePai="181" nomeColuna="CD_CID" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="52">J189</codigoDiagnostico>
              <dsDiagostico idElemento="10041" idEntidadePai="181" nomeColuna="DS_CID" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="53">NEUMONIA, NO ESPECIFICADA</dsDiagostico>
            </diagnostico>
            <tipoSolicitacao idElemento="10042" idEntidadePai="181" nomeColuna="TP_SOLICITACAO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="54">U</tipoSolicitacao>
            <dataPedido idElemento="10043" idEntidadePai="181" nomeColuna="DT_PEDIDO" mascara="YYYY-MM-DD" valorFixo="" snMensagemFormatada="S" sequencial="55">2022-08-05</dataPedido>
            <horaPedido idElemento="10044" idEntidadePai="181" nomeColuna="HR_PEDIDO" mascara="YYYY-MM-DD HH24:mi:ss" valorFixo="" snMensagemFormatada="S" sequencial="56">2022-08-05 13:27:00</horaPedido>
            <observacao idElemento="10045" idEntidadePai="181" nomeColuna="DS_OBSERVACAO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="57" />
            <setorSolicitante idElemento="10046" idEntidadePai="181" nomeColuna="CD_SETOR_SOLICITANTE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="58">75</setorSolicitante>
            <setorSolicitanteDePara idElemento="10047" idEntidadePai="181" nomeColuna="CD_SETOR_SOLIC_INTEGRA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="59" />
            <descSetorSolicitante idElemento="10048" idEntidadePai="181" nomeColuna="NM_SETOR_SOLICITANTE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="60">EMERGENCIA</descSetorSolicitante>
            <prestadorSolicitante idElemento="10049" idEntidadePai="181" nomeColuna="CD_PRESTADOR_SOLICITANTE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="61">2121</prestadorSolicitante>
            <prestadorSolicitanteDePara idElemento="10050" idEntidadePai="181" nomeColuna="CD_PRESTADOR_SOLIC_INTEGRA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="62" />
            <descPrestadorSolicitante idElemento="10051" idEntidadePai="181" nomeColuna="NM_PRESTADOR_SOLICITANTE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="63">POLANCO PAEZ CARLOS XAVIER</descPrestadorSolicitante>
            <numeroConselhoSolicitante idElemento="10052" idEntidadePai="181" nomeColuna="NR_CONSELHO_SOLICITANTE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="64">0602575995</numeroConselhoSolicitante>
            <tipoConselhoSolicitante idElemento="10053" idEntidadePai="181" nomeColuna="TP_CONSELHO_SOLICITANTE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="65">COLEGIO MEDICO</tipoConselhoSolicitante>
            <numeroControle idElemento="10054" idEntidadePai="181" nomeColuna="NR_CONTROLE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="66" />
            <descLocalExame idElemento="10055" idEntidadePai="181" nomeColuna="DS_LOCAL_EXAME" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="67" />
            <convenio idElemento="10056" idEntidadePai="181" nomeColuna="CD_CONVENIO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="68">4</convenio>
            <convenioDePara idElemento="10057" idEntidadePai="181" nomeColuna="CD_CONVENIO_INTEGRA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="69" />
            <descConvenio idElemento="10058" idEntidadePai="181" nomeColuna="NM_CONVENIO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="70">HM - PARTICULAR</descConvenio>
            <plano idElemento="10059" idEntidadePai="181" nomeColuna="CD_PLANO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="71">2</plano>
            <planoDePara idElemento="10060" idEntidadePai="181" nomeColuna="CD_PLANO_INTEGRA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="72" />
            <descPlano idElemento="10061" idEntidadePai="181" nomeColuna="DS_PLANO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="73">HM-PLAN NORMAL PARTICULAR INTE</descPlano>
            <codigoprestInseriuPedido idElemento="10062" idEntidadePai="181" nomeColuna="CD_PREST_INSERIU_PEDIDO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="74">2121</codigoprestInseriuPedido>
            <nomeprestInseriuPedido idElemento="10063" idEntidadePai="181" nomeColuna="DS_PREST_INSERIU_PEDIDO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="75">POLANCO PAEZ CARLOS XAVIER</nomeprestInseriuPedido>
            <paciente idElemento="10064" idEntidadePai="181" nomeColuna="" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="76">
              <codigoPaciente idElemento="10065" idEntidadePai="181" nomeColuna="CD_PACIENTE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="77">426786</codigoPaciente>
              <codigoPacienteDePara idElemento="10066" idEntidadePai="181" nomeColuna="CD_PACIENTE_INTEGRA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="78" />
              <nome idElemento="10067" idEntidadePai="181" nomeColuna="NM_PACIENTE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="79">PEREZ RAMIREZ GUSTAVO</nome>
              <dataNascimento idElemento="10068" idEntidadePai="181" nomeColuna="DT_NASCIMENTO" mascara="YYYY-MM-DD" valorFixo="" snMensagemFormatada="S" sequencial="80">1928-10-03</dataNascimento>
              <peso idElemento="10069" idEntidadePai="181" nomeColuna="NR_PESO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="81" />
              <altura idElemento="10070" idEntidadePai="181" nomeColuna="NR_ALTURA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="82" />
              <sexo idElemento="10071" idEntidadePai="181" nomeColuna="TP_SEXO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="83">M</sexo>
              <cpf idElemento="10072" idEntidadePai="181" nomeColuna="NR_CPF_PACIENTE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="84" />
              <carteira idElemento="10073" idEntidadePai="181" nomeColuna="NR_CARTEIRA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="85" />
              <dataValidadeCarteira idElemento="10074" idEntidadePai="181" nomeColuna="DT_VALIDADE_CARTEIRA" mascara="YYYY-MM-DD" valorFixo="" snMensagemFormatada="S" sequencial="86" />
              <numeroCpf idElemento="10075" idEntidadePai="181" nomeColuna="NR_CPF_PACIENTE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="87" />
              <endereco idElemento="10076" idEntidadePai="181" nomeColuna="DS_ENDERECO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="88">AV. GONZALEZ SUAREZ 2b EDIFICIO PANOERAMA DEP P2</endereco>
              <numeroEndereco idElemento="10077" idEntidadePai="181" nomeColuna="NR_ENDERECO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="89" />
              <complemento idElemento="10078" idEntidadePai="181" nomeColuna="DS_COMPLEMENTO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="90" />
              <bairro idElemento="10079" idEntidadePai="181" nomeColuna="NM_BAIRRO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="91">GONZALEZ SUAREZ</bairro>
              <cep idElemento="10080" idEntidadePai="181" nomeColuna="NR_CEP" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="92" />
              <cidade idElemento="10081" idEntidadePai="181" nomeColuna="CD_CIDADE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="93">21701</cidade>
              <uf idElemento="10082" idEntidadePai="181" nomeColuna="CD_UF" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="94" />
              <email idElemento="10083" idEntidadePai="181" nomeColuna="DS_EMAIL" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="95">rick.cegreda@gmail.com</email>
              <telefone idElemento="10084" idEntidadePai="181" nomeColuna="NR_TELEFONE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="96" />
              <primeiroNome idElemento="10085" idEntidadePai="181" nomeColuna="DS_PRIMEIRO_NOME" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="97">GUSTAVO</primeiroNome>
              <primeiroSobrenome idElemento="10086" idEntidadePai="181" nomeColuna="DS_PRIMEIRO_SOBRENOME" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="98">PEREZ</primeiroSobrenome>
              <segundoSobrenome idElemento="10087" idEntidadePai="181" nomeColuna="DS_SEGUNDO_SOBRENOME" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="99">RAMIREZ</segundoSobrenome>
              <segundoNome idElemento="10088" idEntidadePai="181" nomeColuna="DS_SEGUNDO_NOME" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="100" />
              <numeroDocumentoPaciente idElemento="10089" idEntidadePai="181" nomeColuna="NR_DOCUMENTO_ESTRANGEIRO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="101" />
              <tipoDocumentoPaciente idElemento="10090" idEntidadePai="181" nomeColuna="TP_DOCUMENTO_ESTRANGEIRO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="102">CI</tipoDocumentoPaciente>
              <nomeCidade idElemento="10091" idEntidadePai="181" nomeColuna="NM_CIDADE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="103">QUITO</nomeCidade>
              <codigoIDPessoa idElemento="10092" idEntidadePai="181" nomeColuna="CD_IDENTIFICADOR_PESSOA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="104">1715259352</codigoIDPessoa>
            </paciente>
            <guia idElemento="10093" idEntidadePai="181" nomeColuna="CD_GUIA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="105" />
            <dataValidadeGuia idElemento="10094" idEntidadePai="181" nomeColuna="DT_VALIDADE_GUIA" mascara="YYYY-MM-DD" valorFixo="" snMensagemFormatada="S" sequencial="106" />
            <senha idElemento="10095" idEntidadePai="181" nomeColuna="CD_SENHA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="107" />
            <identificador idElemento="10096" idEntidadePai="181" nomeColuna="CD_PEDIDO" mascara="" valorFixo="" snMensagemFormatada="N" sequencial="108">22006692</identificador>
            <usuarioSolicitante idElemento="10097" idEntidadePai="181" nomeColuna="CD_USUARIO_PEDIDO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="109">CPOLANCO</usuarioSolicitante>
            <tipoEntrega idElemento="10098" idEntidadePai="181" nomeColuna="CD_TIPO_ENTREGA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="110" />
            <descTipoEntrega idElemento="10099" idEntidadePai="181" nomeColuna="DS_TIPO_ENTREGA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="111" />
            <empresaOrigem idElemento="10105" idEntidadePai="181" nomeColuna="CD_MULTI_EMPRESA" mascara="" valorFixo="" snMensagemFormatada="N" sequencial="112">1</empresaOrigem>
            <tipoPedido idElemento="10107" idEntidadePai="181" nomeColuna="TP_PEDIDO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="113">L</tipoPedido>
            <esperaColeta idElemento="10108" idEntidadePai="181" nomeColuna="SN_ESPERA_COLETA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="114" />
            <dataExame idElemento="10109" idEntidadePai="181" nomeColuna="DT_EXAME" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="115">2022-08-05 13:27:00</dataExame>
            <dataColetaPedido idElemento="10110" idEntidadePai="181" nomeColuna="DT_COLETA_PEDIDO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="116">2022-08-05 13:27:31</dataColetaPedido>
            <listaExame listaIdEntidade="252" elementoLista="S" isRoot="S" lista="N" tipoRegistro="004" idEntidadePai="0" sequencial="117">
              <Exame idEntidade="252" elementoLista="N" isRoot="N" lista="S" tipoRegistro="005" idEntidadePai="181" sequencial="118">
                <operacao idElemento="10111" idEntidadePai="252" nomeColuna="TP_MOVIMENTO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="119">I</operacao>
                <codigoItemPedido idElemento="10112" idEntidadePai="252" nomeColuna="CD_ITEM_PEDIDO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="120">68344</codigoItemPedido>
                <codigoItemPedidoDePara idElemento="10113" idEntidadePai="252" nomeColuna="" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="121" />
                <modalidade idElemento="10114" idEntidadePai="252" nomeColuna="CD_MODALIDADE_EXAME" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="122" />
                <siglaModalidade idElemento="10115" idEntidadePai="252" nomeColuna="DS_SIGLA_MODALIDADE_EXAME" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="123" />
                <codigoExame idElemento="10116" idEntidadePai="252" nomeColuna="CD_EXAME" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="124">167</codigoExame>
                <codigoExameDePara idElemento="10117" idEntidadePai="252" nomeColuna="CD_EXAME_INTEGRA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="125" />
                <descExame idElemento="10118" idEntidadePai="252" nomeColuna="DS_EXAME" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="126">SARS-CoV-2 POR PCR</descExame>
                <codigoExameFaturamento idElemento="10119" idEntidadePai="252" nomeColuna="CD_EXAME_FATURAMENTO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="127">31000109</codigoExameFaturamento>
                <descExameFaturamento idElemento="10120" idEntidadePai="252" nomeColuna="DS_EXAME_FATURAMENTO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="128">SARS-CoV-2 POR PCR</descExameFaturamento>
                <laboratorio idElemento="10121" idEntidadePai="252" nomeColuna="CD_LABORATORIO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="129">7</laboratorio>
                <laboratorioDePara idElemento="10122" idEntidadePai="252" nomeColuna="CD_LABORATORIO_INTEGRA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="130" />
                <descLaboratorio idElemento="10123" idEntidadePai="252" nomeColuna="DS_LABORATORIO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="131">MOLECULAR</descLaboratorio>
                <setorExecutante idElemento="10124" idEntidadePai="252" nomeColuna="CD_SETOR_EXECUTANTE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="132">10</setorExecutante>
                <setorExecutanteDePara idElemento="10125" idEntidadePai="252" nomeColuna="CD_SETOR_EXECUTANTE_INTEGRA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="133" />
                <descSetorExecutante idElemento="10126" idEntidadePai="252" nomeColuna="NM_SETOR_EXECUTANTE" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="134">LABORATORIO BIOLOGIA MOLECULAR</descSetorExecutante>
                <pendenciaColeta idElemento="10127" idEntidadePai="252" nomeColuna="SN_PENDENCIA_COLETA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="135" />
                <prestadorExecutante idElemento="10128" idEntidadePai="252" nomeColuna="CD_PRESTADOR_EXEC" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="136" />
                <dataAgendamento idElemento="10129" idEntidadePai="252" nomeColuna="DT_AGENDAMENTO" mascara="YYYY-MM-DD" valorFixo="" snMensagemFormatada="S" sequencial="137" />
                <horaAgendamento idElemento="10130" idEntidadePai="252" nomeColuna="HR_AGENDAMENTO" mascara="YYYY-MM-DD HH24:mi:ss" valorFixo="" snMensagemFormatada="S" sequencial="138" />
                <orientacao idElemento="10131" idEntidadePai="252" nomeColuna="DS_ORIENTACAO_EXAME" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="139" />
                <dataRealizacao idElemento="10132" idEntidadePai="252" nomeColuna="DT_REALIZACAO" mascara="YYYY-MM-DD" valorFixo="" snMensagemFormatada="S" sequencial="140" />
                <descLocalRealizacao idElemento="10133" idEntidadePai="252" nomeColuna="DS_LOCAL_REALIZACAO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="141" />
                <esperaColeta idElemento="10134" idEntidadePai="252" nomeColuna="SN_ESPERA_COLETA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="142" />
                <tipoSituacao idElemento="10135" idEntidadePai="252" nomeColuna="TP_SITUACAO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="143" />
                <etiquetaExterna idElemento="10136" idEntidadePai="252" nomeColuna="NR_ETIQUETA_EXTERNA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="144" />
                <snCultura idElemento="10137" idEntidadePai="252" nomeColuna="SN_CULTURA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="145">N</snCultura>
                <coleta idElemento="10138" idEntidadePai="252" nomeColuna="" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="146">
                  <coletaRealizada idElemento="10139" idEntidadePai="252" nomeColuna="SN_COLETA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="147" />
                  <dataColeta idElemento="10140" idEntidadePai="252" nomeColuna="DT_COLETA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="148" />
                  <coletaMaterial idElemento="10141" idEntidadePai="252" nomeColuna="CD_MATERIAL" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="149">166</coletaMaterial>
                  <amostraPrincipal idElemento="10142" idEntidadePai="252" nomeColuna="CD_AMOSTRA_PAI" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="150" />
                  <amostra idElemento="10143" idEntidadePai="252" nomeColuna="CD_AMOSTRA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="151" />
                  <bancada idElemento="10144" idEntidadePai="252" nomeColuna="CD_BANCADA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="152" />
                  <numeroColeta idElemento="10145" idEntidadePai="252" nomeColuna="NR_ORDEM_COLETA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="153" />
                  <tubo idElemento="10146" idEntidadePai="252" nomeColuna="CD_TUBO_COLETA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="154">227</tubo>
                  <codigoMaterial idElemento="10147" idEntidadePai="252" nomeColuna="CD_MATERIAL" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="155">166</codigoMaterial>
                  <codigoMaterialDePara idElemento="10148" idEntidadePai="252" nomeColuna="" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="156" />
                  <dataRecoleta idElemento="10149" idEntidadePai="252" nomeColuna="DT_RECOLETA" mascara="YYYY-MM-DD" valorFixo="" snMensagemFormatada="S" sequencial="157" />
                  <coletaSetor idElemento="10150" idEntidadePai="252" nomeColuna="SN_COLETA_SETOR" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="158" />
                  <dataColetaSetor idElemento="10151" idEntidadePai="252" nomeColuna="DT_COLETA_SETOR" mascara="YYYY-MM-DD" valorFixo="" snMensagemFormatada="S" sequencial="159" />
                  <horaColetaSetor idElemento="10152" idEntidadePai="252" nomeColuna="HR_COLETA_SETOR" mascara="HH24:MI:SS" valorFixo="" snMensagemFormatada="S" sequencial="160" />
                  <dataColetaPedido idElemento="10153" idEntidadePai="252" nomeColuna="DT_COLETA_PEDIDO" mascara="YYYY-MM-DD" valorFixo="" snMensagemFormatada="S" sequencial="161">2022-08-05</dataColetaPedido>
                  <horaColetaPedido idElemento="10154" idEntidadePai="252" nomeColuna="HR_COLETA_PEDIDO" mascara="HH24:MI:SS" valorFixo="" snMensagemFormatada="S" sequencial="162">13:27:31</horaColetaPedido>
                  <posto idElemento="10155" idEntidadePai="252" nomeColuna="CD_POSTO_COLETA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="163" />
                  <postoDePara idElemento="10156" idEntidadePai="252" nomeColuna="CD_POSTO_COLETA_INTEGRA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="164" />
                  <descPosto idElemento="10157" idEntidadePai="252" nomeColuna="DS_POSTO_COLETA" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="165" />
                  <descricaoMaterial idElemento="10158" idEntidadePai="252" nomeColuna="DS_MATERIAL" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="166">ASPIRADO NASOFARINGEO</descricaoMaterial>
                </coleta>
                <observacao idElemento="10165" idEntidadePai="252" nomeColuna="DS_OBSERVACAO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="167" />
                <codigoMaterial idElemento="10166" idEntidadePai="252" nomeColuna="CD_MATERIAL" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="168">166</codigoMaterial>
                <descricaoMaterial idElemento="10167" idEntidadePai="252" nomeColuna="DS_MATERIAL" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="169">ASPIRADO NASOFARINGEO</descricaoMaterial>
                <versaoAtual idElemento="10168" idEntidadePai="252" nomeColuna="CD_VERSAO_ATUAL" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="170" />
                <sequenciaExame idElemento="10169" idEntidadePai="252" nomeColuna="VCOUNTOBR" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="171">0</sequenciaExame>
                <listaCampo listaIdEntidade="253" elementoLista="S" isRoot="N" lista="S" tipoRegistro="005" idEntidadePai="181" sequencial="172">
                  <Campo idEntidade="253" elementoLista="N" isRoot="N" lista="S" tipoRegistro="006" idEntidadePai="252" sequencial="173">
                    <idIntegracao idElemento="10170" idEntidadePai="253" nomeColuna="CD_IMV_SOLICITACAO_SADT" mascara="" valorFixo="" snMensagemFormatada="N" sequencial="174">405472</idIntegracao>
                    <operacao idElemento="10171" idEntidadePai="253" nomeColuna="TP_MOVIMENTO" mascara="" valorFixo="" snMensagemFormatada="N" sequencial="175">I</operacao>
                    <codigoItemPedido idElemento="10172" idEntidadePai="253" nomeColuna="CD_ITEM_PEDIDO" mascara="" valorFixo="" snMensagemFormatada="N" sequencial="176">68344</codigoItemPedido>
                    <codigoPedido idElemento="10173" idEntidadePai="253" nomeColuna="CD_PEDIDO" mascara="" valorFixo="" snMensagemFormatada="N" sequencial="177">22006692</codigoPedido>
                    <codigoPedidoDePara idElemento="10174" idEntidadePai="253" nomeColuna="CD_PEDIDO_INTEGRA" mascara="" valorFixo="" snMensagemFormatada="N" sequencial="178" />
                    <descricaoMaterial idElemento="10181" idEntidadePai="253" nomeColuna="DS_MATERIAL" mascara="" valorFixo="" snMensagemFormatada="N" sequencial="179">ASPIRADO NASOFARINGEO</descricaoMaterial>
                    <versaoAtual idElemento="10182" idEntidadePai="253" nomeColuna="CD_VERSAO_ATUAL" mascara="" valorFixo="" snMensagemFormatada="N" sequencial="180" />
                    <mnemonico idElemento="10183" idEntidadePai="253" nomeColuna="NM_MNEMONICO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="181">SARSCOV</mnemonico>
                    <descCampo idElemento="10184" idEntidadePai="253" nomeColuna="DS_CAMPO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="182">SARS COV 2</descCampo>
                    <codigoCampo idElemento="10185" idEntidadePai="253" nomeColuna="CD_CAMPO" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="183">1</codigoCampo>
                    <sequenciaCampo idElemento="10186" idEntidadePai="253" nomeColuna="VCOUNTOBR" mascara="" valorFixo="" snMensagemFormatada="S" sequencial="184">1</sequenciaCampo>
                  </Campo>
                </listaCampo>
              </Exame>
            </listaExame>
            <tipoDocumento>PEDIDO_EXAME_SADT</tipoDocumento>
            <versaoXML>1</versaoXML>
          </PedidoExameLab>
          </Mensagem>
          ]]>
          </core:mensagem>
       </soapenv:Body>
    </soapenv:Envelope>';

            $headers = array(
                'Content-Type: text/xml; charset=utf-8',
                'Content-Length: ' . strlen($request_param),
            );

            $ch = curl_init($webservice_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request_param);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $data = curl_exec($ch);

            $result = $data;

            if ($result === false) {

                return array(
                    'status' => false,
                    'data' => [],
                    'message' => "CURL error (#%d): %s<br>\n" . curl_errno($ch) . htmlspecialchars(curl_error($ch))
                );

            }

            curl_close($ch);

            return array(
                'status' => true,
                'data' => $result,
                'message' => 'Proceso realizado con éxito.',
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

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
