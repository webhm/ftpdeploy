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
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\DeviceParserAbstract;
use Doctrine\DBAL\DriverManager;
use Firebase\JWT\JWT;
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Users
 */
class Auth extends Models implements IModels
{

    /**
     * Variables privadas
     * @return void
     */

    private $secret_key = 'hdSunv8NPTA7evYI7gY$etcqvKk4^XUhWU*bldvdlpOUG@PffH_hm_api_v1';
    private $encrypt = ['HS256'];
    private $aud = null;
    private $USER = null;
    private $_conexion = null;
    private $os_name = null;
    private $os_version = null;
    private $client_type = null;
    private $client_name = null;
    private $device = null;

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();

        //..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

    }

    public function generateKey($data)
    {

        $time = time();

        $data['user_token'] = true;

        $token = array(
            'exp' => strtotime('+1 week', $time),
            // 'exp'  => $time + (60 * 60),
            // 'exp'  => $time--,
            'aud' => $this->Aud(),
            'data' => $data,
        );

        $app_token = Helper\Strings::ocrend_encode(http_build_query($token), $this->secret_key);

        unset($data['CP_PTE']);
        unset($data['CP_MED']);
        unset($data['CP_PRO']);
        unset($data['DNI']);
        unset($data['COD_PERSONA']);
        unset($data['user_token']);

        # SETEAR VALORES DE RESPUESTA
        return array(
            'status' => true,
            'data' => $data,
            'user_token' => JWT::encode($token, $this->secret_key),
            'app_token' => $app_token,
            // 'datos'  => $this->GetData(JWT::encode($token, $this->secret_key)),

        );
    }

    public function generateKeyAnalytics($data)
    {

        $time = time();

        $token = array(
            'exp' => strtotime('+1 day', $time),
            //'exp'  => $time--,
            'aud' => $this->Aud(),
            'data' => $data,
        );

        return array(
            'status' => true,
            'token' => JWT::encode($token, $this->secret_key),
            // 'datos'  => $this->GetData(JWT::encode($token, $this->secret_key)),

        );
    }

    public function regenerateUser_Token($data)
    {

        try {

            if (Helper\Functions::e($data['USER_TOKEN'], $data['APP_TOKEN'])) {
                throw new ModelsException('¡Error! No estan definidos todos los parametros.', 4001);
            }

            # Decodificar app_token
            $app_token = Helper\Strings::ocrend_decode($data['APP_TOKEN'], $this->secret_key);

            $user_token = $data['USER_TOKEN'];

            # "Decodificar valores"
            $data = explode('&', urldecode($app_token));

            # array de datos
            $data_token = array();

            foreach ($data as $key => $value) {
                $val = explode('=', $value);
                $data_token[$val[0]] = $val[1];
            }

            if (time() > $data_token['exp']) {

                # generar nuesvos tokens
                $user_token = (array) $this->GetData($user_token);

                $new_token = $this->generateKey($user_token);

                return $new_token;

            }

            # throw new ModelsException("user_token no expired. " . $time . '-----' . $data_USER_TOKEN_expired->exp);
            throw new ModelsException("user_token no expired. ", 4030);

        } catch (ModelsException $e) {
            // Errores del modelo
            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    public function verifyToken($token)
    {

        if (empty($token)) {
            return true;
        }

        return false;

    }

    public function Check($token)
    {

        try {

            if (empty($token)) {
                # "Invalid token supplied."
                throw new ModelsException("Invalid token supplied.", 4032);
            }

            $decode = JWT::decode(
                $token,
                $this->secret_key,
                $this->encrypt
            );

            $data = JWT::decode(
                $token,
                $this->secret_key,
                $this->encrypt
            )->data;

            if ($decode->aud !== $this->Aud()) {
                throw new ModelsException("Invalid user logged in.", 4033);
            }

            if (!isset($data->user_token)) {
                throw new ModelsException("Invalid user_token.", 4032);
            }

            # Auditoria metodos y logs de endpoint y recursos compartido
            # $this->insertLogUser();

            return false;

        } catch (ModelsException $e) {
            // Errores del modelo
            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        } catch (\Exception $b) {

            if ($b->getMessage() == 'Expired token') {
                return array('status' => false, 'message' => $b->getMessage(), 'errorCode' => 4031);
            }

            // Errores de JWT > para todos los demas errores token invalido
            return array('status' => false, 'message' => $b->getMessage(), 'errorCode' => 4032);

        }

    }

    public function GetData($token)
    {

        try {

            $data = JWT::decode(
                $token,
                $this->secret_key,
                $this->encrypt
            )->data;
            return $data;

        } catch (\Exception $b) {

            if ($b->getMessage() == 'Algorithm not allowed') {

                $auth = new Model\Login;
                $key = $auth->getDataTokenMS($token);

                if (isset($key['id'])) {
                    $object = json_decode(json_encode($key), false);
                    return $object;
                } else {
                    return $b->getMessage();
                }

            } else {

                return $b->getMessage();
            }

        }

    }

    public function Aud()
    {
        $aud = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $aud = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $aud = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $aud = $_SERVER['REMOTE_ADDR'];
        }

        $aud .= @$_SERVER['HTTP_USER_AGENT'];
        $aud .= gethostname();

        return sha1($aud);
    }

    public function IpClient()
    {
        $aud = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $aud = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $aud = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $aud = $_SERVER['REMOTE_ADDR'];
        } else {
            $aud = 'NOT HTTP CLIENT';
        }

        return $aud;
    }

    private function getAuthorizationn()
    {

        try {

            global $http;

            $token = $http->headers->get("Authorization");

            $key = $this->GetData($token);

            $this->USER = $key;

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function insertLogUser()
    {

        global $http;

        if ($http->getMethod() != 'OPTIONS') {

            # Get terminal
            $this->getTerminal();

            # recurso
            $recurso = explode('/', $http->getPathInfo())[1];

            # Extraer datos
            $this->getAuthorizationn();

            # Conectar base de datos
            $this->conectar_Oracle();

            # Conectar base de datos
            $this->setTimeOracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Query USERues
            $queryBuilder
                ->insert('ANALITICAS_USUARIOS_WEB')
                ->values(
                    array(
                        'codigo_persona' => '?',
                        'perfil' => '?',
                        'recurso' => '?',
                        'fecha' => '?',
                        'hora' => '?',
                        'os_name' => '?',
                        'os_version' => '?',
                        'device' => '?',
                        'client_name' => '?',
                        'client_type' => '?',
                    )
                )
                ->setParameter(0, $this->USER->COD_PERSONA)
                ->setParameter(1, $this->USER->PTE . ',' . $this->USER->MED . ',' . $this->USER->PRO)
                ->setParameter(2, $recurso)
                ->setParameter(3, date('d-m-Y H:i:s'))
                ->setParameter(4, date('d-m-Y H:i:s'))
                ->setParameter(5, $this->os_name)
                ->setParameter(6, $this->os_version)
                ->setParameter(7, $this->device)
                ->setParameter(8, $this->client_name)
                ->setParameter(9, $this->client_type)

            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

        }

    }

    private function setTimeOracle()
    {

        $sql = "alter session set NLS_DATE_FORMAT='DD-MM-YYYY HH24:MI:SS'";
        # Execute
        $stmt = $this->_conexion->query($sql);

    }

    public function getTerminal()
    {

        global $http;

        $userAgent = $http->headers->get('User-Agent');

        DeviceParserAbstract::setVersionTruncation(DeviceParserAbstract::VERSION_TRUNCATION_NONE);

        $dd = new DeviceDetector($userAgent);

        # Prsear informacion

        $dd->parse();

        # Resultado
        if ($dd->isBot()) {

            $this->os_name = '';
            $this->os_version = '';
            $this->client_name = '';
            $this->client_type = '';
            $this->device = 'Bot';

        } elseif (is_null($dd->getClient()['name'])) {

            $this->os_name = '';
            $this->os_version = '';
            $this->client_name = '';
            $this->client_type = '';
            $this->device = 'Bot';

        } else {

            $this->os_name = $dd->getOs()['name'];
            $this->os_version = $dd->getOs()['version'];
            $this->client_name = $dd->getClient()['name'];
            $this->client_type = $dd->getClient()['type'];
            $this->device = $dd->getDeviceName();

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
