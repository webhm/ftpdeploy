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
use Doctrine\DBAL\DriverManager;
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Models\Traits\DBModel;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Login
 */
class Login extends Models implements IModels
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

    private $userToken = null;

    private $dataUser = null;

    private $isHM = null;

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();

        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracleNaf'], $_config);

    }

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

    private function base64_url_decode($arg)
    {
        $res = $arg;
        $res = str_replace('-', '+', $res);
        $res = str_replace('_', '/', $res);
        switch (strlen($res) % 4) {
            case 0:
                break;
            case 2:
                $res .= "==";
                break;
            case 3:
                $res .= "=";
                break;
            default:
                break;
        }
        $res = base64_decode($res);
        return $res;
    }

    public function decodeJWT($token)
    {

        $claims_arr = array();

        if ($token !== null) {
            $token_arr = explode('.', $token);
            $claims_enc = $token_arr[1];
            $claims_arr = json_decode($this->base64_url_decode($claims_enc), true);
        }

        return $claims_arr;

    }

    public function getDataTokenMS(string $token)
    {

        $data = $this->decodeJWT($token);

        $email = $data['upn'];

        # Formato de email
        if (!Helper\Strings::is_email($email)) {
            throw new ModelsException('El email no tiene un formato válido.');
        }

        $_user = explode('@', $email);
        $user = $_user[0];

        # Existencia de email
        $query = $this->db->select('id, rol, codMedico, user', 'usuarios', null, "user='$user'", 1);

        if (false !== $query) {
            return $query[0];
        } else {
            return array();
        }

    }

    /**
     * Verifica el email ingresado, tanto el formato como su existencia en el sistema
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
        $query = $this->db->select('*', 'usuarios', null, "email='$email'", 1);
        if (false !== $query && $query[0]['statusActive'] == 0) {
            throw new ModelsException('El email ingresado ya existe. Confirme la solicitud de registro enviada a: ' . $email . ' para continuar. Si necesita ayuda comuníquese al (02) 399 8000 Ext: 2020.');
        }

    }

    private function validarStatusRegistro($codMedico)
    {

        # Existencia de email
        $query = $this->db->select('*', 'usuarios', null, "codMedico='$codMedico'", 1);
        if (false !== $query && $query[0]['statusActive'] == 0) {
            throw new ModelsException('El email ingresado ya existe. Confirme la solicitud de registro enviada a: ' . $query[0]['email'] . ' para continuar. Si necesita ayuda comuníquese al (02) 399 8000 Ext: 2020.');
        }

        if (false !== $query && $query[0]['statusActive'] == 1) {
            throw new ModelsException('El usuario ya existe. Ingrese con sus credenciales enviadas anteriormente. Si necesita ayuda comuníquese al (02) 399 8000 Ext: 2020.');
        }

    }

    private function validarHM_LOGIN($user)
    {

        $query = $this->db->select('email,statusActive', 'usuarios', null, "user='$user'", 1);
        if (false !== $query && !is_null($query[0]['statusActive']) && $query[0]['statusActive'] == 0) {
            throw new ModelsException('Registro de usuario incompleto. Confirme la solicitud de registro enviada a: ' . $query[0]['email'] . ' para continuar. Si necesita ayuda comuníquese al (02) 399 8000 Ext: 2020.');
        }

        # Existencia de email
        $query = $this->db->select('email', 'usuarios', null, "user='$user'", 1);
        if (false !== $query && strpos($query[0]['email'], '@hmetro.med.ec') !== false) {
            $this->isHM = true;
        } else {
            $this->isHM = false;
        }

    }

    /**
     * Verfica si el RUC ingresado consta como medico para el registro
     *
     * @param string $user:
     *
     * @throws ModelsException en caso de que no tenga formato válido o ya exista
     */
    private function checkRUC(string $user)
    {

        # Devolver todos los resultados
        $sql = " SELECT distinct mp.no_prove, mp.nombre, mp.cedula, mc.valor correos
        from arcpmp mp, bab_medios_contacto mc
        where mp.no_cia = '01'
        and mp.cedula = '$user'
        and mp.grupo = '88'
        and mc.pk_fk_persona = mp.codigo_persona
        --and mc.fk_tipo_medio_contacto in (7)
        and mc.valor like '%@%'
        order by mp.no_prove";

        # Conectar base de datos
        $this->conectar_Oracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetchAll();

        if (count($data) === 0) {
            throw new ModelsException('No existe información. N° de RUC inconsistente.');
        }

        $correos = array('');

        foreach ($data as $key) {
            $correos[] = $key['CORREOS'];
        }

        $data[0]['CORREOS'] = $correos;

        $this->dataUser = $data[0];

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
            $this->recentAttempts[$email]['time'] = null;
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
    private function generateSessionJWT(array $user_data)
    {
        $auth = new Model\Auth;
        return $auth->generateKey($user_data);
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
    private function authentication(string $email, string $pass, string $type): bool
    {
        $email = $this->db->scape($email);
        $query = $this->db->select('id,rol,user,pass,codMedico', 'usuarios', null, "user='$email'", 1);

        if ($type == 'hm') {

            # Incio de sesión con éxito
            if (false !== $query) {

                # Restaurar intentos
                # $this->restoreAttempts($email);

                unset($query[0]['pass']);

                # Generar la sesión
                $jwt = $this->generateSessionJWT($query[0]);

                $this->userToken = $jwt['user_token'];

                $moduleAccess = array(
                    'admisiones' => $this->getRolesUser($query[0]['id'], 'admisiones'),
                    'emergencia' => $this->getRolesUser($query[0]['id'], 'emergencia'),
                    'farmacia' => $this->getRolesUser($query[0]['id'], 'farmacia'),
                    'imagen' => $this->getRolesUser($query[0]['id'], 'imagen'),
                    'laboratorio' => $this->getRolesUser($query[0]['id'], 'laboratorio'),
                    'mantenimiento' => $this->getRolesUser($query[0]['id'], 'mantenimiento'),
                    'hospitalizacion' => $this->getRolesUser($query[0]['id'], 'hospitalizacion'),
                    'terapia-respiratoria' => $this->getRolesUser($query[0]['id'], 'terapia-respiratoria'),
                    'bco-sangre' => $this->getRolesUser($query[0]['id'], 'bco-sangre'),
                    'neurofisiologia' => $this->getRolesUser($query[0]['id'], 'neurofisiologia'),
                );

                $this->dataUser = array(
                    'user' => $query[0],
                    'rol' => $query[0]['rol'],
                    'modulesAccess' => $moduleAccess,
                );

                return true;
            }
        }

        if ($type == 'user') {
            # Incio de sesión con éxito
            if (false !== $query && Helper\Strings::chash($query[0]['pass'], $pass)) {

                # Restaurar intentos
                # $this->restoreAttempts($email);

                unset($query[0]['pass']);

                # Generar la sesión
                $jwt = $this->generateSessionJWT($query[0]);

                $this->userToken = $jwt['user_token'];

                $moduleAccess = array(
                    'admisiones' => $this->getRolesUser($query[0]['id'], 'admisiones'),
                    'emergencia' => $this->getRolesUser($query[0]['id'], 'emergencia'),
                    'farmacia' => $this->getRolesUser($query[0]['id'], 'farmacia'),
                    'imagen' => $this->getRolesUser($query[0]['id'], 'imagen'),
                    'laboratorio' => $this->getRolesUser($query[0]['id'], 'laboratorio'),
                    'mantenimiento' => $this->getRolesUser($query[0]['id'], 'mantenimiento'),
                    'hospitalizacion' => $this->getRolesUser($query[0]['id'], 'hospitalizacion'),
                    'terapia-respiratoria' => $this->getRolesUser($query[0]['id'], 'terapia-respiratoria'),
                    'bco-sangre' => $this->getRolesUser($query[0]['id'], 'bco-sangre'),
                    'neurofisiologia' => $this->getRolesUser($query[0]['id'], 'neurofisiologia'),
                );

                $this->dataUser = array(
                    'user' => $query[0],
                    'rol' => $query[0]['rol'],
                    'modulesAccess' => $moduleAccess,
                );

                return true;
            }
        }

        return false;
    }

    /**
     * Verifica en la base de datos, que roles tiene le usuario
     */
    private function getRolesUser(string $idUser, string $modulo)
    {
        $accesos = $this->db->select('*', 'VW_ACCESOS_METROVIRTUAL', null, "idUser='$idUser' AND modulo='$modulo'");

        $permisos = array();

        # Incio de sesión con éxito
        if (false !== $accesos) {

            foreach ($accesos as $key) {
                if ($key['idUser'] == $idUser) {
                    $permisos[] = $key;
                }
            }

            return $permisos;

        } else {
            return array();
        }

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
                'time' => null, # Tiempo
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
    public function login_Api(): array
    {
        try {
            global $http;

            # Definir de nuevo el control de intentos
            # $this->setDefaultAttempts();

            # Obtener los datos $_POST
            $user = strtolower($http->request->get('user'));
            $pass = $http->request->get('pass');

            # Verificar que no están vacíos
            if (Helper\Functions::e($user, $pass)) {
                throw new ModelsException('Credenciales incompletas.');
            }

            # Añadir intentos
            # $this->setNewAttempt($user);

            # Verificar intentos
            # $this->maximumAttempts($user);

            $this->validarHM_LOGIN($user);

            if ($this->isHM) {

                # Autentificar
                $u = new Model\ActiveDirectoryHM;
                $u->loginUsuario($user, $pass);
                $pass = "123456";

                # Autentificar
                if ($this->authentication($user, $pass, 'hm')) {
                    return array(
                        'status' => true,
                        'message' => 'Conectado con éxito.',
                        'jwt' => $this->userToken,
                        'data' => $this->dataUser,
                        'dataUss' => $u->busquedaUsuario(),
                    );
                }

                throw new ModelsException('Credenciales incorrectas.');

            } else {

                # Autentificar
                if ($this->authentication($user, $pass, 'user')) {
                    return array(
                        'status' => true,
                        'message' => 'Conectado con éxito.',
                        'jwt' => $this->userToken,
                        'data' => $this->dataUser,
                    );
                }

                throw new ModelsException('Credenciales incorrectas.');

            }

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    public function verificarTokenLostPass(): array
    {
        try {

            global $http;

            # Obtener los datos $_POST
            $token = $http->request->get('token');

            # Existencia de email
            $query = $this->db->select('*', 'usuarios', null, "token='$token'", 1);
            if (false == $query) {
                throw new ModelsException('Registro de solicitud inválido o caducado.');
            }

            $dataUser = array('user' => $query[0]['user']);

            return array('status' => true, 'data' => $dataUser, 'message' => 'Solicitud validada con éxito. Ingrese su nueva contraseña para continuar.');

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());
        }
    }

    public function recoveryPass(): array
    {
        try {

            global $http;

            # Obtener los datos $_POST
            $user = $http->request->get('user');

            # Verificar ruc para registro
            $this->checkRUC($user);

            $codMedico = $this->dataUser['NO_PROVE'];

            # Existencia de email
            $query = $this->db->select('*', 'usuarios', null, "codMedico='$codMedico'", 1);

            if (false === $query) {
                throw new ModelsException('El usuario no existe. Crea una nueva cuenta para continuar.');
            }

            if (false !== $query && $query[0]['statusActive'] == 0) {
                throw new ModelsException('Solicitud de registro pendiente. Confirme la solicitud de registro enviada a: ' . $query[0]['email'] . ' para continuar. Si necesita ayuda comuníquese al (02) 399 8000 Ext: 2020.');
            }

            $idUser = $query[0]['id'];
            $user = $query[0]['user'];
            $email = $query[0]['email'];

            # Existencia de email
            $query = $this->db->select('email', 'usuarios', null, "codMedico='$codMedico'", 1);
            if (false !== $query && strpos($query[0]['email'], '@hmetro.med.ec') !== false) {
                $this->isHM = true;
            } else {
                $this->isHM = false;
            }

            $token = str_shuffle(md5(time()) . md5(time()));

            $link = 'https://medicos.hospitalmetropolitano.org/lostpass/?token=' . $token;

            if ($this->isHM) {

                $this->sendMail_LostPass_HM($user, 'Misma contraseña de correo @hmetro.med.ec', $email, 'https://metropolitano.proactivanet.com/proactivanet/portal/ui/loginform/changePasswordAD.paw');

            } else {

                $pass = uniqid();

                $this->db->update('usuarios', array(
                    'tmp_pass' => Helper\Strings::hash($pass),
                    'token' => $token,
                ), "id='$idUser'", 1);

                $this->sendMail_LostPass($user, $pass, $email, $link);
            }

            return array('status' => true, 'message' => 'Proceso realizado con éxito. Hemos enviado un correo electrónico a: ' . $email);

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    public function register(): array
    {
        try {

            global $http;

            # Obtener los datos $_POST
            $user = $http->request->get('user');
            $email = $http->request->get('email');
            $pass = $http->request->get('pass');

            # Verificar que no están vacíos
            if (Helper\Functions::e($user, $email, $pass)) {
                throw new ModelsException('Todos los datos son necesarios');
            }

            # Verificar email
            $this->checkEmail($email);

            # Verificar ruc para registro
            $this->checkRUC($user);

            $mailUser = explode('@', $email);
            $user = $mailUser[0];
            $codMedico = $this->dataUser['NO_PROVE'];

            $token = str_shuffle(md5(time()) . md5(time()));

            $link = 'https://medicos.hospitalmetropolitano.org/verify/?token=' . $token;

            # Validar si regsitro es con @hmetro.med.ec
            if (strpos($email, '@hmetro.med.ec') !== false) {
                # Registrar al usuario
                $id_user = $this->db->insert('usuarios', array(
                    'user' => $user,
                    'email' => $email,
                    'codMedico' => $codMedico,
                    'rol' => 1,
                    'tmp_pass' => $token,
                    'statusActive' => 0,
                ));

                $this->sendMail_Register($user, 'Misma contraseña de correo @hmetro.med.ec', $email, $link);

            } else {

                # Registrar al usuario
                $id_user = $this->db->insert('usuarios', array(
                    'user' => $user,
                    'email' => $email,
                    'codMedico' => $codMedico,
                    'rol' => 1,
                    'pass' => Helper\Strings::hash($pass),
                    'tmp_pass' => $token,
                    'statusActive' => 0,
                ));

                $this->sendMail_Register($user, $pass, $email, $link);
            }

            return array('status' => true, 'message' => 'Registrado con éxito.');

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    public function sendMail_LostPass_HM($user, $pass, $email, $link)
    {
        try {

            global $config, $http;

            # Construir mensaje y enviar mensaje
            $content = '
              <br />Hola.-
              <br />
              Muchas gracias por confiar en nosotros. Hemos recibido una solicitud de recuperación o cambio de contraseña.
              <br />
              <br />
              <b>*Su cuenta esta registrada con la dirección: ' . $email . '</b>
              <br />
              <br />
              Si necesita ayuda comuníquese al (02) 399 8000 Ext: 2020.
              <br />
              <br />';

            # Enviar el correo electrónico
            $_html = Helper\Emails::loadTemplate(array(
                # Contenido del mensaje
                '{{content}}' => $content,
                # Url del botón
                '{{btn-href}}' => $link,

                '{{btn-name}}' => 'Ir a Portal CONCAS',
                # Copyright
                '{{copyright}}' => '&copy; ' . date('Y') . ' <a href="https://www.hospitalmetropolitano.org">Hospital Metropolitano</a> - Todos los derechos reservados.',
            ), 11);

            # Verificar si hubo algún problema con el envió del correo
            if ($this->sendMail($_html, $email, 'Recuperación de Contraseña') === false) {
                throw new ModelsException('No se ha podido enviar el correo electrónico.');
            }

        } catch (\Throwable $th) {
            throw new ModelsException($th->getMessage());
        }

    }

    public function sendMail_LostPass($user, $pass, $email, $link)
    {
        try {

            global $config, $http;

            # Construir mensaje y enviar mensaje
            $content = '
              <br />Hola.-
              <br />
              Muchas gracias por confiar en nosotros. Hemos recibido una solicitud de recuperación o cambio de contraseña.
              <br />
              <br />
              <b>*Es necesario confirmar esta solicitud, para continuar.</b>
              <br />
              <br />
              <b>Usuario:</b>
              <br />
              ' . $user . '
              <br />
              <b>Contraseña Temporal:</b>
              <br />
              ' . $pass . '
              <br />
              <br />
              Si necesita ayuda comuníquese al (02) 399 8000 Ext: 2020.
              <br />
              <br />';

            # Enviar el correo electrónico
            $_html = Helper\Emails::loadTemplate(array(
                # Contenido del mensaje
                '{{content}}' => $content,
                # Url del botón
                '{{btn-href}}' => $link,

                '{{btn-name}}' => 'Confirmar Solicitud',
                # Copyright
                '{{copyright}}' => '&copy; ' . date('Y') . ' <a href="https://www.hospitalmetropolitano.org">Hospital Metropolitano</a> - Todos los derechos reservados.',
            ), 11);

            # Verificar si hubo algún problema con el envió del correo
            if ($this->sendMail($_html, $email, 'Recuperación de Contraseña') === false) {
                throw new ModelsException('No se ha podido enviar el correo electrónico.');
            }

        } catch (\Throwable $th) {
            throw new ModelsException($th->getMessage());
        }

    }

    public function sendMail_Register($user, $pass, $email, $link)
    {
        try {

            global $config, $http;

            # Construir mensaje y enviar mensaje
            $content = '
              <br />Hola.-
              <br />
              Muchas gracias por confiar en nosotros. Su registro se ha completado con éxito.
              <br />
              <br />
              A continuación sus datos de acceso:
              <br />
              <br />
              <b>Usuario:</b>
              <br />
              ' . $user . '
              <br />
              <b>Contraseña:</b>
              <br />
              ' . $pass . '
              <br />
              <br />
              <b>*Es necesario confirmar esta solicitud, para continuar.</b>
              <br />
              Si necesita ayuda comuníquese al (02) 399 8000 Ext: 2020.
              <br />
              <br />';

            # Enviar el correo electrónico
            $_html = Helper\Emails::loadTemplate(array(
                # Contenido del mensaje
                '{{content}}' => $content,
                # Url del botón
                '{{btn-href}}' => $link,
                # Copyright
                '{{copyright}}' => '&copy; ' . date('Y') . ' <a href="https://www.hospitalmetropolitano.org">Hospital Metropolitano</a> - Todos los derechos reservados.',
            ), 10);

            # Verificar si hubo algún problema con el envió del correo
            if ($this->sendMail($_html, $email, 'Nuevo Usuario MetroVirtual ') === false) {
                throw new ModelsException('No se ha podido enviar el correo electrónico.');
            }

        } catch (\Throwable $th) {
            throw new ModelsException($th->getMessage());
        }

    }

    public function sendMail($html, $to, $subject)
    {

        global $config;

        $stringData = array(
            "TextBody" => "Nuevo Registro - Usuario Médico",
            'From' => 'MetroVirtual metrovirtual@hospitalmetropolitano.org',
            'To' => $to,
            'Bcc' => 'mchangcnt@gmail.com',
            'Subject' => $subject,
            'HtmlBody' => $html,
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
            'X-Postmark-Server-Token: ' . $config['mailer']['user'])
        );

        $result = curl_exec($ch);
        if (curl_errno($ch)) {

            return false;
            $resultobj = curl_error($ch);
        }
        curl_close($ch);
        $resultobj = json_decode($result);

        return true;

    }

    public function validarRUC(): array
    {
        try {

            global $http;

            # Obtener los datos $_POST
            $user = $http->request->get('user');

            # Verificar que no están vacíos VALIDAR RUC
            if (Helper\Functions::e($user)) {
                throw new ModelsException('Todos los datos son necesarios');
            }

            # Verificar ruc para registro
            $this->checkRUC($user);

            $codMedico = $this->dataUser['NO_PROVE'];
            $this->validarStatusRegistro($codMedico);

            return array('status' => true, 'data' => $this->dataUser, 'message' => 'Registrado con éxito.');

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

        }
    }

    public function verifyRegister(): array
    {
        try {

            global $http;

            # Obtener los datos $_POST
            $token = $http->request->get('token');

            # Verificar que no están vacíos VALIDAR RUC
            if (Helper\Functions::e($token)) {
                throw new ModelsException('Todos los datos son necesarios');
            }

            $this->db->update('usuarios', array(
                'tmp_pass' => 'NULL',
                'statusActive ' => 1,
            ), "tmp_pass='$token'", 1);

            return array('status' => true, 'message' => 'Registro validado con éxito. A continuación ingrese con las credenciales enviadas a su correo electrónico anteriormente. Si necesita ayuda comuníquese al (02) 399 8000 Ext: 2020.');

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage());

        }
    }

    public function lostpass(): array
    {
        try {

            global $http, $config;

            # Obtener datos $_POST
            $token = $http->request->get('token');
            $pass = $http->request->get('pass');
            $tempPass = $http->request->get('tempPass');

            $query = $this->db->select('*', 'usuarios', null, "token='$token'", 1);
            if (false == $query) {
                throw new ModelsException('Registro de solicitud inválido o caducado.');
            }

            # Actualizar datos
            $idUser = $query[0]['id'];
            $this->db->update('usuarios', array(
                'pass' => Helper\Strings::hash($pass),
                'tmp_pass' => 'NULL',
                'token' => 'NULL',
            ), "id='$idUser'", 1);

            return array('status' => true, 'message' => 'Proceso realizado con ');
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
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
        $token = $http->query->get('token');

        $success = false;
        if (!Helper\Functions::emp($token) && is_numeric($id_user) && $id_user >= 1) {
            # Filtros a los datos
            $id_user = $this->db->scape($id_user);
            $token = $this->db->scape($token);
            # Ejecutar el cambio
            $this->db->query("UPDATE users SET pass=tmp_pass, tmp_pass=NULL, token=NULL
            WHERE id_user='$id_user' AND token='$token' LIMIT 1;");
            # Éxito
            $success = true;
        }

        # Devolover al sitio de inicio
        Helper\Functions::redir($config['build']['url'] . '?sucess=' . (int) $success);
    }

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

/**
 * __construct()
 */
    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
        $this->startDBConexion();
    }
}
