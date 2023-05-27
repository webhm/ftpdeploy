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
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Models\Traits\DBModel;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Pedidos
 */
class Pedidos extends Models implements IModels
{

    use DBModel;

    # Variables de clase
    private $pstrSessionKey = 0;
    private $USER = null;
    private $sortField = 'ROWNUM_';
    private $sortType = 'desc'; # desc
    private $offset = 1;
    private $limit = 25;
    private $searchField = null;
    private $startDate = null;
    private $endDate = null;
    private $tresMeses = null;
    private $urlApiImagen = '//api.hospitalmetropolitano.org/v1/';
    private $urlApiViewer = '//api.imagen.hospitalmetropolitano.org/';

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

    private function conectar_Oracle_TRN()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
        //..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle_mv_trn'], $_config);

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

    public function build_table($array)
    {
        // start table
        $html = '<table style="border: 1px solid black;">';
        // header row
        $html .= '<tr>';
        foreach ($array[0] as $key => $value) {
            $html .= '<th style="border: 1px solid black;">' . htmlspecialchars($key) . '</th>';
        }
        $html .= '</tr>';

        // data rows
        foreach ($array as $key => $value) {
            $html .= '<tr>';
            foreach ($value as $key2 => $value2) {

                $html .= '<td style="font-size:12px;border: 1px solid black;">' . htmlspecialchars($value2) . '</td>';
            }
            $html .= '</tr>';
        }

        // finish table and return it

        $html .= '</table>';
        return $html;
    }

    public function build_table_compras($array)
    {
        // start table
        $html = '<table style="border: 1px solid black;">';
        // header row
        $html .= '<tr>';
        foreach ($array[0] as $key => $value) {
            $html .= '<th style="border-bottom: 1px solid black;border-left: 1px solid black;">' . htmlspecialchars($key) . '</th>';
        }
        $html .= '</tr>';

        // data rows
        foreach ($array as $key => $value) {
            $html .= '<tr>';
            foreach ($value as $key2 => $value2) {
                if ($key2 == 'DIAS' && ($value2 >= 0 && $value2 <= 2)) {
                    $html .= '<td style="font-size:12px;border-bottom: 1px solid black;border-left: 1px solid black;color:white;background-color:green;text-align:center;">' . htmlspecialchars($value2) . '</td>';
                } else if ($key2 == 'DIAS' && ($value2 > 2 && $value2 <= 3)) {
                    $html .= '<td style="font-size:12px;border-bottom: 1px solid black;border-left: 1px solid black;background-color:#e9c92d;text-align:center;">' . htmlspecialchars($value2) . '</td>';
                } else if ($key2 == 'DIAS' && ($value2 > 3 && $value2 <= 5)) {
                    $html .= '<td style="font-size:12px;border-bottom: 1px solid black;border-left: 1px solid black;color:white;background-color:red;text-align:center;">' . htmlspecialchars($value2) . '</td>';
                } else if ($key2 == 'DIAS' && $value2 > 5) {
                    $html .= '<td style="font-size:12px;border-bottom: 1px solid black;border-left: 1px solid black;color:white;background-color:red;text-align:center;">' . htmlspecialchars($value2) . '</td>';
                } else {
                    $html .= '<td style="font-size:12px;border-bottom: 1px solid black;border-left: 1px solid black;">' . htmlspecialchars($value2) . '</td>';
                }

            }
            $html .= '</tr>';
        }

        // finish table and return it

        $html .= '</table>';
        return $html;
    }

    public function getMailNotificacion_Receta_Beta(array $data = array(), string $correo = 'mchang@hmetro.med.ec')
    {

        global $config, $http;

        $link = 'http://172.16.253.18/mvautenticador-cas/login?service=http%3A%2F%2F172.16.253.18%3A80%2Fmvpep%2Fapi%2Fclinical-documents%2F' . $data['LINK'] . '.pdf?company=1%26department=75';

        # Construir mensaje y enviar mensaje
        $content = '<br />
                    <br />
                    Fecha de Atención:
                    ' . $data['DT_ATENDIMENTO'] . '
                    <br />
                    N° de Atención:
                    ' . $data['CD_ATENDIMENTO'] . '
                    <br />
                    NHC:
                    ' . $data['CD_PACIENTE'] . '01
                    <br />
                    Paciente:
                    ' . $data['NM_PACIENTE'] . '
                    <br />
                    Ubicación:
                    ' . $data['DS_UNID_INT'] . ':' . $data['DS_LEITO'] . '
                    <br />
                    Receta de Alta:
                    <br />
                    <a href="' . $link . '" target="_blank">- Ver Receta de Alta </a>
                    <br />
                    ' . $data['MEDICACION'] . '
                    <br />
                ';
        # Enviar el correo electrónico
        $_html = Helper\Emails::loadTemplate(array(
            # Título del mensaje
            '{{title}}' => 'Nueva Receta Médica - Metrovirtual Hospital Metropolitano',
            # Contenido del mensaje
            '{{content}}' => $content,
            # Copyright
            '{{copyright}}' => '&copy; ' . date('Y') . ' <a href="https://www.hospitalmetropolitano.org">Metrovirtual Hospital Metropolitano</a> Todos los derechos reservados.',
        ), 8);

        # Verificar si hubo algún problema con el envió del correo
        if ($this->sendMail_Receta_Beta($_html, $correo, 'Nueva Receta Médica - Metrovirtual Hospital Metropolitano', $data) != true) {
            return false;
        } else {
            return true;
        }
    }

    public function getMailNotificacion_Compras(array $data = array(), string $correo = 'mchang@hmetro.med.ec')
    {

        global $config, $http;

        # Construir mensaje y enviar mensaje
        $content = '<br />
                    <br />
                    Buenos días estimado(a):
                    <br />
                    <br />
                    Se encuentran pendientes de autorizar las siguientes órdenes de compra:
                    <br />
                    <br />
                    Ingrese <a href="https://appcomprasprd.azurewebsites.net/" target="_blank">aquí</a> para aprobación de órdenes.
                    <br />
                    <br />'
            . $data['DETALLE'];
        # Enviar el correo electrónico
        $_html = Helper\Emails::loadTemplate(array(
            # Título del mensaje
            '{{title}}' => 'Órdenes de Compras Pendientes - Metrovirtual Hospital Metropolitano',
            # Contenido del mensaje
            '{{content}}' => $content,
            # Copyright
            '{{copyright}}' => '&copy; ' . date('Y') . ' <a href="https://www.hospitalmetropolitano.org">Metrovirtual Hospital Metropolitano</a> Todos los derechos reservados.',
        ), 9);

        # Verificar si hubo algún problema con el envió del correo
        if ($this->sendMail_Compras($_html, $correo, 'Órdenes de Compras Pendientes - Metrovirtual', $data) != true) {
            return false;
        } else {
            return true;
        }
    }

    public function getMailNotificacion_Receta(array $data = array(), string $correo = 'mchang@hmetro.med.ec')
    {

        global $config, $http;

        $link = 'http://172.16.253.18/mvautenticador-cas/login?service=http%3A%2F%2F172.16.253.18%3A80%2Fmvpep%2Fapi%2Fclinical-documents%2F' . $data['LINK'] . '.pdf?company=1%26department=75';

        # Construir mensaje y enviar mensaje
        $content = '<br />
                    <br />
                    Fecha de Atención:
                    ' . $data['DT_ATENDIMENTO'] . '
                    <br />
                    N° de Atención:
                    ' . $data['CD_ATENDIMENTO'] . '
                    <br />
                    NHC:
                    ' . $data['CD_PACIENTE'] . '01
                    <br />
                    Paciente:
                    ' . $data['NM_PACIENTE'] . '
                    <br />
                    Ubicación:
                    ' . $data['DS_UNID_INT'] . ': ' . $data['DS_LEITO'] . '
                    <br />
                    Receta de Alta:
                    <br />
                    <a href="' . $link . '" target="_blank">- Ver Receta de Alta </a>
                    <br />
                    ' . $data['MEDICACION'] . '
                    <br />
                ';
        # Enviar el correo electrónico
        $_html = Helper\Emails::loadTemplate(array(
            # Título del mensaje
            '{{title}}' => 'Nueva Receta Médica - Metrovirtual Hospital Metropolitano',
            # Contenido del mensaje
            '{{content}}' => $content,
            # Copyright
            '{{copyright}}' => '&copy; ' . date('Y') . ' <a href="https://www.hospitalmetropolitano.org">Metrovirtual Hospital Metropolitano</a> Todos los derechos reservados.',
        ), 8);

        # Verificar si hubo algún problema con el envió del correo
        if ($this->sendMail_Receta($_html, $correo, 'Nueva Receta Médica - Metrovirtual Hospital Metropolitano', $data) != true) {
            return false;
        } else {
            return true;
        }
    }

    public function getMailNotificacion(array $data = array(), string $correo = 'mchang@hmetro.med.ec')
    {

        global $config, $http;

        $link1 = 'http://172.16.253.18/mvautenticador-cas/login?service=http%3A%2F%2F172.16.253.18%3A80%2Fmvpep%2Fapi%2Fclinical-documents%2F' . $data['link1'] . '.pdf?company=1%26department=75';

        $link2 = 'http://172.16.253.18/mvautenticador-cas/login?service=http%3A%2F%2F172.16.253.18%3A80%2Fmvpep%2Fapi%2Fclinical-documents%2F' . $data['link2'] . '.pdf?company=1%26department=75';

        # Construir mensaje y enviar mensaje
        $content = '<br />
                    Formularios disponibles:
                    <br />
                    <br />
                    Fecha de Atención:
                    ' . $data['HR_ATENDIMENTO'] . '
                    <br />
                    N° de Atención:
                    ' . $data['CD_ATENDIMENTO'] . '
                    <br />
                    NHC:
                    ' . $data['CD_PACIENTE'] . '
                    <br />
                    Paciente:
                    ' . $data['NM_PACIENTE'] . '
                    <br />
                    <a href="' . $link1 . '" target="_blank">- Ver Ficha Epi 1 </a>
                    <br />
                    <a href="' . $link2 . '" target="_blank">- Ver Ficha Epidemiológica</a>
                    <br />
                    <br />
                ';

        # Enviar el correo electrónico
        $_html = Helper\Emails::loadTemplate(array(
            # Título del mensaje
            '{{title}}' => $data['title'],
            # Contenido del mensaje
            '{{content}}' => $content,
            # Copyright
            '{{copyright}}' => '&copy; ' . date('Y') . ' <a href="https://www.hospitalmetropolitano.org">Metrovirtual Hospital Metropolitano</a> Todos los derechos reservados.',
        ), 7);

        # Verificar si hubo algún problema con el envió del correo
        if ($this->sendMail($_html, $correo, $data['title'] . ' - Metrovirtual Hospital Metropolitano', $data) != true) {
            return false;
        } else {
            return true;
        }
    }

    public function sendMail($html, $to, $subject, $_data)
    {

        global $config;

        $stringData = array(
            "TextBody" => "Formulario Epidimiológico - Metrovirtual",
            'From' => 'Metrovirtual metrovirtual@hospitalmetropolitano.org',
            'To' => $to,
            'Cc' => 'preanaliti.lab@hmetro.med.ec;gortega@hmetro.med.ec;slopez@hmetro.med.ec',
            'Bcc' => 'mchangcnt@gmail.com',
            'Subject' => $subject,
            'HtmlBody' => $html,
            'Tag' => 'MVEPI',
            'TrackLinks' => 'HtmlAndText',
            'TrackOpens' => true,
        );

        $data = json_encode($stringData);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.trx.icommarketing.com/email");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            'X-Postmark-Server-Token: 75032b22-cf9b-4fd7-8eb4-e7446c8b118b',
        ));

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $resultobj = curl_error($ch);
            return false;
        }
        curl_close($ch);
        $resultobj = json_decode($result);

        return true;

    }

    public function sendMail_Receta_Beta($html, $to, $subject, $_data)
    {

        global $config;

        $stringData = array(
            "TextBody" => "Nueva Receta - Metrovirtual",
            'From' => 'Metrovirtual metrovirtual@hospitalmetropolitano.org',
            'To' => 'mchangcnt@gmail.com',
            //'Bcc' => 'mchangcnt@gmail.com;esaenz@hmetro.med.ec;jbenitezr@hmetro.med.ec;mpolo@hmetro.med.ec;mborja@hmetro.med.ec',
            'Subject' => $subject,
            'HtmlBody' => $html,
            'Tag' => 'MVREC',
            'TrackOpens' => false,
        );

        $data = json_encode($stringData);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.trx.icommarketing.com/email");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            'X-Postmark-Server-Token: 75032b22-cf9b-4fd7-8eb4-e7446c8b118b',
        ));

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $resultobj = curl_error($ch);
            return false;
        }
        curl_close($ch);
        $resultobj = json_decode($result);

        return true;

    }

    public function sendMail_Compras($html, $to, $subject, $_data)
    {

        global $config;

        $stringData = array(
            "TextBody" => "Notificaciones de Compras - Metrovirtual",
            'From' => 'Metrovirtual metrovirtual@hospitalmetropolitano.org',
            'To' => $to,
            'Bcc' => 'mchangcnt@gmail.com;ivivanco@hmetro.med.ec;plugmana@hmetro.med.ec;lguevara@hmetro.med.ec',
            'Subject' => $subject,
            'HtmlBody' => $html,
            'Tag' => 'MVNOTCOMPRAS',
            'TrackOpens' => false,
        );

        $data = json_encode($stringData);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.trx.icommarketing.com/email");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            'X-Postmark-Server-Token: 75032b22-cf9b-4fd7-8eb4-e7446c8b118b',
        ));

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $resultobj = curl_error($ch);
            return false;
        }
        curl_close($ch);
        $resultobj = json_decode($result);

        return true;

    }

    public function sendMail_Receta($html, $to, $subject, $_data)
    {

        global $config;

        $stringData = array(
            "TextBody" => "Nueva Receta - Metrovirtual",
            'From' => 'Metrovirtual metrovirtual@hospitalmetropolitano.org',
            'To' => 'farmacia@hmetro.med.ec',
            'Bcc' => 'mchangcnt@gmail.com;esaenz@hmetro.med.ec;jbenitezr@hmetro.med.ec;mpolo@hmetro.med.ec;mborja@hmetro.med.ec',
            'Subject' => $subject,
            'HtmlBody' => $html,
            'Tag' => 'MVREC',
            'TrackOpens' => false,
        );

        $data = json_encode($stringData);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.trx.icommarketing.com/email");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            'X-Postmark-Server-Token: 75032b22-cf9b-4fd7-8eb4-e7446c8b118b',
        ));

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $resultobj = curl_error($ch);
            return false;
        }
        curl_close($ch);
        $resultobj = json_decode($result);

        return true;

    }

    public function getFormularios_Epi(): array
    {

        try {

            global $config, $http;

            $sql = " SELECT b.*, p.*, at.*, to_char(dh_documento, 'DD-MM-YYYY HH24:MI:SS') as timestamp_control
            from pw_editor_clinico a, -- enlace documento clinico-documento editor
            pw_documento_clinico b, -- documento clinico
            editor.editor_registro c, -- documento editor
            paciente p, -- paciente
            atendime at, -- atencion
            prestador pr -- medico
            where a.cd_documento=349 and a.cd_documento_clinico=b.cd_documento_clinico and
            b.tp_status='FECHADO' and
            a.cd_editor_registro=c.cd_registro and
            b.cd_paciente=p.cd_paciente and
            b.cd_atendimento=at.cd_atendimento and
            b.cd_prestador=pr.cd_prestador
            order by c.cd_registro desc ";

            # Conectar base de datos
            $this->conectar_Oracle_MV();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # return $data;

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $pedidos = array();

            $time = new DateTime();
            $nuevaHora = strtotime('-2 minutes', $time->getTimestamp());
            $menosHora = date('Y-m-d H:i:s', $nuevaHora);
            $tiempoControl = strtotime($menosHora);

            foreach ($data as $key) {

                $key['TIMESTAMP'] = strtotime($key['TIMESTAMP_CONTROL']);

                if ($key['TIMESTAMP'] > $tiempoControl) {

                    $key['title'] = $key['NM_PACIENTE'] . ' ' . $key['NM_DOCUMENTO'];
                    $message = $this->getMailNotificacion(
                        $key,
                        'sec_emergencia@hmetro.med.ec'
                    );
                    $key['STATUS_ENVIADO'] = $message;
                    $pedidos[] = $key;

                }

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $pedidos,
                'total' => count($pedidos),
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

    public function getFormularios_Epi_1(): array
    {

        try {

            global $config, $http;

            $fechaHasta = date("d-m-Y", strtotime(date("d-m-Y") . " + 1 days"));
            $fechaDesde = date("d-m-Y", strtotime(date("d-m-Y") . " - days"));

            $sql = " SELECT  b.*, p.*, at.*, to_char(dh_documento, 'DD-MM-YYYY HH24:MI:SS') as timestamp_control
            from pw_editor_clinico a, -- enlace documento clinico-documento editor
            pw_documento_clinico b, -- documento clinico
            editor.editor_registro c, -- documento editor
            paciente p, -- paciente
            atendime at, -- atencion
            prestador pr -- medico
            where a.cd_documento=313 and a.cd_documento_clinico=b.cd_documento_clinico and
            b.tp_status='FECHADO' and
            a.cd_editor_registro=c.cd_registro and
            b.cd_paciente=p.cd_paciente and
            b.cd_atendimento=at.cd_atendimento and
            b.cd_prestador=pr.cd_prestador
            order by c.cd_registro desc";

            # Conectar base de datos
            $this->conectar_Oracle_MV();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # return $data;

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $pedidos = array();

            $time = new DateTime();
            $nuevaHora = strtotime('-10 minutes', $time->getTimestamp());
            $menosHora = date('Y-m-d H:i:s', $nuevaHora);
            $tiempoControl = strtotime($menosHora);

            foreach ($data as $key) {

                $key['TIMESTAMP'] = strtotime($key['TIMESTAMP_CONTROL']);

                if ($key['TIMESTAMP'] > $tiempoControl) {

                    $key['title'] = $key['NM_PACIENTE'] . ' ' . $key['NM_DOCUMENTO'];
                    $message = $this->getMailNotificacion(
                        $key,
                        'sec_emergencia@hmetro.med.ec'
                    );
                    $key['STATUS_ENVIADO'] = $message;
                    $pedidos[] = $key;

                }

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $pedidos,
                'total' => count($pedidos),
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

    private function disEpi($cd_atencion = '')
    {
        try {

            global $config, $http;

            $fechaHasta = date("d-m-Y", strtotime(date("d-m-Y") . " + 1 days"));
            $fechaDesde = date("d-m-Y", strtotime(date("d-m-Y") . " - days"));

            // 313
            $sql = " SELECT a.cd_documento_clinico  DOCUMENTO
            from pw_editor_clinico a, -- enlace documento clinico-documento editor
            pw_documento_clinico b, -- documento clinico
            editor.editor_registro c, -- documento editor
            paciente p, -- paciente
            atendime at, -- atencion
            prestador pr -- medico
            where a.cd_documento = 313 and
            a.cd_documento_clinico=b.cd_documento_clinico and
            b.tp_status='FECHADO' and
            a.cd_editor_registro=c.cd_registro and
            b.cd_paciente=p.cd_paciente and
            b.cd_atendimento=at.cd_atendimento and
            b.cd_prestador=pr.cd_prestador and
            b.cd_atendimento = '$cd_atencion'
            order by c.cd_registro desc";

            # Conectar base de datos
            $this->conectar_Oracle_MV();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            // 349
            $sql = " SELECT   a.cd_documento_clinico DOCUMENTO
             from pw_editor_clinico a, -- enlace documento clinico-documento editor
             pw_documento_clinico b, -- documento clinico
             editor.editor_registro c, -- documento editor
             paciente p, -- paciente
             atendime at, -- atencion
             prestador pr -- medico
             where a.cd_documento = 349 and
             a.cd_documento_clinico=b.cd_documento_clinico and
             b.tp_status='FECHADO' and
             a.cd_editor_registro=c.cd_registro and
             b.cd_paciente=p.cd_paciente and
             b.cd_atendimento=at.cd_atendimento and
             b.cd_prestador=pr.cd_prestador and
             b.cd_atendimento = '$cd_atencion'
             order by c.cd_registro desc";

            # Conectar base de datos
            $this->conectar_Oracle_MV();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataEpi = $stmt->fetch();

            $epi1 = 0;
            $epi = 0;

            if ($data !== false) {

                $epi1 = $data['DOCUMENTO'];

            }

            if ($dataEpi !== false) {

                $epi = $dataEpi['DOCUMENTO'];

            }

            if ($epi1 !== 0 && $epi !== 0) {

                return array(
                    'status' => true,
                    'data' => $epi1 . '-' . $epi,
                );
            }

            return array(
                'status' => false,
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
            );

        }
    }

    public function getFormulariosEPI(): array
    {

        try {

            global $config, $http;

            $fechaHasta = date("d-m-Y", strtotime(date("d-m-Y") . " + 1 days"));
            $fechaDesde = date("d-m-Y", strtotime(date("d-m-Y") . " - days"));
            $fecha = date("d-m-Y");

            $sql = " SELECT DISTINCT ate.cd_atendimento
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
            to_char(dh_documento, 'DD-MM-YYYY') = '$fecha'
            order by ate.cd_atendimento desc";

            # Conectar base de datos
            $this->conectar_Oracle_MV();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $pedidos = array();

            foreach ($data as $key) {

                $stsLogPen = "logs/formularios/pendientes/epi-" . $key['CD_ATENDIMENTO'] . ".log";
                $stsLogPro = "logs/formularios/procesados/epi-" . $key['CD_ATENDIMENTO'] . ".log";

                if (@file_get_contents($stsLogPen, true) === false && @file_get_contents($stsLogPro, true) === false) {

                    $log = "sts-0" . PHP_EOL;
                    file_put_contents('logs/formularios/pendientes/epi-' . $key['CD_ATENDIMENTO'] . '.log', $log, FILE_APPEND);
                    $pedidos[] = $key;

                }

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $pedidos,
                'total' => count($pedidos),
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

    private function detalleAt($cd_atencion = '')
    {

        try {

            global $config, $http;

            $fechaHasta = date("d-m-Y", strtotime(date("d-m-Y") . " + 1 days"));
            $fechaDesde = date("d-m-Y", strtotime(date("d-m-Y") . " - days"));

            $sql = "SELECT at.*, p.NM_PACIENTE
            from pw_editor_clinico a, -- enlace documento clinico-documento editor
            pw_documento_clinico b, -- documento clinico
            editor.editor_registro c, -- documento editor
            paciente p, -- paciente
            atendime at, -- atencion
            prestador pr -- medico
            where  a.cd_documento in (313,349) and a.cd_documento_clinico=b.cd_documento_clinico and
            b.tp_status='FECHADO' and
            a.cd_editor_registro=c.cd_registro and
            b.cd_paciente=p.cd_paciente and
            b.cd_atendimento=at.cd_atendimento and
            b.cd_prestador=pr.cd_prestador and
            b.cd_atendimento = '$cd_atencion'
            order by c.cd_registro desc ";

            # Conectar base de datos
            $this->conectar_Oracle_MV();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            if ($data !== false) {
                return $data;
            }

            return false;

        } catch (ModelsException $e) {

            return false;

        }

    }

    private function detalleAtReceta($cd_atencion = '')
    {

        try {

            global $config, $http;

            $fechaHasta = date("d-m-Y", strtotime(date("d-m-Y") . " + 1 days"));
            $fechaDesde = date("d-m-Y", strtotime(date("d-m-Y") . " - days"));

            $sql = " SELECT  u.ds_unid_int, l.ds_leito, a.cd_documento_clinico, to_char(dh_documento, 'DD-MM-YYYY') as timestamp_control, at.cd_atendimento, at.dt_atendimento, at.cd_paciente, p.nm_paciente
            from pw_editor_clinico a, -- enlace documento clinico-documento editor
            pw_documento_clinico b, -- documento clinico
            editor.editor_registro c, -- documento editor
            paciente p, -- paciente
            atendime at, -- atencion
            prestador pr, -- medico
            leito l, --leito
            unid_int u --unidad de internacion
            where  a.cd_documento = 813 and a.cd_documento_clinico=b.cd_documento_clinico and
            b.tp_status='FECHADO' and
            a.cd_editor_registro=c.cd_registro and
            b.cd_paciente=p.cd_paciente and
            b.cd_atendimento=at.cd_atendimento and
            b.cd_prestador=pr.cd_prestador and
            at.cd_leito=l.cd_leito and
            l.cd_unid_int = u.cd_unid_int and
            b.cd_atendimento=$cd_atencion
            order by c.cd_registro desc ";

            # Conectar base de datos 4956
            $this->conectar_Oracle_MV();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            if ($data !== false) {
                return $data;
            }

            return false;

        } catch (ModelsException $e) {

            return false;

        }

    }

    public function procesarMensajes_Epi(): array
    {

        try {

            global $config, $http;

            $epis = Helper\Files::get_files_in_dir('../v1/logs/formularios/pendientes/');

            $i = 0;

            $procesos = array();

            foreach ($epis as $key => $val) {

                $cd_atencion = explode('-', $val)[1];

                $cd_atencion = substr($cd_atencion, 0, -4);

                $valAt = $this->disEpi($cd_atencion);

                if ($valAt['status']) {

                    $detalleAt = $this->detalleAt($cd_atencion);

                    if ($detalleAt !== false) {

                        $detalleAt['title'] = $detalleAt['NM_PACIENTE'] . ' - FICHAS EPIDEMIOLÓGICAS';
                        $link = explode('-', $valAt['data']);

                        $detalleAt['link1'] = $link[0];
                        $detalleAt['link2'] = $link[1];

                        $message = $this->getMailNotificacion(
                            $detalleAt,
                            'sec_emergencia@hmetro.med.ec'
                        );

                        $detalleAt['STATUS_ENVIADO'] = $message;

                        if ($message) {

                            $procesos[] = $cd_atencion;

                            @unlink($val);

                            $log = "sts-1" . PHP_EOL;
                            file_put_contents('logs/formularios/procesados/epi-' . $cd_atencion . '.log', $log, FILE_APPEND);

                        }

                    }

                }

                sleep(1);

                $i++;

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $procesos,
                'total' => count($procesos),
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'message' => $e->getMessage(),
            );

        }

    }

    // Recetas MV

    private function disReceta($cd_atencion = '')
    {
        try {

            global $config, $http;

            /*

            // 313
            $sql = "     SELECT c.ds_tip_presc medicacion,
            editor_custom.fun_calcula_medicina(b.qt_itpre_med*b.nr_dias*(24/fre.nr_intervalo),b.cd_produto,b.cd_uni_pro)||
            ' '||u.ds_unidade AS CANT,  -- CANTIDAD DE MEDICACION
            xx.sn_despacho AS DESP,
            xx.observacion AS OBS -- CANTIDAD A DESPACHAR
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

             */

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

            if ($data !== false) {

                return array(
                    'status' => true,
                    'data' => $data,
                );

            }

            return array(
                'status' => false,
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
            );

        }
    }

    public function getRecetasMV(): array
    {

        try {

            global $config, $http;

            $fecha = date("d-m-Y");

            $sql = " SELECT  a.cd_documento_clinico,
            to_char(dh_documento, 'DD-MM-YYYY HH24:MM:SS') as timestamp_control,
            at.cd_atendimento,
            at.dt_atendimento,
            at.cd_paciente,
            p.nm_paciente,
            pr.nm_prestador
            from pw_editor_clinico a, -- enlace documento clinico-documento editor
            pw_documento_clinico b, -- documento clinico
            editor.editor_registro c, -- documento editor
            paciente p, -- paciente
            atendime at, -- atencion
            prestador pr -- medico
            where  a.cd_documento = 813 and a.cd_documento_clinico=b.cd_documento_clinico and
            b.tp_status='FECHADO' and
            a.cd_editor_registro=c.cd_registro and
            b.cd_paciente=p.cd_paciente and
            b.cd_atendimento=at.cd_atendimento and
            b.cd_prestador=pr.cd_prestador and
            to_char(dh_documento, 'DD-MM-YYYY') = '$fecha'
            order by dh_documento desc";

            # Conectar base de datos
            $this->conectar_Oracle_MV();

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

            $time = new DateTime();
            $nuevaHora = strtotime('-5 minutes', $time->getTimestamp());
            $menosHora = date('Y-m-d H:i:s', $nuevaHora);
            $tiempoControl = strtotime($menosHora);

            foreach ($data as $key) {

                $key['TIMESTAMP'] = strtotime($key['TIMESTAMP_CONTROL']);

                if ($key['TIMESTAMP'] > $tiempoControl) {

                    $notPush = $this->sendProcessMessage_Push_Lab(array(
                        'interests' => array("MetroPlus-Farmacia"),
                        'web' => array(
                            "notification" => array(
                                "title" => "MetroPlus - Nueva Receta",
                                "body" => "NHC: " . $key['CD_PACIENTE'] . "\nPTE: " . $key['NM_PACIENTE'] . "\nMED: " . $key['NM_PRESTADOR'],
                                "icon" => "https://metroplus.hospitalmetropolitano.org/assets/favicon.ico",
                                "deep_link" => "https://metroplus.hospitalmetropolitano.org/farmacia/recetas",
                            ),
                        ),
                    ));

                    $key['STATUS_PUSH'] = $notPush;

                    $recetas[] = $key;

                }

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $recetas,
                'total' => count($recetas),
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function recordNotificaciones_Compras_Hora()
    {
        try {

            global $config, $http;

            $sql = "SELECT * from hmetro.OC_AUTORIZ_PEND_M2 WHERE  DS_EMAIL IS NOT NULL ";

            # Conectar base de datos
            $this->conectar_Oracle_TRN();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            foreach ($data as $key) {
                sleep(0.1);
                $st = $this->procesarNotificaciones_Compras_Horas($key['CD_ID_USUARIO'], $key['DS_EMAIL']);

                $key['ST_EMAIL'] = $st;
            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $data,
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage());

        }

    }

    /*
    Envía emails cada dia 8am
     */

    public function recordNotificaciones_Compras()
    {
        try {

            global $config, $http;

            $sql = "SELECT * from hmetro.OC_AUTORIZ_PEND_M WHERE  DS_EMAIL IS NOT NULL ";

            # Conectar base de datos
            $this->conectar_Oracle_MV();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            foreach ($data as $key) {
                sleep(0.1);

                if ($key['DS_EMAIL'] == 'MVILLACRES@HMETRO.MED.EC') {
                    $key['DS_EMAIL'] = $key['DS_EMAIL'] . ';cnaranjo@hmetro.med.ec';
                }

                $st = $this->procesarNotificaciones_Compras($key['CD_ID_USUARIO'], $key['DS_EMAIL']);

                $key['ST_EMAIL'] = $st;
            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $data,
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage());

        }

    }

    private function updateNotificacionPedido($nc, $usr)
    {

        # Conectar base de datos
        $this->conectar_Oracle_MV();

        $this->setSpanishOracle();

        # QueryBuilder
        $queryBuilder = $this->_conexion->createQueryBuilder();

        $queryBuilder
            ->update('HMETRO.ORDC_ESTADO_MAIL', 'u')
            ->set('u.ESTADO_ENVIO_OC', '?')
            ->set('u.FECHA_ENVIO_OC', '?')
            ->where('u.CD_ORD_COM = ?')
            ->andWhere('u.USUARIO = ?')
            ->setParameter(0, 'E')
            ->setParameter(1, date('d/m/Y'))
            ->setParameter(2, $nc)
            ->setParameter(3, $usr)
        ;

        # Execute
        $stmt = $queryBuilder->execute();

        $this->_conexion->close();

    }

    public function procesarNotificaciones_Compras_Horas($usuario, $correo)
    {
        try {

            global $config, $http;

            $sql = "SELECT DIAS, ORDEN_COMPRA as ORDEN ,FECHA,SECTOR,PROVEEDOR, TO_CHAR(VALOR,'fm9999990.00')  as VALOR, CONCAT(SUBSTR(OBSERVACION,0,25), '...')  as OBSERVACION  from hmetro.OC_ABIERTA_SEMAF2  where usuario = '$usuario' order by fecha desc";

            # Conectar base de datos
            $this->conectar_Oracle_TRN();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            $f = $this->build_table_compras($data);

            $message = $this->getMailNotificacion_Compras(
                array('DETALLE' => $f),
                $correo
            );

            foreach ($data as $key) {
                $this->updateNotificacionPedido($key['ORDEN'], $usuario);
            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $data,
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage());

        }

    }

    public function procesarNotificaciones_Compras($usuario, $correo)
    {
        try {

            global $config, $http;

            $sql = "SELECT DIAS, ORDEN_COMPRA as ORDEN ,FECHA,SECTOR,PROVEEDOR, TO_CHAR(VALOR,'fm9990.00')  as VALOR, CONCAT(SUBSTR(OBSERVACION,0,25), '...')  as OBSERVACION  from hmetro.OC_ABIERTA_SEMAF  where usuario = '$usuario' order by fecha desc";

            # Conectar base de datos
            $this->conectar_Oracle_MV();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            $f = $this->build_table_compras($data);

            $message = $this->getMailNotificacion_Compras(
                array('DETALLE' => $f),
                $correo
            );

            foreach ($data as $key) {
                $this->updateNotificacionPedido($key['ORDEN'], $usuario);
            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $data,
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage());

        }

    }

    public function procesarRecetas(): array
    {

        try {

            global $config, $http;

            $epis = Helper\Files::get_files_in_dir('../v1/logs/recetas/pendientes/');

            $i = 0;

            $procesos = array();

            foreach ($epis as $key => $val) {

                $cd_atencion = explode('-', $val)[1];

                $cd_atencion = substr($cd_atencion, 0, -4);

                $valAt = $this->disReceta($cd_atencion);

                if ($valAt['status'] && count($valAt['data']) !== 0) {

                    $table = $this->build_table($valAt['data']);

                    $detalleAt = $this->detalleAtReceta($cd_atencion);

                    if ($detalleAt !== false) {

                        $detalleAt['title'] = $detalleAt['NM_PACIENTE'] . ' - DESPACHO FARMACIA';

                        $detalleAt['MEDICACION'] = $table;

                        $detalleAt['LINK'] = $detalleAt['CD_DOCUMENTO_CLINICO'];

                        $message = $this->getMailNotificacion_Receta(
                            $detalleAt,
                            'farmacia@hmetro.med.ec'
                        );

                        $detalleAt['STATUS_ENVIADO'] = $message;

                        if ($message) {

                            $procesos[] = $cd_atencion;

                            @unlink($val);

                            $log = "sts-1" . PHP_EOL;
                            file_put_contents('../v1/logs/recetas/procesadas/at-' . $cd_atencion . '.log', $log, FILE_APPEND);

                        }

                    } else {

                        $procesos[] = $detalleAt;

                    }

                } else {
                    $procesos[] = $valAt;

                }

                $i++;

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $procesos,
                'total' => count($procesos),
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'message' => $e->getMessage(),
            );

        }

    }

    public function sendRecetaAlta(): array
    {

        try {

            global $config, $http;

            # Set Variable
            $numAtencion = $http->request->get('numAtencion');

            # Verificar que no están vacíos
            if (Helper\Functions::e($numAtencion)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion.');
            }

            $stsLogPen = "logs/recetas/pendientes/at-" . $numAtencion . ".log";
            $stsLogPro = "logs/recetas/procesadas/at-" . $numAtencion . ".log";

            if (@file_get_contents($stsLogPen, true) === false && @file_get_contents($stsLogPro, true) === false) {

                $log = "sts-0" . PHP_EOL;
                file_put_contents('logs/recetas/pendientes/at-' . $numAtencion . '.log', $log, FILE_APPEND);
                $recetas[] = $numAtencion;

            }

            return array(
                'status' => true,
                'message' => 'Proceso con éxito.',
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'message' => $e->getMessage(),
            );
        }

    }

    public function procesarRecetasBeta(): array
    {

        try {

            global $config, $http;

            $epis = Helper\Files::get_files_in_dir('../v1/logs/recetas/beta/pendientes/');

            $i = 0;

            $procesos = array();

            foreach ($epis as $key => $val) {

                $numDoc = explode('-', $val)[1];

                $cd_atencion = explode('-', $val)[2];
                $cd_atencion = substr($cd_atencion, 0, -4);

                $valAt = $this->disReceta($cd_atencion);

                if ($valAt['status']) {

                    $table = $this->build_table($valAt['data']);

                    $detalleAt = $this->detalleAtReceta($cd_atencion);

                    if ($detalleAt !== false) {

                        $detalleAt['title'] = $detalleAt['NM_PACIENTE'] . ' - DESPACHO FARMACIA';

                        $detalleAt['MEDICACION'] = $table;

                        $detalleAt['LINK'] = $detalleAt['CD_DOCUMENTO_CLINICO'];

                        $message = $this->getMailNotificacion_Receta_Beta(
                            $detalleAt,
                            'mchangcnt@gmail.com'
                        );

                        $detalleAt['STATUS_ENVIADO'] = $message;

                        if ($message) {

                            $procesos[] = array($numDoc, $cd_atencion);

                            @unlink($val);

                            $log = "sts-1" . PHP_EOL;
                            file_put_contents('../v1/logs/recetas/beta/procesadas/at-' . $numDoc . '-' . $cd_atencion . '.log', $log, FILE_APPEND);

                        }

                    } else {

                        $procesos[] = $detalleAt;

                    }

                } else {

                    $procesos[] = $valAt;

                }

                $i++;

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $procesos,
                'total' => count($procesos),
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'message' => $e->getMessage(),
            );

        }

    }

    public function sendRecetaAltaBeta(): array
    {

        try {

            global $config, $http;

            # Set Variable
            $numAtencion = $http->request->get('numAtencion');
            $numFormulario = $http->request->get('numDoc');

            # Verificar que no están vacíos
            if (Helper\Functions::e($numAtencion)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion. => numAtencion');
            }

            if (Helper\Functions::e($numFormulario)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion. => numDoc');
            }

            $stsLogPen = "logs/recetas/beta/pendientes/at-" . $numFormulario . "-" . $numAtencion . ".log";
            $stsLogPro = "logs/recetas/beta/procesadas/at-" . $numFormulario . "-" . $numAtencion . ".log";

            if (@file_get_contents($stsLogPen, true) === false && @file_get_contents($stsLogPro, true) === false) {

                $log = "sts-0" . PHP_EOL;
                file_put_contents('logs/recetas/beta/pendientes/at-' . $numFormulario . '-' . $numAtencion . '.log', $log, FILE_APPEND);
                $recetas[] = $numAtencion;

            }

            return array(
                'status' => true,
                'message' => 'Proceso con éxito.',
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'message' => $e->getMessage(),
            );
        }

    }

    public function getPedidosLaboratorio(): array
    {

        try {

            global $config, $http;

            $fechaHasta = date("d-m-Y", strtotime(date("d-m-Y") . " + 1 days"));
            $fechaDesde = date("d-m-Y", strtotime(date("d-m-Y") . " - days"));
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
            WHERE ROWNUM <= 20
            )
            WHERE NUM > 0
            ";

            # Conectar base de datos
            $this->conectar_Oracle_MV();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $pedidos = array();

            $time = new DateTime();
            $nuevaHora = strtotime('-5 minutes', $time->getTimestamp());
            $menosHora = date('Y-m-d H:i:s', $nuevaHora);
            $tiempoControl = strtotime($menosHora);

            foreach ($data as $key) {

                $key['TIMESTAMP'] = strtotime($key['FECHA_PEDIDO'] . ' ' . $key['HORA_PEDIDO'] . ':00');

                if ($key['TIMESTAMP'] > $tiempoControl) {

                    $message = $this->sendProcessMessage_Laboratorio(array(
                        'message' => 'MV Nuevo Pedido: Fecha:' . $key['FECHA_PEDIDO'] . ' ' . $key['HORA_PEDIDO'] . ' Pte: ' . $key['PTE_MV'] . ' Médico: ' . $key['MED_MV'] . ' Tipo: Laboratorio Status: Enviado.',
                        'url' => 'https://plus.metrovirtual.hospitalmetropolitano.org/emergencia/auxiliar/pedido/' . $key['NUM_PEDIDO_MV'],
                    ));

                    $notPush = $this->sendProcessMessage_Push_Lab(array(
                        'interests' => array("MetroPlus-Laboratorio"),
                        'web' => array(
                            "notification" => array(
                                "title" => "MetroPlus - Nuevo Pedido",
                                "body" => "N°: " . $key['NUM_PEDIDO_MV'] . "\nPTE: " . $key['PTE_MV'] . "\nMED: " . $key['MED_MV'],
                                "icon" => "https://metroplus.hospitalmetropolitano.org/assets/favicon.ico",
                                "deep_link" => "https://metroplus.hospitalmetropolitano.org/laboratorio/flebotomista/?numeroHistoriaClinica=" . $key['HC_MV'] . "&numeroAtencion=" . $key['AT_MV'] . "&numeroPedido=" . $key['NUM_PEDIDO_MV'] . "&track=view",
                            ),
                        ),
                    ));

                    $notPush = $this->sendProcessMessage_Push_Lab(array(
                        'interests' => array("MetroPlus-Flebotmoista"),
                        'web' => array(
                            "notification" => array(
                                "title" => "MetroPlus - Nueva Pedido",
                                "body" => "N°: " . $key['NUM_PEDIDO_MV'] . "\nPTE: " . $key['PTE_MV'] . "\nMED: " . $key['MED_MV'],
                                "icon" => "https://metroplus.hospitalmetropolitano.org/assets/favicon.ico",
                                "deep_link" => "https://metroplus.hospitalmetropolitano.org/laboratorio/flebotomista/?numeroHistoriaClinica=" . $key['HC_MV'] . "&numeroAtencion=" . $key['AT_MV'] . "&numeroPedido=" . $key['NUM_PEDIDO_MV'] . "&track=view",
                            ),
                        ),
                    ));

                    $key['STATUS_ENVIADO'] = $message;
                    $key['STATUS_PUSH'] = $notPush;

                    $pedidos[] = $key;
                }

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $pedidos,
                'total' => count($pedidos),
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

    public function sendNotificacionPush_MV()
    {

        global $http;

        $canal = $http->request->get('canal');
        $title = $http->request->get('title');
        $message = $http->request->get('message');
        $deep_link = $http->request->get('deepLink');

        $notPush = $this->sendProcessMessage_Push_Lab(array(
            'interests' => array($canal),
            'web' => array(
                "notification" => array(
                    "title" => $title,
                    "body" => $message,
                    "icon" => "https://metroplus.hospitalmetropolitano.org/assets/favicon.ico",
                    "deep_link" => $deep_link,
                ),

            ),
        ));

        if ($notPush) {
            return array("status" => true);
        }

        return array("status" => false);

    }

    public function getPedidos(): array
    {

        try {

            global $config, $http;

            $fechaHasta = date("d-m-Y", strtotime(date("d-m-Y") . " + 1 days"));
            $fechaDesde = date("d-m-Y", strtotime(date("d-m-Y") . " - days"));

            $sql = " SELECT  to_number(to_char(t1.hc_mv) || '01') HC,
            fun_busca_nombre_pte(to_number(to_char(t1.hc_mv) || '01')) NOMBRE,
            t1.adm_gema,
            t1.aten_mv,
            to_char(t1.fecha,'DD-MM-YYYY HH24:MI') AS FECHA_HORA,
            DECODE(t1.tipo_pre,'FAR','FARMACIA','LAB','LABORATORIO','IMG','IMAGEN','DIE','DIETETICA','TRE','TERAPIA RESPIRATORIA') TIPO_PEDIDO
            FROM mv_itg_pedidos t1
            ORDER BY FECHA_HORA DESC ";

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
            $pedidos = array();

            $time = new DateTime();
            $nuevaHora = strtotime('-5 minutes', $time->getTimestamp());
            $menosHora = date('Y-m-d H:i:s', $nuevaHora);
            $tiempoControl = strtotime($menosHora);

            foreach ($data as $key) {

                $key['TIMESTAMP'] = strtotime($key['FECHA_HORA']);

                if ($key['TIMESTAMP'] > $tiempoControl) {

                    $message = $this->sendProcessMessage(array(
                        'message' => 'MV Nuevo Pedido: Fecha:' . $key['FECHA_HORA'] . ' Pte: ' . $key['NOMBRE'] . ' Tipo: ' . $key['TIPO_PEDIDO'] . ' Status: Enviado.',
                    ));

                    $key['STATUS_ENVIADO'] = $message;
                    $pedidos[] = $key;
                }

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $pedidos,
                'total' => count($pedidos),
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

    public function getPedidoImagen_Ondemand()
    {

        try {

            global $config, $http;

            $fechaAtencion = $http->request->get('fechaAtencion');
            $nombrePte = $http->request->get('pte');
            $numAtencion = $http->request->get('at');
            $nhc = $http->request->get('nhc');
            $examenPedido = $http->request->get('examenPedido');
            $medico = $http->request->get('medico');

            # Verificar que no están vacíos
            if (Helper\Functions::e($fechaAtencion)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion. => [fechaAtencion]');
            }

            if (Helper\Functions::e($nombrePte)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion. => [nombrePte]');
            }

            if (Helper\Functions::e($numAtencion)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion. => [at]');
            }

            if (Helper\Functions::e($examenPedido)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion. => [examenPedido]');
            }

            $message = $this->sendProcessMessage_Imagen(array(
                'medico' => $medico,
                'message' => 'MV Nuevo Pedido: Examen: ' . $examenPedido . ' Fecha: ' . $fechaAtencion . '  Pte: ' . $nombrePte . '  NHC: ' . $nhc . ' N° Atención: ' . $numAtencion . ' Médico:' . $medico . ' Tipo: Imagen Status: Enviado (Solicitado) ',
            ));

            if ($message) {

                # Devolver Información
                return array(
                    'status' => true,
                    'message' => 'Proceso exitoso.',
                );
            }

            throw new ModelsException('No pudimos procesar esta petición Error en Kaizala ');

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getResultadoImagen_Ondemand()
    {

        try {

            global $config, $http;

            $fechaAtencion = $http->request->get('fechaAtencion');
            $nombrePte = $http->request->get('pte');
            $numAtencion = $http->request->get('at');
            $nhc = $http->request->get('nhc');
            $tipoPedido = $http->request->get('tipoPedido');
            $examenPedido = $http->request->get('examenPedido');
            $medico = $http->request->get('medico');

            # Verificar que no están vacíos
            if (Helper\Functions::e($fechaAtencion)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion. => [fechaAtencion]');
            }

            if (Helper\Functions::e($nombrePte)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion. => [nombrePte]');
            }

            if (Helper\Functions::e($numAtencion)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion. => [at]');
            }

            if (Helper\Functions::e($tipoPedido)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion. => [tipoPedido]');
            }

            if (Helper\Functions::e($examenPedido)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion. => [examenPedido]');
            }

            if ($tipoPedido == 1) {
                $message = $this->sendProcessMessage_Resultados_Imagen(array(
                    'medico' => $medico,
                    'message' => 'Nuevo Resultado de Imagen: Examen: ' . $examenPedido . ' Fecha: ' . $fechaAtencion . '  Pte: ' . $nombrePte . '  NHC: ' . $nhc . ' N° Atención: ' . $numAtencion . ' Médico:' . $medico . ' Tipo: Imagen Status: Escrito (Realizado) Disponible: PEP-MV.',
                ));
            }

            if ($tipoPedido == 2) {
                $message = $this->sendProcessMessage_Resultados_Imagen(array(
                    'medico' => $medico,
                    'message' => 'Nuevo Resultado de Imagen: Examen: ' . $examenPedido . ' Fecha: ' . $fechaAtencion . '  Pte: ' . $nombrePte . '  NHC: ' . $nhc . ' N° Atención: ' . $numAtencion . ' Médico:' . $medico . '  Tipo: Imagen Status: Aprobado (Finalizado) Disponible: Metrovirtual y PEP-MV.',
                ));
            }

            if ($message) {

                # Devolver Información
                return array(
                    'status' => true,
                    'message' => 'Proceso exitoso.',
                );
            }

            throw new ModelsException('No pudimos procesar esta petición Error en Kaizala ');

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getPedidos_Imagen()
    {

        try {

            global $config, $http;

            /*

            $message = $this->sendProcessMessage_Imagen(array(
            'message' => 'MV Nuevo Pedido: Fecha: 06/01/2022  Pte: ERNESTO FEDERICO ORDOÑEZ DONOSO  Tipo: Imagen Status: Enviado.',
            ));
             */

            return true;

            $message = $this->sendProcessMessage_Resultados_Imagen(array(
                'message' => 'Nuevo Resultado de Imagen: Fecha: 06/01/2022  Pte: ERNESTO FEDERICO ORDOÑEZ DONOSO  Tipo: Imagen Status: Disponible en Metrovirtual y PEP-MV.',
            ));

            return $message;

            $fechaHasta = date("d-m-Y", strtotime(date("d-m-Y") . " + 1 days"));
            $fechaDesde = date("d-m-Y", strtotime(date("d-m-Y") . " - days"));

            $sql = " SELECT to_number(to_char(t1.hc_mv) || '01') HC,
            fun_busca_nombre_pte(to_number(to_char(t1.hc_mv) || '01')) NOMBRE,
            t1.adm_gema,
            t1.aten_mv,
            to_char(t1.fecha,'DD-MM-YYYY HH24:MI') AS FECHA_HORA,
            DECODE(t1.tipo_pre,'IMG','IMAGEN') TIPO_PEDIDO
            FROM mv_itg_pedidos t1 WHERE
            DECODE(t1.tipo_pre,'IMG','IMAGEN') IS NOT NULL
            ORDER BY FECHA_HORA DESC ";

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
            $pedidos = array();

            $time = new DateTime();
            $nuevaHora = strtotime('-5 minutes', $time->getTimestamp());
            $menosHora = date('Y-m-d H:i:s', $nuevaHora);
            $tiempoControl = strtotime($menosHora);

            foreach ($data as $key) {

                $key['TIMESTAMP'] = strtotime($key['FECHA_HORA']);

                if ($key['TIMESTAMP'] > $tiempoControl) {
                    $message = $this->sendProcessMessage_Imagen(array(
                        'message' => 'MV Nuevo Pedido: Fecha:' . $key['FECHA_HORA'] . ' Pte: ' . $key['NOMBRE'] . ' Tipo: ' . $key['TIPO_PEDIDO'] . ' Status: Enviado.',
                    ));
                    $key['STATUS_ENVIADO'] = $message;
                    $pedidos[] = $key;
                }

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $pedidos,
                'total' => count($pedidos),
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

    public function procesarMensajes(): array
    {

        try {

            global $config, $http;

            $file = 'logs/pedidos/task_' . date('d-m-Y') . '.json';

            $datos = file_get_contents($file);
            $pedidos = json_decode($datos, true);
            $statusProcesado = false;
            $res = array();

            $i = 0;

            foreach ($pedidos as $key => $val) {

                if ($val['STATUS'] == 0 && !$statusProcesado) {

                    $message = $this->sendProcessMessage(array(
                        'message' => 'MV Nuevo Pedido: Fecha:' . $val['FECHA'] . ' Pte: ' . $val['NOMBRE'] . ' Tipo: ' . $val['TIPO_PEDIDO'] . ' Status: Enviado.',
                    ));

                    if ($message) {

                        // Nuevo Registro
                        $statusProcesado = true;
                        $pedidos[$key]['STATUS'] = 1;
                        $res[] = $val;

                        $file = 'logs/pedidos/task_' . date('d-m-Y') . '.json';
                        $json_string = json_encode($pedidos);
                        file_put_contents($file, $json_string);

                    }

                }

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $res,
                'total' => count($res),
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'message' => $e->getMessage(),
            );

        }

    }

    private function notResults(array $data)
    {
        if (count($data) == 0) {
            throw new ModelsException('No existe más resultados.', 4080);
        }
    }

    public function sendProcessMessage(array $data)
    {

        $_datos = json_encode($data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://prod-122.westus.logic.azure.com:443/workflows/e551939ee9a743e2a08bce85bca5ce37/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=Ea6EKzslglRsXCcPNaA3nKtINSImTroYK-IhNcH7Ih0');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $_datos);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json')
        );

        $response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 200) {
            # return $response;

            return true;
        } else {

            return false;
        }

        # return $response;

        curl_close($ch);

    }

    public function sendProcessMessage_Push_Lab(array $data)
    {

        $_datos = json_encode($data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://75c0a111-da02-4af0-b35b-66124bd7f2b5.pushnotifications.pusher.com/publish_api/v1/instances/75c0a111-da02-4af0-b35b-66124bd7f2b5/publishes');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $_datos);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            array(
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer 0B43112B57EEFBDD46612FD4F6DF6E3EE2FA3299B937DC1B674B1AEC268971FA',
            )
        );

        $response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 200) {
            # return $response;

            return true;
        } else {

            return false;
        }

        # return $response;

        curl_close($ch);

    }

    public function sendProcessMessage_Laboratorio(array $data)
    {

        $_datos = json_encode($data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://prod-59.westus.logic.azure.com:443/workflows/9602ede0bec0486aa612e3d9be3bfd44/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=XDsaTcEieFGLKul2gD4ggu0cUI92Uu1q4Wea-ibBggM');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $_datos);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json')
        );

        $response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 200) {
            # return $response;

            return true;
        } else {

            return false;
        }

        # return $response;

        curl_close($ch);

    }

    public function sendProcessMessage_Imagen(array $data)
    {

        $_datos = json_encode($data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://prod-59.westus.logic.azure.com:443/workflows/4c95e245f13d4fe8a241db239df5f23a/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=veByIFSmIcnoSdmbvVQCyiwUfI7qbSZIVJTLWBgiNjM');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $_datos);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json')
        );

        $response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 200) {
            # return $response;

            return true;
        } else {

            return false;
        }

        # return $response;

        curl_close($ch);

    }

    public function sendProcessMessage_Resultados_Imagen(array $data)
    {

        $_datos = json_encode($data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://prod-56.westus.logic.azure.com:443/workflows/5cbb64dc159f4a60b9a7f09ba7fdbc41/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=G4sI24JjoUwT1krr4khlhGyKdrRvyzzwOLOGqeWYeFI');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $_datos);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json')
        );

        $response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 200) {
            # return $response;

            return true;
        } else {

            return false;
        }

        # return $response;

        curl_close($ch);

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
