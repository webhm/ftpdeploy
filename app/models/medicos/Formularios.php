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
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Models\Traits\DBModel;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Formularios
 */
class Formularios extends Models implements IModels
{

    use DBModel;

    private $_conexion = null;

    private $urlForm = "http://api.hospitalmetropolitano.org/v2/medicos/formulario/p/?id=";

    private $userMV = "dbamv";

    private $passMV = "metro@2020";

    private $TGT_URL = null;

    private $service = null;

    private $ST_TOKEN = null;

    public function nuevoRegistro()
    {

        try {

            global $config, $http;

            # Insertar datos de nueva Atencion
            $this->db->insert(
                'VAL_FOR_008',
                array(
                    'dataFormulario' => json_encode($http->request->all(), JSON_UNESCAPED_UNICODE),
                )
            );

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

    public function conectar_Interfaz_MV()
    {

        $headers = array();

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'http://172.16.253.18/mvautenticador-cas/v1/tickets');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS, "username=" . $this->userMV . "&password=" . $this->passMV . "&company=1&type=AUTHENTICATION_SGU"
        );
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type:  application/x-www-form-urlencoded',
            )
        );

        $http_data = curl_exec($ch);
        $curl_info = curl_getinfo($ch);
        $headers = substr($http_data, 0, $curl_info["header_size"]);
        preg_match("!\r\n(?:Location|URI): *(.*?) *\r\n!", $headers, $matches);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 201) {

            $this->TGT_URL = $matches[1];

        } else {

            throw new ModelsException('Proceso no se completo con éxito.');

        }

    }

    public function getServiceTicket()
    {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->TGT_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS, "service=" . $this->service
        );
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type:  application/x-www-form-urlencoded',
            )
        );

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($http_code == 200) {

            $this->ST_TOKEN = $result;

        } else {

            throw new ModelsException('Proceso no se completo con éxito.');

        }

    }

    public function getFileST_TOKEN()
    {

        $destination = "../../v2/medicos/docs/formularios/" . time() . ".pdf";
        $fp = fopen($destination, 'w+');
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->service . "&ticket=" . $this->ST_TOKEN);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec($ch);
        curl_close($ch);

        fclose($fp);

    }

    public function getFormularioMV($id)
    {

        try {

            global $config, $http;

            # Verificar que no están vacíos
            if (Helper\Functions::e($id)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion.');
            }

            $hashEstudio = Helper\Strings::ocrend_encode($id, 'hm');

            $destination = "../../v2/medicos/docs/formularios/" . $hashEstudio . ".pdf";
            $doc = file_exists($destination);

            if ($doc) {

                $urlPdf = 'https://api.hospitalmetropolitano.org/v2/medicos/f/' . $hashEstudio . '.pdf';

                return array(
                    'status' => true,
                    'data' => $urlPdf,
                );
            }

            $this->conectar_Interfaz_MV();

            $this->service = 'http://172.16.253.18:80/mvpep/api/clinical-documents/975181.pdf?company=1&department=75';

            $this->getServiceTicket();

            $this->getFileST_TOKEN();

            return array('status' => true, 'data' => $this->service . '&ticket=' . $this->ST_TOKEN);

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage()
            );

        }

    }

    public function getFormularios_MV_005($nhcl)
    {

        global $config, $http;

        $sql = " SELECT  at.cd_atendimento as ADM
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
                    at.cd_paciente = '$nhcl'
                    order by at.cd_atendimento DESC  ";

        # Conectar base de datos
        $this->conectar_Oracle();

        # Conectar base de datos
        $this->setSpanishOracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetch();

        return $data['ADM'];

    }

    public function getFormulario()
    {

        try {

            global $config, $http;

            $nhcl = $http->query->get('nhcl');

            $start = (int) $http->query->get('start');

            $length = (int) $http->query->get('length');

            if ($start >= 10) {
                $length = $start + $length;
            }

            $adm = $this->getFormularios_MV_005($nhcl);

            # seteo de valores para paginacion
            $fecha = date('d-m-Y');

            $sql = " SELECT *
            FROM (
            SELECT z.*, ROWNUM AS NUM
            FROM (
              SELECT b.cd_documento_clinico, TO_CHAR(b.dh_fechamento,'DD-MM-YYYY HH24:MI') as FECHA_FOR, pr.nm_prestador as MED_FOR, c.conteudo_corpo
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
                b.cd_prestador=pr.cd_prestador
                and at.cd_paciente = '$nhcl' and at.cd_atendimento = '$adm'
                order by c.cd_registro desc
            ) z
            WHERE ROWNUM <= 12
            )
            WHERE NUM > 0";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Conectar base de datos
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            $res = array();

            foreach ($data as $k => $v) {

                $cuerpo = json_decode($v['CONTEUDO_CORPO'], true);

                $_t = time();

                $hashId = Helper\Strings::ocrend_encode($v['CD_DOCUMENTO_CLINICO'], 'hm');

                $cuerpo['pageBody']['children'][0] = array('name' => 'cd_documento_clinico', 'answer' => $v['CD_DOCUMENTO_CLINICO']);

                $res[] = array(
                    'urlPDF' => $this->urlForm . $hashId,
                    'cd_documento_clinico' => $v['CD_DOCUMENTO_CLINICO'],
                    'fecha_FOR' => $v['FECHA_FOR'],
                    'med_FOR' => $v['MED_FOR'],
                    'contentKey' => $cuerpo['contentKey'],
                    'data' => $cuerpo['pageBody']['children'],
                );

            }

            return $res;

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getFormulariosEPI(): array
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
                    SELECT ate.dt_atendimento, ate.cd_atendimento, p.cd_paciente, p.nm_paciente, a.cd_documento, a.cd_documento_clinico
                    from pw_editor_clinico a, -- enlace documento clinico-documento editor
                    pw_documento_clinico b, -- documento clinico
                    editor.editor_registro c, -- documento editor
                    paciente p, -- paciente
                    atendime ate, -- atencion
                    prestador pr -- medico
                    where a.cd_documento in (313,349) and
                    a.cd_documento_clinico=b.cd_documento_clinico and
                    b.tp_status='FECHADO' and
                    a.cd_editor_registro=c.cd_registro and
                    b.cd_paciente=p.cd_paciente and
                    b.cd_atendimento=ate.cd_atendimento and
                    b.cd_prestador=pr.cd_prestador and
                    (p.cd_paciente LIKE'%$this->searchField%' OR p.nm_paciente LIKE '%$this->searchField%' )
                    order by ate.cd_atendimento desc
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
                    SELECT ate.dt_atendimento, ate.cd_atendimento, p.cd_paciente, p.nm_paciente, a.cd_documento, a.cd_documento_clinico
                    from pw_editor_clinico a, -- enlace documento clinico-documento editor
                    pw_documento_clinico b, -- documento clinico
                    editor.editor_registro c, -- documento editor
                    paciente p, -- paciente
                    atendime ate, -- atencion
                    prestador pr -- medico
                    where a.cd_documento in (313,349) and
                    a.cd_documento_clinico=b.cd_documento_clinico and
                    b.tp_status='FECHADO' and
                    a.cd_editor_registro=c.cd_registro and
                    b.cd_paciente=p.cd_paciente and
                    b.cd_atendimento=ate.cd_atendimento and
                    b.cd_prestador=pr.cd_prestador
                    order by ate.cd_atendimento desc
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
            $formularios = array();

            $link1 = 'http://172.16.253.18/mvautenticador-cas/login?service=http%3A%2F%2F172.16.253.18%3A80%2Fmvpep%2Fapi%2Fclinical-documents%2F%id_documento_mv%.pdf?company=1%26department=75';

            $link2 = 'http://172.16.253.18/mvautenticador-cas/login?service=http%3A%2F%2F172.16.253.18%3A80%2Fmvpep%2Fapi%2Fclinical-documents%2F%id_documento_mv%.pdf?company=1%26department=75';

            foreach ($data as $key) {

                $key['id'] = $key['CD_DOCUMENTO_CLINICO'];

                // EPIDEMIOLÓGICA
                if ($key['CD_DOCUMENTO'] == '349') {
                    $key['URL'] = str_replace("%id_documento_mv%", $key['CD_DOCUMENTO_CLINICO'], $link1);
                }

                // EPI 1
                if ($key['CD_DOCUMENTO'] == '313') {
                    $key['URL'] = str_replace("%id_documento_mv%", $key['CD_DOCUMENTO_CLINICO'], $link2);
                }

                $formularios[] = $key;

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $formularios,
                'total' => count($formularios),
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
