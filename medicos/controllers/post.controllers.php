<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use app\models\medicos as TModel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auntenticar api del hospital modelo LOGIN function auth_Api
 *
 * @return json
 */

$app->post('/testjwt', function () use ($app) {
    global $http;
    $u = new TModel\Login;
    return $app->json($u->getDataTokenMS($http->headers->get("Authorization")));
});

$app->post('/mis-facturas-pagadas', function () use ($app) {
    $u = new TModel\Honorarios;
    return $app->json($u->getMisFacturasPagadas());
});

$app->post('/mis-facturas-pendientes', function () use ($app) {
    $u = new TModel\Honorarios;
    return $app->json($u->getMisFacturasPendientes());
});

$app->post('/mis-transferencias', function () use ($app) {
    $u = new TModel\Honorarios;
    return $app->json($u->getMisTrxRealizadas());
});

$app->post('/honorarios/auditados', function () use ($app) {
    $u = new TModel\Honorarios;
    return $app->json($u->getTodosMisHonorarios());
});

$app->post('/honorarios/detalle-auditados', function () use ($app) {
    $u = new TModel\Honorarios;
    return $app->json($u->getDetalleHonorarioAuditado());
});

$app->post('/agenda/crear-cita', function () use ($app) {
    $u = new TModel\AgendaMV;
    return $app->json($u->crearCita());
});

$app->post('/reportes', function () use ($app) {
    $u = new TModel\Pacientes;
    return $app->json($u->getReportesPEP());
});

$app->post('/buscar-paciente', function () use ($app) {
    $u = new TModel\Pacientes;
    return $app->json($u->buscarPaciente());
});

$app->post('/save/log/formulario', function () use ($app) {
    $u = new TModel\Formularios;
    return $app->json($u->nuevoRegistro());
});

$app->post('/send-pedido-lab/{idPedido}', function ($idPedido) use ($app) {
    $u = new TModel\Notificaciones;
    return $app->json($u->registroPedido($idPedido));
});

$app->post('/send-pedido-eme-lab/{idPedido}', function ($idPedido) use ($app) {
    $u = new TModel\Notificaciones;
    return $app->json($u->registroPedidoEme($idPedido));
});

$app->post('/up-pedido-eme-lab/{idPedido}', function ($idPedido) use ($app) {
    $u = new TModel\Notificaciones;
    return $app->json($u->actualizarPedidoEme($idPedido));
});

$app->post('/up-pedido-lab/{idPedido}', function ($idPedido) use ($app) {
    $u = new TModel\Notificaciones;
    return $app->json($u->actualizarPedido($idPedido));
});

$app->post('/noti-eme/{idPedido}', function ($idPedido) use ($app) {
    $u = new TModel\Notificaciones;
    return $app->json($u->notiPedidoEme($idPedido));
});

$app->post('/message-pedido/{idPedido}', function ($idPedido) use ($app) {
    $u = new TModel\Notificaciones;
    return $app->json($u->messagePedido($idPedido));
});

$app->post('/auth2', function () use ($app) {
    $u = new TModel\ActiveDirectoryHM;
    return $app->json($u->busquedaUsuario());
});

$app->post('/auth', function () use ($app) {
    $u = new TModel\Login;
    return $app->json($u->login_Api());
});

$app->post('/vruc', function () use ($app) {
    $u = new TModel\Login;
    return $app->json($u->validarRUC());
});

$app->post('/recovery', function () use ($app) {
    $u = new TModel\Login;
    return $app->json($u->recoveryPass());
});

$app->post('/lostpass', function () use ($app) {
    $u = new TModel\Login;
    return $app->json($u->lostPass());
});

$app->post('/token', function () use ($app) {
    $u = new TModel\Login;
    return $app->json($u->verificarTokenLostPass());
});

$app->post('/register', function () use ($app) {
    $u = new TModel\Login;
    return $app->json($u->register());
});

$app->post('/verify', function () use ($app) {
    $u = new TModel\Login;
    return $app->json($u->verifyRegister());
});

# Metrovirtual para Medicos
$app->post('/status-paciente-emergencia', function () use ($app) {
    $u = new TModel\Emergencia;
    return $app->json($u->getStatusPaciente_Emergencia());
});

$app->post('/sv-paciente-emergencia', function () use ($app) {
    $u = new TModel\Emergencia;
    return $app->json($u->getSVPaciente_Emergencia());
});

$app->post('/ev-paciente-emergencia', function () use ($app) {
    $u = new TModel\Emergencia;
    return $app->json($u->getFormularios_MV_005());
});

$app->post('/pacientes-admisiones', function () use ($app) {
    $m = new TModel\Admisiones;
    return $app->json($m->buscarPaciente());
});

$app->post('/integracion-higienizacion', function () use ($app) {
    $m = new TModel\Admisiones;
    return $app->json($m->buscarPaciente());
});

$app->post('/status-pedido-lab', function () use ($app) {
    global $config, $http;

    $m = new TModel\Laboratorio;

    return $app->json($m->getStatusPedidoLabDetalle($http->request->get('numeroPedido')));
});

$app->post('/up-status-pedido-lab', function () use ($app) {
    global $config, $http;

    $m = new TModel\Laboratorio;
    return $app->json($m->updateTomaMuestraPedido());
});

$app->post('/status-receta', function () use ($app) {
    global $config, $http;

    $m = new TModel\Farmacia;

    return $app->json($m->getStatusRecetaDetalle($http->request->get('numeroReceta')));
});

$app->post('/pedidos/nuevo-pedido', function () use ($app) {

    $xml = file_get_contents('php://input');

    $data = utf8_encode($xml);
    // Extract XML Dcoument
    $dataPedido = simplexml_load_string($data);
    $pedido = $dataPedido->children('soap', true)->Body->children();
    $codigoPedido = $pedido->Mensagem->PedidoExameLab->codigoPedido;
    $nomeMaquina = $pedido->Mensagem->PedidoExameLab->atendimento->nomeMaquina;
    $usuarioSolicitante = $pedido->Mensagem->PedidoExameLab->atendimento->usuarioSolicitante;
    $strSolicitante = $pedido->Mensagem->PedidoExameLab->descSetorSolicitante;
    $operacion = $pedido->Mensagem->PedidoExameLab->operacao;

    $fechaTomaMuestra = $pedido->Mensagem->PedidoExameLab->atendimento->dataColetaPedido;
    $fechaPedido = date("Y-m-d", strtotime($fechaTomaMuestra));

    $fileIngresadas = 'lisa/pedidos/ingresados/' . $codigoPedido . '.xml';

    if ($usuarioSolicitante == 'MYANEZ') {

        $new_xml = str_replace($nomeMaquina, 'CAJA1', $xml);

        file_put_contents($fileIngresadas, $new_xml);
        chmod($fileIngresadas, 0777);

    } else if ($usuarioSolicitante == 'TVELEZ') {

        $new_xml = str_replace($nomeMaquina, 'CAJA2', $xml);

        file_put_contents($fileIngresadas, $new_xml);
        chmod($fileIngresadas, 0777);

    } else if ($usuarioSolicitante == 'MMARTINEZ') {

        $new_xml = str_replace($nomeMaquina, 'CAJA5', $xml);

        file_put_contents($fileIngresadas, $new_xml);
        chmod($fileIngresadas, 0777);

    } else if ($usuarioSolicitante == 'mTVELEZ') {

        $new_xml = str_replace($nomeMaquina, 'CAJA4', $xml);

        file_put_contents($fileIngresadas, $new_xml);
        chmod($fileIngresadas, 0777);

    } else {

        if ($strSolicitante == 'EMERGENCIA' || strpos($strSolicitante, 'HOSPITALIZACION') !== false) {
            $new_xml = str_replace($nomeMaquina, 'CAJA4', $xml);
        } else {
            $new_xml = $xml;
        }

        file_put_contents($fileIngresadas, $new_xml);
        chmod($fileIngresadas, 0777);

    }

    if ($operacion == 'E') {

        $webservice_url = "http://172.16.253.17:8084/mv-api-hl7bus/proxySaidaMLLP";

        $headers = array(
            'Content-Type: text/xml; charset=utf-8',
            'Content-Length: ' . strlen($new_xml),
        );

        $ch = curl_init($webservice_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $new_xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {

            $fileEnviados = 'lisa/pedidos/enviados/' . $codigoPedido . '.xml';
            file_put_contents($fileEnviados, $new_xml);
            $logsfileEnviados = 'lisa/pedidos/enviados/log_success_' . $codigoPedido . '.xml';
            file_put_contents($logsfileEnviados, $data);

        } else {

            $logsfileEnviados = 'lisa/pedidos/enviados/log_error_' . $codigoPedido . '.xml';
            file_put_contents($logsfileEnviados, $data);

        }

    } else {

        if ($strSolicitante == 'EMERGENCIA' || strpos($strSolicitante, 'HOSPITALIZACION') !== false) {

            if ($strSolicitante == 'EMERGENCIA') {
                $fileRetenidos = 'lisa/pedidos/retenidos/sector/emergencia/' . $codigoPedido . '.xml';
                file_put_contents($fileRetenidos, $new_xml);
            }

            if (strpos($strSolicitante, 'HOSPITALIZACION') !== false) {
                $fileRetenidos = 'lisa/pedidos/retenidos/sector/hospitalizacion/' . $codigoPedido . '.xml';
                file_put_contents($fileRetenidos, $new_xml);
            }

        } else {

            # Registro envío de Log
            if ($fechaPedido == date('Y-m-d')) {

                $webservice_url = "http://172.16.253.17:8084/mv-api-hl7bus/proxySaidaMLLP";

                $headers = array(
                    'Content-Type: text/xml; charset=utf-8',
                    'Content-Length: ' . strlen($new_xml),
                );

                $ch = curl_init($webservice_url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $new_xml);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $data = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code == 200) {

                    $fileEnviados = 'lisa/pedidos/enviados/' . $codigoPedido . '.xml';
                    file_put_contents($fileEnviados, $new_xml);
                    $logsfileEnviados = 'lisa/pedidos/enviados/log_success_' . $codigoPedido . '.xml';
                    file_put_contents($logsfileEnviados, $data);

                } else {

                    $logsfileEnviados = 'lisa/pedidos/enviados/log_error_' . $codigoPedido . '.xml';
                    file_put_contents($logsfileEnviados, $data);

                }

            } else {

                $fileRetenidos = 'lisa/pedidos/retenidos/' . $codigoPedido . '.xml';
                file_put_contents($fileRetenidos, $new_xml);

            }

        }

    }

    $output = '<?xml version="1.0"?><Mensagem><sucesso><descricao>OPERACAO REALIZADA COM SUCESSO</descricao></sucesso></Mensagem>';

    return new Response(
        $output,
        200,
        array('Content-Type' => 'application/xml')
    );

});