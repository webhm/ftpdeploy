<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models\nss\imagen;

use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Notificar
 */
class Notificar extends Models implements IModels
{

    # Variables de clase
    private $ordenes = array();
    private $documento = array();
    private $urlApiImagen = 'https://pacientes.hospitalmetropolitano.org/';
    private $urlApiViewer = 'https://api.imagen.hospitalmetropolitano.org/';
    private $urlApiViewerMTR = 'https://pacientesmetrored.hospitalmetropolitano.org/';
    private $urlApiImagenMTR = 'https://pacientesmetrored.hospitalmetropolitano.org/';

    private $urlViewer = 'https://imagen.hmetro.med.ec/zfp?Lights=on&mode=proxy#view&ris_exam_id=';
    private $keyImagen = '&un=WEBAPI&pw=lEcfvZxzlXTsfimMMonmVZZ15IqsgEcdV%2forI8EUrLY%3d';

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();

        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

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

        $sql = " alter session set NLS_DATE_FORMAT = 'DD/MM/YYYY' ";
        # Execute
        $stmt = $this->_conexion->query($sql);

    }

    public function notificarInformes_MTR()
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../nss/imagen/ingresados/metrored/', '_pendiente_.json');

            $i = 0;

            // Extraer ORDENES PARA VALIDAR
            foreach ($list as $key => $val) {

                $content = file_get_contents($val);
                $documento = json_decode($content, true);
                $documento['file'] = $val;

                if ($i <= 0) {
                    sleep(0.1);
                    $documento['ultimaValidacion'] = date('Y-m-d H:i:s');
                    $this->ordenes[] = $documento;
                    $i++;
                }

            }

            $this->enviar_MTR();

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->ordenes,
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

        }

    }

    public function enviar_MTR()
    {

        try {

            $ordenes = array();

            foreach ($this->ordenes as $key) {

                $this->documento = $key;

                # Status Envio -1- Generacion de Lista de Direcciones
                $this->generarCorreosElectronicosMedico_MTR();

                # Status Envio -2- Generacion de Correc de Paciente Correcta
                $this->generarCorreosElectronicosPaciente_MTR();

                # Status Envio 3 - Generar Envio
                $this->enviarNotificacion_MTR();

                # Validar Envio
                $this->validarNotificacion_MTR();

                // Validar Resultdos
                if ($this->documento['statusEnvio'] == 4 && $this->documento['numeroEstudio'] == $key['numeroEstudio']) {
                    @unlink($this->documento['file']);
                    unset($this->documento['file']);
                    $this->documento['logsEnvio'][] = array(
                        'timestampLog' => date('Y-m-d H:i:s'),
                        'log' => 'Envio exitoso de Notificación.',
                    );
                    $file = 'imagen/enviados/metrored/st_' . $this->documento['numeroEstudio'] . '_' . $this->documento['fechaEstudio'] . '.json';
                    $json_string = json_encode($this->documento);
                    file_put_contents($file, $json_string);
                } else {
                    // Filtrada Para No Envío
                    @unlink($this->documento['file']);
                    unset($this->documento['file']);
                    $this->documento['logsEnvio'][] = array(
                        'timestampLog' => date('Y-m-d H:i:s'),
                        'log' => 'Error en proceso de envío',
                    );
                    $file = 'imagen/errores/enviados/metrored/st_' . $this->documento['numeroEstudio'] . '_' . $this->documento['fechaEstudio'] . '.json';
                    $json_string = json_encode($this->documento);
                    file_put_contents($file, $json_string);
                }

                $ordenes[] = $this->documento;

            }

            $this->ordenes = $ordenes;

        } catch (ModelsException $e) {

            throw new ModelsException('Error en proceso Envviar.');

        }

    }

    public function notificarInformes()
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../nss/imagen/ingresados/', '_pendiente_.json');

            $i = 0;

            // Extraer ORDENES PARA VALIDAR
            foreach ($list as $key => $val) {

                $content = file_get_contents($val);
                $documento = json_decode($content, true);
                $documento['file'] = $val;

                if ($i <= 4) {
                    sleep(0.1);
                    $documento['ultimaValidacion'] = date('Y-m-d H:i:s');
                    $this->ordenes[] = $documento;
                    $i++;
                }

            }

            $this->enviar();

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->ordenes,
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

        }

    }

    public function enviar()
    {

        try {

            $ordenes = array();

            foreach ($this->ordenes as $key) {

                $this->documento = $key;

                # Status Envio -1- Generacion de Lista de Direcciones
                $this->generarCorreosElectronicosMedico();

                # Status Envio -2- Generacion de Correc de Paciente Correcta
                $this->generarCorreosElectronicosPaciente();

                # Status Envio 3 - Generar Envio
                $this->enviarNotificacion();

                # Validar Envio
                $this->validarNotificacion();

                // Validar Resultdos
                if ($this->documento['statusEnvio'] == 4 && $this->documento['numeroEstudio'] == $key['numeroEstudio']) {
                    @unlink($this->documento['file']);
                    unset($this->documento['file']);
                    $this->documento['logsEnvio'][] = array(
                        'timestampLog' => date('Y-m-d H:i:s'),
                        'log' => 'Envio exitoso de Notificación.',
                    );
                    $file = 'imagen/enviados/st_' . $this->documento['numeroEstudio'] . '_' . $this->documento['fechaEstudio'] . '.json';
                    $json_string = json_encode($this->documento);
                    file_put_contents($file, $json_string);
                } else {
                    // Filtrada Para No Envío
                    @unlink($this->documento['file']);
                    unset($this->documento['file']);
                    $this->documento['logsEnvio'][] = array(
                        'timestampLog' => date('Y-m-d H:i:s'),
                        'log' => 'Error en proceso de envío',
                    );
                    $file = 'imagen/errores/enviados/st_' . $this->documento['numeroEstudio'] . '_' . $this->documento['fechaEstudio'] . '.json';
                    $json_string = json_encode($this->documento);
                    file_put_contents($file, $json_string);
                }

                $ordenes[] = $this->documento;

            }

            $this->ordenes = $ordenes;

        } catch (ModelsException $e) {

            throw new ModelsException('Error en proceso Envviar.');

        }

    }

    public function validarNotificacion()
    {

        if ($this->documento['statusEnvio'] == 3) {
            $this->documento['statusEnvio'] = 4;
        }

    }

    public function validarNotificacion_MTR()
    {

        if ($this->documento['statusEnvio'] == 3) {
            $this->documento['statusEnvio'] = 4;
        }

    }

    public function generarCorreosElectronicosMedico()
    {

        $this->documento['statusEnvio'] = -1;

        $listaMedicos = file_get_contents('../nss/imagen/filtros/medicos.json');
        $medicos = json_decode($listaMedicos, true);

        foreach ($medicos as $key) {

            if ($key['codigoMedico'] == $this->documento['codigoMedico']) {

                $this->documento['filtrosProceso'][] = $key;
                foreach ($key['correosElectronicos'] as $k => $v) {
                    $this->documento['correosElectronicos'][] = $v;
                }

            }

        }

        $this->documento['statusEnvio'] = 1;

    }

    public function generarCorreosElectronicosMedico_MTR()
    {

        $this->documento['statusEnvio'] = -1;

        $listaMedicos = file_get_contents('../nss/imagen/filtros/medicos.mtr.json');
        $medicos = json_decode($listaMedicos, true);

        foreach ($medicos as $key) {

            if ($key['codigoMedico'] == $this->documento['codigoMedico']) {

                $this->documento['filtrosProceso'][] = $key;
                foreach ($key['correosElectronicos'] as $k => $v) {
                    $this->documento['correosElectronicos'][] = $v;
                }

            }

        }

        $this->documento['statusEnvio'] = 1;

    }

    public function generarCorreosElectronicosPaciente()
    {
        # Extraer Datos de Paciente
        $documento = $this->documento;

        $documento['statusEnvio'] = -2;

        $nhc = $documento['numeroHistoriaClinica'];

        $sql = " SELECT
          b.fk_persona
          from cad_pacientes b
          where  b.pk_nhcl = '$nhc' ";

        # Conectar base de datos
        $this->conectar_Oracle();

        $this->setSpanishOracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetch();

        if ($data === false) {

            $documento['logsEnvio'][] = array(
                'timestampLog' => date('Y-m-d H:i:s'),
                'log' => 'NÚmero de Historia Clinica del Paciente no existe en BDD GEMA.',
            );

            $documento['statusEnvio'] = -2.1;

            $this->documento = $documento;

        } else {

            $codPersona = $data['FK_PERSONA'];

            $sql = "SELECT fun_busca_mail_persona(" . $codPersona . ") as emailsPaciente from dual ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            $getCorreos = $data['EMAILSPACIENTE'];

            if (is_null($getCorreos)) {

                $documento['logsEnvio'][] = array(
                    'timestampLog' => date('Y-m-d H:i:s'),
                    'log' => 'NÚmero de Historia Clinica del Paciente no devuelve ningún correo electrónico disponible en BDD GEMA.',
                );

                $documento['statusEnvio'] = -2.2;

                $this->documento = $documento;

            } else {

                $pos = strpos($getCorreos, '|');

                # Solo un correo
                if ($pos === false) {

                    $correoPaciente = $getCorreos;

                    $existeCorreo = $this->agregarCorreoEnvio($correoPaciente);

                    if (!$existeCorreo) {

                        $documento['correosElectronicos'][] = $correoPaciente;

                        $documento['statusEnvio'] = 2;

                        $this->documento = $documento;

                    }

                } else {

                    $_correosPacientes = explode('|', $getCorreos);

                    foreach ($_correosPacientes as $key => $val) {

                        $existeCorreo = $this->agregarCorreoEnvio($val);

                        if (!$existeCorreo) {

                            $documento['correosElectronicos'][] = $val;

                        }

                    }

                    $documento['statusEnvio'] = 2;

                    $this->documento = $documento;

                }

            }

        }
    }

    public function generarCorreosElectronicosPaciente_MTR()
    {
        # Extraer Datos de Paciente
        $documento = $this->documento;

        $documento['statusEnvio'] = -2;

        $nhc = $documento['numeroHistoriaClinica'];

        /*

        $sql = " SELECT
        b.fk_persona
        from cad_pacientes b
        where  b.pk_nhcl = '$nhc' ";

        # Conectar base de datos
        $this->conectar_Oracle();

        $this->setSpanishOracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetch();

         */

        $data = array();

        if ($data === false) {

            $documento['logsEnvio'][] = array(
                'timestampLog' => date('Y-m-d H:i:s'),
                'log' => 'NÚmero de Historia Clinica del Paciente no existe en BDD GEMA.',
            );

            $documento['statusEnvio'] = -2.1;

            $this->documento = $documento;

        } else {
            /*

            $codPersona = $data['FK_PERSONA'];

            $sql = "SELECT fun_busca_mail_persona(" . $codPersona . ") as emailsPaciente from dual ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            $getCorreos = $data['EMAILSPACIENTE'];

             */

            $getCorreos = 'martinfranciscochavez@gmail.com';

            if (is_null($getCorreos)) {

                $documento['logsEnvio'][] = array(
                    'timestampLog' => date('Y-m-d H:i:s'),
                    'log' => 'NÚmero de Historia Clinica del Paciente no devuelve ningún correo electrónico disponible en BDD GEMA.',
                );

                $documento['statusEnvio'] = -2.2;

                $this->documento = $documento;

            } else {

                $pos = strpos($getCorreos, '|');

                # Solo un correo
                if ($pos === false) {

                    $correoPaciente = $getCorreos;

                    $existeCorreo = $this->agregarCorreoEnvio($correoPaciente);

                    if (!$existeCorreo) {

                        $documento['correosElectronicos'][] = $correoPaciente;

                        $documento['statusEnvio'] = 2;

                        $this->documento = $documento;

                    }

                } else {

                    $_correosPacientes = explode('|', $getCorreos);

                    foreach ($_correosPacientes as $key => $val) {

                        $existeCorreo = $this->agregarCorreoEnvio($val);

                        if (!$existeCorreo) {

                            $documento['correosElectronicos'][] = $val;

                        }

                    }

                    $documento['statusEnvio'] = 2;

                    $this->documento = $documento;

                }

            }

        }
    }

    public function agregarCorreoEnvio($correo)
    {

        $existe = false;

        if (count($this->documento['correosElectronicos']) !== 0) {

            foreach ($this->documento['correosElectronicos'] as $key => $val) {

                if ($val == $correo) {
                    $existe = true;
                }

            }
        }

        return $existe;

    }

    public function enviarNotificacion()
    {

        $this->documento['statusEnvio'] = -3;

        $envioExitoso = false;

        foreach ($this->documento['correosElectronicos'] as $k => $v) {
            sleep(0.5);
            $stsEnvio = $this->enviarCorreoPersonalizado($v);
            $this->documento['logsEnvio'][] = $stsEnvio;
            if ($stsEnvio['status']) {
                $envioExitoso = true;
            }
        }

        if ($envioExitoso) {
            $this->documento['statusEnvio'] = 3;
        }

    }

    public function enviarNotificacion_MTR()
    {

        $this->documento['statusEnvio'] = -3;

        $envioExitoso = false;

        foreach ($this->documento['correosElectronicos'] as $k => $v) {
            sleep(0.5);
            $stsEnvio = $this->enviarCorreoPersonalizado_MTR($v);
            $this->documento['logsEnvio'][] = $stsEnvio;
            if ($stsEnvio['status']) {
                $envioExitoso = true;
            }
        }

        if ($envioExitoso) {
            $this->documento['statusEnvio'] = 3;
        }

    }

    public function enviarCorreoPersonalizado_MTR($correoElectronico = '')
    {

        $envioExitoso = false;

        $logsEnvio = array();

        $statusEnvio = $this->getMailNotificacion_MTR($this->documento, $correoElectronico);

        if ($statusEnvio['status']) {

            $logsEnvio[] = $statusEnvio['data'];

        } else {

            $logsEnvio[] = $statusEnvio['data'];
        }

        if ($statusEnvio['status']) {
            $envioExitoso = true;
        }

        return array(
            'status' => $envioExitoso,
            'data' => $logsEnvio,
        );

    }

    public function getMailNotificacion_MTR(array $data, string $correo)
    {

        $hashEstudio = Helper\Strings::ocrend_encode($data['numeroEstudio'], 'temp');
        $hashReport = Helper\Strings::ocrend_encode($data['numeroReporte'], 'hm');

        $token = Helper\Strings::ocrend_encode(time(), 'temp');

        // Notificación de correo electrónico para pacientes // Estado f
        $linkViewer = $this->urlApiViewerMTR . 'viewer/' . $hashEstudio . '&key=' . $token;
        $linkInforme = $this->urlApiImagenMTR . 'resultados/i/' . $hashReport;

        # Construir mensaje y enviar mensaje
        $content = '<br />
                    Estimado(a).-
                    <br />
                    <br />
                    <b>' . $data['paciente'] . '</b> está disponible un nuevo resultado de Imagen.
                    <br />
                    <b>Fecha de Examen:</b> ' . $data['fechaEstudio'] . '
                    <br />';

        # Enviar el correo electrónico
        $_html = Helper\Emails::loadTemplate(
            array(
                # Título del mensaje
                '{{title}}' => ' Nuevo Resultado de Imagen - MetroVirtual Metrored',
                # Contenido del mensaje
                '{{content}}' => $content,
                # Url del botón
                '{{btn-href}}' => $linkViewer,

                '{{btn-href-informe}}' => $linkInforme,
                # Texto del boton
                '{{btn-name}}' => 'Ver Resultado de Imagen',
                # Copyright
                '{{copyright}}' => '&copy; ' . date('Y') . ' <a href="https://www.metrored.med.ec">Metrored CENTROS MÉDICOS</a> Todos los derechos reservados.',
            ),
            13
        );

        # Verificar si hubo algún problema con el envió del correo
        $sendMail = $this->sendMailNotificacion_MTR($_html, $correo, 'Nuevo Resultado de Imagen - Metrored CENTROS MÉDICOS');

        return $sendMail;
    }

    public function sendMailNotificacion_MTR($html, $to, $subject)
    {

        $stringData = array(
            "TextBody" => "Resultado de Imagen - Metrored CENTROS MÉDICOS",
            'From' => 'Metrored CENTROS MÉDICOS metrovirtual@hospitalmetropolitano.org',
            'To' => 'martinfranciscochavez@gmail.com',
            'Bcc' => 'pamelat1295@gmail.com',
            'Subject' => $subject,
            'HtmlBody' => $html,
            'Tag' => 'NRIPMTRV3',
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
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Accept: application/json',
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data),
                'X-Postmark-Server-Token: 7f14b454-8df3-4e75-9def-30e45cab59e9',
            )
        );

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $resultobj = curl_error($ch);
            return array('status' => false, 'data' => $resultobj);
        }

        curl_close($ch);
        $resultobj = json_decode($result);
        return array('status' => true, 'data' => $resultobj);

    }

    public function sendMailNotificacion($html, $to, $subject)
    {

        $stringData = array(
            "TextBody" => "Resultado de Imagen - MetroVirtual Metrored",
            'From' => 'MetroVirtual metrovirtual@hospitalmetropolitano.org',
            'To' => 'martinfranciscochavez@gmail.com',
            'Subject' => $subject,
            'HtmlBody' => $html,
            'Tag' => 'NRIPMTRV3',
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
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Accept: application/json',
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data),
                'X-Postmark-Server-Token: 7f14b454-8df3-4e75-9def-30e45cab59e9',
            )
        );

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $resultobj = curl_error($ch);
            return array('status' => false, 'data' => $resultobj);
        }

        curl_close($ch);
        $resultobj = json_decode($result);
        return array('status' => true, 'data' => $resultobj);

    }

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);

    }
}
