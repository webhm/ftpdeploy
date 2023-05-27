<?php

/*
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
 * Modelo MetroredPedidos
 */
class MetroredPedidos extends Models implements IModels
{

    private $numeroHistoriaClinica = null;
    private $numeroCodigo = null;
    private $pedidos = false;
    private $urlApi = 'http://recetas.hmetro.med.ec';
    private $remitente = 'metrored.pedidos@hospitalmetropolitano.org';

    /**
     * Obtiene datos de Pedidos para envio a farmacias de Metrored
     *
     * @return array : Con información de éxito/falla al terminar el proceso.
     */
    public function taskSendPedidos()
    {
        try {

            $sql = " SELECT * FROM ENVIO_MAIL_METRORED  WHERE ESTADO='GEN' AND PK_TIPO='2'   ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # CERRAR CONEXION
            $this->_conexion->close();

            # VERIFICAR RESULTADOS
            $data = $stmt->fetchAll();

            #  return $data;

            if (false === $data) {

                return false;

            } else {

                $this->pedidos = $data;

                $taskEnvioPedidos = $this->taskSendMailsPedidos();

                return $taskEnvioPedidos;

            }

        } catch (ModelsException $e) {

            return $e;

        }
    }

    private function actualizarRegistroMetrored()
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        # Set españish language oracle Citas Agendadas
        $this->setSpanishOracle();

        # QueryBuilder
        $queryBuilder = $this->_conexion->createQueryBuilder();

        # QUERY PARA INSERTAR
        $queryBuilder
            ->update('ENVIO_MAIL_METRORED', 'u')
            ->set('u.ESTADO', '?')
            ->where("u.PK_HC = ?")
            ->andWhere("u.PK_CODIGO = ?")
            ->setParameter(0, 'ENV')
            ->setParameter(1, $this->numeroHistoriaClinica)
            ->setParameter(2, $this->numeroCodigo)
        ;

        # EXECUTE
        $stmt = $queryBuilder->execute();

        $this->_conexion->close();

        # return true;

    }

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();

        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA NMETROAMB
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle_metrored'], $_config);

    }

    private function setSpanishOracle()
    {

        $sql = "alter session set NLS_LANGUAGE = 'SPANISH'";
        # Execute
        $stmt = $this->_conexion->query($sql);

        $sql = "alter session set NLS_TERRITORY = 'SPAIN'";
        # Execute
        $stmt = $this->_conexion->query($sql);

        $sql = " alter session set NLS_DATE_FORMAT = 'DD/MM/YYYY HH24:MI' ";
        # Execute
        $stmt = $this->_conexion->query($sql);

    }

    public function taskSendMailsPedidos()
    {
        global $config;

        if (false === $this->pedidos) {
            return false;
        }

        $logs = array();

        $i = 0;

        // Establecer condicional de envio

        foreach ($this->pedidos as $key) {

            // Pedido de laboratorio e imagen envia dos enlaces al paciente
            if ($key['RUTA'] !== 'NO ENVIAR' && $key['RUTA2'] !== 'NO ENVIAR') {

                $key['pdfUrl1'] = str_replace("face", "recetas", $key['RUTA']);

                $key['pdfUrl2'] = str_replace("face", "recetas", $key['RUTA2']);

                # Enviar el correo electrónico
                $_html = Helper\Emails::loadTemplate(array(
                    # Título del mensaje
                    '{{title}}' => 'Pedido Metrored',
                    # Contenido del mensaje
                    '{{content}}' => 'Estimad@ Paciente <br/>Enviamos a usted datos informativos de su última consulta médica en Metrored.<br/>Se ha recibido un nuevo pedido desde Metrored.<br/>Fecha (dd/mm/yyyy): ' . $key['TEXTO_FECHA'] . '<br/>Centro: ' . $key['TEXTO_LOCALIZACION'] . '.<br/>' . $key['CUERPO_MENSAJE'],
                    # Url del botón 1
                    '{{btn-href1}}' => $this->urlApi . $key['pdfUrl1'],
                    # Texto del boton
                    '{{btn-name1}}' => 'Descargar Pedido Imagen',
                    # Url del botón 2
                    '{{btn-href2}}' => $this->urlApi . $key['pdfUrl2'],
                    # Texto del boton 2
                    '{{btn-name2}}' => 'Descargar Pedido Laboratorio',
                    # Copyright
                    '{{copyright}}' => '&copy; ' . date('Y') . ' <a href="http://www.metrored.med.ec/">Metrored CENTROS MÉDICOS</a> Todos los derechos reservados.',
                ), 3);

                $sendMail = $this->sendMail($_html, $key['DESTINATARIO'], $key['ASUNTO']);

                if ($sendMail->ErrorCode == 0) {

                    # Actualizar la tarea en bdd
                    $this->numeroHistoriaClinica = $key['PK_HC'];
                    $this->numeroCodigo = $key['PK_CODIGO'];
                    $this->actualizarRegistroMetrored();

                    $logs[] = array(
                        'id' => $key['PK_HC'],
                        'logs' => $sendMail,
                    );

                } else {

                    $logs[] = array(
                        'id' => $key['PK_HC'],
                        'logs' => $sendMail,
                    );
                }

                // Envia solo pedido de imagen
            } else if ($key['RUTA'] !== 'NO ENVIAR') {

                $key['pdfUrl1'] = str_replace("face", "recetas", $key['RUTA']);

                # Enviar el correo electrónico
                $_html = Helper\Emails::loadTemplate(array(
                    # Título del mensaje
                    '{{title}}' => 'Pedido Metrored',
                    # Contenido del mensaje
                    '{{content}}' => 'Estimad@ Paciente <br/>Enviamos a usted datos informativos de su última consulta médica en Metrored.<br/>Se ha recibido un nuevo pedido desde Metrored.<br/>Fecha (dd/mm/yyyy): ' . $key['TEXTO_FECHA'] . '<br/>Centro: ' . $key['TEXTO_LOCALIZACION'] . '.<br/>' . $key['CUERPO_MENSAJE'],
                    # Url del botón 1
                    '{{btn-href1}}' => $this->urlApi . $key['pdfUrl1'],
                    # Texto del boton
                    '{{btn-name1}}' => 'Descargar Pedido Imagen',
                    # Copyright
                    '{{copyright}}' => '&copy; ' . date('Y') . ' <a href="http://www.metrored.med.ec/">Metrored CENTROS MÉDICOS</a> Todos los derechos reservados.',
                ), 4);

                $sendMail = $this->sendMail($_html, $key['DESTINATARIO'], $key['ASUNTO']);

                if ($sendMail->ErrorCode == 0) {

                    # Actualizar la tarea en bdd
                    $this->numeroHistoriaClinica = $key['PK_HC'];
                    $this->numeroCodigo = $key['PK_CODIGO'];
                    $this->actualizarRegistroMetrored();

                    $logs[] = array(
                        'id' => $key['PK_HC'],
                        'logs' => $sendMail,
                    );

                } else {

                    $logs[] = array(
                        'id' => $key['PK_HC'],
                        'logs' => $sendMail,
                    );
                }

                // Envia solo pedido de laboratorio
            } else if ($key['RUTA2'] !== 'NO ENVIAR') {

                $key['pdfUrl2'] = str_replace("face", "recetas", $key['RUTA2']);

                # Enviar el correo electrónico
                $_html = Helper\Emails::loadTemplate(array(
                    # Título del mensaje
                    '{{title}}' => 'Pedido Metrored',
                    # Contenido del mensaje
                    '{{content}}' => 'Estimad@ Paciente <br/>Enviamos a usted datos informativos de su última consulta médica en Metrored.<br/>Se ha recibido un nuevo pedido desde Metrored.<br/>Fecha (dd/mm/yyyy): ' . $key['TEXTO_FECHA'] . '<br/>Centro: ' . $key['TEXTO_LOCALIZACION'] . '.<br/>' . $key['CUERPO_MENSAJE'],
                    # Url del botón 1
                    '{{btn-href1}}' => $this->urlApi . $key['pdfUrl2'],
                    # Texto del boton
                    '{{btn-name1}}' => 'Descargar Pedido Laboratorio',
                    # Copyright
                    '{{copyright}}' => '&copy; ' . date('Y') . ' <a href="http://www.metrored.med.ec/">Metrored CENTROS MÉDICOS</a> Todos los derechos reservados.',
                ), 5);

                $sendMail = $this->sendMail($_html, $key['DESTINATARIO'], $key['ASUNTO']);

                if ($sendMail->ErrorCode == 0) {

                    # Actualizar la tarea en bdd
                    $this->numeroHistoriaClinica = $key['PK_HC'];
                    $this->numeroCodigo = $key['PK_CODIGO'];
                    $this->actualizarRegistroMetrored();

                    $logs[] = array(
                        'id' => $key['PK_HC'],
                        'logs' => $sendMail,
                    );

                } else {

                    $logs[] = array(
                        'id' => $key['PK_HC'],
                        'logs' => $sendMail,
                    );
                }

                //Envía solo texto, no enlaces de pedidos
            } else {

                # Enviar el correo electrónico
                $_html = Helper\Emails::loadTemplate(array(
                    # Título del mensaje
                    '{{title}}' => 'Metrored',
                    # Contenido del mensaje
                    '{{content}}' => 'Fecha (dd/mm/yyyy): ' . $key['TEXTO_FECHA'] . '<br/>Centro: ' . $key['TEXTO_LOCALIZACION'] . '.<br/>' . $key['CUERPO_MENSAJE'],
                    # Copyright
                    '{{copyright}}' => '&copy; ' . date('Y') . ' <a href="http://www.metrored.med.ec/">Metrored CENTROS MÉDICOS</a> Todos los derechos reservados.',
                ), 6);

                $sendMail = $this->sendMail($_html, $key['DESTINATARIO'], $key['ASUNTO']);

                if ($sendMail->ErrorCode == 0) {

                    # Actualizar la tarea en bdd
                    $this->numeroHistoriaClinica = $key['PK_HC'];
                    $this->numeroCodigo = $key['PK_CODIGO'];
                    $this->actualizarRegistroMetrored();

                    $logs[] = array(
                        'id' => $key['PK_HC'],
                        'logs' => $sendMail,
                    );

                } else {

                    $logs[] = array(
                        'id' => $key['PK_HC'],
                        'logs' => $sendMail,
                    );
                }

            }

            $i++;

        }

        return $logs;

    }

    public function sendMail($htmlBody = '', $to = 'mchang@hmetro.med.ec', $subject = 'Nueva Receta Metrored')
    {

        global $config;

        $stringData = array(
            'TextBody' => '',
            'From' => 'Metrored Pedidos ' . $this->remitente,
            'To' => $to,
            'Subject' => $subject,
            'HtmlBody' => $htmlBody,
        );

        $data = json_encode($stringData, JSON_UNESCAPED_UNICODE);

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
            'X-Postmark-Server-Token: ' . $config['mailer']['user'])
        );

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $resultobj = curl_error($ch);
        }
        curl_close($ch);
        $resultobj = json_decode($result);

        return $resultobj;

    }

/**
 * __construct()
 */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);

    }
}
