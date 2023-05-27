<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models\nss;

use app\models\nss as Model;
use Doctrine\DBAL\DriverManager;
use Endroid\QrCode\QrCode;
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;
use setasign\Fpdi\Fpdi;
use SoapClient;

/**
 * Modelo Enviar
 */
class Enviar extends Models implements IModels
{

    # Variables de clase
    private $ordenes = array();
    private $documento = array();
    private $id = null;

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

    private function setParameters()
    {

        global $http;

        foreach ($http->request->all() as $key => $value) {
            $this->$key = $value;
        }

        foreach ($http->query->all() as $key => $value) {
            $this->$key = $value;
        }

    }

    public function getResultado()
    {

        try {

            global $config, $http;

            $this->setParameters();

            // Extraer ORDENES PARA VALIDAR
            foreach ($list as $key => $val) {

                if ($i == 0) {

                    $content = file_get_contents($val);
                    $documento = json_decode($content, true);
                    $documento['file'] = $val;
                    $documento['statusEnvio'] = 0;
                    $this->ordenes[] = $documento;
                    $i++;

                }

            }

            // Enviar Ordenes de Envio
            $this->enviar();

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->ordenes,

            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => $this->ordenes, 'message' => $this->ordenes, 'errorCode' => $e->getCode());

        }

    }

    public function enviarOrdenes(): array
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/porenviar/');

            $i = 0;

            // Extraer ORDENES PARA VALIDAR
            foreach ($list as $key => $val) {

                if ($i == 0) {

                    $content = file_get_contents($val);
                    $documento = json_decode($content, true);
                    $documento['file'] = $val;
                    $documento['statusEnvio'] = 0;
                    $this->ordenes[] = $documento;
                    $i++;

                }

            }

            // Enviar Ordenes de Envio
            $this->enviar();

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->ordenes,

            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => $this->ordenes, 'message' => $this->ordenes, 'errorCode' => $e->getCode());

        }

    }

    public function enviar()
    {

        try {

            $ordenes = array();

            foreach ($this->ordenes as $key) {

                $this->documento = $key;

                # Status Envio -1- Generacion de Corec de Paciente Correcta
                $this->generarCorreosElectronicosPaciente();

                # Status Envio -2- Generacion de Coreo Adjunto Pdf
                $this->generarResultadoPDF();

                # Status Envio -3- Generar Fomrato QR Personalizado
                $this->generarFormatoQR();

                # Status Envio -4- Generar Fomrato QR Personalizado
                $this->enviarResultado();

                # Status Envio -5- Generar Fomrato QR Personalizado
                $this->verificarRevalidacion();

                $ordenes[] = $this->documento;

                // Validar Resultdos
                if ($this->documento['statusEnvio'] == 4 && $this->documento['sc'] == $key['sc']) {

                    @unlink($this->documento['file']);
                    unset($this->documento['file']);

                    $this->documento['logsEnvio'][] = array(
                        'timestampLog' => date('Y-m-d H:i:s'),
                        'log' => 'Envio exitoso de Notificación.',
                    );

                    $file = 'ordenes/enviadas/sc_' . $this->documento['sc'] . '_' . $this->documento['fechaExamen'] . '.json';
                    $json_string = json_encode($this->documento);
                    file_put_contents($file, $json_string);

                } else if ($this->documento['statusEnvio'] == 5 && $this->documento['sc'] == $key['sc']) {

                    @unlink($this->documento['file']);
                    unset($this->documento['file']);

                    $this->documento['logsEnvio'][] = array(
                        'timestampLog' => date('Y-m-d H:i:s'),
                        'log' => 'Reproceso por Revalidación de resultado.',
                    );

                    $file = 'ordenes/filtradas/sc_' . $this->documento['sc'] . '_' . $this->documento['fechaExamen'] . '.json';
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

                    $file = 'ordenes/errores/enviadas/sc_' . $this->documento['sc'] . '_' . $this->documento['fechaExamen'] . '.json';
                    $json_string = json_encode($this->documento);
                    file_put_contents($file, $json_string);

                }

            }

            $this->ordenes = $ordenes;

        } catch (ModelsException $e) {

            throw new ModelsException('Error en proceso Enviae.');

        }

    }

    public function verificarRevalidacion()
    {

        # Extraer Datos de Paciente
        $documento = $this->documento;

        if ($documento['tipoValidacion'] == 1) {

            if ($documento['validacionClinica'] !== 0 && $documento['validacionMicro'] !== 0) {
                $documento['statusEnvio'] = 4;
                $this->documento = $documento;
            } else {
                $documento['statusEnvio'] = 5;
                $this->documento = $documento;
            }

        } else {

            if ($documento['validacionClinica'] == 1) {
                $documento['statusEnvio'] = 4;
                $this->documento = $documento;
            }

            if ($documento['validacionMicro'] == 1) {
                $documento['statusEnvio'] = 4;
                $this->documento = $documento;
            }

        }

    }

    public function getMailNotificacion(string $correo = 'mchangcnt@gmail.com')
    {

        $documento = $this->documento;

        # Construir mensaje y enviar mensaje
        $content = '<br />
                    Estimado(a).- <br /><br /><b>' . $documento['apellidosPaciente'] . ' ' . $documento['nombresPaciente'] . '</b> está disponible un nuevo resultado de Laboratorio.
                    <br />
                    <b>Fecha de Examen:</b> ' . $documento['fechaExamen'] . '
                    <br />';

        # Enviar el correo electrónico
        $_html = Helper\Emails::loadTemplate(array(
            # Título del mensaje
            '{{title}}' => 'Nuevo Resultado de Laboratorio  - Metrovirtual',
            # Contenido del mensaje
            '{{content}}' => $content,
            # Texto del boton
            '{{btn-name}}' => 'Ver Resultado de Laboratorio',
            # Copyright
            '{{copyright}}' => '&copy; ' . date('Y') . ' <a href="https://www.hospitalmetropolitano.org">Metrovirtual Hospital Metropolitano</a> Todos los derechos reservados.',
        ), 7);

        # Verificar si hubo algún problema con el envió del correo
        $sendMail = $this->sendMailNotificacion($_html, $correo, 'Nuevo Resultado de Laboratorio - Metrovirtual Hospital Metropolitano');

        return $sendMail;

    }

    public function sendMailNotificacion($html, $to, $subject)
    {

        global $config;

        $documento = $this->documento;

        $adjunto[] = $documento['_PDF'];

        $stringData = array(
            "TextBody" => "Resultado de Laboratorio - Metrovirtual",
            'From' => 'Metrovirtual metrovirtual@hospitalmetropolitano.org',
            'To' => 'martinfranciscochavez@gmail.com;gracerecalde@hotmail.com',
            'Subject' => $subject,
            'HtmlBody' => $html,
            'Attachments' => $adjunto,
            'Tag' => 'NRLPV2',
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
            return array('status' => false, 'data' => $resultobj);
        }

        curl_close($ch);
        $resultobj = json_decode($result);
        return array('status' => true, 'data' => $resultobj);

    }

    public function enviarResultado()
    {

        # Extraer Datos de Paciente
        $documento = $this->documento;

        $envioExitoso = false;

        if (count($documento['correosElectronicos']) !== 0) {

            foreach ($documento['correosElectronicos'] as $key => $val) {
                if ($val['dirrecciones'] !== '1') {

                    $statusEnvio = $this->getMailNotificacion($val['dirrecciones']);

                    if ($statusEnvio['status']) {

                        $documento['logsEnvio'][] = $statusEnvio['data'];
                        $this->documento = $documento;

                    } else {

                        $documento['logsEnvio'][] = $statusEnvio['data'];
                        $this->documento = $documento;
                    }

                    if ($statusEnvio['status']) {
                        $envioExitoso = true;
                    }

                }
            }

            if ($envioExitoso) {
                $documento['logsEnvio'][] = array(
                    'timestampLog' => date('Y-m-d H:i:s'),
                    'log' => 'Proceso de envóo Exitoso',
                );
                $documento['statusEnvio'] = 4;
                $this->documento = $documento;
            } else {
                $documento['logsEnvio'][] = array(
                    'timestampLog' => date('Y-m-d H:i:s'),
                    'log' => 'Proceso de envóo con Error ningún correo electrónico respondio afirmativamente.',
                );
                $this->documento = $documento;

            }

        }

    }

    public function generarFormatoQR()
    {

        $documento = $this->documento;
        $documento['statusEnvio'] = 3;
        $this->documento = $documento;

    }

    public function generarQR_PCR($linkResultado)
    {

        try {

            $destination = "../../nss/v1/ordenes/downloads/" . $this->documento['sc'] . ".pdf";

            $fp = fopen($destination, 'w+');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $linkResultado);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_exec($ch);
            curl_close($ch);

            fclose($fp);

            $urlPdf = 'https://api.hospitalmetropolitano.org';

            # Generate QR CODE
            $qrCode = new QrCode($urlPdf);
            $qrCode->setLogoPath('../../nss/v1/ordenes/downloads/hm.png');

            // Save it to a file
            $qrCode->writeFile('../../nss/v1/ordenes/downloads/' . $this->documento['sc'] . '.png');

            $qrImage = '../../nss/v1/ordenes/downloads/' . $this->documento['sc'] . '.png';

            $qrAcess = '../../nss/v1/ordenes/downloads/acess.png';

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

            $newDestination = "../../nss/v1/ordenes/downloads/" . $this->documento['sc'] . ".qr.pdf";

            $pdf->Output('F', $newDestination);

            $_file = base64_encode(file_get_contents($newDestination));

            @unlink($destination);
            @unlink($newDestination);
            @unlink($qrImage);

            return $_file;

        } catch (ModelsException $e) {

            $documento['logsEnvio'][] = array(
                'timestampLog' => date('Y-m-d H:i:s'),
                'log' => 'No fue posible generar el documento firmado QR.',
            );

            $this->documento = $documento;

            return false;
        }

    }

    public function generarResultadoPDF()
    {

        try {

            $documento = $this->documento;

            if ($documento['_PCR'] !== 0) {
                # INICIAR SESSION
                $this->wsLab_LOGIN_PCR();
            } else {
                # INICIAR SESSION
                $this->wsLab_LOGIN();
            }

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wReports.wsdl.xml');

            $Preview = $client->Preview(array(
                "pstrSessionKey" => $this->pstrSessionKey,
                "pstrSampleID" => $documento['sc'],
                "pstrRegisterDate" => $documento['fechaExamen'],
                "pstrFormatDescription" => 'METROPOLITANO',
                "pstrPrintTarget" => 'Destino por defecto',
            ));

            # CERRAR SESSION POR LICENCIAS HSF
            $this->wsLab_LOGOUT();

            # No existe documento

            if (!isset($Preview->PreviewResult)) {

                $documento['logsEnvio'][] = array(
                    'timestampLog' => date('Y-m-d H:i:s'),
                    'log' => 'No existe el documento solicitado.',
                );

                $this->documento = $documento;
            }

            # No existe documento

            if (isset($Preview->PreviewResult) or $Preview->PreviewResult == '0') {

                if ($Preview->PreviewResult == '0') {

                    $documento['logsEnvio'][] = array(
                        'timestampLog' => date('Y-m-d H:i:s'),
                        'log' => 'No existe el documento solicitado.',
                    );

                    $this->documento = $documento;

                } else {

                    if ($documento['_PCR'] !== 0) {

                        $_file = $this->generarQR_PCR($Preview->PreviewResult);

                        if ($_file !== false) {

                            $documento['_PDF'] = array(
                                'Name' => 'resultado_' . $this->documento['sc'] . '.pdf',
                                'ContentType' => 'application/pdf',
                                'Content' => $_file,
                            );

                            $documento['statusEnvio'] = 2;
                            $this->documento = $documento;
                        } else {

                            $documento['logsEnvio'][] = array(
                                'timestampLog' => date('Y-m-d H:i:s'),
                                'log' => 'No existe el documento solicitado ERROR CONVERSION QR.',
                            );

                            $this->documento = $documento;
                        }

                    } else {

                        $_file = base64_encode(file_get_contents($Preview->PreviewResult));

                        $documento['_PDF'] = array(
                            'Name' => 'resultado_' . $documento['sc'] . '.pdf',
                            'ContentType' => 'application/pdf',
                            'Content' => $_file,
                        );

                        $documento['statusEnvio'] = 2;
                        $this->documento = $documento;

                    }

                }

            }

        } catch (SoapFault $e) {

            $this->wsLab_LOGOUT();

            $documento['logsEnvio'][] = array(
                'timestampLog' => date('Y-m-d H:i:s'),
                'log' => 'No existe el documento solicitado.',
            );

            $this->documento = $documento;

        } catch (ModelsException $b) {

            $documento['logsEnvio'][] = array(
                'timestampLog' => date('Y-m-d H:i:s'),
                'log' => 'No existe el documento solicitado.',
            );

            $this->documento = $documento;
        }

    }

    public function generarCorreosElectronicosPaciente()
    {

        # Extraer Datos de Paciente
        $documento = $this->documento;

        $extraerDatosPaciente = false;

        if (count($documento['correosElectronicos']) !== 0) {

            foreach ($documento['correosElectronicos'] as $key => $val) {
                if ($val['dirrecciones'] == 1) {
                    $extraerDatosPaciente = true;
                }
            }

            if ($extraerDatosPaciente) {
                $this->extraerDatosPaciente();
            }

        }

    }

    public function agregarCorreoEnvio($correo)
    {

        $existe = false;

        if (count($this->documento['correosElectronicos']) !== 0) {

            foreach ($this->documento['correosElectronicos'] as $key => $val) {

                if ($val['dirrecciones'] == $correo) {
                    $existe = true;
                }

            }
        }

        return $existe;

    }

    public function extraerDatosPaciente()
    {

        # Extraer Datos de Paciente
        $documento = $this->documento;

        $_sc = $documento['sc'];

        $sc = (int) $_sc;

        if ($sc > 22000000) {
            $nhc = $documento['numeroHistoriaClinica'] . '01';
        } else {
            $nhc = $documento['numeroHistoriaClinica'];
        }

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

                $this->documento = $documento;

            } else {

                $pos = strpos($getCorreos, '|');

                # Solo un correo
                if ($pos === false) {

                    $correoPaciente = $getCorreos;

                    $existeCorreo = $this->agregarCorreoEnvio($correoPaciente);

                    if (!$existeCorreo) {

                        $documento['correosElectronicos'][] = array(
                            'dirrecciones' => $correoPaciente,
                        );

                        $documento['statusEnvio'] = 1;

                        $this->documento = $documento;

                    }

                } else {

                    $_correosPacientes = explode('|', $getCorreos);

                    foreach ($_correosPacientes as $key => $val) {

                        $existeCorreo = $this->agregarCorreoEnvio($val);

                        if (!$existeCorreo) {

                            $documento['correosElectronicos'][] = array(
                                'dirrecciones' => $val,
                            );

                        }

                    }

                    $documento['statusEnvio'] = 1;

                    $this->documento = $documento;

                }

            }

        }

    }

    # Metodo LOGIN webservice laboratorio ROCHE
    public function wsLab_LOGIN()
    {

        try {

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'zdk.ws.wSessions.wsdl.xml');

            $Login = $client->Login(array(
                "pstrUserName" => "CONSULTA",
                "pstrPassword" => "CONSULTA1",
            ));

            # Guaradar  KEY de session WS
            $this->pstrSessionKey = $Login->LoginResult;

            # Retorna KEY de session WS
            return $Login->LoginResult;

        } catch (SoapFault $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }

    }

    public function wsLab_LOGIN_PCR()
    {

        try {

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'zdk.ws.wSessions.wsdl.xml');

            $Login = $client->Login(array(
                "pstrUserName" => "CWMETRO",
                "pstrPassword" => "CWM3TR0",
            ));

            # Guaradar  KEY de session WS
            $this->pstrSessionKey = $Login->LoginResult;

            # Retorna KEY de session WS
            # return $Login->LoginResult;

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

            $Logout = $client->Logout(array(
                "pstrSessionKey" => $this->pstrSessionKey,
            ));

            return $Logout->LogoutResult;

        } catch (SoapFault $e) {
            return array('success' => false, 'message' => $e->getMessage());
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
