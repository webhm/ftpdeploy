<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models\medicos;

use app\models\medicos as Model;
use DateTime;
use Doctrine\DBAL\DriverManager;
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Honorarios
 */

class Honorarios extends Models implements IModels
{
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
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracleNaf'], $_config);

    }

    private function conectar_Oracle_MV()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();

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

    public function getPedidoImagenPlus($numPedido)
    {

        try {

            $detallePedido = $this->getDetallePedidoImagenPlus($numPedido);

            if ($detallePedido) {

                $cd_pre_med = $detallePedido['CD_PRE_MED'];

                # Conectar base de datos
                $this->conectar_Oracle_MV();

                $this->setSpanishOracle();

                # Devolver todos los resultados
                $sql = " SELECT e.ds_exa_rx EXAMEN,
            it.sn_realizado STATUS
            FROM ped_rx pd,
                itped_rx it,
                exa_rx e
            WHERE e.cd_exa_rx = it.cd_exa_rx
            AND it.cd_ped_rx = pd.cd_ped_rx
            AND pd.cd_pre_med = '$cd_pre_med'  ";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $dataPedido = $stmt->fetchAll();

                return array(
                    'status' => true,
                    'data' => $detallePedido,
                    'examenes' => $dataPedido,

                );

            } else {

                return array(
                    'status' => false,
                    'data' => [],
                    'examenes' => [],

                );

            }

        } catch (ModelsException $e) {
            return array('status' => false, 'data' => [], 'examenes' => [], 'message' => $e->getMessage());
        }

    }

    public function getPedidoImagen($numPedido)
    {

        try {

            $detallePedido = $this->getDetallePedidoImagen($numPedido);

            if ($detallePedido) {

                $cd_pre_med = $detallePedido['CD_PRE_MED'];

                # Conectar base de datos
                $this->conectar_Oracle_MV();

                $this->setSpanishOracle();

                # Devolver todos los resultados
                $sql = " SELECT e.ds_exa_rx EXAMEN,
            it.sn_realizado STATUS
            FROM ped_rx pd,
                itped_rx it,
                exa_rx e
            WHERE e.cd_exa_rx = it.cd_exa_rx
            AND it.cd_ped_rx = pd.cd_ped_rx
            AND pd.cd_pre_med = '$cd_pre_med'  ";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $dataPedido = $stmt->fetchAll();

                return array(
                    'status' => true,
                    'data' => $detallePedido,
                    'examenes' => $dataPedido,

                );

            } else {

                return array(
                    'status' => false,
                    'data' => [],
                    'examenes' => [],

                );

            }

        } catch (ModelsException $e) {
            return array('status' => false, 'data' => [], 'examenes' => [], 'message' => $e->getMessage());
        }

    }

    public function getDetallePedidoImagenPlus($numPedido)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle_MV();

            $this->setSpanishOracle();

            # Devolver todos los resultados
            $sql = " SELECT pd.cd_ped_rx, t5.cd_paciente,
            t6.nm_paciente,
            TRUNC((MONTHS_BETWEEN(SYSDATE, t6.dt_nascimento)) / 12) || ' AÑOS' EDAD,
            t1.cd_atendimento AT_MV,
            pe.nm_prestador MED_MV,
            e.ds_especialid ESPECIALIDAD,
            t1.cd_pre_med,
            pd.tp_motivo TIPO_PEDIDO,
            pd.dt_pedido FECHA_PEDIDO,
            TO_CHAR(pd.hr_pedido, 'HH24:MI') HORA_PEDIDO,
            t3.ds_itpre_med OBS_PRE_MED,
            l.ds_leito UBICACION,
            ui.ds_unid_int SECTOR,
            pd.nr_peso PESO,
            pd.nr_altura ALTURA,
            s.ds_servico SERVICIO
     FROM pre_med t1,
          itpre_med t3,
          tip_presc t4,
          atendime t5,
          especialid e,
          paciente t6,
          tip_esq t7,
          ped_rx pd,
          leito l,
          unid_int ui,
          prestador pe,
          servico s
     WHERE t1.cd_objeto IN (420)
       AND t1.fl_impresso = 'S'
       AND t1.cd_pre_med = t3.cd_pre_med
       AND t1.cd_pre_med = pd.cd_pre_med
       AND t3.cd_tip_presc = t4.cd_tip_presc
       AND t4.cd_tip_esq IN ('EXI')
       AND t1.cd_atendimento = t5.cd_atendimento
       AND t5.cd_paciente = t6.cd_paciente
       AND t4.cd_tip_esq = t7.cd_tip_esq
       AND pd.cd_prestador = pe.cd_prestador (+)
       AND t5.cd_especialid = e.cd_especialid (+)
       AND t5.cd_leito = l.cd_leito (+)
       AND l.cd_unid_int = ui.cd_unid_int (+)
       AND t5.cd_servico = s.cd_servico (+)
       AND t1.cd_pre_med = '$numPedido'  ";

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataPedido = $stmt->fetch();

            return $dataPedido;

        } catch (ModelsException $e) {
            return false;
        }

    }

    public function getDetallePedidoImagen($numPedido)
    {

        try {

            $_numPedido = substr($numPedido, 2);

            $numPedido = $_numPedido;

            # Conectar base de datos
            $this->conectar_Oracle_MV();

            $this->setSpanishOracle();

            # Devolver todos los resultados
            $sql = " SELECT pd.cd_ped_rx, t5.cd_paciente,
            t6.nm_paciente,
            TRUNC((MONTHS_BETWEEN(SYSDATE, t6.dt_nascimento)) / 12) || ' AÑOS' EDAD,
            t1.cd_atendimento AT_MV,
            pe.nm_prestador MED_MV,
            e.ds_especialid ESPECIALIDAD,
            t1.cd_pre_med,
            pd.tp_motivo TIPO_PEDIDO,
            pd.dt_pedido FECHA_PEDIDO,
            TO_CHAR(pd.hr_pedido, 'HH24:MI') HORA_PEDIDO,
            t3.ds_itpre_med OBS_PRE_MED,
            l.ds_leito UBICACION,
            ui.ds_unid_int SECTOR,
            pd.nr_peso PESO,
            pd.nr_altura ALTURA,
            s.ds_servico SERVICIO
     FROM pre_med t1,
          itpre_med t3,
          tip_presc t4,
          atendime t5,
          especialid e,
          paciente t6,
          tip_esq t7,
          ped_rx pd,
          leito l,
          unid_int ui,
          prestador pe,
          servico s
     WHERE
       t1.cd_objeto IN (420)
       AND t1.fl_impresso = 'S'
       AND t1.cd_pre_med = t3.cd_pre_med
       AND t1.cd_pre_med = pd.cd_pre_med
       AND t3.cd_tip_presc = t4.cd_tip_presc
       AND t4.cd_tip_esq IN ('EXI')
       AND t1.cd_atendimento = t5.cd_atendimento
       AND t5.cd_paciente = t6.cd_paciente
       AND t4.cd_tip_esq = t7.cd_tip_esq
       AND pd.cd_prestador = pe.cd_prestador (+)
       AND t5.cd_especialid = e.cd_especialid (+)
       AND t5.cd_leito = l.cd_leito (+)
       AND l.cd_unid_int = ui.cd_unid_int (+)
       AND t5.cd_servico = s.cd_servico (+)
       AND pd.cd_ped_rx = '$numPedido'  ";

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataPedido = $stmt->fetch();

            return $dataPedido;

        } catch (ModelsException $e) {
            return false;
        }

    }

    public function getPedidosImagen()
    {

        try {

            global $http;

            $typeFilter = $http->query->get('idFiltro');

            # Conectar base de datos
            $this->conectar_Oracle_MV();

            $this->setSpanishOracle();

            if ($typeFilter == '1') {

                # Devolver todos los resultados
                $sql = " SELECT DISTINCT  t1.cd_pre_med,  t5.cd_paciente,
            t6.nm_paciente,
            TRUNC((MONTHS_BETWEEN(SYSDATE, t6.dt_nascimento)) / 12) || ' AÑOS' EDAD,
            t1.cd_atendimento AT_MV,
            pe.nm_prestador MED_MV,
            e.ds_especialid ESPECIALIDAD,
            pd.tp_motivo TIPO_PEDIDO,
            pd.dt_pedido FECHA_PEDIDO,
            TO_CHAR(pd.hr_pedido, 'HH24:MI') HORA_PEDIDO,
            t3.ds_itpre_med OBS_PRE_MED,
            l.ds_leito UBICACION,
            ui.ds_unid_int SECTOR,
            pd.nr_peso PESO,
            pd.nr_altura ALTURA,
            s.ds_servico SERVICIO,
            t3.sn_cancelado SN_CANCELADO
     FROM pre_med t1,
          itpre_med t3,
          tip_presc t4,
          atendime t5,
          especialid e,
          paciente t6,
          tip_esq t7,
          ped_rx pd,
          leito l,
          unid_int ui,
          prestador pe,
          servico s
     WHERE TRUNC(t1.hr_pre_med) = TRUNC(SYSDATE)
       AND t1.cd_objeto IN (420)
       AND t1.fl_impresso = 'S'
       AND t1.cd_pre_med = t3.cd_pre_med
       AND t1.cd_pre_med = pd.cd_pre_med
       AND t3.cd_tip_presc = t4.cd_tip_presc
       AND t3.sn_cancelado = 'N'
       AND t4.cd_tip_esq IN ('EXI')
       AND t1.cd_atendimento = t5.cd_atendimento
       AND t5.cd_paciente = t6.cd_paciente
       AND t4.cd_tip_esq = t7.cd_tip_esq (+)
       AND pd.cd_prestador = pe.cd_prestador (+)
       AND t5.cd_especialid = e.cd_especialid (+)
       AND t5.cd_leito = l.cd_leito (+)
       AND l.cd_unid_int = ui.cd_unid_int (+)
       AND t5.cd_servico = s.cd_servico (+)
     ORDER BY t1.cd_pre_med ";

            } else {

                $fechaDesde = $http->query->get('fechaDesde');
                $fechaHasta = $http->query->get('fechaHasta');

                # Devolver todos los resultados
                $sql = " SELECT DISTINCT  t1.cd_pre_med,  t5.cd_paciente,
            t6.nm_paciente,
            TRUNC((MONTHS_BETWEEN(SYSDATE, t6.dt_nascimento)) / 12) || ' AÑOS' EDAD,
            t1.cd_atendimento AT_MV,
            pe.nm_prestador MED_MV,
            e.ds_especialid ESPECIALIDAD,
            pd.tp_motivo TIPO_PEDIDO,
            pd.dt_pedido FECHA_PEDIDO,
            TO_CHAR(pd.hr_pedido, 'HH24:MI') HORA_PEDIDO,
            t3.ds_itpre_med OBS_PRE_MED,
            l.ds_leito UBICACION,
            ui.ds_unid_int SECTOR,
            pd.nr_peso PESO,
            pd.nr_altura ALTURA,
            s.ds_servico SERVICIO,
            t3.sn_cancelado SN_CANCELADO
     FROM pre_med t1,
          itpre_med t3,
          tip_presc t4,
          atendime t5,
          especialid e,
          paciente t6,
          tip_esq t7,
          ped_rx pd,
          leito l,
          unid_int ui,
          prestador pe,
          servico s
     WHERE TRUNC(t1.hr_pre_med) >= TO_DATE('$fechaDesde', 'DD-MM-YYYY')
       AND TRUNC(t1.hr_pre_med) <= TO_DATE('$fechaHasta', 'DD-MM-YYYY')
       AND t1.cd_objeto IN (420)
       AND t1.fl_impresso = 'S'
       AND t1.cd_pre_med = t3.cd_pre_med
       AND t1.cd_pre_med = pd.cd_pre_med
       AND t3.cd_tip_presc = t4.cd_tip_presc
       AND t3.sn_cancelado = 'N'
       AND t4.cd_tip_esq IN ('EXI')
       AND t1.cd_atendimento = t5.cd_atendimento
       AND t5.cd_paciente = t6.cd_paciente
       AND t4.cd_tip_esq = t7.cd_tip_esq (+)
       AND pd.cd_prestador = pe.cd_prestador (+)
       AND t5.cd_especialid = e.cd_especialid (+)
       AND t5.cd_leito = l.cd_leito (+)
       AND l.cd_unid_int = ui.cd_unid_int (+)
       AND t5.cd_servico = s.cd_servico (+)
     ORDER BY t1.cd_pre_med ";

            }

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            return array(
                'status' => true,
                'data' => $data,
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

    public function getMisFacturasPagadas()
    {

        try {

            global $config, $http;

            $this->getAuthorization();

            $codMedico = $this->user->codMedico;

            # seteo de valores para paginacion
            $fecha = date('d-m-Y');

            $this->start = (int) $http->query->get('start');

            $this->length = (int) $http->query->get('length');

            if ($this->start >= 10) {
                $this->length = $this->start + $this->length;
            }

            $typeFilter = $http->query->get('typeFilter');

            # Busqueda por nombres
            if ($typeFilter == 1) {

                $time = new DateTime();
                $nuevoTime = strtotime('-1 month', $time->getTimestamp());
                $nuevaFecha = date('Y-m-d', $nuevoTime);
                $fecha = date('Y-m-d');

                $this->searchField = $this->quitar_tildes(mb_strtoupper($this->sanear_string($http->request->get('searchField')), 'UTF-8'));

                $searchField = str_replace(" ", "%", $this->searchField);

                $sql = " SELECT tr.fecha,
                a.tipo_doc,
                rd.tipo_doc,
                a.serie_fisico||'-'||a.no_fisico factura,
                nvl(a.numero_prefactura,'S/N') prefactura,
                nvl(a.hist_clinica_pac,0) historia_clinica,
                fun_busca_nombre_pte(nvl(a.hist_clinica_pac,0)) paciente,
                nvl(a.numero_admision,0) admision,
                substr(a.detalle,1,80) detalle,
                tr.no_docu no_transaccion,
                sum(nvl(a.monto,0)) monto,
                sum(nvl(rd.monto,0)) cancela,
                sum(nvl(a.saldo,0)) saldo
        from arcpmd a, arcprd rd, arcpmd tr
        where a.no_cia = '01'
        and a.no_prove = '$codMedico'
        and a.tipo_doc NOT in ( 'PH')
        and a.ind_act != 'P'
        and a.anulado = 'N'
        and rd.no_cia = a.no_cia
        and rd.tipo_refe = a.tipo_doc
        and rd.no_refe = a.no_docu
       and rd.tipo_doc NOT  in ( 'RT' , 'AN')
        and tr.no_cia = rd.no_cia
        and tr.tipo_doc = rd.tipo_doc
        and tr.no_docu = rd.no_docu
        and tr.no_prove = rd.no_prove
        and trunc(tr.fecha) >= '$nuevaFecha'
        and trunc(tr.fecha) <= '$fecha'
        and fun_busca_nombre_pte(nvl(a.hist_clinica_pac,0)) LIKE '%$searchField%'
        group by tr.fecha, a.tipo_doc, rd.tipo_doc, a.serie_fisico||'-'||a.no_fisico,
               a.numero_prefactura, a.hist_clinica_pac,
               a.numero_admision,
               a.detalle,
               tr.no_docu
        order by 1 desc  ";

            }

            # Busqueda por factura
            if ($typeFilter == 2) {

                $time = new DateTime();
                $nuevoTime = strtotime('-1 month', $time->getTimestamp());
                $nuevaFecha = date('Y-m-d', $nuevoTime);
                $fecha = date('Y-m-d');

                $searchField = $http->request->get('searchField');

                $sql = " SELECT tr.fecha,
                a.tipo_doc,
                rd.tipo_doc,
                a.serie_fisico||'-'||a.no_fisico factura,
                nvl(a.numero_prefactura,'S/N') prefactura,
                nvl(a.hist_clinica_pac,0) historia_clinica,
                fun_busca_nombre_pte(nvl(a.hist_clinica_pac,0)) paciente,
                nvl(a.numero_admision,0) admision,
                substr(a.detalle,1,80) detalle,
                tr.no_docu no_transaccion,
                sum(nvl(a.monto,0)) monto,
                sum(nvl(rd.monto,0)) cancela,
                sum(nvl(a.saldo,0)) saldo
        from arcpmd a, arcprd rd, arcpmd tr
        where a.no_cia = '01'
        and a.no_prove = '$codMedico'
        and a.tipo_doc NOT in ( 'PH')
        and a.ind_act != 'P'
        and a.anulado = 'N'
        and rd.no_cia = a.no_cia
        and rd.tipo_refe = a.tipo_doc
        and rd.no_refe = a.no_docu
       and rd.tipo_doc NOT  in ( 'RT' , 'AN')
        and tr.no_cia = rd.no_cia
        and tr.tipo_doc = rd.tipo_doc
        and tr.no_docu = rd.no_docu
        and tr.no_prove = rd.no_prove
        and trunc(tr.fecha) >= '$nuevaFecha'
        and trunc(tr.fecha) <= '$fecha'
        and a.no_fisico LIKE '%$searchField%'
        group by tr.fecha, a.tipo_doc, rd.tipo_doc, a.serie_fisico||'-'||a.no_fisico,
               a.numero_prefactura, a.hist_clinica_pac,
               a.numero_admision,
               a.detalle,
               tr.no_docu
        order by 1 desc  ";

            }

            # Busqueda por fechas
            if ($typeFilter == 3) {

                $time = new DateTime();
                $fecha = date('Y-m-d');

                $fechaDesde = $http->query->get('fechaDesde');
                $fechaHasta = $http->query->get('fechaHasta');

                $sql = "   SELECT tr.fecha,
                a.tipo_doc,
                rd.tipo_doc,
                a.serie_fisico||'-'||a.no_fisico factura,
                nvl(a.numero_prefactura,'S/N') prefactura,
                nvl(a.hist_clinica_pac,0) historia_clinica,
                fun_busca_nombre_pte(nvl(a.hist_clinica_pac,0)) paciente,
                nvl(a.numero_admision,0) admision,
                substr(a.detalle,1,80) detalle,
                tr.no_docu no_transaccion,
                sum(nvl(a.monto,0)) monto,
                sum(nvl(rd.monto,0)) cancela,
                sum(nvl(a.saldo,0)) saldo
        from arcpmd a, arcprd rd, arcpmd tr
        where a.no_cia = '01'
        and a.no_prove = '$codMedico'
        and a.tipo_doc NOT in ( 'PH')
        and a.ind_act != 'P'
        and a.anulado = 'N'
        and rd.no_cia = a.no_cia
        and rd.tipo_refe = a.tipo_doc
        and rd.no_refe = a.no_docu
       and rd.tipo_doc NOT  in ( 'RT' , 'AN')
        and tr.no_cia = rd.no_cia
        and tr.tipo_doc = rd.tipo_doc
        and tr.no_docu = rd.no_docu
        and tr.no_prove = rd.no_prove
        and trunc(tr.fecha) >= '$fechaDesde'
        and trunc(tr.fecha) <= '$fechaHasta'
        group by tr.fecha, a.tipo_doc, rd.tipo_doc, a.serie_fisico||'-'||a.no_fisico,
               a.numero_prefactura, a.hist_clinica_pac,
               a.numero_admision,
               a.detalle,
               tr.no_docu
        order by 1 desc ";

            }

            # Búsqueda default
            if ($typeFilter == 4) {

                $time = new DateTime();
                $nuevoTime = strtotime('-1 month', $time->getTimestamp());
                $nuevaFecha = date('Y-m-d', $nuevoTime);
                $fecha = date('Y-m-d');

                $sql = " SELECT tr.fecha,
                        a.tipo_doc,
                        rd.tipo_doc,
                        a.serie_fisico||'-'||a.no_fisico factura,
                        nvl(a.numero_prefactura,'S/N') prefactura,
                        nvl(a.hist_clinica_pac,0) historia_clinica,
                        nvl(a.numero_admision,0) admision,
                        substr(a.detalle,1,80) detalle,
                        fun_busca_nombre_pte(nvl(a.hist_clinica_pac,0)) paciente,
                        tr.no_docu no_transaccion,
                        sum(nvl(a.monto,0)) monto,
                        sum(nvl(rd.monto,0)) cancela,
                        sum(nvl(a.saldo,0)) saldo
                from arcpmd a, arcprd rd, arcpmd tr
                where a.no_cia = '01'
                and a.no_prove = '$codMedico'
                and a.tipo_doc NOT in ( 'PH')
                and a.ind_act != 'P'
                and a.anulado = 'N'
                and rd.no_cia = a.no_cia
                and rd.tipo_refe = a.tipo_doc
                and rd.no_refe = a.no_docu
               and rd.tipo_doc NOT  in ( 'RT' , 'AN')
                and tr.no_cia = rd.no_cia
                and tr.tipo_doc = rd.tipo_doc
                and tr.no_docu = rd.no_docu
                and tr.no_prove = rd.no_prove
                and trunc(tr.fecha) >= '$nuevaFecha'
                and trunc(tr.fecha) <= '$fecha'
                group by tr.fecha, a.tipo_doc, rd.tipo_doc, a.serie_fisico||'-'||a.no_fisico,
                       a.numero_prefactura, a.hist_clinica_pac,
                       a.numero_admision,
                       a.detalle,
                       tr.no_docu
                order by 1 desc ";

            }

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            return array(
                'status' => true,
                'data' => $data,
                'codMedico' => $codMedico,
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
                'codMedico' => $codMedico
            );

        }

    }

    public function getMisTrxRealizadas()
    {

        try {

            global $config, $http;

            $this->getAuthorization();

            $codMedico = $this->user->codMedico;

            #$codMedico = '000755';

            # seteo de valores para paginacion
            $fecha = date('d-m-Y');

            $this->start = (int) $http->query->get('start');

            $this->length = (int) $http->query->get('length');

            if ($this->start >= 10) {
                $this->length = $this->start + $this->length;
            }

            $typeFilter = $http->query->get('typeFilter');

            # Busqueda por fechas
            if ($typeFilter == 3) {

                $time = new DateTime();
                $nuevoTime = strtotime('-1 month', $time->getTimestamp());
                $nuevaFecha = date('Y-m-d', $nuevoTime);
                $fecha = date('Y-m-d');

                $fechaDesde = $http->query->get('fechaDesde');
                $fechaHasta = $http->query->get('fechaHasta');

                $sql = "    SELECT c.fecha,	--0
                a.tipo_doc, --1
                b.descripcion, --2
                --c.no_fisico, --3
                nvl(ce.cta_bco_transfe,'S/N') cta_bancaria, --4
                a.no_docu no_transaccion, --5
                sum(nvl(a.monto,0)) monto,	--6
                fun_subtotal_pago(a.no_cia,a.no_docu) subtotal, --7 FUNCION DE SUBTOTAL
                fun_retencion_pago(a.no_cia,a.no_docu) retencion, --8 FUNCION DE RETENCION
                a.no_prove no_prove --9
           from arcprd a, arcptd b, arcpmd c, arckce ce
           where a.no_cia = '01'
           and nvl(c.anulado,'N') = 'N'
           and a.no_prove = '$codMedico'
           and c.tipo_doc in ('CK','TR')
           and a.no_cia = b.no_cia
           and a.tipo_doc = b.tipo_doc
           and a.no_cia = c.no_cia
           and a.no_docu = c.no_docu
           and trunc(c.fecha)>='$fechaDesde'
           and trunc(c.fecha)<='$fechaHasta'
           and ce.no_cia = c.no_cia
           and ce.no_secuencia = to_number(c.no_secuencia)
           group by a.no_cia, c.fecha, a.tipo_doc, b.descripcion, c.no_fisico, ce.cta_bco_transfe, a.no_docu, a.no_prove
           order by c.fecha desc ";

            }

            # Búsqueda default
            if ($typeFilter == 4) {

                $time = new DateTime();
                $nuevoTime = strtotime('-1 month', $time->getTimestamp());
                $nuevaFecha = date('Y-m-d', $nuevoTime);
                $fecha = date('Y-m-d');

                $sql = "SELECT c.fecha,	--0
                a.tipo_doc, --1
                b.descripcion, --2
                --c.no_fisico, --3
                nvl(ce.cta_bco_transfe,'S/N') cta_bancaria, --4
                a.no_docu no_transaccion, --5
                sum(nvl(a.monto,0)) monto,	--6
                fun_subtotal_pago(a.no_cia,a.no_docu) subtotal, --7 FUNCION DE SUBTOTAL
                fun_retencion_pago(a.no_cia,a.no_docu) retencion, --8 FUNCION DE RETENCION
                a.no_prove no_prove --9
           from arcprd a, arcptd b, arcpmd c, arckce ce
           where a.no_cia = '01'
           and nvl(c.anulado,'N') = 'N'
           and a.no_prove = '$codMedico'
           and c.tipo_doc in ('CK','TR')
           and a.no_cia = b.no_cia
           and a.tipo_doc = b.tipo_doc
           and a.no_cia = c.no_cia
           and a.no_docu = c.no_docu
           and trunc(c.fecha)>='$nuevaFecha'
           and trunc(c.fecha)<='$fecha'
           and ce.no_cia = c.no_cia
           and ce.no_secuencia = to_number(c.no_secuencia)
           group by a.no_cia, c.fecha, a.tipo_doc, b.descripcion, c.no_fisico, ce.cta_bco_transfe, a.no_docu, a.no_prove
           order by c.fecha desc ";

            }

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            $_data = array();

            foreach ($data as $k) {
                $k['FECHA_PDF'] = strtoupper(date('d-M-y', strtotime($k['FECHA'])));

                $_data[] = $k;
            }

            return array(
                'status' => true,
                'data' => $_data,
                'codMedico' => $codMedico,
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
                'codMedico' => $codMedico
            );

        }

    }

    public function getMisFacturasPendientes()
    {

        try {

            global $config, $http;

            $this->getAuthorization();

            $codMedico = $this->user->codMedico;

            # seteo de valores para paginacion
            $fecha = date('d-m-Y');

            $this->start = (int) $http->query->get('start');

            $this->length = (int) $http->query->get('length');

            if ($this->start >= 10) {
                $this->length = $this->start + $this->length;
            }

            $typeFilter = $http->query->get('typeFilter');

            # Es Seguros typeFiltro: 1

            if ($typeFilter == 1) {

                $time = new DateTime();
                $nuevoTime = strtotime('-12 month', $time->getTimestamp());
                $nuevaFecha = date('Y-m-d', $nuevoTime);
                $fecha = date('Y-m-d');

                # Búsqueda default
                $sql = " SELECT trunc(md.fecha) fecha, --0
                fun_nombre_cliente('01',nvl(md.numero_prefactura,'1')) cliente, --1
                md.serie_fisico||'-'||md.no_fisico factura, --2
                nvl(md.numero_prefactura,'S/N') prefactura, --3
                nvl(md.hist_clinica_pac,0) historia_clinica, --4
                nvl(fun_busca_nombre_pte(md.hist_clinica_pac),'S/N') paciente, --5
                sum(nvl(md.monto,0)) monto, --6
                sum(nvl(md.saldo,0)) saldo --7
                from arcpmd md
                    where md.no_cia = '01'
                    and md.no_prove = '$codMedico'
                    and md.tipo_doc in ( 'FM', 'FP', 'FH', 'FO', 'HC', 'HS' )
                    and trunc(md.fecha) >= '$nuevaFecha'
                    and trunc(md.fecha) <= '$fecha'
                    and md.saldo > 0
                    and md.anulado != 'S'
                    and fun_nombre_pagador('01',nvl(md.numero_prefactura,'1')) = 'SEGUROS'
                    group by trunc(md.fecha),
                            md.serie_fisico||'-'||md.no_fisico,
                            md.numero_prefactura,
                            md.hist_clinica_pac,
                            fun_busca_nombre_pte(md.hist_clinica_pac),
                            decode(md.tipo_doc,'FM','FACTURA TERCEROS','FACTURA CONCLINA')
                    order by 1 desc ";

            }

            # Hospital Metropolitano
            if ($typeFilter == 2) {

                $time = new DateTime();
                $nuevoTime = strtotime('-12 month', $time->getTimestamp());
                $nuevaFecha = date('Y-m-d', $nuevoTime);
                $fecha = date('Y-m-d');

                # Búsqueda default
                $sql = " SELECT trunc(md.fecha) fecha, --0
                fun_nombre_cliente('01',nvl(md.numero_prefactura,'1')) cliente, --1
                md.serie_fisico||'-'||md.no_fisico factura, --2
                nvl(md.numero_prefactura,'S/N') prefactura, --3
                nvl(md.hist_clinica_pac,0) historia_clinica, --4
                nvl(fun_busca_nombre_pte(md.hist_clinica_pac),'S/N') paciente, --5
                sum(nvl(md.monto,0)) monto, --6
                sum(nvl(md.saldo,0)) saldo --7
           from arcpmd md
           where md.no_cia = '01'
           and md.no_prove = '$codMedico'
           and md.tipo_doc in ( 'FM', 'FP', 'FH', 'FO', 'HC', 'HS' )
           and trunc(md.fecha) >= '$nuevaFecha'
           and trunc(md.fecha) <= '$fecha'
           and md.saldo > 0
           and md.anulado != 'S'
           and fun_nombre_pagador('01',nvl(md.numero_prefactura,'1')) not in('SEGUROS','PÚBLICAS')
           group by trunc(md.fecha),
                  md.serie_fisico||'-'||md.no_fisico,
                  md.numero_prefactura,
                  md.hist_clinica_pac,
                  fun_busca_nombre_pte(md.hist_clinica_pac),
                  decode(md.tipo_doc,'FM','FACTURA TERCEROS','FACTURA CONCLINA')
           order by 1 desc ";

            }

            # Instituciones Publicas
            if ($typeFilter == 3) {

                $time = new DateTime();
                $nuevoTime = strtotime('-12 month', $time->getTimestamp());
                $nuevaFecha = date('Y-m-d', $nuevoTime);
                $fecha = date('Y-m-d');

                # Búsqueda default
                $sql = " SELECT trunc(md.fecha) fecha, --0
                fun_nombre_cliente('01',nvl(md.numero_prefactura,'1')) cliente, --1
                md.serie_fisico||'-'||md.no_fisico factura, --2
                nvl(md.numero_prefactura,'S/N') prefactura, --3
                nvl(md.hist_clinica_pac,0) historia_clinica, --4
                nvl(fun_busca_nombre_pte(md.hist_clinica_pac),'S/N') paciente, --5
                sum(nvl(md.monto,0)) monto, --6
                sum(nvl(md.saldo,0)) saldo --7
           from arcpmd md
           where md.no_cia = '01'
           and md.no_prove = '$codMedico'
           and md.tipo_doc in ( 'FM', 'FP', 'FH', 'FO', 'HC', 'HS' )
           and trunc(md.fecha) >= '$nuevaFecha'
            and trunc(md.fecha) <= '$fecha'
           and md.saldo > 0
           and md.anulado != 'S'
           and fun_nombre_pagador('01',nvl(md.numero_prefactura,'1')) in ('PÚBLICAS')
           group by trunc(md.fecha),
                  md.serie_fisico||'-'||md.no_fisico,
                  md.numero_prefactura,
                  md.hist_clinica_pac,
                  fun_busca_nombre_pte(md.hist_clinica_pac),
                  decode(md.tipo_doc,'FM','FACTURA TERCEROS','FACTURA CONCLINA')
           order by 1 desc ";

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

            return array(
                'status' => true,
                'data' => $data,
                'codMedico' => $codMedico,
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
                'codMedico' => $codMedico
            );

        }

    }

    public function getTodosMisHonorarios()
    {

        try {

            global $config, $http;

            $this->getAuthorization();

            $codMedico = $this->user->codMedico;

            #$codMedico = '000755';

            # seteo de valores para paginacion
            $fecha = date('d-m-Y');

            $this->start = (int) $http->query->get('start');

            $this->length = (int) $http->query->get('length');

            if ($this->start >= 10) {
                $this->length = $this->start + $this->length;
            }

            $typeFilter = $http->query->get('typeFilter');

            # Busqueda por fechas
            if ($typeFilter == 3) {

                $time = new DateTime();
                $nuevoTime = strtotime('-3 month', $time->getTimestamp());
                $nuevaFecha = date('Y-m-d', $nuevoTime);
                $fecha = date('Y-m-d');

                $fechaDesde = $http->query->get('fechaDesde');
                $fechaHasta = $http->query->get('fechaHasta');

                $sql = "  SELECT *

                FROM VW_CCP_HONORARIOS t

                WHERE t.cod_medico LIKE  '%$codMedico'

                AND trunc(t.fecha) >= '$nuevaFecha'

                AND trunc(t.fecha) <= '$fecha'

                ORDER BY t.fecha DESC ";

            }

            # Búsqueda default
            if ($typeFilter == 4) {

                $time = new DateTime();
                $nuevoTime = strtotime('-3 month', $time->getTimestamp());
                $nuevaFecha = date('Y-m-d', $nuevoTime);
                $fecha = date('Y-m-d');

                $sql = " SELECT *

                FROM VW_CCP_HONORARIOS t

                WHERE t.cod_medico LIKE '%10809'

                AND trunc(t.fecha) >= '$nuevaFecha'

                AND trunc(t.fecha) <= '$fecha'

                ORDER BY t.fecha DESC ";

            }

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            $_data = array();

            foreach ($data as $k) {
                // $k['FECHA_PDF'] = strtoupper(date('d-M-y', strtotime($k['FECHA'])));

                $_data[] = $k;
            }

            return array(
                'status' => true,
                'data' => $_data,
                'codMedico' => $codMedico,
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
                'codMedico' => $codMedico
            );

        }

    }

    public function getDetalleHonorarioAuditado()
    {

        try {

            global $config, $http;

            $this->getAuthorization();

            $codMedico = $this->user->codMedico;

            #$codMedico = '000755';

            # seteo de valores para paginacion
            $fecha = date('d-m-Y');

            $this->start = (int) $http->query->get('start');

            $this->length = (int) $http->query->get('length');

            if ($this->start >= 10) {
                $this->length = $this->start + $this->length;
            }

            $typeFilter = $http->query->get('typeFilter');

            $adm = $http->request->get('adm');

            $hcl = $http->request->get('hcl');

            $cargo = $http->request->get('cargo');

            $time = new DateTime();
            $nuevoTime = strtotime('-3 month', $time->getTimestamp());
            $nuevaFecha = date('Y-m-d', $nuevoTime);
            $fecha = date('Y-m-d');

            $sql = " SELECT *

                FROM VW_CCP_TRNS_HONORARIOS t

                WHERE t.hcl = '$hcl' AND t.adm  = '$adm' AND t.cargo = '$cargo' ";

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            $_data = array();

            foreach ($data as $k) {
                // $k['FECHA_PDF'] = strtoupper(date('d-M-y', strtotime($k['FECHA'])));
                $_data[] = $k;
            }

            return array(
                'status' => true,
                'data' => $_data,
                'codMedico' => $codMedico,
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
                'codMedico' => $codMedico
            );

        }

    }

    public function verLogs()
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

                if (false === $data) {
                    throw new ModelsException('No existe Información.');

                }

                $data = array($data);

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

                $data = array($data);

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
            return array(
                'status' => false,
                'data' => [],
                'tipoBusqueda' => $this->tipoBusqueda,
                'message' => $e->getMessage()
            );
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

                if (false === $data) {
                    throw new ModelsException('No existe Información.');

                }

                $data = array($data);

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

                $data = array($data);

            } else {

                $this->pte = str_replace(" ", "%", $this->pte);

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
                'tipoBusqueda' => $this->tipoBusqueda . " - " . $this->pte,
                'data' => $data,
            );

        } catch (ModelsException $e) {
            return array(
                'status' => false,
                'data' => [],
                'tipoBusqueda' => $this->tipoBusqueda . " - " . $this->pte,
                'message' => $e->getMessage()
            );
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

        $sql = " alter session set NLS_DATE_FORMAT = 'YYYY-MM-DD' ";
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
