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
use Doctrine\DBAL\DriverManager;
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Models\Traits\DBModel;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Users
 */
class Login extends Models implements IModels
{
    use DBModel;

    /**
     * Máximos intentos de inincio de sesión de un usuario
     *
     * @var int
     */
    const MAX_ATTEMPTS = 8;

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

    # Variables de Clase
    private $DNI         = null;
    private $COD_PERSONA = null;
    private $USER        = null;
    private $CP_PTE      = null;
    private $CP_MED      = null;
    private $CP_PRO      = null;
    private $ACCOUNT     = null;
    private $EMAIL       = null;
    private $PASS        = null;
    private $_conexion   = null;
    private $errors      = null;

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

    }

    private function setParameters()
    {

        global $http, $config;

        $this->errors = $config['errors'];

        $DNI = strtoupper($http->request->get('DNI'));

        $DNI = $this->db->scape($DNI);

        if (Helper\Functions::e($DNI)) {
            throw new ModelsException($this->errors['notDNI']['message'], $this->errors['notDNI']['code']);
        }

        if (strlen($DNI) < 3) {
            throw new ModelsException($this->errors['notAvalibleDNI']['message'], $this->errors['notAvalibleDNI']['code']);
        }

        if (null != $http->request->get('PASS')) {

            # Setear Pasword para la validaciond de contraseña
            $PASS       = $this->db->scape($http->request->get('PASS'));
            $this->PASS = $PASS;

        }

        # SETEAR DNI
        $this->DNI = $DNI;
    }

    private function validacionDNI()
    {

        $cedula = $this->DNI;
        $dni    = new Model\ValidacionDNI;

        # si es cédula
        if (ctype_digit(Helper\Strings::remove_spaces($cedula))) {

            # VALIDAR FORMATO SI ES CEDULA
            if (strlen(Helper\Strings::remove_spaces($cedula)) == 10) {

                if (!$dni->validarCedula($cedula)) {
                    throw new ModelsException($this->errors['notAvalibleDNI']['message'], $this->errors['notAvalibleDNI']['code']);
                }
            }

            # si documento estrangero
            if ((strlen(Helper\Strings::remove_spaces($cedula)) > 13
                and strlen(Helper\Strings::remove_spaces($cedula)) > 25)) {
                throw new ModelsException($this->errors['notFormatPass']['message'], $this->errors['notFormatPass']['code']);
            }

        }

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
     * Restaura los intentos de un usuario al iniciar sesión
     *
     * @param string $email: Email del usuario a restaurar
     *
     * @throws ModelsException cuando hay un error de lógica utilizando este método
     * @return void
     */
    private function restoreAttempts()
    {
        $email = $this->DNI;

        if (array_key_exists($email, $this->recentAttempts)) {
            $this->recentAttempts[$email]['attempts'] = 0;
            $this->recentAttempts[$email]['time']     = null;
            $this->updateSessionAttempts();
        } else {
            throw new ModelsException('Error lógico');
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
    private function setNewAttempt()
    {
        $email = $this->DNI;

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
    private function maximumAttempts()
    {
        $email = $this->DNI;

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
                $this->restoreAttempts();
            }
        }
    }

    /**
     * Realiza la acción de autenticar al usuario
     *
     * @return array : Con información de éxito/falla al inicio de sesión.
     */
    public function auth_Api()
    {
        try {

            # Setear variables de peticion
            $this->setParameters();

            # Definir de nuevo el control de intentos
            $this->setDefaultAttempts();

            # Añadir intentos para Login
            $this->setNewAttempt();

            # Verificar intentos de Login
            $this->maximumAttempts();

            # Validar FORMATO DNI si es cedula o RUC NATURAL
            $this->validacionDNI();

            # Verificar si usuario existe en base de datos GEMA
            $this->validacionBDDGEMA();

            # Verificar si uusuario ya tiene una cuenta electrónica y esta activa
            $this->cuentaElectronica();

            # Si no esta registrada imprimir valores para proceder a registro de cuenta electronica
            return array(
                'status'  => true,
                'account' => true,
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4006) {

                # Cuenta no esta registrada proceder a registro
                $dataAccount = $this->dataAccount();

                return array(
                    'status'    => true,
                    'account'   => false,
                    'message'   => $e->getMessage(),
                    'errorCode' => $e->getCode(),
                    'data'      => $dataAccount,
                );
            }

            if ($e->getCode() == 4007) {

                # Cuenta registrada pero no esta activa
                return array(
                    'status'    => false,
                    'message'   => $e->getMessage(),
                    'errorCode' => $e->getCode(),
                    'EMAIL'     => $this->EMAIL,
                );
            }

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

            # Definir parametros de clase
            $this->setParameters();

            # Definir de nuevo el control de intentos
            $this->setDefaultAttempts();

            # Verificar que no están vacíos
            if (Helper\Functions::e($this->DNI, $this->PASS)) {
                throw new ModelsException($this->errors['notParameters']['message'], $this->errors['notParameters']['code']);
            }

            # Añadir intentos
            $this->setNewAttempt();

            # Verificar intentos
            $this->maximumAttempts();

            # Validar FORMATO DNI si es cedula o RUC NATURAL
            $this->validacionDNI();

            # Verificar si usuario existe en base de datos GEMA
            $this->validacionBDDGEMA();

            # Verificar si uusuario ya tiene una cuenta electrónica y esta activa
            $this->cuentaElectronica();

            # SI EL CAMPO PASS NO ESTA VACIO VALIDA CONTRASEÑA y genera key
            $this->getPass();

            # Restaurar intentos
            $this->restoreAttempts();

            # GENERAR LA CLAVE KEY Y JWT
            $auth = new Model\Auth;

            return $auth->generateKey($this->USER);

        } catch (ModelsException $e) {

            $error = array(
                'status'    => false,
                'message'   => $e->getMessage(),
                'errorCode' => $e->getCode(),
            );

            # Si hay error por cuenta no activa devolver email
            if ($e->getCode() == 4007) {
                $error['EMAIL'] = $this->EMAIL;
            }

            return $error;
        }
    }

    private function dataAccount()
    {

        # Devolver valores si es proveedor para regsitrar

        # Conectar base de datos
        $this->conectar_Oracle();

        # Setear valores
        $codes = array_merge($this->CP_PTE, $this->CP_MED, $this->CP_PRO);
        $codes = implode(',', $codes);

        # Query
        $sql = " SELECT EMAILS AS EMAILS
                FROM WEB_VW_PERSONAS
                WHERE FK_PERSONA IN ($codes) ";

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        # Datos de usuario cuenta activa
        $pacientes = $stmt->fetch();

        # Conectar base de datos
        $this->conectar_Oracle();

        # Query
        $sql = "SELECT EMAIL_EMP AS EMAIL_EMP,
                EMAIL_PERS AS EMAIL_PERS
                FROM CP_VW_DATOS_PROV
                WHERE CODIGO_PERSONA IN ($codes) ";

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        # Datos de usuario cuenta activa
        $proveedores = $stmt->fetch();

        if ((false === $pacientes || is_null($pacientes['EMAILS'])) && false === $proveedores) {
            throw new ModelsException($this->errors['notEmailRegister']['message'], $this->errors['notEmailRegister']['code']);
        }

        $emails_proveedores = array($proveedores['EMAIL_EMP'], $proveedores['EMAIL_PERS']);

        $emails_pacientes = explode(';', $pacientes['EMAILS']);

        $_emails_pacientes = array();

        foreach ($emails_pacientes as $key => $value) {

            if (empty($value)) {
                unset($key);
            } else {
                $_emails_pacientes[] = explode(' ', $value)[1];
            }
        }

        $emails = array_merge($emails_proveedores, $_emails_pacientes);

        # SETEAR VALORES DE CORREO ELECTRONICO PARA PROVEEDORES
        $data_emails_for_register = array();

        # Extraer correos electrónicos para porterior registro de cuenta electrónica
        foreach ($emails as $key) {

            if (empty($key)) {
                unset($key);
            } else {
                $e                          = explode('@', $key);
                $data_emails_for_register[] =
                Helper\Strings::hiddenString($e[0], 4) . '@' . Helper\Strings::hiddenString($e[1], 4);
            }

        }

        return array_values(array_unique($data_emails_for_register));

    }

    private function cuentaElectronica()
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        # Setear valores
        $codes = array_merge($this->CP_PTE, $this->CP_MED, $this->CP_PRO);
        $codes = implode(',', $codes);

        $sql = " SELECT * FROM AAS_CLAVES_WEB
        WHERE PK_FK_PERSONA IN ($codes) ";

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $account = $stmt->fetch();

        # cUENTA ELECTRONICA NO ESTA REGISTRADA
        if (false === $account) {
            throw new ModelsException($this->errors['notRegisterAccount']['message'], $this->errors['notRegisterAccount']['code']);
        }

        # Cuenta electrónica esta registrada pero no activa
        if ($account['ESTADO'] == 'I') {

            $e     = explode('@', $account['CORREO']);
            $EMAIL = Helper\Strings::hiddenString($e[0]) . '@' . Helper\Strings::hiddenString($e[1], 4, 2);

            # setear email del usuario
            $this->EMAIL = $account['CORREO'];

            throw new ModelsException($this->errors['notActiveAccount']['message'] . $EMAIL, $this->errors['notActiveAccount']['code']);

        }

    }

    private function validacionBDDGEMA()
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        # Devolver todos los resultados
        $sql = "SELECT * FROM WEB2_VW_LOGIN
        WHERE CC = '" . $this->DNI . "' OR RUC LIKE '" . $this->DNI . "%'
        OR PASAPORTE LIKE '" . $this->DNI . "%' ";

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetchAll();

        if (count($data) === 0) {
            throw new ModelsException($this->errors['notExistedGema']['message'], $this->errors['notExistedGema']['code']);
        }

        # Extraer Roles de Usuario
        $roles = array(
            'PTE' => 0,
            'MED' => 0,
            'PRO' => 0,
            'VIP' => 0,
        );

        # Extraer Códigos de Persona para Pacientes
        $cp_pacientes = array();

        # Extraer Códigos de Persona para Médicos
        $cp_medicos = array();

        # Extraer Códigos de Persona para Proveedores
        $cp_proveedores = array();

        foreach ($data as $key => $value) {

            # Verificar si es Rol Paciente
            if (!is_null($value['COD_PTE'])) {
                $roles['PTE']   = 1;
                $cp_pacientes[] = (int) $value['COD_PTE'];
            }

            # Verificar si es Rol Médico
            if (!is_null($value['COD_MED'])) {
                $roles['MED'] = 1;
                $cp_medicos[] = (int) $value['COD_MED'];

            }

            # Verificar si es Rol Proveedor
            if (!is_null($value['COD_PROV'])) {
                $roles['PRO']     = 1;
                $cp_proveedores[] = (int) $value['COD_PROV'];

            }

            # Verificar si es Rol VIP
            if ($value['VIP'] != 0) {
                $roles['VIP'] = 1;
            }

            # SETEAR VALORES PARA DEFINICION
            if (!is_null($value['CC'])) {
                if (!is_null($value['COD_PTE'])) {
                    $this->COD_PERSONA = $value['COD_PTE'];
                } elseif (!is_null($value['COD_MED'])) {
                    $this->COD_PERSONA = $value['COD_MED'];
                }
            }

            # SETEAR VALORES PARA DEFINICION PARA PROVEEDOR
            if ($roles['PRO'] === 1 && $roles['PTE'] === 0 && $roles['MED'] === 0) {
                $this->COD_PERSONA = $value['COD_PROV'];
            }

        }

        # Extraer Códigos de Persona para Pacientes
        $_cp_pacientes['CP_PTE'] = array_unique($cp_pacientes);

        # Extraer Códigos de Persona para Médicos
        $_cp_medicos['CP_MED'] = array_unique($cp_medicos);

        # Extraer Códigos de Persona para Proveedores
        $_cp_proveedores['CP_PRO'] = array_unique($cp_proveedores);

        # Union de arrays
        $res = array_merge(array('DNI' => $this->DNI, 'COD_PERSONA' => (int) $this->COD_PERSONA), $roles, $_cp_pacientes, $_cp_medicos, $_cp_proveedores);

        # Ultima validacion antes de proseguir

        if ($roles['PTE'] === 0 && $roles['MED'] === 0 && $roles['PRO'] === 0) {
            throw new ModelsException($this->errors['notExistedGema']['message'], $this->errors['notExistedGema']['code']);
        }

        # Imprimir valores
        $this->USER   = $res;
        $this->CP_PTE = $_cp_pacientes['CP_PTE'];
        $this->CP_MED = $_cp_medicos['CP_MED'];
        $this->CP_PRO = $_cp_proveedores['CP_PRO'];

    }

    private function getPass()
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        # Setear valores
        $codes = array_merge($this->CP_PTE, $this->CP_MED, $this->CP_PRO);
        $codes = implode(',', $codes);

        $sql = " SELECT * FROM AAS_CLAVES_WEB
        WHERE PK_FK_PERSONA IN ($codes) ";

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $user = $stmt->fetch();

        # Cuenta no esta registrada electrónicamente
        if ($user === false) {
            throw new ModelsException($this->errors['notRegisterAccount']['message'], $this->errors['notRegisterAccount']['code']);
        }

        # cONTRASEÑA INCORRECTA
        if (!Helper\Strings::chash($user['CLAVE'], $this->PASS)) {

            throw new ModelsException($this->errors['incorrectPassword']['message'], $this->errors['incorrectPassword']['code']);

        }

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
