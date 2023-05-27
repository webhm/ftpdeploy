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
 * Modelo Notificaciones
 */
class Notificaciones extends Models implements IModels
{
    use DBModel;

    public function getNotificacionesPedidos()
    {
        try {
            global $http;

            # Obtener los datos $_POST
            $dataPedido = $http->request->get('dataPedido');

            $data = $this->db->select('*', 'noti_push_laboratorio', "ORDER BY id DESC", null, 4);

            if (false === $data) {
                return array('status' => false, 'data' => [], 'message' => 'No existe notificaciones');
            }

            return array('status' => true, 'data' => $data, 'message' => 'Todas las notificaciones.');

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

        }
    }

    public function getNotificacionesPedido($idPedido)
    {
        try {
            global $http;

            # Obtener los datos $_POST
            $dataPedido = $http->request->get('dataPedido');

            $data = $this->db->select('*', 'noti_push_laboratorio', null, "idPedido = '$idPedido' ORDER BY id DESC");

            if (false === $data) {
                return array('status' => false, 'data' => [], 'message' => 'No existe notificaciones');
            }

            return array('status' => true, 'data' => $data, 'message' => 'Todas las notificaciones.');

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

        }
    }

    public function getStatusPedidoLab($idPedido)
    {
        try {
            global $http;

            # Obtener los datos $_POST
            $dataPedido = $http->request->get('dataPedido');

            $data = $this->db->select('*', 'items_pedido_laboratorio', null, "idPedido = '$idPedido'");

            if (false === $data) {
                return array('status' => false, 'data' => [], 'message' => 'No existe registros');
            }

            return array('status' => true, 'data' => json_decode($data[0]['dataPedido'], true), 'statusPedido' => $data[0]['statusPedido'], 'message' => 'Todos los registros');

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

        }
    }

    public function registroPedidoEme($idPedido)
    {
        try {
            global $http;

            # Obtener los datos $_POST
            $dataPedido = $http->request->get('dataPedido');
            $statusPedido = $http->request->get('statusPedido');

            $data = $this->db->select('*', 'items_pedidos_eme_lab', null, " idPedido='$idPedido'  ", 1);

            if (!$data) {
                # Registrar al usuario
                $id_pedido = $this->db->insert('items_pedidos_eme_lab', array(
                    'idPedido' => $idPedido,
                    'dataPedido' => json_encode($dataPedido, JSON_UNESCAPED_UNICODE),
                    'statusPedido' => $statusPedido,
                    'timestamp' => time(),
                ));

                return array('status' => true, 'data' => $dataPedido, 'statusPedido' => (int) $statusPedido, 'message' => 'Registrado con éxito.');

            }

            return array('status' => true, 'data' => json_decode($data[0]['dataPedido'], true), 'statusPedido' => (int) $data[0]['statusPedido'], 'message' => 'Registrado con éxito.');

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage());

        }
    }

    public function registroPedido($idPedido)
    {
        try {
            global $http;

            # Obtener los datos $_POST
            $dataPedido = $http->request->get('dataPedido');
            $statusPedido = $http->request->get('statusPedido');

            $data = $this->db->select('*', 'items_pedido_laboratorio', null, " idPedido='$idPedido'  ", 1);

            if (!$data) {
                # Registrar al usuario
                $id_pedido = $this->db->insert('items_pedido_laboratorio', array(
                    'idPedido' => $idPedido,
                    'dataPedido' => json_encode($dataPedido, JSON_UNESCAPED_UNICODE),
                    'statusPedido' => $statusPedido,
                    'timestamp' => time(),
                ));

                return array('status' => true, 'data' => $dataPedido, 'statusPedido' => (int) $statusPedido, 'message' => 'Registrado con éxito.');

            }

            if ($statusPedido == 4) {
                return array('status' => true, 'data' => json_decode($data[0]['dataPedido'], true), 'statusPedido' => 4, 'message' => 'Registrado con éxito.');

            }

            return array('status' => true, 'data' => json_decode($data[0]['dataPedido'], true), 'statusPedido' => (int) $data[0]['statusPedido'], 'message' => 'Registrado con éxito.');

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage());

        }
    }

    public function messagePedido($idPedido)
    {
        try {
            global $http;

            # Obtener los datos $_POST
            $message = $http->request->get('message');
            $dataPedido = $http->request->get('dataPedido');

            $id_message = $this->db->insert('noti_push_laboratorio', array(
                'idPedido' => $idPedido,
                'module' => 'EME',
                'title' => "Nuevo Mensaje",
                'message' => $message,
                'dataPedido' => json_encode($dataPedido, JSON_UNESCAPED_UNICODE),
                'timestamp' => time(),
            ));

            $notPush = $this->sendProcessMessage_Push_Lab(array(
                'interests' => array("Metrovirtual"),
                'web' => array(
                    "notification" => array(
                        "title" => "MetroPlus - Nuevo Mensaje",
                        "body" => "Pedido: " . $idPedido . "\nMensaje: " . substr($message, 0, 10) . "...",
                        "icon" => "https://metroplus.hospitalmetropolitano.org/assets/favicon.ico",
                        "deep_link" => "https://metroplus.hospitalmetropolitano.org",
                    ),
                ),
            ));

            return array('status' => true, 'data' => [], 'message' => 'Registrado con éxito.');

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

        }
    }

    public function notiPedidoEme($idPedido)
    {
        try {
            global $http;

            # Obtener los datos $_POST
            $message = $http->request->get('message');
            $dataPedido = $http->request->get('dataPedido');

            $id_message = $this->db->insert('noti_push_laboratorio', array(
                'idPedido' => $idPedido,
                'module' => 'EME',
                'title' => "Muestra Pendiente",
                'message' => $message,
                'dataPedido' => json_encode($dataPedido, JSON_UNESCAPED_UNICODE),
                'timestamp' => time(),
            ));

            return array('status' => true, 'data' => [], 'message' => 'Registrado con éxito.');

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

        }
    }

    public function actualizarPedidoEme($idPedido)
    {
        try {
            global $http;

            # Obtener los datos $_POST
            $dataPedido = $http->request->get('dataPedido');
            $statusPedido = $http->request->get('statusPedido');

            $this->db->update('items_pedidos_eme_lab', array(
                'dataPedido' => json_encode($dataPedido, JSON_UNESCAPED_UNICODE),
                'statusPedido' => $statusPedido,
                'timestamp' => time(),
            ), " idPedido='$idPedido' ", 1);

            return array('status' => true, 'data' => $dataPedido, 'message' => 'Actualziado con éxito.');

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

        }
    }

    public function actualizarPedido($idPedido)
    {
        try {
            global $http;

            # Obtener los datos $_POST
            $dataPedido = $http->request->get('dataPedido');
            $statusPedido = $http->request->get('statusPedido');

            $this->db->update('items_pedido_laboratorio', array(
                'dataPedido' => json_encode($dataPedido, JSON_UNESCAPED_UNICODE),
                'statusPedido' => $statusPedido,
                'timestamp' => time(),
            ), " idPedido='$idPedido' ", 1);

            return array('status' => true, 'data' => $dataPedido, 'message' => 'Actualziado con éxito.');

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

        }
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

/**
 * __construct()
 */
    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
        $this->startDBConexion();
    }
}
