<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models\ptes;

use app\models\ptes as Model;
use DateTime;
use Doctrine\DBAL\DriverManager;
use Endroid\QrCode\QrCode;
use Exception;
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;
use setasign\Fpdi\Fpdi;
use SoapClient;

/**
 * Modelo Laboratorio
 */
class Laboratorio extends Models implements IModels
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
    private $urlPDF = 'https://api.hospitalmetropolitano.org/v2/pacientes/resultado/l/?id=';
    private $viewPDF = '/resultado/l/';

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

        if ($this->length > 25) {
            throw new ModelsException('!Error! Solo se pueden mostrar 25 resultados por página.');
        }

        if ($this->length == 0 or $this->length < 0) {
            throw new ModelsException('!Error! {length} no puede ser 0 o negativo');
        }

        if ($this->start == 0 or $this->start < 0) {
            throw new ModelsException('!Error! {start} no puede ser 0 o negativo.');
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
        $nuevafecha = strtotime('-1 year', strtotime($fecha));

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

    private function setSpanishOracle_Insert()
    {

        $sql = "alter session set NLS_LANGUAGE = 'SPANISH'";
        # Execute
        $stmt = $this->_conexion->query($sql);

        $sql = "alter session set NLS_TERRITORY = 'SPAIN'";
        # Execute
        $stmt = $this->_conexion->query($sql);

        $sql = " alter session set NLS_DATE_FORMAT = 'DD-MM-YYYY HH24:MI:SS' ";
        # Execute
        $stmt = $this->_conexion->query($sql);

    }

    public function updateTomaMuestraPedido()
    {

        global $config, $http;

        $doc = json_decode($http->request->get('documento'), true);

        $numeroPedido = $doc['pedidoLaboratorio']['dataPedido']['numeroPedido'];

        # Conectar base de datos
        $this->conectar_Oracle_MV();

        $this->setSpanishOracle_Insert();

        # QueryBuilder
        $queryBuilder = $this->_conexion->createQueryBuilder();

        # Insertar nuevo registro de cuenta electrónica.
        $queryBuilder
            ->update('HMETRO.TOMA_DE_MUESTRA', 'u')
            ->set('u.DATA_PEDIDO', '?')
            ->where('u.CD_PED_LAB = ?')
            ->setParameter(0, json_encode($doc, JSON_UNESCAPED_UNICODE))
            ->setParameter(1, $numeroPedido);

        $del_registro = $queryBuilder->execute();

        $this->_conexion->close();

        return array(
            'status' => true,
            'data' => $doc,
        );

    }

    public function getResultadosLab(): array
    {

        try {

            global $config, $http;

            # SETEAR VARIABLES DE CLASE
            $this->setParameters();

            # ERRORES DE PETICION
            $this->errorsPagination();

            $CP_PTE = $http->query->get('numeroHistoriaClinica');

            $sql = "SELECT WEB2_RESULTADOS_LAB.*, ROWNUM AS ROWNUM_ FROM WEB2_RESULTADOS_LAB WHERE COD_PERSONA = '$CP_PTE' AND TOT_SC!=TOD_DC AND FECHA >= TO_DATE('02-04-2019', 'dd-mm-yyyy') ORDER BY FECHA DESC ";

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

            //CP_ 37169901

            foreach ($data as $key) {

                $key['urlPDF'] = $this->urlPDF . $key['SC'] . '/' . $key['FECHA'];

                $resultados[] = $key;

            }

            //CP_ 37169901

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Order by asc to desc
            $RESULTADOS = $this->get_Order_Pagination($resultados);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->get_page($RESULTADOS, $this->start, $this->length),
                'total' => count($resultados),
                'length' => intval($this->length),
                'start' => intval($this->start),
                # 'dataddd' => $http->request->all(),

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

    public function getNPL(): array
    {

        try {

            global $config, $http;

            # seteo de valores para paginacion
            $fecha = date('d-m-Y');

            $sql = " SELECT *
            FROM (
            SELECT b.*, ROWNUM AS NUM
            FROM (
                SELECT aa.cd_ped_lab AS NUM_PEDIDO_MV,
                OO.CD_ATENDIMENTO AS AT_MV,
                UU.CD_PACIENTE AS HC_MV,
                UU.NM_PACIENTE AS PTE_MV,
                AA.DT_PEDIDO AS FECHA_PEDIDO,
                TO_CHAR(AA.HR_PED_LAB,'HH24:MI') HORA_PEDIDO,
                PR.NM_PRESTADOR AS MED_MV
                from ped_lab aa, atendime oo,  paciente uu, prestador pr
                where aa.cd_atendimento = oo.cd_atendimento
                and uu.cd_paciente = oo.cd_paciente
                and aa.cd_prestador = pr.cd_prestador
                order by aa.cd_ped_lab desc
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

    public function getPedidosEmergenciaLab(): array
    {

        try {

            global $config, $http;

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
                    SELECT aa.cd_ped_lab AS NUM_PEDIDO_MV,
                    OO.CD_ATENDIMENTO AS AT_MV,
                    UU.CD_PACIENTE AS HC_MV,
                    UU.NM_PACIENTE AS PTE_MV,
                    AA.DT_PEDIDO AS FECHA_PEDIDO,
                    TO_CHAR(AA.HR_PED_LAB,'HH24:MI') HORA_PEDIDO,
                    PR.NM_PRESTADOR AS MED_MV
                    from ped_lab aa, atendime oo,  paciente uu, prestador pr
                    where aa.cd_atendimento = oo.cd_atendimento
                    and uu.cd_paciente = oo.cd_paciente
                    and aa.cd_prestador = pr.cd_prestador
                    and (UU.CD_PACIENTE LIKE'%$this->searchField%' OR UU.NM_PACIENTE LIKE '%$this->searchField%')
                    order by  aa.cd_ped_lab desc
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
                    SELECT aa.cd_ped_lab AS NUM_PEDIDO_MV,
                    OO.CD_ATENDIMENTO AS AT_MV,
                    UU.CD_PACIENTE AS HC_MV,
                    UU.NM_PACIENTE AS PTE_MV,
                    AA.DT_PEDIDO AS FECHA_PEDIDO,
                    TO_CHAR(AA.HR_PED_LAB,'HH24:MI') HORA_PEDIDO,
                    PR.NM_PRESTADOR AS MED_MV
                    from ped_lab aa, atendime oo,  paciente uu, prestador pr
                    where aa.cd_atendimento = oo.cd_atendimento
                    and uu.cd_paciente = oo.cd_paciente
                    and aa.cd_prestador = pr.cd_prestador
                    order by aa.cd_ped_lab desc
                ) b
                WHERE ROWNUM <= " . $this->length . "
                )
                WHERE NUM > " . $this->start . "
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

    public function getPedidosLab(): array
    {

        try {

            global $config, $http;

            # seteo de valores para paginacion
            $fecha = date('d-m-Y');

            $this->start = (int) $http->query->get('start');

            $this->length = (int) $http->query->get('length');

            if ($this->start >= 10) {
                $this->length = $this->start + $this->length;
            }

            $_searchField = (bool) $http->query->get('search')['value'];

            if ($_searchField != false && $this->isTipoPiso($http->query->get('search')['value'])) {

                $this->searchField = explode('-', $http->query->get('search')['value']);

                $tipoPiso = $this->searchField[1];

                $tipoPiso = str_replace(",", '","', $tipoPiso);

                $tipoPiso = explode(",", '"' . $tipoPiso . '"');

                $tipoPiso = implode(',', $tipoPiso);

                $tipoPiso = preg_replace("/\"/", "'", $tipoPiso);

                $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (
                    SELECT aa.cd_ped_lab AS NUM_PEDIDO_MV,
                    AA.TP_SOLICITACAO AS TIPO_PEDIDO,
                    OO.CD_ATENDIMENTO AS AT_MV,
                    UU.CD_PACIENTE AS HC_MV,
                    UU.NM_PACIENTE AS PTE_MV,
                    AA.DT_PEDIDO AS FECHA_PEDIDO,
                    TO_CHAR(AA.HR_PED_LAB,'HH24:MI') HORA_PEDIDO,
                    PR.NM_PRESTADOR AS MED_MV,
                    SS.NM_SETOR AS SECTOR,
                    l.ds_leito as UBICACION,
                    toma.usuario_recep as USUARIO_RECEP
                    from ped_lab aa, atendime oo,  paciente uu, prestador pr, setor ss, leito l, HMETRO.v_tomademuestra toma
                    where aa.cd_atendimento = oo.cd_atendimento
                    and uu.cd_paciente = oo.cd_paciente
                    and aa.cd_prestador = pr.cd_prestador
                    and aa.cd_setor = ss.cd_setor
                    and oo.cd_leito = l.cd_leito
                    and SS.NM_SETOR in ($tipoPiso)
                    order by aa.cd_ped_lab desc
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
                    SELECT aa.cd_ped_lab AS NUM_PEDIDO_MV,
                    AA.TP_SOLICITACAO AS TIPO_PEDIDO,
                    OO.CD_ATENDIMENTO AS AT_MV,
                    UU.CD_PACIENTE AS HC_MV,
                    UU.NM_PACIENTE AS PTE_MV,
                    AA.DT_PEDIDO AS FECHA_PEDIDO,
                    TO_CHAR(AA.HR_PED_LAB,'HH24:MI') HORA_PEDIDO,
                    PR.NM_PRESTADOR AS MED_MV,
                    SS.NM_SETOR AS SECTOR,
                    l.ds_leito as UBICACION,
                    toma.usuario_recep as USUARIO_RECEP
                    from ped_lab aa, atendime oo,  paciente uu, prestador pr, setor ss, leito l, HMETRO.v_tomademuestra toma
                    where aa.cd_atendimento = oo.cd_atendimento
                    and uu.cd_paciente = oo.cd_paciente
                    and aa.cd_prestador = pr.cd_prestador
                    and aa.cd_setor = ss.cd_setor
                    and oo.cd_leito = l.cd_leito
                    order by aa.cd_ped_lab desc
                ) b
                WHERE ROWNUM <= " . $this->length . "
                )
                WHERE NUM > " . $this->start . "
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

    public function getStatusPedidoLab($sc = ''): array
    {

        try {

            global $config, $http;

            $sql = "  SELECT OO.DT_ATENDIMENTO, PR.NM_PRESTADOR,  UU.NM_PACIENTE, OO.CD_ATENDIMENTO, UU.CD_PACIENTE, EE.CD_PED_LAB, EE.CD_EXA_LAB, PP.NM_MNEMONICO, PP.NM_EXA_LAB, AA.DT_PEDIDO,
            EE.SN_REALIZADO, AA.TP_SOLICITACAO, AA.DT_COLETA,    TO_CHAR(AA.HR_COLETA,'HH24:MI') HORA_MUESTRA
            FROM ITPED_LAB EE, PED_LAB AA, ATENDIME  OO, PACIENTE UU, EXA_LAB PP, PRESTADOR PR
            WHERE EE.CD_PED_LAB = AA.CD_PED_LAB
            AND AA.CD_ATENDIMENTO = OO.CD_ATENDIMENTO
            AND UU.CD_PACIENTE = OO.CD_PACIENTE
            AND PP.CD_EXA_LAB = EE.CD_EXA_LAB
            AND AA.CD_PRESTADOR = PR.CD_PRESTADOR
            AND EE.CD_PED_LAB = '$sc' ";

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
            $pendiente = array();

            $res = array();

            foreach ($data as $key) {

                $key['TIMESTAMP_TOMA'] = "";

                if (is_null($key['SN_REALIZADO'])) {
                    $pendiente[] = $key;
                }

                $res[] = $key;

            }

            return array(
                'status' => true,
                'data' => $res,
                'pendienteResultado' => ((count($pendiente) !== 0) ? true : false),
                //  'pendienteMuestras' => ((count($pendiente) !== 0) ? true : false),

            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'message' => $e->getMessage(),
            );

        }

    }

    public function getStatusTomaRecepcionPedido($sc = ''): array
    {

        try {

            global $config, $http;

            $sql = " SELECT OO.DT_ATENDIMENTO,
            PR.NM_PRESTADOR AS MED_MV,
            UU.NM_PACIENTE AS PTE_MV,
            OO.CD_ATENDIMENTO as AT_MV,
            UU.CD_PACIENTE AS HC_MV,
            EE.CD_PED_LAB AS NM_PEDIDO_LAB,
            EE.CD_EXA_LAB,
            PP.NM_MNEMONICO,
            PP.NM_EXA_LAB,
            AA.DT_PEDIDO AS FECHA_PEDIDO,
            EE.SN_REALIZADO AS STATUS_RESULTADO,
            AA.TP_SOLICITACAO AS TIPO_PEDIDO,
            AA.DT_COLETA AS FECHA_MUESTRA,
            TO_CHAR(AA.HR_COLETA,'HH24:MI') HORA_MUESTRA,
            ui.DS_UNID_INT AS PISO,
            s.nm_setor AS SECTOR,
            l.ds_leito as UBICACION
            FROM ITPED_LAB EE, PED_LAB AA, ATENDIME  OO, PACIENTE UU, EXA_LAB PP, PRESTADOR PR, unid_int ui, leito l, setor s
            WHERE EE.CD_PED_LAB = AA.CD_PED_LAB
            AND AA.CD_ATENDIMENTO = OO.CD_ATENDIMENTO
            AND UU.CD_PACIENTE = OO.CD_PACIENTE
            AND PP.CD_EXA_LAB = EE.CD_EXA_LAB
            AND AA.CD_PRESTADOR = PR.CD_PRESTADOR
            and aa.cd_setor = ui.cd_setor
            and aa.cd_setor = s.cd_setor
            and oo.cd_leito = l.cd_leito
            AND EE.CD_PED_LAB = '$sc' ";

            # Conectar base de datos
            $this->conectar_Oracle_MV();

            # Conectar base de datos
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            $res = array();

            foreach ($data as $key) {

                if (is_null($key['STATUS_RESULTADO'])) {
                    $key['STATUS_RESULTADO'] = '';
                }

                $key['STATUS_TOMA'] = '';
                $key['FECHA_TOMA'] = '';
                $key['USR_TOMA'] = '';
                $key['STATUS_RECEP'] = '';
                $key['FECHA_RECEP'] = '';
                $key['USR_RECEP'] = '';
                $key['customCheked'] = false;

                $res[] = $key;

            }

            return $res;

        } catch (ModelsException $e) {

            return array();

        }

    }

    public function getStatusPedidoLabDetalle($sc = ''): array
    {

        try {

            global $config, $http;

            // Documento Pedido
            $docPedido = array(
                "pedidoLaboratorio" => array(
                    "dataPedido" => array(
                        "numeroPedido" => "",
                        "statusPedido" => "",
                        "descStatusPedido" => "",
                        "tipoPedido" => "",
                        "fechaPedido" => "",
                        "horaPedido" => "",
                        "dgPedido" => "",
                        'numeroHistoriaClinica' => "",
                        "nombrePaciente" => "",
                        "numeroAtencion" => "",
                        "nombreMedico" => "",
                        "ubicacionPaciente" => "",
                        "edadPaciente" => "",
                    ),
                    "dataTomaMuestra" => array(
                        "fechaToma" => "",
                        "usuarioToma" => "",
                        "examenesToma" => array(
                        ),
                        "insumosToma" => array(
                        ),
                    ),
                    "dataRecepcion" => array(
                        "fechaRecep" => "",
                        "usuarioRecep" => "",
                        "examenesRecep" => array(
                        ),
                        "insumosRecep" => array(
                        ),
                    ),
                    "dataObservaciones" => array(),
                ),
            );

            $sql = " SELECT * FROM HMETRO.TOMA_DE_MUESTRA WHERE CD_PED_LAB = '$sc' ";

            # Conectar base de datos
            $this->conectar_Oracle_MV();

            # Conectar base de datos
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            if ($data !== false) {

                return array(
                    'status' => true,
                    'data' => json_decode($data['DATA_PEDIDO'], true),
                );

            } else {

                $sql = "  SELECT aa.cd_ped_lab AS NUM_PEDIDO_MV,
                TP_SOLICITACAO AS TIPO_PEDIDO,
                AA.DT_PEDIDO AS FECHA_PEDIDO,
                TO_CHAR(AA.HR_PED_LAB,'HH24:MI') HORA_PEDIDO,
                OO.CD_PACIENTE AS HC_MV,
                OO.CD_ATENDIMENTO AS AT_MV,
                UU.NM_PACIENTE AS PTE_MV,
                PR.NM_PRESTADOR AS MED_MV,
                SS.NM_SETOR AS NM_SECTOR,
                TRUNC((MONTHS_BETWEEN(SYSDATE, UU.dt_nascimento)) / 12) || ' AÑOS' AS EDAD,
                editor_custom.fun_diag_ingreso_def_prin(OO.cd_atendimento) AS DG
                from ped_lab aa, atendime oo,  paciente uu, prestador pr, setor ss
                where aa.cd_atendimento = oo.cd_atendimento
                and uu.cd_paciente = oo.cd_paciente
                and aa.cd_prestador = pr.cd_prestador
                and AA.CD_SETOR = SS.CD_SETOR
                and  aa.cd_ped_lab = '$sc'
                order by aa.cd_ped_lab desc ";

                # Conectar base de datos
                $this->conectar_Oracle_MV();

                # Conectar base de datos
                $this->setSpanishOracle();

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $data = $stmt->fetch();

                # DataPedido
                $docPedido['pedidoLaboratorio']['dataPedido']['numeroPedido'] = $data['NUM_PEDIDO_MV'];
                $docPedido['pedidoLaboratorio']['dataPedido']['edadPaciente'] = $data['EDAD'];
                $docPedido['pedidoLaboratorio']['dataPedido']['dgPedido'] = $data['DG'];
                $docPedido['pedidoLaboratorio']['dataPedido']['numeroAtencion'] = $data['AT_MV'];
                $docPedido['pedidoLaboratorio']['dataPedido']['fechaPedido'] = $data['FECHA_PEDIDO'];
                $docPedido['pedidoLaboratorio']['dataPedido']['horaPedido'] = $data['HORA_PEDIDO'];
                $docPedido['pedidoLaboratorio']['dataPedido']['numeroHistoriaClinica'] = $data['HC_MV'];
                $docPedido['pedidoLaboratorio']['dataPedido']['nombrePaciente'] = $data['PTE_MV'];
                $docPedido['pedidoLaboratorio']['dataPedido']['nombreMedico'] = $data['MED_MV'];
                $docPedido['pedidoLaboratorio']['dataPedido']['ubicacionPaciente'] = $data['NM_SECTOR'];

                # Data Muestras
                $dataMuestras = $this->getStatusTomaRecepcionPedido($sc);

                $docPedido['pedidoLaboratorio']['dataTomaMuestra']['examenesToma'] = $dataMuestras;
                $docPedido['pedidoLaboratorio']['dataRecepcion']['examenesRecep'] = $dataMuestras;

                // INSERTAR NUEVOS VALORES

                # Conectar base de datos Para Nuevo Registro
                $this->conectar_Oracle_MV();
                $this->setSpanishOracle_Insert();
                # QueryBuilder
                $queryBuilder = $this->_conexion->createQueryBuilder();
                # Insertar nuevo registro de cuenta electrónica.
                $queryBuilder
                    ->insert('HMETRO.TOMA_DE_MUESTRA')
                    ->values(
                        array(
                            'CD_PED_LAB' => '?',
                            'DATA_PEDIDO' => '?',
                        )
                    )
                    ->setParameter(0, $sc)
                    ->setParameter(1, json_encode($docPedido, JSON_UNESCAPED_UNICODE));

                $nuevo_registro = $queryBuilder->execute();
                $this->_conexion->close();

                return array(
                    'status' => true,
                    'data' => $docPedido,
                );

            }

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'message' => $e->getMessage(),
            );

        }

    }

    public function getPedidosFlebotomistaLab(): array
    {

        try {

            global $config, $http;

            # seteo de valores para paginacion
            $fecha = date('d-m-Y');

            $this->start = (int) $http->query->get('start');

            $this->length = (int) $http->query->get('length');

            if ($this->start >= 10) {
                $this->length = $this->start + $this->length;
            }

            $_searchField = (bool) $http->query->get('search')['value'];

            if ($_searchField != false && $this->isTipoPiso($http->query->get('search')['value']) === false) {

                $this->searchField = $this->quitar_tildes(mb_strtoupper($this->sanear_string($http->query->get('search')['value']), 'UTF-8'));

                $this->searchField = str_replace(" ", "%", $this->searchField);

                $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (
                    SELECT aa.cd_ped_lab AS NUM_PEDIDO_MV,
                    AA.TP_SOLICITACAO AS TIPO_PEDIDO,
                    OO.CD_ATENDIMENTO AS AT_MV,
                    UU.CD_PACIENTE AS HC_MV,
                    UU.NM_PACIENTE AS PTE_MV,
                    AA.DT_PEDIDO AS FECHA_PEDIDO,
                    TO_CHAR(AA.HR_PED_LAB,'HH24:MI') HORA_PEDIDO,
                    PR.NM_PRESTADOR AS MED_MV,
                    ui.DS_UNID_INT AS SECTOR,
                    l.ds_leito as UBICACION,
                    toma.usuario_toma as USUARIO_TOMA
                    from ped_lab aa, atendime oo, paciente uu, prestador pr, unid_int ui, leito l, HMETRO.v_tomademuestra toma
                    where aa.cd_atendimento = oo.cd_atendimento
                    and uu.cd_paciente = oo.cd_paciente
                    and aa.cd_prestador = pr.cd_prestador
                    and aa.cd_setor = ui.cd_setor
                    and oo.cd_leito = l.cd_leito
                    and toma.usuario_toma is null
                    and AA.DT_PEDIDO = sysdate
                    and (UU.CD_PACIENTE LIKE'%$this->searchField%' OR UU.NM_PACIENTE LIKE '%$this->searchField%')
                    order by  aa.cd_ped_lab desc
                ) b
                WHERE ROWNUM <= " . $this->length . "
                )
                WHERE NUM > " . $this->start . "
                ";

            } elseif ($_searchField != false && $this->isTipoPiso($http->query->get('search')['value'])) {

                $this->searchField = explode('-', $http->query->get('search')['value']);
                $tipoPiso = $this->searchField[1];
                $tipoPiso = str_replace(",", '","', $tipoPiso);
                $tipoPiso = explode(",", '"' . $tipoPiso . '"');
                $tipoPiso = implode(',', $tipoPiso);
                $tipoPiso = preg_replace("/\"/", "'", $tipoPiso);

                $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (
                    SELECT aa.cd_ped_lab AS NUM_PEDIDO_MV,
                    AA.TP_SOLICITACAO AS TIPO_PEDIDO,
                    OO.CD_ATENDIMENTO AS AT_MV,
                    UU.CD_PACIENTE AS HC_MV,
                    UU.NM_PACIENTE AS PTE_MV,
                    AA.DT_PEDIDO AS FECHA_PEDIDO,
                    TO_CHAR(AA.HR_PED_LAB,'HH24:MI') HORA_PEDIDO,
                    PR.NM_PRESTADOR AS MED_MV,
                    ui.DS_UNID_INT AS SECTOR,
                    l.ds_leito as UBICACION,
                    toma.usuario_toma as USUARIO_TOMA
                    from ped_lab aa, atendime oo, paciente uu, prestador pr, unid_int ui, leito l, HMETRO.v_tomademuestra toma
                    where aa.cd_atendimento = oo.cd_atendimento
                    and uu.cd_paciente = oo.cd_paciente
                    and aa.cd_prestador = pr.cd_prestador
                    and aa.cd_setor = ui.cd_setor
                    and oo.cd_leito = l.cd_leito
                    and ui.DS_UNID_INT in ($tipoPiso)
                    order by aa.cd_ped_lab desc
                ) b
                WHERE ROWNUM <= " . $this->length . "
                )
                WHERE NUM > " . $this->start . "
                ";

            } else {

                # Devolver Información
                return array(
                    'status' => true,
                    'data' => array(),
                    'total' => count(array()),
                    'start' => intval($this->start),
                    'length' => intval($this->length),
                    'startDate' => $this->startDate,
                );

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

    public function getDetallePedidoEmeLab($idPedido): array
    {

        try {

            global $config, $http;

            $sql = "SELECT OO.DT_ATENDIMENTO, PR.NM_PRESTADOR,  UU.NM_PACIENTE, OO.CD_ATENDIMENTO, UU.CD_PACIENTE, EE.CD_PED_LAB, EE.CD_EXA_LAB, PP.NM_EXA_LAB, AA.DT_PEDIDO, EE.SN_REALIZADO
            FROM ITPED_LAB EE, PED_LAB AA, ATENDIME  OO, PACIENTE UU, EXA_LAB PP, PRESTADOR  PR
             WHERE EE.CD_PED_LAB = AA.CD_PED_LAB
            AND AA.CD_ATENDIMENTO = OO.CD_ATENDIMENTO
            AND UU.CD_PACIENTE = OO.CD_PACIENTE
            AND PP.CD_EXA_LAB = EE.CD_EXA_LAB
            AND AA.CD_PRESTADOR = PR.CD_PRESTADOR
            AND EE.CD_PED_LAB = '$idPedido' ";

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

            $nombrePaciente = "";
            $hcPaciente = "";
            $fechaAtencion = "";
            $numAtMV = "";
            $numPedidoGema = "";

            foreach ($data as $key) {

                $nombrePaciente = $key['NM_PACIENTE'];
                $hcPaciente = $key['CD_PACIENTE'];
                $fechaAtencion = $key['DT_ATENDIMENTO'];
                $numAtMV = $key['CD_ATENDIMENTO'];
                $numPedidoGema = $key['CD_PED_LAB'];
                $fechaPedido = $key['DT_PEDIDO'];

                if (is_null($key['SN_REALIZADO'])) {
                    $status = "-P-";
                } else if ($key['SN_REALIZADO'] == "S") {
                    $status = "-R-";
                } else {
                    $status = "-C-";
                }

                $resultados[] = $status . " : " . $key['NM_EXA_LAB'];
            }

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Devolver Información
            return array(
                'status' => true,
                'obj' => $data,
                'data' => array(
                    'FECHA_PEDIDO' => $fechaPedido,
                    'NOMBRE_PACIENTE' => $nombrePaciente,
                    'HC' => $hcPaciente . "-01",
                    'NUM_PEDIDO_MV' => $numPedidoGema,
                    'ATEN_MV' => $numAtMV,
                    'DESCRIPCION' => $resultados,
                ),
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getCustomParam($idPedido)
    {

        try {

            $sql = "SELECT  AA.DT_COLETA
            FROM ITPED_LAB EE, PED_LAB AA, ATENDIME  OO, PACIENTE UU, EXA_LAB PP, PRESTADOR  PR
             WHERE EE.CD_PED_LAB = AA.CD_PED_LAB
            AND AA.CD_ATENDIMENTO = OO.CD_ATENDIMENTO
            AND UU.CD_PACIENTE = OO.CD_PACIENTE
            AND PP.CD_EXA_LAB = EE.CD_EXA_LAB
            AND AA.CD_PRESTADOR = PR.CD_PRESTADOR
            AND EE.CD_PED_LAB = '$idPedido' ";

            # Conectar base de datos
            $this->conectar_Oracle_MV();

            # Conectar base de datos
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            return $data['DT_COLETA'];

        } catch (\Throwable $e) {

            return false;
        }

    }

    public function getDetallePedidoLab($idPedido): array
    {

        try {

            global $config, $http;

            $sql = "SELECT OO.DT_ATENDIMENTO, PR.NM_PRESTADOR,  UU.NM_PACIENTE, OO.CD_ATENDIMENTO, UU.CD_PACIENTE, EE.CD_PED_LAB, EE.CD_EXA_LAB, PP.NM_EXA_LAB, AA.DT_PEDIDO, EE.SN_REALIZADO
            FROM ITPED_LAB EE, PED_LAB AA, ATENDIME  OO, PACIENTE UU, EXA_LAB PP, PRESTADOR  PR
             WHERE EE.CD_PED_LAB = AA.CD_PED_LAB
            AND AA.CD_ATENDIMENTO = OO.CD_ATENDIMENTO
            AND UU.CD_PACIENTE = OO.CD_PACIENTE
            AND PP.CD_EXA_LAB = EE.CD_EXA_LAB
            AND AA.CD_PRESTADOR = PR.CD_PRESTADOR
            AND EE.CD_PED_LAB = '$idPedido' ";

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

            $nombrePaciente = "";
            $hcPaciente = "";
            $fechaAtencion = "";
            $numAtMV = "";
            $numPedidoGema = "";

            foreach ($data as $key) {

                $nombrePaciente = $key['NM_PACIENTE'];
                $hcPaciente = $key['CD_PACIENTE'];
                $fechaAtencion = $key['DT_ATENDIMENTO'];
                $numAtMV = $key['CD_ATENDIMENTO'];
                $numPedidoGema = $key['CD_PED_LAB'];
                $fechaPedido = $key['DT_PEDIDO'];

                if (is_null($key['SN_REALIZADO'])) {
                    $status = "-P-";
                } else if ($key['SN_REALIZADO'] == "S") {
                    $status = "-R-";
                } else {
                    $status = "-C-";
                }

                $resultados[] = $status . " : " . $key['NM_EXA_LAB'];
            }

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Devolver Información
            return array(
                'status' => true,
                'obj' => $data,
                'data' => array(
                    'FECHA_PEDIDO' => $fechaPedido,
                    'NOMBRE_PACIENTE' => $nombrePaciente,
                    'HC' => $hcPaciente . "-01",
                    'NUM_PEDIDO_MV' => $numPedidoGema,
                    'ATEN_MV' => $numAtMV,
                    'DESCRIPCION' => $resultados,
                ),
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    # Metodo LOGIN webservice laboratorio ROCHE
    public function wsLab_LOGIN_PCR()
    {

        try {

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'zdk.ws.wSessions.wsdl.xml');

            $Login = $client->Login(
                array(
                    "pstrUserName" => "CWMETRO",
                    "pstrPassword" => "CWM3TR0",
                )
            );

            # Guaradar  KEY de session WS
            $this->pstrSessionKey = $Login->LoginResult;

            # Retorna KEY de session WS
            # return $Login->LoginResult;

        } catch (SoapFault $e) {
            return array('success' => false, 'message' => $e->getMessage());
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

    # Extraer Ordenes de Lis Directo
    public function getConsultaLIS($nhc)
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
                    'pstrPatientID1' => $nhc,
                    'pstrUse' => "5",
                )
            );

            $this->wsLab_LOGOUT();

            $xml = simplexml_load_string($Preview->GetListResult->any);
            $json = json_encode($xml);
            $array = json_decode($json, true);
            $resultados = array();
            $time = time();

            foreach ($array['DefaultDataSet']['SQL'] as $key) {

                // INSERTAR LOGS PARA LABORATORIO
                $log = array(
                    'ID_STUDIO' => $key['SampleID'],
                    'FECHA_REGISTRADO' => $key['RegisterDate'],
                    'STATUS_REGISTRADO' => (int) 1,
                    'FECHA' => $key['RegisterDate'] . ' ' . $key['RegisterHour'],
                    'TIMESTAMP' => strtotime($key['RegisterDate'] . ' ' . $key['RegisterHour']),
                    'fecha_' => date('Y-m-d H:i:s', strtotime($key['RegisterDate'] . ' ' . $key['RegisterHour'])),
                );

                $resultados[] = $log;

            }

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $resultados,
                'total' => count($resultados),
            );

        } catch (\Exception $b) {

            $this->wsLab_LOGOUT();

            return array('status' => false, 'data' => [], 'message' => $b->getMessage(), 'errorCode' => $b->getCode());

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function extraerData($sc, $fecha)
    {

        try {

            # INICIAR SESSION

            $this->wsLab_LOGIN();

            $client = new SoapClient(
                dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wOrderTests.xml',
                array(
                    'soap_version' => SOAP_1_1,
                    'exceptions' => true,
                    'trace' => 1,
                    'cache_wsdl' => WSDL_CACHE_NONE,
                )
            );

            $Preview = $client->GetListAndGroups(
                array(
                    'pstrSessionKey' => $this->pstrSessionKey,
                    'pstrSampleID' => $sc,
                    'pstrRegisterDate' => $fecha,
                )
            );

            $this->wsLab_LOGOUT();

            $xml = simplexml_load_string($Preview->GetListAndGroupsResult->any);

            $json = json_encode($xml);

            $array = json_decode($json, true);

            $tests = array();

            if (!isset($array['DefaultDataSet']['SQL']['rTests'])) {

                foreach ((array) $array['DefaultDataSet']['SQL'] as $key) {

                    if (strpos($key['ItemAbbreviation'], 'Tit') !== false) {
                        $tests[] = $key['ItemName'];
                    }

                }

            } else {

                $key = $array['DefaultDataSet']['SQL']['rTests'];

                if (strpos($key['ItemAbbreviation'], 'Tit') !== false) {
                    $tests[] = $key['ItemName'];
                }

            }

            return $tests;

        } catch (\Exception $e) {

            $this->wsLab_LOGOUT();

            return array();

        } catch (ModelsException $b) {

            return array();

        }
    }

    public function getOrdenesLaboratorio($nhc)
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
                    'pstrPatientID1' => $nhc,
                    'pstrUse' => "5",
                )
            );

            $this->wsLab_LOGOUT();

            $xml = simplexml_load_string($Preview->GetListResult->any);
            $json = json_encode($xml);
            $array = json_decode($json, true);

            # Ya no existe resultadso
            $this->notResults($array);

            $resultados = array();

            $time = time();

            if (isset($array['DefaultDataSet']['SQL']['Origin'])) {
                $array['DefaultDataSet']['SQL'] = array($array['DefaultDataSet']['SQL']);
            }

            $i = 0;

            $pte = '';

            foreach ($array['DefaultDataSet']['SQL'] as $key) {

                $idHashRes = Helper\Strings::ocrend_encode($key['SampleID'] . '.' . date('d-m-Y', strtotime($key['RegisterDate'])), 'hm');

                /*
                if ($i <= 5) {
                $examenes = $this->extraerData($key['SampleID'], $key['RegisterDate']);
                } else {
                $examenes = array();
                }
                 */

                $examenes = array();

                // INSERTAR LOGS PARA LABORATORIO
                $log = array(
                    'ORIGEN' => ($key['Origin'] == 'Urgencias' ? 'Emergencia' : $key['Origin']),
                    'MEDICO' => (isset($key['Doctor']) ? $key['Doctor'] : ''),
                    'ID_STUDIO' => $key['SampleID'],
                    'FECHA_REGISTRADO' => date('d-m-Y', strtotime($key['RegisterDate'])),
                    'STATUS_REGISTRADO' => (int) 1,
                    'FECHA' => date('d-m-Y H:i', strtotime($key['RegisterDate'] . ' ' . $key['RegisterHour'])),
                    'TIMESTAMP' => strtotime($key['RegisterDate'] . ' ' . $key['RegisterHour']),
                    'fecha_' => date('Y-m-d H:i:s', strtotime($key['RegisterDate'] . ' ' . $key['RegisterHour'])),
                    'urlPdf' => $this->urlPDF . $idHashRes,
                    'deep_link' => $this->viewPDF . $idHashRes,
                    'IsOrderValidated' => $key['IsOrderValidated'],
                    'PTE' => $key['LastName'] . ' ' . $key['FirstName'],
                    '_key' => $key,
                    'examenes' => $examenes,

                );

                $i++;

                $pte = $log['PTE'];

                $resultados[] = $log;

            }

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $resultados,
                'total' => count($resultados),
                'pte' => $pte,
            );

        } catch (\Exception $b) {

            $this->wsLab_LOGOUT();

            return array('status' => false, 'data' => [], 'message' => $b->getMessage(), 'errorCode' => $b->getCode(), 'pte' => '');

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage(), 'errorCode' => $e->getCode(), 'pte' => '');

        }

    }

    public function getOrdenesLaboratorioMV($nhc)
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
                    'pstrPatientID1' => $nhc,
                    'pstrUse' => "5",
                )
            );

            $this->wsLab_LOGOUT();

            $xml = simplexml_load_string($Preview->GetListResult->any);
            $json = json_encode($xml);
            $array = json_decode($json, true);

            # Ya no existe resultadso
            $this->notResults($array);

            $resultados = array();

            $time = time();

            if (isset($array['DefaultDataSet']['SQL']['Origin'])) {
                $array['DefaultDataSet']['SQL'] = array($array['DefaultDataSet']['SQL']);
            }

            $i = 0;

            $pte = '';

            foreach ($array['DefaultDataSet']['SQL'] as $key) {

                $idHashRes = Helper\Strings::ocrend_encode($key['SampleID'] . '.' . date('d-m-Y', strtotime($key['RegisterDate'])), 'hm');

                /*
                if ($i <= 5) {
                $examenes = $this->extraerData($key['SampleID'], $key['RegisterDate']);
                } else {
                $examenes = array();
                }
                 */

                $examenes = array();

                // INSERTAR LOGS PARA LABORATORIO
                $log = array(
                    'ORIGEN' => ($key['Origin'] == 'Urgencias' ? 'Emergencia' : $key['Origin']),
                    'MEDICO' => (isset($key['Doctor']) ? $key['Doctor'] : ''),
                    'ID_STUDIO' => $key['SampleID'],
                    'FECHA_REGISTRADO' => date('d-m-Y', strtotime($key['RegisterDate'])),
                    'STATUS_REGISTRADO' => (int) 1,
                    'FECHA' => date('d-m-Y H:i', strtotime($key['RegisterDate'] . ' ' . $key['RegisterHour'])),
                    'TIMESTAMP' => strtotime($key['RegisterDate'] . ' ' . $key['RegisterHour']),
                    'fecha_' => date('Y-m-d H:i:s', strtotime($key['RegisterDate'] . ' ' . $key['RegisterHour'])),
                    'urlPdf' => $this->urlPDF . $idHashRes,
                    'deep_link' => $this->viewPDF . $idHashRes,
                    'IsOrderValidated' => $key['IsOrderValidated'],
                    'PTE' => $key['LastName'] . ' ' . $key['FirstName'],
                    '_key' => $key,
                    'examenes' => $examenes,

                );

                $pte = $log['PTE'];

                $i++;

                $resultados[] = $log;

            }

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $resultados,
                'total' => count($resultados),
                'pte' => $pte,
            );

        } catch (\Exception $b) {

            $this->wsLab_LOGOUT();

            return array('status' => false, 'data' => [], 'message' => $b->getMessage(), 'errorCode' => $b->getCode(), 'pte' => '');

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage(), 'errorCode' => $e->getCode(), 'pte' => '');

        }

    }

    public function getResultadosLaboratorio($nhc)
    {

        try {

            global $config, $http;

            #Resultados GEMA
            $resGema = $this->getOrdenesLaboratorio($nhc);

            if (str_ends_with($nhc, '01')) {
                #Resultados MV
                $resMV = $this->getOrdenesLaboratorioMV(substr($nhc, 0, -2));
            } else {
                #Resultados MV
                $resMV = $this->getOrdenesLaboratorioMV($nhc);
            }

            $resultados = array_merge($resMV['data'], $resGema['data']);

            if (count($resultados) === 0) {
                throw new ModelsException('El Paciente todavía no tiene resultados disponibles.');
            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->orderMultiDimensionalArray($resultados, 'TIMESTAMP'),
                'total' => count($resultados),
                'ResGema' => $resGema,
                'ResMV' => $resMV,

            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage(), '_data' => $resMV);

        }

    }

    public function getConsultaResultados()
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
                    'pstrPatientID1' => "93493401",
                    'pstrUse' => "5",
                )
            );

            $this->wsLab_LOGOUT();

            $xml = simplexml_load_string($Preview->GetListResult->any);
            $json = json_encode($xml);
            $array = json_decode($json, true);

            $resultados = array();

            $time = time();

            foreach ($array['DefaultDataSet']['SQL'] as $key) {

                // INSERTAR LOGS PARA LABORATORIO
                $log = array(
                    'ID_STUDIO' => $key['SampleID'],
                    'FECHA_REGISTRADO' => $key['RegisterDate'],
                    'STATUS_REGISTRADO' => (int) 1,
                    'FECHA' => $key['RegisterDate'] . ' ' . $key['RegisterHour'],
                    'TIMESTAMP' => strtotime($key['RegisterDate'] . ' ' . $key['RegisterHour']),
                    'fecha_' => date('Y-m-d H:i:s', strtotime($key['RegisterDate'] . ' ' . $key['RegisterHour'])),
                );

                $resultados[] = $log;

            }

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $resultados,
                'total' => count($resultados),
            );

        } catch (\Exception $b) {

            $this->wsLab_LOGOUT();

            return array('status' => false, 'data' => [], 'message' => $b->getMessage(), 'errorCode' => $b->getCode());

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    # Metodo wReports webservice laboratorio ROCHEdevuelve el resultado pdf del paciente
    public function getInformeResultadoPCR(string $SC, string $FECHA)
    {

        try {

            $id_resultado = Helper\Strings::ocrend_encode($SC, 'SC');

            # INICIAR SESSION

            $FECHA_final = explode('-', $FECHA);

            $FECHA = $FECHA_final[2] . '-' . $FECHA_final[1] . '-' . $FECHA_final[0];

            $this->wsLab_LOGIN_PCR();

            $client = new SoapClient(
                dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wResults.xml',
                array(
                    'soap_version' => SOAP_1_1,
                    'exceptions' => true,
                    'trace' => 1,
                    'cache_wsdl' => WSDL_CACHE_NONE,
                )
            );

            $Preview = $client->GetResults(
                array(
                    'pstrSessionKey' => $this->pstrSessionKey,
                    'pstrSampleID' => $SC,
                    # '0015052333',
                    'pstrRegisterDate' => $FECHA,
                )
            );

            $this->wsLab_LOGOUT();

            # return array('status' => true, 'data' => '&');

            #  return $Preview;

            if (!isset($Preview->GetResultsResult)) {
                throw new ModelsException('Error 2 => Resultado no disponible.');
            }

            # REVISAR SI EXISTEN PRUEBAS NO DISPONIBLES
            $listaPruebas = $Preview->GetResultsResult->Orders->LISOrder->LabTests->LISLabTest;

            if (!isset($Preview->GetResultsResult->Orders->LISOrder->MotiveDesc)) {
                $MotiveDesc = 'LABORATORIO';
            } else {
                $MotiveDesc = $Preview->GetResultsResult->Orders->LISOrder->MotiveDesc;
            }

            $i = 0;

            $lista = array();

            if (is_array($listaPruebas)) {
                foreach ($listaPruebas as $key) {
                    $lista[] = array(
                        'TestID' => $key->TestID,
                        'TestStatus' => $key->TestStatus,
                        'TestName' => $key->TestName,
                        'MotiveDesc' => $MotiveDesc,
                    );
                }
            } else {
                $lista[] = array(
                    'TestID' => $listaPruebas->TestID,
                    'TestStatus' => $listaPruebas->TestStatus,
                    'TestName' => $listaPruebas->TestName,
                    'MotiveDesc' => $MotiveDesc,
                );
            }

            # return $lista;
            $esPcr = false;

            foreach ($lista as $k) {

                // 9720
                if ($k['TestID'] == '9720') {
                    $esPcr = true;
                }

            }

            if ($esPcr) {

                $copyPCR = $this->getCopyPCR($SC, $FECHA);

                return array('status' => true, 'data' => $copyPCR['urlDoc']);

            } else {
                throw new ModelsException('Error 3 => Resultado no disponible.PruEba no es PCR.', 3);
            }

        } catch (\Exception $e) {

            $this->wsLab_LOGOUT();

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => 2);

        } catch (ModelsException $b) {

            return array('status' => false, 'message' => $b->getMessage(), 'errorCode' => $b->getCode());

        }
    }

    # Metodo wReports webservice laboratorio ROCHEdevuelve el resultado pdf del paciente
    public function wsLab_GET_REPORT_PDF_PCR(string $SC, string $FECHA)
    {

        try {

            # INICIAR SESSION
            $this->wsLab_LOGIN_PCR();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wReports.wsdl.xml');

            # $FECHA_final = explode('-', $FECHA);

            $Preview = $client->Preview(
                array(
                    "pstrSessionKey" => $this->pstrSessionKey,
                    "pstrSampleID" => $SC,
                    # '0015052333',
                    "pstrRegisterDate" => $FECHA,
                    # $FECHA_final[2] . '-' . $FECHA_final[1] . '-' . $FECHA_final[0], # '2018-11-05',
                    "pstrFormatDescription" => 'SARS',
                    "pstrPrintTarget" => 'Destino por defecto',
                )
            );

            # CERRAR SESSION POR LICENCIAS HSF
            $this->wsLab_LOGOUT();

            # No existe documento

            if (!isset($Preview->PreviewResult)) {
                throw new ModelsException('Error 0 => No existe el documento solicitado.');
            }

            # No existe documento

            if (isset($Preview->PreviewResult) or $Preview->PreviewResult == '0') {

                if ($Preview->PreviewResult == '0') {

                    throw new ModelsException('Error 1 => No existe el documento solicitado.');

                } else {

                    return array(
                        'status' => true,
                        'data' => $Preview->PreviewResult,
                    );

                }

            }

            #
            throw new ModelsException('Error 2 => No existe el documento solicitado.');

        } catch (SoapFault $e) {

            if ($e->getCode() == 0) {
                return array('status' => false, 'message' => $e->getMessage());
            } else {
                return array('status' => false, 'message' => $e->getMessage());

            }

        } catch (ModelsException $b) {

            if ($b->getCode() == 0) {
                return array('status' => false, 'message' => $b->getMessage());
            } else {
                return array('status' => false, 'message' => $b->getMessage());

            }
        }

    }

    # Metodo wReports webservice laboratorio ROCHEdevuelve el resultado pdf del paciente
    public function getCopyPCR(string $SC, string $FECHA)
    {

        try {

            $doc_resultado = $this->wsLab_GET_REPORT_PDF_PCR($SC, $FECHA);

            // No existe documeneto
            if (!$doc_resultado['status']) {
                throw new ModelsException($doc_resultado['message']);
            }

            $id_resultado = Helper\Strings::ocrend_encode($SC, 'SC');

            $url = $doc_resultado['data'];
            $destination = "../../lis/v1/downloads/resultados/PCR/" . $SC . ".pdf";
            $fp = fopen($destination, 'w+');
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_exec($ch);
            curl_close($ch);

            fclose($fp);

            // Generate codigo QR

            # return array('status' => true, 'urlDoc' => '&&');

            $destination = "../../lis/v1/downloads/resultados/PCR/" . $SC . ".pdf";

            $urlPdf = 'https://api.hospitalmetropolitano.org/lis/v1/qrs/resultados/' . $id_resultado . '.pdf';

            # Generate QR CODE
            $qrCode = new QrCode($urlPdf);
            $qrCode->setLogoPath('../../lis/v1/downloads/resultados/PCR/QRS/hm.png');

            // Save it to a file
            $qrCode->writeFile('../../lis/v1/downloads/resultados/PCR/QRS/' . $id_resultado . '.png');

            $qrImage = '../../lis/v1/downloads/resultados/PCR/QRS/' . $id_resultado . '.png';

            $qrAcess = '../../lis/v1/downloads/resultados/PCR/QRS/acess.png';

            // Editar template PDF QR

            $pdf = new Fpdi();

            $staticIds = array();
            $pageCount = $pdf->setSourceFile($destination);
            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $staticIds[$pageNumber] = $pdf->importPage($pageNumber);
            }

            // get the page count of the uploaded file
            $pageCount = $pdf->setSourceFile($destination);

            // let's track the page number for the filler page
            $fillerPageCount = 1;
            // import the uploaded document page by page
            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {

                if ($fillerPageCount == 1) {
                    // add the current filler page
                    $pdf->AddPage();
                    $pdf->useTemplate($staticIds[$fillerPageCount]);
                    $pdf->Image($qrImage, 5, 237, 40, 40);
                    // QR ACESS
                    $pdf->Image($qrAcess, 46, 237.1, 40, 39);
                }

                // update the filler page number or reset it
                $fillerPageCount++;
                if ($fillerPageCount > count($staticIds)) {
                    $fillerPageCount = 1;
                }
            }

            $newDestination = "../../lis/v1/downloads/resultados/PCR/" . $id_resultado . ".qr.pdf";

            $pdf->Output('F', $newDestination);

            return array(
                'status' => true,
                'urlDoc' => $urlPdf,
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
            );
        }

    }

    public function getCopyLAB(string $SC, string $FECHA)
    {

        try {

            $id_resultado = Helper\Strings::ocrend_encode($SC, 'SC');

            /*
            $destination = "../../v2/pacientes/docs/lab/" . $id_resultado . ".pdf";
            $doc = file_exists($destination);
            if ($doc) {
            $urlPdf = 'https://api.hospitalmetropolitano.org/v2/pacientes/d/' . $id_resultado . '.pdf';
            return array(
            'status' => true,
            'data' => $urlPdf,
            );
            }
             */

            $doc_resultado = $this->wsLab_GET_REPORT_PDF($SC, $FECHA);

            // No existe documeneto
            if (!$doc_resultado['status']) {
                throw new ModelsException($doc_resultado['message']);
            }

            $url = $doc_resultado['data'];
            $destination = "../../v2/pacientes/docs/lab/" . $id_resultado . ".pdf";
            $fp = fopen($destination, 'w+');
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_exec($ch);
            curl_close($ch);

            fclose($fp);

            $urlPdf = 'https://api.hospitalmetropolitano.org/v2/pacientes/d/' . $id_resultado . '.pdf';

            return array(
                'status' => true,
                'data' => $urlPdf,
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
            );
        }

    }

    # Metodo wReports webservice laboratorio ROCHEdevuelve el resultado pdf del paciente
    public function wsLab_GET_REPORT_PDF(string $SC, string $FECHA)
    {

        try {

            # INICIAR SESSION
            $this->wsLab_LOGIN();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wReports.wsdl.xml');

            $FECHA_final = explode('-', $FECHA);

            $Preview = $client->Preview(
                array(
                    "pstrSessionKey" => $this->pstrSessionKey,
                    "pstrSampleID" => $SC,
                    # '0015052333',
                    "pstrRegisterDate" => $FECHA_final[2] . '-' . $FECHA_final[1] . '-' . $FECHA_final[0],
                    # '2018-11-05',
                    "pstrFormatDescription" => 'METROPOLITANO',
                    "pstrPrintTarget" => 'Destino por defecto',
                )
            );

            # CERRAR SESSION POR LICENCIAS HSF
            $this->wsLab_LOGOUT();

            # No existe documento

            if (!isset($Preview->PreviewResult)) {
                throw new ModelsException('No existe el documento solicitado.', 4080);
            }

            # No existe documento

            if (isset($Preview->PreviewResult) or $Preview->PreviewResult == '0') {

                if ($Preview->PreviewResult == '0') {

                    throw new ModelsException('No existe el documento solicitado.', 4080);

                } else {

                    return array(
                        'status' => true,
                        'data' => $Preview->PreviewResult,

                    );

                }

            }

            #
            throw new ModelsException('No existe el documento solicitado.', 4080);

        } catch (SoapFault $e) {

            if ($e->getCode() == 0) {
                return array('success' => false, 'message' => 'No existe el documento solicitado.', 'errorCode' => 4080);
            } else {
                return array('success' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

            }

        } catch (ModelsException $b) {

            if ($b->getCode() == 0) {
                return array('success' => false, 'message' => 'No existe el documento solicitado.', 'errorCode' => 4080);
            } else {
                return array('success' => false, 'message' => $b->getMessage(), 'errorCode' => $b->getCode());

            }
        }

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

    private function isTipoPiso($value)
    {

        $pos = strpos($value, 'tipoFiltro');

        if ($pos !== false) {
            return true;
        } else {
            return false;
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
