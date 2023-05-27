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
 * Modelo Register
 */
class Register extends Models implements IModels
{
    use DBModel;

    /**
     * Máximos intentos de inincio de sesión de un usuario
     *
     * @var int
     */
    const MAX_ATTEMPTS = 5;

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
    private $DNI = null;
    private $COD_PERSONA = null;
    private $USER = null;
    private $CP_PTE = null;
    private $CP_MED = null;
    private $CP_PRO = null;
    private $QSEC = null;
    private $EMAIL = null;
    private $PASS = null;
    private $TOKEN = null;
    private $_conexion = null;
    private $errors = null;
    private $proveedor = null;

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
        $PASS = $http->request->get('PASS');
        $Q1 = $http->request->get('Q1');
        $Q2 = $http->request->get('Q2');
        $Q3 = $http->request->get('Q3');
        $Q4 = $http->request->get('Q4');
        $Q5 = $http->request->get('Q5');

        $DNI = $this->db->scape($DNI);
        $PASS = $this->db->scape($PASS);

        if (strlen($DNI) < 3) {
            throw new ModelsException($this->errors['notAvalibleDNI']['message'], $this->errors['notAvalibleDNI']['code']);
        }

        if (Helper\Functions::e($DNI, $PASS)) {
            throw new ModelsException($this->errors['notParameters']['message'], $this->errors['notParameters']['code']);
        }

        # SETEAR variable spara registro
        $this->DNI = $DNI;
        $this->PASS = $PASS;
        $this->QSEC = $Q1;
    }

    private function setParameters_Qsec()
    {

        global $http, $config;

        $this->errors = $config['errors'];

        $DNI = strtoupper($http->request->get('DNI'));
        $Q1 = $http->request->get('Q1');
        $Q2 = $http->request->get('Q2');
        $Q3 = $http->request->get('Q3');
        $Q4 = $http->request->get('Q4');
        $Q5 = $http->request->get('Q5');

        $DNI = $this->db->scape($DNI);

        if (strlen($DNI) < 3) {
            throw new ModelsException($this->errors['notAvalibleDNI']['message'], $this->errors['notAvalibleDNI']['code']);
        }

        if (Helper\Functions::e($DNI)) {
            throw new ModelsException($this->errors['notParameters']['message'], $this->errors['notParameters']['code']);
        }

        # SETEAR variable spara registro
        $this->DNI = $DNI;
    }

    private function setParameters_Lostpass()
    {

        global $http, $config;

        $this->errors = $config['errors'];

        $DNI = strtoupper($http->request->get('DNI'));
        $Q1 = $http->request->get('Q1');
        $Q2 = $http->request->get('Q2');
        $Q3 = $http->request->get('Q3');
        $Q4 = $http->request->get('Q4');
        $Q5 = $http->request->get('Q5');

        $DNI = $this->db->scape($DNI);

        if (strlen($DNI) < 3) {
            throw new ModelsException($this->errors['notAvalibleDNI']['message'], $this->errors['notAvalibleDNI']['code']);
        }

        $qsec = array();

        if ($http->request->get('Q1') !== null) {
            $qsec['Q1'] = $http->request->get('Q1');
        }

        if ($http->request->get('Q2') !== null) {
            $qsec['Q2'] = $http->request->get('Q2');
        }

        if ($http->request->get('Q3') !== null) {
            $qsec['Q3'] = $http->request->get('Q3');
        }

        if ($http->request->get('Q4') !== null) {
            $qsec['Q4'] = $http->request->get('Q4');
        }

        if ($http->request->get('Q5') !== null) {
            $qsec['Q5'] = $http->request->get('Q5');
        }

        if (Helper\Functions::e($DNI)) {
            throw new ModelsException($this->errors['notParameters']['message'], $this->errors['notParameters']['code']);
        }

        if (count($qsec) == 0) {
            throw new ModelsException($this->errors['notParameters']['message'], $this->errors['notParameters']['code']);
        }

        # SETEAR variable spara registro
        $this->DNI = $DNI;
        $this->QSEC = $qsec;
    }

    private function setParameters_Changepass()
    {

        global $http, $config;

        $this->errors = $config['errors'];

        $TOKEN = $http->request->get('TOKEN');
        $PASS = $http->request->get('PASS');

        $PASS = $this->db->scape($PASS);

        if (Helper\Functions::e($PASS, $TOKEN)) {
            throw new ModelsException($this->errors['notParameters']['message'], $this->errors['notParameters']['code']);
        }

        # SETEAR variable spara registro
        $this->TOKEN = $TOKEN;
        $this->PASS = $PASS;
    }

    private function validacionDNI()
    {

        $cedula = $this->DNI;
        $dni = new Model\ValidacionDNI;

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
            $this->recentAttempts[$email]['time'] = null;
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
                $e = explode('@', $key);
                $data_emails_for_register[] =
                Helper\Strings::hiddenString($e[0]) . '@' . Helper\Strings::hiddenString($e[1], 3, 1);
            }

        }

        return array_values(array_unique($data_emails_for_register));

    }

    private function dataDetailAccount()
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
                $data_emails_for_register[] = $key;
            }

        }

        return array_values(array_unique($data_emails_for_register));

    }

    private function validarQsec()
    {
        global $http;

        # Obtener datros de preguntas de seguridad
        $this->getQsecAccount();
        /*

    if ($http->request->get('Q1') !== null) {
    if ($this->QSEC['Q1'] == $http->request->get('Q1')) {

    }
    }
     */

    }

    /**
     * Realiza la acción de login dentro del sistema
     *
     * @return array : Con información de éxito/falla al inicio de sesión.
     */
    public function lostpass_Api(): array
    {
        try {

            global $http;

            # Definir parametros de clase
            $this->setParameters_Lostpass();

            # Definir de nuevo el control de intentos
            # $this->setDefaultAttempts();

            # Añadir intentos
            # $this->setNewAttempt();

            # Verificar intentos
            # $this->maximumAttempts();

            # Validar FORMATO DNI si es cedula o RUC NATURAL
            # $this->validacionDNI();

            # Verificar si usuario existe en base de datos GEMA
            $this->validacionBDDGEMA();

            # Validar emails para registro
            $this->cuentaElectronica();

            # Validar Pregunats de seguridad
            $this->validarQsec();

            # Validar las preguntas de seguridad

            # URL PARA ACTTIVAR CUENTA desues de proceso de recuperar contraseña
            $token = str_shuffle(md5(time()) . md5(time()));

            # $link  = $http->getUri() . '/verify/' . $token . '&req=auth';

            $link = $token . '&req=lostpass';

            $this->TOKEN = $token;

            $this->iniChangePassWeb();

            $dataAccount = $this->dataAccount();
            $dataDetailAccount = $this->dataDetailAccount();

            return array(
                'status' => true,
                'message' => 'Recuperación de contraseña ejecutada con éxito. Verifique su correo electrónico para confirmar esta solicitud.',
                'verify' => $link,
                'token' => $token,
                'data' => $dataAccount,
                'emails' => $dataDetailAccount,
            );

        } catch (ModelsException $e) {

            $error = array(
                'status' => false,
                'message' => $e->getMessage(),
                'errorCode' => $e->getCode(),
                'data' => array(),
                'emails' => array(),
            );

            $dataAccount = $this->dataAccount();
            $dataDetailAccount = $this->dataDetailAccount();

            # Si hay error por cuenta no activa devolver email
            if ($e->getCode() == 4007) {
                $error['data'] = $dataAccount;
                $error['emails'] = $dataDetailAccount;
            }

            return $error;

        }
    }

    /**
     * Realiza la acción de login dentro del sistema
     *
     * @return array : Con información de éxito/falla al inicio de sesión.
     */
    public function lostpass_Api_GET(): array
    {
        try {

            global $http, $config;

            // DESCE
            $this->errors = $config['errors'];

            # Definir parametros de clase
            $this->DNI = $http->query->get('DNI');

            # Verificar si usuario existe en base de datos GEMA
            $this->validacionBDDGEMA();

            # Error
            return $this->getQsecDetailAccount();

        } catch (ModelsException $e) {

            $error = array(
                'status' => false,
                'message' => $e->getMessage(),
                'errorCode' => $e->getCode(),
                'data' => array(),
                'emails' => array(),
            );

            $dataAccount = $this->dataAccount();

            # Si hay error por cuenta no activa devolver email
            if ($e->getCode() == 4007) {
                $error['data'] = $dataAccount;
            }

            return $error;

        }
    }

    /**
     * Realiza la acción de login dentro del sistema
     *
     * @return array : Con información de éxito/falla al inicio de sesión.
     */
    public function changepass_Api(): array
    {
        try {

            global $http;

            # Definir parametros de clase
            $this->setParameters_Changepass();

            $this->PASS = Helper\Strings::hash($this->PASS);

            # $this->TOKEN = $token;
            # $this->getEmailAccount();

            # query de actualizacion de registro
            $this->changePassWeb();

            return array(
                'status' => true,
                'message' => 'Recuperación de contraseña ejecutada con éxito.',
            );

        } catch (ModelsException $e) {

            $error = array(
                'status' => false,
                'message' => $e->getMessage(),
                'errorCode' => $e->getCode(),

            );

            return $error;

        }
    }

    /**
     * Realiza la acción de login dentro del sistema
     *
     * @return array : Con información de éxito/falla al inicio de sesión.
     */
    public function register_QsecApi(): array
    {
        try {

            global $http;

            # Definir parametros de clase
            $this->setParameters_Qsec();

            # Definir de nuevo el control de intentos
            # $this->setDefaultAttempts();

            # Añadir intentos
            #$this->setNewAttempt();

            # Verificar intentos
            #$this->maximumAttempts();

            # Validar FORMATO DNI si es cedula o RUC NATURAL
            # $this->validacionDNI();

            # Verificar si usuario existe en base de datos GEMA
            $this->validacionBDDGEMA();

            # Verificar si uusuario ya tiene una cuenta electrónica y esta activa
            # $this->cuentaElectronica();

            # URL PARA ACTTIVAR CUENTA
            $token = str_shuffle(md5(time()) . md5(time()));

            # $link  = $http->getUri() . '/verify/' . $token . '&req=auth';
            $link = $token . '&req=auth';

            $this->TOKEN = $token;

            $qsec = array();

            if ($http->request->get('Q1') !== null) {
                $qsec[] = 'Q1:' . $http->request->get('Q1');
            }

            if ($http->request->get('Q2') !== null) {
                $qsec[] = 'Q2:' . $http->request->get('Q2');
            }

            if ($http->request->get('Q3') !== null) {
                $qsec[] = 'Q3:' . $http->request->get('Q3');
            }

            if ($http->request->get('Q4') !== null) {
                $qsec[] = 'Q4:' . $http->request->get('Q4');
            }

            if ($http->request->get('Q5') !== null) {
                $qsec[] = 'Q5:' . $http->request->get('Q5');
            }

            $hashQsec = Helper\Strings::ocrend_encode(implode(',', $qsec), 'hash');

            $this->QSEC = $hashQsec;

            $this->updateWebQsec();

            $dataAccount = $this->dataAccount();
            $dataDetailAccount = $this->dataDetailAccount();

            return array(
                'status' => true,
                'message' => 'Preguntas de Usuario registrado con éxito.',
            );

        } catch (ModelsException $e) {

            $error = array(
                'status' => false,
                'message' => $e->getMessage(),
                'errorCode' => $e->getCode(),
                'data' => array(),
                'emails' => array(),
            );

            # Si hay error por cuenta no activa devolver email
            if ($e->getCode() == 4007) {
                $error['data'] = $dataAccount;
                $error['emails'] = $dataDetailAccount;
            }

            return $error;

        }
    }

    /**
     * Realiza la acción de login dentro del sistema
     *
     * @return array : Con información de éxito/falla al inicio de sesión.
     */
    public function register_Api(): array
    {
        try {

            global $http;

            # Definir parametros de clase
            $this->setParameters();

            # Definir de nuevo el control de intentos
            # $this->setDefaultAttempts();

            # Añadir intentos
            #$this->setNewAttempt();

            # Verificar intentos
            #$this->maximumAttempts();

            # Validar FORMATO DNI si es cedula o RUC NATURAL
            # $this->validacionDNI();

            # Verificar si usuario existe en base de datos GEMA
            $this->validacionBDDGEMA();

            # Verificar si uusuario ya tiene una cuenta electrónica y esta activa
            # $this->cuentaElectronica();

            # URL PARA ACTTIVAR CUENTA
            $token = str_shuffle(md5(time()) . md5(time()));

            # $link  = $http->getUri() . '/verify/' . $token . '&req=auth';
            $link = $token . '&req=auth';

            $this->PASS = Helper\Strings::hash($this->PASS);
            $this->TOKEN = $token;

            $qsec = array();

            if ($http->request->get('Q1') !== null) {
                $qsec[] = 'Q1:' . $http->request->get('Q1');
            }

            if ($http->request->get('Q2') !== null) {
                $qsec[] = 'Q2:' . $http->request->get('Q2');
            }

            if ($http->request->get('Q3') !== null) {
                $qsec[] = 'Q3:' . $http->request->get('Q3');
            }

            if ($http->request->get('Q4') !== null) {
                $qsec[] = 'Q4:' . $http->request->get('Q4');
            }

            if ($http->request->get('Q5') !== null) {
                $qsec[] = 'Q5:' . $http->request->get('Q5');
            }

            $hashQsec = Helper\Strings::ocrend_encode(implode(',', $qsec), 'hash');

            $this->QSEC = $hashQsec;

            $this->registroWeb();

            $dataAccount = $this->dataAccount();
            $dataDetailAccount = $this->dataDetailAccount();

            return array(
                'status' => true,
                'message' => 'Usuario registrado con éxito. Cuenta electrónica debe activarse.',
                'verify' => $link,
                'data' => $dataAccount,
                'emails' => $dataDetailAccount,
            );

        } catch (ModelsException $e) {

            $error = array(
                'status' => false,
                'message' => $e->getMessage(),
                'errorCode' => $e->getCode(),
                'data' => array(),
                'emails' => array(),
            );

            # Si hay error por cuenta no activa devolver email
            if ($e->getCode() == 4007) {
                $error['data'] = $dataAccount;
                $error['emails'] = $dataDetailAccount;
            }

            return $error;

        }
    }

    private function setSpanishOracle()
    {

        $sql = "alter session set NLS_LANGUAGE = 'SPANISH'";
        # Execute
        $stmt = $this->_conexion->query($sql);

        $sql = "alter session set NLS_TERRITORY = 'SPAIN'";
        # Execute
        $stmt = $this->_conexion->query($sql);

        $sql = " alter session set NLS_DATE_FORMAT = 'DD-MM-YYYY' ";
        # Execute
        $stmt = $this->_conexion->query($sql);

    }

    private function verfiyEmails()
    {

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

        # Array s de emails
        $ems[] = array();

        foreach ($emails as $key => $value) {
            if (empty($value)) {
                unset($key);
            } else {
                $ems[] = trim($value);
            }

        }

        if (!in_array($this->EMAIL, $ems)) {
            throw new ModelsException($this->errors['incorrectMailRegister']['message'], $this->errors['incorrectMailRegister']['code']);
        }

    }

    private function cuentaElectronica()
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        # QueryBuilder
        $queryBuilder = $this->_conexion->createQueryBuilder();

        # Query
        $queryBuilder
            ->select('*')
            ->from('AAS_CLAVES_WEB')
            ->where('PK_FK_PERSONA = :COD_PERSONA')
            ->setParameter('COD_PERSONA', $this->COD_PERSONA)
        ;

        # Execute
        $stmt = $queryBuilder->execute();

        # Cerrar conexion
        $this->_conexion->close();

        # Datos de usuario cuenta activa
        $account = $stmt->fetch();

        if ($account === false) {
            throw new ModelsException($this->errors['notRegisterAccount']['message'], $this->errors['notRegisterAccount']['code']);
        }

        # Cuenta electrónica esta registrada pero no activa
        if ($account['ESTADO'] == 'I') {
            throw new ModelsException($this->errors['notActiveAccount']['message'], $this->errors['notActiveAccount']['code']);
        }

        # Cuenta electrónica esta registrada pero no activa
        if (is_null($account['STATUS_QSEC'])) {
            throw new ModelsException($this->errors['notQsecAccount']['message'], $this->errors['notQsecAccount']['code']);
        }

    }

    private function validacionBDDGEMA()
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        # Devolver todos los resultados
        $sql = "SELECT * FROM WEB2_VW_LOGIN
        WHERE COD_PTE IS NOT NULL AND CC = '" . $this->DNI . "'  OR PASAPORTE = '" . $this->DNI . "' ";

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
                $roles['PTE'] = 1;
                $cp_pacientes[] = (int) $value['COD_PTE'];
            }

            # Verificar si es Rol Médico
            if (!is_null($value['COD_MED'])) {
                $roles['MED'] = 1;
                $cp_medicos[] = (int) $value['COD_MED'];

            }

            # Verificar si es Rol Proveedor
            if (!is_null($value['COD_PROV'])) {
                $roles['PRO'] = 1;
                $cp_proveedores[] = (int) $value['COD_PROV'];

            }

            $roles['PTE'] = 1;
            $roles['MED'] = 0;
            $roles['PRO'] = 0;

            # Verificar si es Rol VIP
            if ($value['VIP'] != 0) {
                $roles['VIP'] = 1;
            }

            # SETEAR VALORES PARA DEFINICION
            if (!is_null($value['CC'])) {
                if (!is_null($value['COD_PTE'])) {
                    $this->COD_PERSONA = (int) $value['COD_PTE'];
                } elseif (!is_null($value['COD_MED'])) {
                    $this->COD_PERSONA = (int) $value['COD_MED'];
                }
            }

            # SETEAR VALORES PARA DEFINICION PARA PROVEEDOR
            if ($roles['PRO'] === 1 && $roles['PTE'] === 0 && $roles['MED'] === 0) {
                $this->COD_PERSONA = (int) $value['COD_PROV'];
            }

            # SETEAR VALORES PARA DEFINICION PARA MEDICO
            if ($roles['PRO'] === 1 && $roles['PTE'] === 1 && $roles['MED'] === 0) {
                $this->COD_PERSONA = (int) $value['COD_PTE'];
            }

        }

        # Extraer Códigos de Persona para Pacientes
        $_cp_pacientes['CP_PTE'] = array_unique($cp_pacientes);

        # Extraer Códigos de Persona para Médicos
        $_cp_medicos['CP_MED'] = array_unique($cp_medicos);

        # Extraer Códigos de Persona para Proveedores
        $_cp_proveedores['CP_PRO'] = array_unique($cp_proveedores);

        # Union de arrays
        $res = array_merge(
            array
            (
                'DNI' => $this->DNI,
                'COD_PERSONA' => (int) $this->COD_PERSONA,
            ),
            $roles,
            $_cp_pacientes,
            $_cp_medicos,
            $_cp_proveedores
        );

        # Ultima validacion antes de proseguir
        if ($roles['PTE'] === 0 && $roles['MED'] === 0 && $roles['PRO'] === 0) {
            throw new ModelsException($this->errors['notExistedGema']['message'], $this->errors['notExistedGema']['code']);
        }

        # Imprimir valores
        $this->USER = $res;
        $this->CP_PTE = $_cp_pacientes['CP_PTE'];
        $this->CP_MED = $_cp_medicos['CP_MED'];
        $this->CP_PRO = $_cp_proveedores['CP_PRO'];

        if ($this->COD_PERSONA == 0) {
            throw new ModelsException('Inconsistencia en BDD-GEMA', $this->errors['notExistedGema']['code']);
        }

    }

    private function getPass()
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        $this->setSpanishOracle();

        # QueryBuilder
        $queryBuilder = $this->_conexion->createQueryBuilder();

        # Query
        $queryBuilder
            ->select('AAS_CLAVES_WEB.*', 'ROWNUM AS ROWNUM_')
            ->from('AAS_CLAVES_WEB')
            ->where('PK_FK_PERSONA = :COD_PERSONA')
            ->setParameter('COD_PERSONA', $this->COD_PERSONA)
        ;

        # Execute
        $stmt = $queryBuilder->execute();

        # Cerrar conexion
        $this->_conexion->close();

        # Datos de usuario cuenta activa
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

    private function errors_registroWeb()
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        $this->setSpanishOracle();

        # Execute queryBuilder
        $queryBuilder = $this->_conexion->createQueryBuilder();

        # Query
        $queryBuilder
            ->select('*')
            ->from('AAS_CLAVES_WEB')
            ->where('PK_FK_PERSONA = :COD_PERSONA')
            ->setParameter('COD_PERSONA', $this->COD_PERSONA)
        ;

        $stmt = $queryBuilder->execute();

        $this->_conexion->close();

        $user = $stmt->fetch();

        # Cuenta ya esta registrada electrónicamente
        if ($user != false) {
            throw new ModelsException($this->errors['AccountisRegistered']['message'], $this->errors['AccountisRegistered']['code']);
        }

    }

    private function reset_registroWeb()
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        $this->setSpanishOracle();

        # Execute queryBuilder
        $queryBuilder = $this->_conexion->createQueryBuilder();

        # Query
        $queryBuilder
            ->select('*')
            ->from('AAS_CLAVES_WEB')
            ->where('PK_FK_PERSONA = :COD_PERSONA')
            ->setParameter('COD_PERSONA', $this->COD_PERSONA)
        ;

        $stmt = $queryBuilder->execute();

        $this->_conexion->close();

        $user = $stmt->fetch();

        # Cuenta ya esta registrada electrónicamente
        if ($user != false) {

            if (is_null($user['STATUS_QSEC'])) {

                # QueryBuilder
                $queryBuilder = $this->_conexion->createQueryBuilder();

                $queryBuilder
                    ->delete('AAS_CLAVES_WEB', 'u')
                    ->where('u.pk_fk_persona = ?')
                    ->setParameter(0, $this->COD_PERSONA)
                ;

                # Execute
                $stmt = $queryBuilder->execute();

                $this->_conexion->close();

            }

        }

    }

    private function updateWebQsec()
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        $this->setSpanishOracle();

        # Execute queryBuilder
        $queryBuilder = $this->_conexion->createQueryBuilder();

        # Insertar nuevo registro de cuenta electrónica.
        $queryBuilder
            ->update('AAS_CLAVES_WEB', 'u')
            ->set('u.STATUS_QSEC', '?')
            ->set('u.CONTENT_QSEC', '?')
            ->where('u.pk_fk_persona = ?')
            ->setParameter(0, '1')
            ->setParameter(1, $this->QSEC)
            ->setParameter(2, $this->COD_PERSONA)
        ;

        $stmt = $queryBuilder->execute();

        $this->_conexion->close();

    }

    private function registroWeb()
    {

        # control de errores
        //  $this->reset_registroWeb();

        # Conectar base de datos
        $this->conectar_Oracle();

        $this->setSpanishOracle();

        # Execute queryBuilder
        $queryBuilder = $this->_conexion->createQueryBuilder();

        # Insertar nuevo registro de cuenta electrónica.
        $queryBuilder
            ->insert('AAS_CLAVES_WEB')
            ->values(
                array(
                    'PK_FK_PERSONA' => '?',
                    'CLAVE' => '?',
                    'CLAVE_ANTERIOR' => '?',
                    'FECHA_REGISTRO' => '?',
                    'ESTADO' => '?',
                    'STATUS_QSEC' => '?',
                    'CONTENT_QSEC' => '?',
                )
            )
            ->setParameter(0, $this->COD_PERSONA)
            ->setParameter(1, $this->PASS)
            ->setParameter(2, $this->TOKEN)
            ->setParameter(3, date('d/m/Y'))
            ->setParameter(4, 'I')
            ->setParameter(5, '1')
            ->setParameter(6, $this->QSEC);

        $nuevo_registro = $queryBuilder->execute();

        $this->_conexion->close();

    }

    private function iniChangePassWeb()
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        $this->setSpanishOracle();

        # QueryBuilder
        $queryBuilder = $this->_conexion->createQueryBuilder();

        $queryBuilder
            ->update('AAS_CLAVES_WEB', 'u')
            ->set('u.clave_anterior', '?')
            ->where('u.pk_fk_persona = ?')
            ->setParameter(0, $this->TOKEN . '&req=lostpass')
            ->setParameter(1, $this->COD_PERSONA)
        ;

        # Execute
        $stmt = $queryBuilder->execute();

        $this->_conexion->close();

    }

    private function changePassWeb()
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        $this->setSpanishOracle();

        # Execute queryBuilder
        $queryBuilder = $this->_conexion->createQueryBuilder();

        # Query
        $queryBuilder
            ->select('*')
            ->from('AAS_CLAVES_WEB')
            ->where('clave_anterior = ?')
            ->setParameter(0, $this->TOKEN)
        ;

        $stmt = $queryBuilder->execute();

        $this->_conexion->close();

        $user = $stmt->fetch();

        # Cuenta ya esta registrada electrónicamente
        if ($user == false) {
            throw new ModelsException('Usted debe confirmar esta solicitud de cambio de contraseña antes de continuar.', $this->errors['notRegisterAccount']['code']);
        }

        # Conectar base de datos
        $this->conectar_Oracle();

        $this->setSpanishOracle();

        # QueryBuilder
        $queryBuilder = $this->_conexion->createQueryBuilder();

        $queryBuilder
            ->update('AAS_CLAVES_WEB', 'u')
            ->set('u.clave', '?')
            ->set('u.clave_anterior', '?')
            ->set('u.fecha_registro', '?')
            ->where('u.clave_anterior = ?')
            ->setParameter(0, $this->PASS)
            ->setParameter(1, '')
            ->setParameter(2, date('d/m/Y'))
            ->setParameter(3, $this->TOKEN)
        ;

        # Execute
        $stmt = $queryBuilder->execute();

        $this->_conexion->close();
    }

    private function check_emails_Account_Active()
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        $this->setSpanishOracle();

        # QueryBuilder
        $queryBuilder = $this->_conexion->createQueryBuilder();

        # Query
        $queryBuilder
            ->select('CORREO')
            ->from('AAS_CLAVES_WEB')
            ->where('PK_FK_PERSONA = :COD_PERSONA')
            ->andWhere('CORREO = :CORREO')
            ->setParameter('COD_PERSONA', $this->COD_PERSONA)
            ->setParameter('CORREO', $this->EMAIL)
        ;

        # Execute
        $stmt = $queryBuilder->execute();

        # Cerrar conexion
        $this->_conexion->close();

        # Datos de usuario cuenta activa
        $data = $stmt->fetch();

        if (false == $data) {
            throw new ModelsException($this->errors['incorrectMailRegister']['message'], $this->errors['incorrectMailRegister']['code']);
        }

    }

    private function getEmailAccount()
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Query
            $queryBuilder
                ->select('AAS_CLAVES_WEB.*', 'ROWNUM as ROWNUM_')
                ->from('AAS_CLAVES_WEB')
                ->where('CLAVE_ANTERIOR = :CLAVE_ANTERIOR')
                ->setParameter('CLAVE_ANTERIOR', $this->TOKEN)
            ;

            # Execute
            $stmt = $queryBuilder->execute();

            # Cerrar conexion
            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $pte = $stmt->fetch();

            if (false === $pte) {
                throw new ModelsException('!Error! Usuario ya tiene una cuenta electrónica registrada.', 4010);
            }

            # valor final
            $this->EMAIL = $pte['CORREO'];

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }
    }

    private function getQsecDetailAccount()
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Query
            $queryBuilder
                ->select('AAS_CLAVES_WEB.*', 'ROWNUM as ROWNUM_')
                ->from('AAS_CLAVES_WEB')
                ->where('PK_FK_PERSONA = :COD_PERSONA')
                ->setParameter('COD_PERSONA', $this->COD_PERSONA)
            ;

            # Execute
            $stmt = $queryBuilder->execute();

            # Cerrar conexion
            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $pte = $stmt->fetch();

            if ($pte === false) {
                throw new ModelsException($this->errors['notRegisterAccount']['message'], $this->errors['notRegisterAccount']['code']);
            }

            # Cuenta electrónica esta registrada pero no activa
            if ($pte['ESTADO'] == 'I') {
                throw new ModelsException($this->errors['notActiveAccount']['message'], $this->errors['notActiveAccount']['code']);
            }

            # Cuenta electrónica esta registrada pero no activa
            if (is_null($pte['STATUS_QSEC'])) {
                throw new ModelsException($this->errors['notQsecAccount']['message'], $this->errors['notQsecAccount']['code']);
            }

            $contentQsec = Helper\Strings::ocrend_decode($pte['CONTENT_QSEC'], 'hash');

            $this->QSEC = explode(',', $contentQsec);

            $formatQuestins = array();

            foreach ($this->QSEC as $key) {
                $k = explode(':', $key);
                $formatQuestins[] = $k[0];
            }

            return array('status' => true, 'data' => $formatQuestins);

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }
    }

    private function getQsecAccount()
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Query
            $queryBuilder
                ->select('AAS_CLAVES_WEB.*', 'ROWNUM as ROWNUM_')
                ->from('AAS_CLAVES_WEB')
                ->where('PK_FK_PERSONA = :COD_PERSONA')
                ->setParameter('COD_PERSONA', $this->COD_PERSONA)
            ;

            # Execute
            $stmt = $queryBuilder->execute();

            # Cerrar conexion
            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $pte = $stmt->fetch();

            if (false === $pte) {
                throw new ModelsException('!Error! Usuario no existe.', 4010);
            }

            $contentQsec = Helper\Strings::ocrend_decode($pte['CONTENT_QSEC'], 'hash');

            $this->QSEC = explode(',', $contentQsec);

            $formatQuestins = array();

            foreach ($this->QSEC as $key) {
                $k = explode(':', $key);
                $formatQuestins[$k[0]] = $k[1];
            }

            $this->QSEC = $formatQuestins;

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
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
