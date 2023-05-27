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
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Models\Traits\DBModel;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Farmacia
 */
class Farmacia extends Models implements IModels
{

    use DBModel;

    private $_conexion = null;

    public function nuevoRegistro()
    {

        try {

            global $config, $http;

            # Insertar datos de nueva Atencion
            $this->db->insert('VAL_FOR_008', array(
                'dataFormulario' => json_encode($http->request->all(), JSON_UNESCAPED_UNICODE),
            ));

            return array('status' => true, 'message' => '', 'data' => $http->request->all());

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage(), 'data' => $http->request->all());
        }
    }

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
        //..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracleMVEditor'], $_config);

    }

    private function conectar_Oracle_MV()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
        //..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle_mv'], $_config);

    }

    private function setSpanishOracle()
    {

        # 71001 71101
        $sql = "alter session set NLS_LANGUAGE = 'LATIN AMERICAN SPANISH'";
        # Execute
        $stmt = $this->_conexion->query($sql);

        $sql = "alter session set NLS_TERRITORY = 'ECUADOR'";
        # Execute
        $stmt = $this->_conexion->query($sql);

        $sql = " alter session set NLS_DATE_FORMAT = 'DD-MM-YYYY' ";
        # Execute
        $stmt = $this->_conexion->query($sql);

    }

    public function getRecetasAlta(): array
    {

        try {

            global $config, $http;

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
                    SELECT  a.cd_documento_clinico, at.cd_atendimento, at.dt_atendimento, at.cd_paciente, p.nm_paciente, tp.ds_tipo_internacao as ubicacion
                    from pw_editor_clinico a, -- enlace documento clinico-documento editor
                    pw_documento_clinico b, -- documento clinico
                    editor.editor_registro c, -- documento editor
                    paciente p, -- paciente
                    atendime at, -- atencion
                    prestador pr, -- medico
                    tipo_internacao tp
                    where  a.cd_documento = 813 and a.cd_documento_clinico=b.cd_documento_clinico and
                    b.tp_status='FECHADO' and
                    a.cd_editor_registro=c.cd_registro and
                    b.cd_paciente=p.cd_paciente and
                    b.cd_atendimento=at.cd_atendimento and
                    b.cd_prestador=pr.cd_prestador and
                    at.cd_tipo_internacao = tp.cd_tipo_internacao and
                    (p.cd_paciente LIKE'%$this->searchField%' OR p.nm_paciente LIKE '%$this->searchField%' )
                    order by c.cd_registro desc
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
                    SELECT  a.cd_documento_clinico, at.cd_atendimento, at.dt_atendimento, at.cd_paciente, p.nm_paciente, tp.ds_tipo_internacao as ubicacion
                    from pw_editor_clinico a, -- enlace documento clinico-documento editor
                    pw_documento_clinico b, -- documento clinico
                    editor.editor_registro c, -- documento editor
                    paciente p, -- paciente
                    atendime at, -- atencion
                    prestador pr, -- medico
                    tipo_internacao tp
                    where  a.cd_documento = 813 and a.cd_documento_clinico=b.cd_documento_clinico and
                    b.tp_status='FECHADO' and
                    a.cd_editor_registro=c.cd_registro and
                    b.cd_paciente=p.cd_paciente and
                    b.cd_atendimento=at.cd_atendimento and
                    b.cd_prestador=pr.cd_prestador and
                    at.cd_tipo_internacao = tp.cd_tipo_internacao
                    order by c.cd_registro desc
                   ) b
                   WHERE ROWNUM <= " . $this->length . "
                   )
                   WHERE NUM >  " . $this->start . "
                   ";

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
            $recetas = array();

            $link1 = 'http://172.16.253.18/mvautenticador-cas/login?service=http%3A%2F%2F172.16.253.18%3A80%2Fmvpep%2Fapi%2Fclinical-documents%2F%id_documento_mv%.pdf?company=1%26department=75';

            foreach ($data as $key) {

                $key['id'] = $key['CD_DOCUMENTO_CLINICO'];

                // Receta
                $key['URL'] = str_replace("%id_documento_mv%", $key['CD_DOCUMENTO_CLINICO'], $link1);

                $recetas[] = $key;

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $recetas,
                'total' => count($recetas),
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

        }

    }

    private function disReceta($cd_atencion = '')
    {
        try {

            global $config, $http;

            $sql = "SELECT c.ds_tip_presc medicacion,
             editor_custom.fun_calcula_medicina(b.qt_itpre_med*b.nr_dias*(24/decode(fre.nr_intervalo,0,24,fre.nr_intervalo)),b.cd_produto,b.cd_uni_pro)||
             ' '||editor_custom.fun_unidad_padre(b.cd_uni_pro) AS CANT,  -- CANTIDAD DE MEDICACION
             xx.sn_despacho AS DESP,
             xx.observacion AS OBS-- CANTIDAD A DESPACHAR
           from  dbamv.pre_med a,
             dbamv.itpre_med b,
             dbamv.tip_presc c,
             dbamv.tip_esq d,
             dbamv.pw_objeto_grupo_prescricao f,
             dbamv.pw_grupo_prescricao_tipo_esqm g,
             dbamv.pw_documento_clinico h,
             dbamv.for_apl apl,
             dbamv.tip_fre fre,
             dbamv.pw_observacao_predefinida obs,
             dbamv.uni_pro u,
             editor_custom.t_prescripcion_farmacia xx,
             dbamv.paciente pp,
             dbamv.atendime aa
           where   aa.cd_atendimento=a.cd_atendimento and
             aa.cd_paciente=pp.cd_paciente and
             a.cd_objeto=editor_custom.fun_parametro('MADETSO') and
             a.cd_pre_med=b.cd_pre_med and
             b.cd_tip_presc=c.cd_tip_presc and
             c.cd_tip_esq=d.cd_tip_esq and
             d.cd_tip_esq=g.cd_tip_esq and
             b.sn_cancelado='N' and
             f.cd_grupo_prescricao=g.cd_grupo_prescricao and
             a.cd_objeto=f.cd_objeto and
             a.cd_documento_clinico=h.cd_documento_clinico and
             h.tp_status='FECHADO' and
             f.ds_exibicao like 'Medica%' and
             b.cd_for_apl=apl.cd_for_apl and
             b.cd_tip_fre=fre.cd_tip_fre and
             c.cd_observacao_predefinida=obs.cd_observacao_predefinida(+) and
             b.cd_uni_pro=u.cd_uni_pro and
             b.cd_itpre_med=xx.cd_itpre_med(+) and
             a.cd_atendimento=xx.cd_atendimento(+) and
             a.cd_atendimento=$cd_atencion
           order by xx.cd_atendimento";

            # Conectar base de datos 4956
            $this->conectar_Oracle_MV();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            return $data;

        } catch (ModelsException $e) {

            return array();

        }
    }

    public function getStatusRecetaDetalle($numeroReceta = ""): array
    {

        try {

            global $config, $http;

            $sql = "  SELECT  a.cd_documento_clinico, at.cd_atendimento, at.dt_atendimento, at.cd_paciente, p.nm_paciente, tp.ds_tipo_internacao as ubicacion
            from pw_editor_clinico a, -- enlace documento clinico-documento editor
            pw_documento_clinico b, -- documento clinico
            editor.editor_registro c, -- documento editor
            paciente p, -- paciente
            atendime at, -- atencion
            prestador pr, -- medico
            tipo_internacao tp
            where  a.cd_documento = 813 and a.cd_documento_clinico=b.cd_documento_clinico and
            b.tp_status='FECHADO' and
            a.cd_editor_registro=c.cd_registro and
            b.cd_paciente=p.cd_paciente and
            b.cd_atendimento=at.cd_atendimento and
            b.cd_prestador=pr.cd_prestador and
            at.cd_tipo_internacao = tp.cd_tipo_internacao and
            a.cd_documento_clinico = '$numeroReceta'
            order by c.cd_registro desc ";

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
            $recetas = array();

            $link1 = 'http://172.16.253.18/mvautenticador-cas/login?service=http%3A%2F%2F172.16.253.18%3A80%2Fmvpep%2Fapi%2Fclinical-documents%2F%id_documento_mv%.pdf?company=1%26department=75';

            foreach ($data as $key) {

                $key['id'] = $key['CD_DOCUMENTO_CLINICO'];
                $key['URL'] = str_replace("%id_documento_mv%", $key['CD_DOCUMENTO_CLINICO'], $link1);
                $key['DATA'] = $this->disReceta($key['CD_ATENDIMENTO']);

                $recetas[] = $key;

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $recetas,
                'total' => count($recetas),
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

        }

    }

    private function notResults(array $data)
    {
        if (count($data) == 0) {
            throw new ModelsException('No existe más resultados.', 4080);
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

/**
 * __construct()
 */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
        $this->startDBConexion();

    }
}
