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
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Models\Traits\DBModel;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Users
 */
class Users extends Models implements IModels
{
    use DBModel;

    /**
     * Máximos intentos de inincio de sesión de un usuario
     *
     * @var int
     */
    const MAX_ATTEMPTS = 10;

    /**
     * Tiempo entre máximos intentos en segundos
     *
     * @var int
     */
    const MAX_ATTEMPTS_TIME = 300; # (300 => 5 minutos)

    /**
     * Log de intentos recientes con la forma 'email' => (int) intentos
     *
     * @var array
     */
    private $recentAttempts = array();

    /**
     * Hace un set() a la sesión login_user_recentAttempts con el valor actualizado.
     *
     * @return void
     */
    private function updateSessionAttempts()
    {
        global $session;

        $session->set('login_user_recentAttempts', $this->recentAttempts);
    }

    /**
     * Revisa si las contraseñas son iguales
     *
     * @param string $pass : Contraseña sin encriptar
     * @param string $pass_repeat : Contraseña repetida sin encriptar
     *
     * @throws ModelsException cuando las contraseñas no coinciden
     */
    private function checkPassMatch(string $pass, string $pass_repeat)
    {
        if ($pass != $pass_repeat) {
            throw new ModelsException('Las contraseñas no coinciden.');
        }
    }

    /**
     * Verifica el email introducido, tanto el formato como su existencia en el sistema
     *
     * @param string $email: Email del usuario
     *
     * @throws ModelsException en caso de que no tenga formato válido o ya exista
     */
    private function checkEmail(string $email)
    {
        # Formato de email
        if (!Helper\Strings::is_email($email)) {
            throw new ModelsException('El email no tiene un formato válido.');
        }
        # Existencia de email
        $email = $this->db->scape($email);
        $query = $this->db->select('id_user', 'users', null, "email='$email'", 1);
        if (false !== $query) {
            throw new ModelsException('El email introducido ya existe.');
        }
    }

    /**
     * Restaura los intentos de un usuario al iniciar sesión
     *
     * @param string $email: Email del usuario a restaurar
     *
     * @throws ModelsException cuando hay un error de lógica utilizando este método
     * @return void
     */
    private function restoreAttempts(string $email)
    {
        if (array_key_exists($email, $this->recentAttempts)) {
            $this->recentAttempts[$email]['attempts'] = 0;
            $this->recentAttempts[$email]['time']     = null;
            $this->updateSessionAttempts();
        } else {
            throw new ModelsException('Error lógico');
        }
    }

    /**
     * Genera la sesión con el id del usuario que ha iniciado
     *
     * @param array $user_data: Arreglo con información de la base de datos, del usuario
     *
     * @return void
     */
    private function generateSession(array $user_data)
    {
        global $session, $cookie, $config;

        # Generar un session hash
        $cookie->set('session_hash', md5(time()), $config['sessions']['user_cookie']['lifetime']);

        # Generar la sesión del usuario
        $session->set($cookie->get('session_hash') . '__user_id', (int) $user_data['id_user']);

        # Generar data encriptada para prolongar la sesión
        if ($config['sessions']['user_cookie']['enable']) {
            # Generar id encriptado
            $encrypt = Helper\Strings::ocrend_encode($user_data['id_user'], $config['sessions']['user_cookie']['key_encrypt']);

            # Generar cookies para prolongar la vida de la sesión
            $cookie->set('appsalt', Helper\Strings::hash($encrypt), $config['sessions']['user_cookie']['lifetime']);
            $cookie->set('appencrypt', $encrypt, $config['sessions']['user_cookie']['lifetime']);
        }
    }

    /**
     * Verifica en la base de datos, el email y contraseña ingresados por el usuario
     *
     * @param string $email: Email del usuario que intenta el login
     * @param string $pass: Contraseña sin encriptar del usuario que intenta el login
     *
     * @return bool true: Cuando el inicio de sesión es correcto
     *              false: Cuando el inicio de sesión no es correcto
     */
    private function authentication(string $email, string $pass): bool
    {
        $email = $this->db->scape($email);
        $query = $this->db->select('id_user,pass', 'users', null, "email='$email'", 1);

        # Incio de sesión con éxito
        if (false !== $query && Helper\Strings::chash($query[0]['pass'], $pass)) {

            # Restaurar intentos
            $this->restoreAttempts($email);

            # Generar la sesión
            $this->generateSession($query[0]);
            return true;
        }

        return false;
    }

    /**
     * Verifica en la base de datos, el client y key ingresados por el usuario de la api
     *
     * @param string $client: String que intenta el login
     * @param string $key: Contraseña sin encriptar del usuario que intenta el login
     *
     * @return bool true: Cuando el inicio de sesión es correcto
     *              false: Cuando el inicio de sesión no es correcto
     */
    private function authentication_Api_All(string $user, string $pass)
    {
        $user  = $this->db->scape($user);
        $query = $this->db->select('id,pass', 'users', null, "user='$user'", 1);

        # Incio de sesión con éxito
        if (false !== $query and crypt($pass, substr($query[0]['pass'], 0, 29))) {

            # Restaurar intentos
            $this->restoreAttempts($user);

            # Generar TOKEN jwt
            return true;
        }

        return false;
    }

    /**
     * Verifica en la base de datos, el client y key ingresados por el usuario de la api
     *
     * @param string $client: String que intenta el login
     * @param string $key: Contraseña sin encriptar del usuario que intenta el login
     *
     * @return bool true: Cuando el inicio de sesión es correcto
     *              false: Cuando el inicio de sesión no es correcto
     */
    private function authentication_Api_Analytics(string $user, string $pass)
    {
        $user  = $this->db->scape($user);
        $query = $this->db->select('id,pass', 'users', null, "user='$user' and api='analytics'", 1);

        # Incio de sesión con éxito
        if (false !== $query and Helper\Strings::chash($query[0]['pass'], $pass)) {

            # Restaurar intentos
            $this->restoreAttempts($user);

            return true;
        }

        return false;
    }

    /**
     * Verifica en la base de datos, el client y key ingresados por el usuario de la api
     *
     * @param string $client: String que intenta el login
     * @param string $key: Contraseña sin encriptar del usuario que intenta el login
     *
     * @return bool true: Cuando el inicio de sesión es correcto
     *              false: Cuando el inicio de sesión no es correcto
     */
    private function authentication_Api_Persons(string $DNI)
    {
        $DNI = $this->db->scape($DNI);
        # MODELO DE GEMA LOGIN
        $auth = new Model\Odbc;
        # QUERY DE LOGIN
        $query = $auth->getAuth($DNI);

        # Incio de sesión con éxito
        if ($query['status']) {

            # Generar TOKEN jwt
            return $query;
        }

        return $query;
    }

    /**
     * Verifica en la base de datos, el client y key ingresados por el usuario de la api
     *
     * @param string $client: String que intenta el login
     * @param string $key: Contraseña sin encriptar del usuario que intenta el login
     *
     * @return bool true: Cuando el inicio de sesión es correcto
     *              false: Cuando el inicio de sesión no es correcto
     */
    private function authentication_Api_Pass(string $user, string $COD, string $pass)
    {
        $user = $this->db->scape($user);

        $auth = new Model\Odbc;

        # QUERY DE LOGIN
        $query = $auth->getPass($COD);

        # Incio de sesión con éxito
        if (false != $query['status'] and Helper\Strings::chash($query['pass'], $pass)) {

            # Restaurar intentos
            $this->restoreAttempts($user);

            return true;
        }

        return false;
    }

    /**
     * Establece los intentos recientes desde la variable de sesión acumulativa
     *
     * @return void
     */
    private function setDefaultAttempts()
    {
        global $session;

        if (null != $session->get('login_user_recentAttempts')) {
            $this->recentAttempts = $session->get('login_user_recentAttempts');
        }
    }

    /**
     * Establece el intento del usuario actual o incrementa su cantidad si ya existe
     *
     * @param string $email: Email del usuario
     *
     * @return void
     */
    private function setNewAttempt(string $email)
    {
        if (!array_key_exists($email, $this->recentAttempts)) {
            $this->recentAttempts[$email] = array(
                'attempts' => 0, # Intentos
                'time'     => null, # Tiempo
            );
        }

        $this->recentAttempts[$email]['attempts']++;
        $this->updateSessionAttempts();
    }

    /**
     * Controla la cantidad de intentos permitidos máximos por usuario, si llega al límite,
     * el usuario podrá seguir intentando en self::MAX_ATTEMPTS_TIME segundos.
     *
     * @param string $email: Email del usuario
     *
     * @throws ModelsException cuando ya ha excedido self::MAX_ATTEMPTS
     * @return void
     */
    private function maximumAttempts(string $email)
    {
        if ($this->recentAttempts[$email]['attempts'] >= self::MAX_ATTEMPTS) {

            # Colocar timestamp para recuperar más adelante la posibilidad de acceso
            if (null == $this->recentAttempts[$email]['time']) {
                $this->recentAttempts[$email]['time'] = time() + self::MAX_ATTEMPTS_TIME;
            }

            if (time() < $this->recentAttempts[$email]['time']) {
                # Setear sesión
                $this->updateSessionAttempts();
                # Lanzar excepción
                throw new ModelsException('Ya ha superado el límite de intentos para iniciar sesión.');
            } else {
                $this->restoreAttempts($email);
            }
        }
    }

    /**
     * Obtiene datos de un usuario según su id en la base de datos
     *
     * @param int $id: Id del usuario a obtener
     * @param string $select : Por defecto es *, se usa para obtener sólo los parámetros necesarios
     *
     * @return false|array con información del usuario
     */
    public function getUserById(int $id, string $select = '*')
    {
        return $this->db->select($select, 'users', null, "id_user='$id'", 1);
    }

    /**
     * Obtiene a todos los usuarios
     *
     * @param string $select : Por defecto es *, se usa para obtener sólo los parámetros necesarios
     *
     * @return false|array con información de los usuarios
     */
    public function getUsers(string $select = '*')
    {
        return $this->db->select($select, 'users');
    }

    /**
     * Obtiene datos del usuario conectado actualmente
     *
     * @param string $select : Por defecto es *, se usa para obtener sólo los parámetros necesarios
     *
     * @throws ModelsException si el usuario no está logeado
     * @return array con datos del usuario conectado
     */
    public function getOwnerUser(string $select = '*'): array
    {
        if (null !== $this->id_user) {

            $user = $this->db->select($select, 'users', null, "id_user='$this->id_user'", 1);

            # Si se borra al usuario desde la base de datos y sigue con la sesión activa
            if (false === $user) {
                $this->logout();
            }

            return $user[0];
        }

        throw new \RuntimeException('El usuario no está logeado.');
    }

    /**
     * Realiza la acción de login dentro del sistema
     *
     * @return array : Con información de éxito/falla al inicio de sesión.
     */
    public function login(): array
    {
        try {
            global $http;

            # Definir de nuevo el control de intentos
            $this->setDefaultAttempts();

            # Obtener los datos $_POST
            $email = strtolower($http->request->get('email'));
            $pass  = $http->request->get('pass');

            # Verificar que no están vacíos
            if (Helper\Functions::e($email, $pass)) {
                throw new ModelsException('Credenciales incompletas.');
            }

            # Añadir intentos
            $this->setNewAttempt($email);

            # Verificar intentos
            $this->maximumAttempts($email);

            # Autentificar
            if ($this->authentication($email, $pass)) {
                return array('success' => 1, 'message' => 'Conectado con éxito.');
            }

            throw new ModelsException('Credenciales incorrectas.');

        } catch (ModelsException $e) {
            return array('success' => 0, 'message' => $e->getMessage());
        }
    }

    /**
     * Realiza la acción de login dentro del sistema
     *
     * @return array : Con información de éxito/falla al inicio de sesión.
     */
    public function login_Api_Analytics(): array
    {
        try {
            global $http;

            # Definir de nuevo el control de intentos
            $this->setDefaultAttempts();

            # Obtener los datos $_POST
            $user = strtolower($http->request->get('user'));
            $pass = $http->request->get('pass');

            # Verificar que no están vacíos
            if (Helper\Functions::e($user, $pass)) {
                throw new ModelsException('Credenciales incompletas.');
            }

            # Añadir intentos
            $this->setNewAttempt($user);

            # Verificar intentos
            $this->maximumAttempts($user);

            # Autentificar
            if ($this->authentication_Api_Analytics($user, $pass)) {

                $auth = new Model\Auth;
                if ($auth->generateKeyAnalytics(array('user' => $user, 'pass' => $pass))) {

                    $response            = $auth->generateKeyAnalytics(array('user' => $user, 'pass' => $pass));
                    $response['message'] = 'Conectado con éxito.';
                    return $response;
                }

            }

            throw new ModelsException('Credenciales incorrectas.');

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    /**
     * Realiza la acción de login dentro del sistema
     *
     * @return array : Con información de éxito/falla al inicio de sesión.
     */
    public function track_request_Api(): array
    {
        global $http;
        $REQ = $http->request->get('REQ');

        switch ($REQ) {

            case 'LOGIN':

                $LOGIN = $this->login_Api();
                return $LOGIN;

                break;

            case 'AUTH':

                $AUTH = $this->auth_Api();
                return $AUTH;

                break;

            case 'REGISTER':

                $REGISTER = $this->registro_Api();
                return $REGISTER;

                break;

            case 'GENERATE':

                $GENERATE = $this->generateUser_Token();
                return $GENERATE;

                break;

            case 'UNSUBSCRIBE':

                $UNSUBSCRIBE = $this->unsubscribe_Api();
                return $UNSUBSCRIBE;

                break;

            default:

                return array(
                    'status'    => false,
                    'message'   => 'No existe un proceso definido.',
                    'errorCode' => 4000,

                );

                break;
        }

    }

    /**
     * Realiza la generación de una nuevo user_token para las peticiones
     *
     * @return array : Con información de la nueva user_token éxito/falla al de la peticion.
     */
    public function generateUser_Token(): array
    {
        global $http;

        $auth               = new Model\Auth;
        $data['USER_TOKEN'] = $http->headers->get("Authorization");
        $data['APP_TOKEN']  = $http->request->get('APP_TOKEN');

        return $auth->regenerateUser_Token($data);
    }

    /**
     * Realiza la acción de login dentro del sistema
     *
     * @return array : Con información de éxito/falla al inicio de sesión.
     */
    public function verify_TOKEN(string $token): array
    {

        $REQ = explode('&req=', $token);

        switch ($REQ[1]) {

            case 'auth':

                $auth = $this->verify_TOKEN_AUTH($REQ[0]);
                return $auth;

                break;

            case 'lostpass':

                $auth = $this->verify_TOKEN_LOSTPASS($REQ[0]);
                return $auth;

                break;

            case 'changepass':

                $auth = $this->verify_TOKEN_CHANGEPASS($REQ[0]);
                return $auth;

                break;

            default:

                return array(
                    'status'    => false,
                    'message'   => 'No existe un proceso definido.',
                    'errorCode' => 4000,
                );

                break;
        }

    }

    private function verify_TOKEN_CHANGEPASS(string $TOKEN)
    {
        # MODELO DE GEMA ACTIVAR CUENTA METODO VERIFY
        $auth = new Model\Odbc;
        # QUERY DE LOGIN
        $query = $auth->getAuth_TOKEN($TOKEN);

        # Incio de sesión con éxito
        if ($query['status']) {

            $auth->getAuth_ACTIVE_ACCOUNT($TOKEN);

            Helper\Functions::redir();
        }

        return $query;
    }

    private function verify_TOKEN_LOSTPASS(string $TOKEN)
    {
        # MODELO DE GEMA ACTIVAR CUENTA METODO VERIFY
        $auth = new Model\Odbc;
        # QUERY DE LOGIN
        $query = $auth->getAuth_TOKEN_LOSTPASS($TOKEN);

        # Incio de sesión con éxito
        if ($query['status']) {

            $auth->getAuth_ACTIVE_ACCOUNT_LOSTPASS($TOKEN);

            Helper\Functions::redir();
        }

        return $query;
    }

    private function verify_TOKEN_AUTH(string $TOKEN)
    {
        # MODELO DE GEMA ACTIVAR CUENTA METODO VERIFY
        $auth = new Model\Odbc;
        # QUERY DE LOGIN
        $query = $auth->getAuth_TOKEN($TOKEN);

        # Incio de sesión con éxito
        if ($query['status']) {

            $auth->getAuth_ACTIVE_ACCOUNT($TOKEN);

            Helper\Functions::redir();
        }

        return $query;
    }

    /**
     * Realiza la acción de login dentro del sistema
     *
     * @return array : Con información de éxito/falla al inicio de sesión.
     */
    public function unsubscribe_Api(): array
    {
        try {
            global $http;

            # Obtener los datos $_POST
            $DNI = strtoupper($http->request->get('DNI'));

            # Verificar que no están vacíos
            if (Helper\Functions::e($DNI)) {
                throw new ModelsException('¡Error! DNI es necesario.', 4001);
            }

            # Autentificar Personas
            $d = $this->authentication_Api_Persons($DNI);

            # SI EL CAMPO PASS ESTA VACIO
            if ($d['status']) {

                $COD = $d['data'][0]['COD'];

                $unsuscribe_Api = new Model\Odbc;
                $unsuscribe     = $unsuscribe_Api->unsubscribeWeb($COD);

                if ($unsuscribe['status']) {
                    return array(
                        'status'  => $unsuscribe['status'],
                        'message' => 'Usuario fue eliminado.',
                    );

                }

                return array(
                    'status'    => false,
                    'message'   => 'Usuario no existe.',
                    'errorCode' => 4006,
                );

            }

            # Errores de formato en documento cedula o pasaporte o ruc
            throw new ModelsException($d['message'], $d['errorCode']);

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }
    }

    /**
     * Realiza la acción de login dentro del sistema
     *
     * @return array : Con información de éxito/falla al inicio de sesión.
     */
    public function auth_Api(): array
    {
        try {
            global $http;

            # Definir de nuevo el control de intentos
            $this->setDefaultAttempts();

            # Obtener los datos $_POST
            $DNI = strtoupper($http->request->get('DNI'));

            # Verificar que no están vacíos
            if (Helper\Functions::e($DNI)) {
                throw new ModelsException('¡Error! DNI es necesario.', 4001);
            }

            # Añadir intentos
            $this->setNewAttempt($DNI);

            # Verificar intentos
            $this->maximumAttempts($DNI);

            # Autentificar Personas
            $d = $this->authentication_Api_Persons($DNI);

            # SI EL CAMPO PASS ESTA VACIO o el usuario no esta registrado electronicamente
            if ($d['status']) {

                $COD = $d['data'][0]['COD'];
                unset($d['data']);

                $emails    = new Model\Odbc;
                $d['data'] = array();
                $datos     = $emails->getPacienteAuth($COD);

                # return $datos;

                # USUARIO EXISTE EN BASE DE DATOS PERO NO TIENE HC O  TIENE DATOS INCOMPLETOS
                if (!$datos['status']) {
                    throw new ModelsException($datos['message'], 4002);
                }

                foreach ($datos['data'][0]['EMAILS'] as $key => $value) {

                    if (Helper\Strings::remove_spaces($value['FIELD']) != '' and Helper\Strings::is_email($value['FIELD'])) {
                        # Crear arrays de emails ofuscados
                        $e           = explode('@', $value['FIELD']);
                        $d['data'][] = Helper\Strings::hiddenString($e[0]) . '@' . Helper\Strings::hiddenString($e[1], 4, 2);
                    } else {
                        throw new ModelsException('¡Error! El número de cédula o RUC ingresado no dispone de información suficiente para crear una cuenta electrónica.', 4002);
                    }

                }

                # VERIFICAR SI YA ESTA REGISTRA EN CUENTA ELECTRONICA
                $register = $emails->getPass($COD);

                # no tiene cuenta registrada
                if (isset($register['errorCode']) and $register['errorCode'] == 4006) {
                    $d['account'] = false;
                }

                # Tiene cuenta pero noe sta activa
                if (isset($register['errorCode']) and $register['errorCode'] == 4007) {

                    $d['status']  = false;
                    $d['account'] = true;
                }

                if ($d['account']) {
                    unset($d['data']);
                    return $d;
                }

                # valores definitivos USUARIO NO TIENE CUENTA REGSITRADA
                # unset($d['message']);

                return $d;

            }

            # Errores de formato en documento cedula o pasaporte o ruc
            throw new ModelsException($d['message'], $d['errorCode']);

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }
    }

    /**
     * Realiza la acción de login dentro del sistema
     *
     * @return array : Con información de éxito/falla al inicio de sesión.
     */
    public function login_Api(): array
    {
        try {
            global $http;

            # Definir de nuevo el control de intentos
            $this->setDefaultAttempts();

            # Obtener los datos $_POST
            $DNI  = strtoupper($http->request->get('DNI'));
            $PASS = $http->request->get('PASS');

            # Verificar que no están vacíos
            if (Helper\Functions::e($DNI, $PASS)) {
                throw new ModelsException('¡Error! DNI es necesario.', 4001);
            }

            # Añadir intentos
            $this->setNewAttempt($DNI);

            # Verificar intentos
            $this->maximumAttempts($DNI);

            # Autentificar Personas
            $d = $this->auth_Api();

            # Tiene cuenta pero no esta activa
            if (isset($d['errorCode']) and $d['errorCode'] == 4007) {

                throw new ModelsException($d['message'], $d['errorCode']);
            }

            # DNI NO EXISTE EN BDD O TIENE INCOSISTENCIAS
            if (!$d['status']) {
                throw new ModelsException($d['message'], $d['errorCode']);
            }

            # uSUARIO NO TIENE CUENTA ELECTRÓNICA REGISTRADA
            if (!$d['account']) {
                throw new ModelsException($d['message'], $d['errorCode']);
            }

            $d = $this->authentication_Api_Persons($DNI);

            # SI EL CAMPO PASS NO ESTA VACIO VALIDA CONTRASEÑA y genera key
            $p = $this->authentication_Api_Pass($DNI, $d['data'][0]['COD'], $PASS);

            if (!$p) {
                throw new ModelsException('¡Error en contraseña!', 4013);
            }

            # Genarar token de usuario
            if ($d['status'] and $p) {

                $auth = new Model\Auth;
                return $auth->generateKey($d);
            }

        } catch (ModelsException $e) {

            $error = array(
                'status'    => false,
                'message'   => $e->getMessage(),
                'errorCode' => $e->getCode(),
            );

            # Si hay error por cuenta no activa devolver email
            if ($e->getCode() == 4007) {
                $error['EMAIL'] = $d['EMAIL'];
            }

            return $error;
        }
    }

/**
 * Realiza la acción de registro dentro del sistema web del hospital
 *
 * @return array : Con información de éxito/falla al registrar el usuario nuevo.
 */
    public function registro_Api(): array
    {
        try {
            global $http;

            # Obtener los datos $_POST
            $DNI   = strtoupper($http->request->get('DNI'));
            $PASS  = $http->request->get('PASS');
            $EMAIL = Helper\Strings::remove_spaces(strtolower($http->request->get('EMAIL')));

            # Verificar que no están vacíos
            if (Helper\Functions::e($DNI, $PASS, $EMAIL)) {
                throw new ModelsException('Cédula, RUC o Pasaporte, Contraseña y Correo electrónico es obligatorio para registro de usuario.', 4001);
            }

            # Verificar email cumpla validacion de formato
            if (!Helper\Strings::is_email($EMAIL)) {
                throw new ModelsException('Correo electrónico no tiene un formato válido.', 4009);
            }

            $d = $this->authentication_Api_Persons($DNI);

            if (!$d['status']) {
                throw new ModelsException($d['message'], $d['errorCode']);
            }

            # URL PARA ACTTIVAR CUENTA
            $token = str_shuffle(md5(time()) . md5(time()));
            # $link  = $http->getUri() . '/verify/' . $token . '&req=auth';
            $link = $token . '&req=auth';

            $user['PASS']  = Helper\Strings::hash($PASS);
            $user['COD']   = $d['data'][0]['COD'];
            $user['EMAIL'] = $EMAIL;
            $user['TOKEN'] = $token;

            $registro_Api = new Model\Odbc;
            # QUERY de isnercion de resgitro
            $registro = $registro_Api->registroWeb($user);

            if ($registro['status']) {
                return array(
                    'status'  => true,
                    'message' => 'Usuario registrado con éxito. Cuenta electrónica debe activarse.',
                    'verify'  => $link,
                );
            }

            throw new ModelsException($registro['message'], $registro['errorCode']);

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }
    }

/**
 * Realiza la acción de registro dentro del sistema
 *
 * @return array : Con información de éxito/falla al registrar el usuario nuevo.
 */
    public function register(): array
    {
        try {
            global $http;

            # Obtener los datos $_POST
            $name        = $http->request->get('name');
            $email       = $http->request->get('email');
            $pass        = $http->request->get('pass');
            $pass_repeat = $http->request->get('pass_repeat');

            # Verificar que no están vacíos
            if (Helper\Functions::e($name, $email, $pass, $pass_repeat)) {
                throw new ModelsException('Todos los datos son necesarios');
            }

            # Verificar email
            $this->checkEmail($email);

            # Veriricar contraseñas
            $this->checkPassMatch($pass, $pass_repeat);

            # Registrar al usuario
            $id_user = $this->db->insert('users', array(
                'name'  => $name,
                'email' => $email,
                'pass'  => Helper\Strings::hash($pass),
            ));

            # Iniciar sesión
            $this->generateSession(array(
                'id_user' => $id_user,
            ));

            return array('success' => 1, 'message' => 'Registrado con éxito.');
        } catch (ModelsException $e) {
            return array('success' => 0, 'message' => $e->getMessage());
        }
    }

/**
 * Envía un correo electrónico al usuario que quiere recuperar la contraseña, con un token y una nueva contraseña.
 * Si el usuario no visita el enlace, el sistema no cambiará la contraseña.
 *
 * @return array<string,integer|string>
 */
    public function lostpass(): array
    {
        try {
            global $http, $config;

            # Obtener datos $_POST
            $email = $http->request->get('email');

            # Campo lleno
            if (Helper\Functions::emp($email)) {
                throw new ModelsException('El campo email debe estar lleno.');
            }

            # Filtro
            $email = $this->db->scape($email);

            # Obtener información del usuario
            $user_data = $this->db->select('id_user,name', 'users', null, "email='$email'", 1);

            # Verificar correo en base de datos
            if (false === $user_data) {
                throw new ModelsException('El email no está registrado en el sistema.');
            }

            # Generar token y contraseña
            $token = md5(time());
            $pass  = uniqid();
            $link  = $config['build']['url'] . 'lostpass?token=' . $token . '&user=' . $user_data[0]['id_user'];

            # Construir mensaje y enviar mensaje
            $HTML = 'Hola <b>' . $user_data[0]['name'] . '</b>, ha solicitado recuperar su contraseña perdida, si no ha realizado esta acción no necesita hacer nada.
                    <br />
                    <br />
                    Para cambiar su contraseña por <b>' . $pass . '</b> haga <a href="' . $link . '" target="_blank">clic aquí</a> o en el botón de recuperar.';

            # Enviar el correo electrónico
            $dest         = array();
            $dest[$email] = $user_data[0]['name'];
            $email_send   = Helper\Emails::send($dest, array(
                # Título del mensaje
                '{{title}}'     => 'Recuperar contraseña de ' . $config['build']['name'],
                # Url de logo
                '{{url_logo}}'  => $config['build']['url'],
                # Logo
                '{{logo}}'      => $config['mailer']['logo'],
                # Contenido del mensaje
                '{{content}} '  => $HTML,
                # Url del botón
                '{{btn-href}}'  => $link,
                # Texto del boton
                '{{btn-name}}'  => 'Recuperar Contraseña',
                # Copyright
                '{{copyright}}' => '&copy; ' . date('Y') . ' <a href="' . $config['build']['url'] . '">' . $config['build']['name'] . '</a> - Todos los derechos reservados.',
            ), 0);

            # Verificar si hubo algún problema con el envío del correo
            if (false === $email_send) {
                throw new ModelsException('No se ha podido enviar el correo electrónico.');
            }

            # Actualizar datos
            $id_user = $user_data[0]['id_user'];
            $this->db->update('users', array(
                'tmp_pass' => Helper\Strings::hash($pass),
                'token'    => $token,
            ), "id_user='$id_user'", 1);

            return array('success' => 1, 'message' => 'Se ha enviado un enlace a su correo electrónico.');
        } catch (ModelsException $e) {
            return array('success' => 0, 'message' => $e->getMessage());
        }
    }

/**
 * Desconecta a un usuario si éste está conectado, y lo devuelve al inicio
 *
 * @return void
 */
    public function logout()
    {
        global $session, $cookie;

        $session->remove($cookie->get('session_hash') . '__user_id');
        foreach ($cookie->all() as $name => $value) {
            $cookie->remove($name);
        }

        Helper\Functions::redir();
    }

/**
 * Cambia la contraseña de un usuario en el sistema, luego de que éste haya solicitado cambiarla.
 * Luego retorna al sitio de inicio con la variable GET success=(bool)
 *
 * La URL debe tener la forma URL/lostpass?token=TOKEN&user=ID
 *
 * @return void
 */
    public function changeTemporalPass()
    {
        global $config, $http;

        # Obtener los datos $_GET
        $id_user = $http->query->get('user');
        $token   = $http->query->get('token');

        $success = false;
        if (!Helper\Functions::emp($token) && is_numeric($id_user) && $id_user >= 1) {
            # Filtros a los datos
            $id_user = $this->db->scape($id_user);
            $token   = $this->db->scape($token);
            # Ejecutar el cambio
            $this->db->query("UPDATE users SET pass=tmp_pass, tmp_pass=NULL, token=NULL
            WHERE id_user='$id_user' AND token='$token' LIMIT 1;");
            # Éxito
            $success = true;
        }

        # Devolover al sitio de inicio
        Helper\Functions::redir($config['build']['url'] . '?sucess=' . (int) $success);
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
