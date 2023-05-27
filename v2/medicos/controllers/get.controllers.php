<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use app\models\medicos as Model;
use Ocrend\Kernel\Helpers as Helper;

$app->get('/', function () use ($app) {
    return $app->json();
});

# Resultados Imagen
$app->get('/resultado/imagen', function () use ($app) {
    $m = new Model\Imagen;
    return $app->json($m->getResultado());
});

# Resultados Lab
$app->get('/resultado/lab', function () use ($app) {
    $m = new Model\Laboratorio;
    return $app->json($m->getResultado());
});

# Api Agenda MV
$app->get('/mi-agenda', function () use ($app) {
    $u = new Model\AgendaMV;
    return $app->json($u->getAgenda());
});

$app->get('/mi-agenda/cita', function () use ($app) {
    $u = new Model\AgendaMV;
    return $app->json($u->getCita());
});

$app->get('/mis-pacientes', function () use ($app) {
    $u = new Model\Pacientes;
    return $app->json($u->getMisPacientes());
});

$app->get('/estado-cuenta', function () use ($app) {
    return $app->json();
});

$app->get('/mis-resultados', function () use ($app) {
    $u = new Model\Pacientes;
    return $app->json($u->getResultadosPacientes());
});

$app->get('/formulario', function () use ($app) {
    $m = new Model\Formularios;
    return $app->json($m->getFormulario());
});

# Genera un Documento Formulario de MV
$app->get('/formulario/p/', function () use ($app) {

    global $http;

    $id = Helper\Strings::ocrend_decode($http->query->get('id'), 'hm');

    $u = new Model\Formularios;

    $m = $u->getFormularioMV($id);

    if ($m['status']) {

        return $app->json(
            array(
                'status' => true,
                'url' => $m['data'],
            )
        );
    } else {
        // return new Response('', 404);
        return $app->json($m);
    }
});

$app->get('/f/{filename}', function ($filename) use ($app) {

    global $http;

    $http->headers->set('Access-Control-Allow-Origin', '*');

    $_file = 'docs/formularios/' . $filename;
    $doc = file_exists($_file);

    # si eiste documento renderizar elemtno
    if ($doc) {

        return $app
            ->sendFile($_file)
            ->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                basename($_file)
            );
    }

    return new Response('No existe el documento solicitado o no esta disponible. DOC: ' . $filename, 404);
});

# Analiticas Reporte Medicos

$app->get('/analiticas', function () use ($app) {
    $m = new Model\Logs;
    return $app->json($m->getLogs());
});

$app->get('/update-logs', function () use ($app) {
    $m = new Model\Logs;
    return $app->json($m->updateLogs());
});

$app->get('/analiticas/recursos', function () use ($app) {
    $m = new Model\Logs;
    return $app->json($m->getLogsRecursos());
});
