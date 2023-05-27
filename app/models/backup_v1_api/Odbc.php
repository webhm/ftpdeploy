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
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Odbc
 */

class Odbc extends Models implements IModels
{

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

    }

    private function errorsPaginationMedicos($limit)
    {

        try {

            if ($limit > 25) {
                throw new ModelsException('!Error! Solo se pueden mostrar 25 resultados por página.');
            }

            if ($limit == 0 or $limit < 0) {
                throw new ModelsException('!Error! {Limit} no puede ser 0 o negativo');

            }

            return false;
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function errorsPagination($limit)
    {

        try {

            if ($limit > 20) {
                throw new ModelsException('!Error! Solo se pueden mostrar 20 resultados por página.');
            }

            if ($limit == 0 or $limit < 0) {
                throw new ModelsException('!Error! {Limit} no puede ser 0 o negativo');

            }

            return false;
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function errorsPaginationFacturas($limit)
    {

        try {

            if ($limit > 10) {
                throw new ModelsException('!Error! Solo se pueden mostrar 10 resultados por página.');
            }

            if ($limit == 0 or $limit < 0) {
                throw new ModelsException('!Error! ' . $limit . ' no puede ser 0 o negativo');

            }

            return false;
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function errorsValidacion($cedula = '')
    {

        try {

            $dni = new Model\ValidacionDNI;
            # si es cédula
            if (ctype_digit(Helper\Strings::remove_spaces($cedula))) {

                #validar si es ruc o si es cedula normal
                if (strlen(Helper\Strings::remove_spaces($cedula)) == 10) {
                    if (!$dni->validarCedula($cedula)) {
                        throw new ModelsException('!Error! Cédula ingresada no es válida.', 4003);
                    }
                }

                # si documento estrangero
                if ((strlen(Helper\Strings::remove_spaces($cedula)) > 13 and strlen(Helper\Strings::remove_spaces($cedula)) > 25)) {
                    throw new ModelsException('!Error! Documento extrangero no puede ser mayor que 25 caracteres.', 4005);
                }

            }

            return false;
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }
    }

    public function getPacienteAuth($FK_PERSONA = ''): array
    {

        $sql   = "SELECT WEB_VW_PERSONAS.*, ROWNUM as ROWNUM_ FROM WEB_VW_PERSONAS WHERE FK_PERSONA='$FK_PERSONA'";
        $filas = 0;

        $stmt = oci_parse($this->conectar_Oracle(), $sql); // Preparar la sentencia
        $ok   = oci_execute($stmt); // Ejecutar la sentencia
        $arr  = array();

        if ($ok == true) {
            /* Mostrar los datos. Lo hacemos de este modo puesto que no es posible obtener el número de
            registros sin antes haber accedido a los datos mediante las funciones 'oci_fetch_*'):
             */
            if ($obj = oci_fetch_object($stmt)) {

                // Recorrer el resource y mostrar los datos (HAY QUE PONER LOS NOMBRES DE LOS CAMPOS EN MAYÚSCULAS):
                do {

                    // EXAMPLE 141054737 // T62790414

                    $arr[] = array(

                        'NOMBRES'     => $obj->NOMBRES,
                        'APELLIDOS'   => $obj->APELLIDOS,
                        'DIRECCIONES' => $obj->DIRECCIONES,
                        'TELEFONOS'   => $obj->TELEFONOS,
                        'CELULARES'   => $obj->CELULARES,
                        'EMAILS'      => $obj->EMAILS,

                    );

                } while ($obj = oci_fetch_object($stmt));

            } else {
                $arr[] = false;
            }

        } else {
            $ok = false;
        }

        oci_free_statement($stmt); // Liberar los recursos asociados a una sentencia o cursor

        oci_close($this->conectar_Oracle());

        if (!$arr[0]) {

            return array(
                'status'  => $arr[0],
                'message' => 'El número de cédula o RUC ingresado presenta algún problema o no existe en BDD GEMA',
            );
        }

        $DIRECCIONES = array();

        foreach (explode(';', $arr[0]['DIRECCIONES']) as $key => $value) {

            $FIELD = explode(' ', $value);
            unset($FIELD[0]);

            $DIRECCIONES[] = array(
                'ID'    => intval(explode(' ', $value)[0]),
                'FIELD' => implode(' ', $FIELD),
            );
        }

        $arr[0]['DIRECCIONES'] = $DIRECCIONES;

        $TELEFONOS = array();

        foreach (explode(';', $arr[0]['TELEFONOS']) as $key => $value) {

            $FIELD = explode(' ', $value);
            unset($FIELD[0]);

            $TELEFONOS[] = array(
                'ID'    => intval(explode(' ', $value)[0]),
                'FIELD' => implode(' ', $FIELD),
            );
        }

        $arr[0]['TELEFONOS'] = $TELEFONOS;

        $CELULARES = array();

        foreach (explode(';', $arr[0]['CELULARES']) as $key => $value) {

            $FIELD = explode(' ', $value);
            unset($FIELD[0]);

            $CELULARES[] = array(
                'ID'    => intval(explode(' ', $value)[0]),
                'FIELD' => implode(' ', $FIELD),
            );
        }

        $arr[0]['CELULARES'] = $CELULARES;

        $EMAILS = array();

        foreach (explode(';', $arr[0]['EMAILS']) as $key => $value) {

            $FIELD = explode(' ', $value);
            unset($FIELD[0]);

            $EMAILS[] = array(
                'ID'    => intval(explode(' ', $value)[0]),
                'FIELD' => Helper\Strings::remove_spaces(implode(' ', $FIELD)),
            );
        }

        $arr[0]['EMAILS'] = $EMAILS;

        // 1706962840

        return array(
            'status' => true,
            'data'   => $arr,
        );

    }

    public function getPacientePedido($COD_PERSONA): array
    {
        global $http;

        $sql = "SELECT WEB_VW_PERSONAS.*, ROWNUM as ROWNUM_ FROM WEB_VW_PERSONAS WHERE FK_PERSONA='$COD_PERSONA'";

        # Conectar base de datos
        $this->conectar_Oracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        # Cerrar conexion
        $this->_conexion->close();

        # DATOS DEL PTE
        $pte = $stmt->fetch();

        if (false === $pte) {

            return array(
                'status'  => false,
                'message' => 'El número de cédula o RUC ingresado presenta algún problema o no existe en BDD GEMA',
            );
        }

        $arr[] = array(

            'APELLIDOS'   => $pte['APELLIDOS'],
            'NOMBRES'     => $pte['NOMBRES'],
            'DIRECCIONES' => $pte['DIRECCIONES'],
            'TELEFONOS'   => $pte['TELEFONOS'],
            'CELULARES'   => $pte['CELULARES'],
            'EMAILS'      => $pte['EMAILS'],

        );

        $DIRECCIONES = array();

        foreach (explode(';', $arr[0]['DIRECCIONES']) as $key => $value) {

            $FIELD = explode(' ', $value);
            unset($FIELD[0]);

            $DIRECCIONES[] = array(
                'ID'    => intval(explode(' ', $value)[0]),
                'FIELD' => implode(' ', $FIELD),
            );
        }

        $arr[0]['DIRECCIONES'] = $DIRECCIONES;

        $TELEFONOS = array();

        foreach (explode(';', $arr[0]['TELEFONOS']) as $key => $value) {

            $FIELD = explode(' ', $value);
            unset($FIELD[0]);

            $TELEFONOS[] = array(
                'ID'    => intval(explode(' ', $value)[0]),
                'FIELD' => implode(' ', $FIELD),
            );
        }

        $arr[0]['TELEFONOS'] = $TELEFONOS;

        $CELULARES = array();

        foreach (explode(';', $arr[0]['CELULARES']) as $key => $value) {

            $FIELD = explode(' ', $value);
            unset($FIELD[0]);

            $CELULARES[] = array(
                'ID'    => intval(explode(' ', $value)[0]),
                'FIELD' => implode(' ', $FIELD),
            );
        }

        $arr[0]['CELULARES'] = $CELULARES;

        $EMAILS = array();

        foreach (explode(';', $arr[0]['EMAILS']) as $key => $value) {

            $FIELD = explode(' ', $value);
            unset($FIELD[0]);

            $EMAILS[] = array(
                'ID'    => intval(explode(' ', $value)[0]),
                'FIELD' => implode(' ', $FIELD),
            );
        }

        $arr[0]['EMAILS'] = $EMAILS;

        return array(
            'status' => true,
            'data'   => $arr,
        );

    }

    public function getPaciente(): array
    {
        global $http;

        $token = $http->headers->get("Authorization");

        # EXTRAER CÉDULA DEL OBJETO TOKEN
        $auth       = new Model\Auth;
        $FK_PERSONA = $auth->GetData($token)->data[0]->COD;
        $DNI        = $auth->GetData($token)->data[0]->DNI;

        # maximo de resultados por pagina de la pai 20
        $error = $this->errorsValidacion($DNI);
        if (!is_bool($error)) {
            return $error;
        }

        $sql   = "SELECT WEB_VW_PERSONAS.*, ROWNUM as ROWNUM_ FROM WEB_VW_PERSONAS WHERE FK_PERSONA='$FK_PERSONA'";
        $filas = 0;

        $stmt = oci_parse($this->conectar_Oracle(), $sql); // Preparar la sentencia
        $ok   = oci_execute($stmt); // Ejecutar la sentencia
        $arr  = array();

        if ($ok == true) {
            /* Mostrar los datos. Lo hacemos de este modo puesto que no es posible obtener el número de
            registros sin antes haber accedido a los datos mediante las funciones 'oci_fetch_*'):
             */
            if ($obj = oci_fetch_object($stmt)) {

                // Recorrer el resource y mostrar los datos (HAY QUE PONER LOS NOMBRES DE LOS CAMPOS EN MAYÚSCULAS):
                do {

                    // EXAMPLE 141054737 // T62790414

                    $arr[] = array(

                        'APELLIDOS'   => $obj->APELLIDOS,
                        'NOMBRES'     => $obj->NOMBRES,
                        'DIRECCIONES' => $obj->DIRECCIONES,
                        'TELEFONOS'   => $obj->TELEFONOS,
                        'CELULARES'   => $obj->CELULARES,
                        'EMAILS'      => $obj->EMAILS,

                    );

                } while ($obj = oci_fetch_object($stmt));

            } else {
                $arr[] = false;
            }

        } else {
            $ok = false;
        }

        oci_free_statement($stmt); // Liberar los recursos asociados a una sentencia o cursor

        oci_close($this->conectar_Oracle());

        if (!$arr[0]) {

            return array(
                'status'  => $arr[0],
                'message' => 'El número de cédula o RUC ingresado presenta algún problema o no existe en BDD GEMA',
            );
        }

        $DIRECCIONES = array();

        foreach (explode(';', $arr[0]['DIRECCIONES']) as $key => $value) {

            $FIELD = explode(' ', $value);
            unset($FIELD[0]);

            $DIRECCIONES[] = array(
                'ID'    => intval(explode(' ', $value)[0]),
                'FIELD' => implode(' ', $FIELD),
            );
        }

        $arr[0]['DIRECCIONES'] = $DIRECCIONES;

        $TELEFONOS = array();

        foreach (explode(';', $arr[0]['TELEFONOS']) as $key => $value) {

            $FIELD = explode(' ', $value);
            unset($FIELD[0]);

            $TELEFONOS[] = array(
                'ID'    => intval(explode(' ', $value)[0]),
                'FIELD' => implode(' ', $FIELD),
            );
        }

        $arr[0]['TELEFONOS'] = $TELEFONOS;

        $CELULARES = array();

        foreach (explode(';', $arr[0]['CELULARES']) as $key => $value) {

            $FIELD = explode(' ', $value);
            unset($FIELD[0]);

            $CELULARES[] = array(
                'ID'    => intval(explode(' ', $value)[0]),
                'FIELD' => implode(' ', $FIELD),
            );
        }

        $arr[0]['CELULARES'] = $CELULARES;

        $EMAILS = array();

        foreach (explode(';', $arr[0]['EMAILS']) as $key => $value) {

            $FIELD = explode(' ', $value);
            unset($FIELD[0]);

            $EMAILS[] = array(
                'ID'    => intval(explode(' ', $value)[0]),
                'FIELD' => implode(' ', $FIELD),
            );
        }

        $arr[0]['EMAILS'] = $EMAILS;

        return array(
            'status' => $ok,
            'data'   => $arr,
        );

    }

    public function getHistorialPaciente($limit = 10, $offset = 1): array
    {
        global $http;

        $token = $http->headers->get("Authorization");

        # EXTRAER CÉDULA DEL OBJETO TOKEN
        $auth   = new Model\Auth;
        $FK_NHC = $auth->GetData($token)->data[0]->NHC;
        $DNI    = $auth->GetData($token)->data[0]->DNI;

        # maximo de resultados por pagina de la pai 20
        $error = $this->errorsValidacion($DNI);
        if (!is_bool($error)) {
            return $error;
        }

        $error = $this->errorsPaginationFacturas($limit);
        if (!is_bool($error)) {
            return $error;

        }

        if ($offset == 0 or $offset < 0) {
            return array('status' => false, 'message' => '!Error! {Offset} no puede ser 0 o negativo.');
        }

        $sql   = "SELECT * FROM web_vw_atenciones WHERE HCL='$FK_NHC'";
        $filas = 0;

        $stmt = oci_parse($this->conectar_Oracle(), $sql); // Preparar la sentencia
        $ok   = oci_execute($stmt); // Ejecutar la sentencia
        $arr  = array();

        if ($ok == true) {
            /* Mostrar los datos. Lo hacemos de este modo puesto que no es posible obtener el número de
            registros sin antes haber accedido a los datos mediante las funciones 'oci_fetch_*'):
             */
            if ($obj = oci_fetch_object($stmt)) {

                // Recorrer el resource y mostrar los datos (HAY QUE PONER LOS NOMBRES DE LOS CAMPOS EN MAYÚSCULAS):
                do {

                    // EXAMPLE 141054737 // T62790414

                    $obj->NHC = $obj->HCL;
                    $obj->ADM = $obj->ADMISION;
                    unset($obj->HCL);
                    unset($obj->ADMISION);

                    $arr[] = $obj;

                } while ($obj = oci_fetch_object($stmt));

            } else {
                $arr[] = false;
            }

        } else {
            $ok = false;
        }

        oci_free_statement($stmt); // Liberar los recursos asociados a una sentencia o cursor

        oci_close($this->conectar_Oracle());

        $historial = array();

        array_unshift($arr, "");
        unset($arr[0]);

        if ($offset == 1) {
            foreach ($arr as $key => $val) {
                if ($key >= $offset and $key <= $limit) {
                    $val->NUM    = $key;
                    $historial[] = $val;
                }
            }
        } else {

            $inicio = $limit * $offset - $limit + 1; // 3*2-3+1=4
            $final  = $inicio + $limit - 1; // 6+3-1=6
            foreach ($arr as $key => $val) {
                if ($key >= $inicio and $key <= $final) {
                    $val->NUM    = $key;
                    $historial[] = $val;
                }
            }
        }

        $url = explode('/', $http->getPathInfo());

        $paging = array(
            'prev' => ($offset == 1) ? '/' . $url[1] . '/' . $url[2] . '/' . 1 : '/' . $url[1] . '/' . $url[2] . '/' . $url[3] . '/' . ($offset - 1),
            'page' => $http->getPathInfo(),
            'next' => ($offset == 1) ? '/' . $url[1] . '/' . $url[2] . '/' . $url[3] . '/' . 2 : '/' . $url[1] . '/' . $url[2] . '/' . $url[3] . '/' . ($offset + 1),

        );

        if ($offset == 1) {
            unset($paging['prev']);
        }

        // VALIDACION NO HAY MAS FACTURAS DEL USUARIO

        if (count($arr) <= $limit) {

            unset($paging['prev']);
            unset($paging['next']);

            return array(
                'status' => $ok,
                'data'   => $historial,
                'limit'  => intval($limit),
                'paging' => $paging,

            );

        }

        if (count($historial) == 0) {

            unset($paging['next']);

            return array(
                'status'  => false,
                'message' => 'No existen más resultados.',
                'limit'   => intval($limit),
                'paging'  => $paging,
            );
            # code...
        }

        return array(
            'status' => $ok,
            'data'   => $historial,
            'limit'  => intval($limit),
            'paging' => $paging,
        );

    }

    public function getAllPacientes($limit = 20, $offset = 1): array
    {
        global $http;

        # maximo de resultados por pagina de la pai 20
        $error = $this->errorsPagination($limit);
        if (!is_bool($error)) {
            return $error;
        }

        if ($offset == 1) {
            $sql = "SELECT * FROM (select CAD_VW_PACIENTES.*, ROWNUM as ROWNUM_ from CAD_VW_PACIENTES)  WHERE ROWNUM_ BETWEEN $offset AND $limit";
        } else {

            $inicio = $limit * $offset - $limit + 1; // 3*2-3+1=4
            $final  = $inicio + $limit - 1; // 6+3-1=6

            $sql = "SELECT * FROM (select CAD_VW_PACIENTES.*, ROWNUM as ROWNUM_ from CAD_VW_PACIENTES)  WHERE ROWNUM_ BETWEEN $inicio AND $final";

        }

        $filas = 0;

        $stmt = oci_parse($this->conectar_Oracle(), $sql); // Preparar la sentencia
        $ok   = oci_execute($stmt); // Ejecutar la sentencia
        $arr  = array();

        if ($ok == true) {
            /* Mostrar los datos. Lo hacemos de este modo puesto que no es posible obtener el número de
            registros sin antes haber accedido a los datos mediante las funciones 'oci_fetch_*'):
             */
            if ($obj = oci_fetch_object($stmt)) {

                // Recorrer el resource y mostrar los datos (HAY QUE PONER LOS NOMBRES DE LOS CAMPOS EN MAYÚSCULAS):
                do {

                    $arr[] = array(

                        'NUM'              => intval($obj->ROWNUM_),
                        'NHCU'             => intval($obj->NHCL),
                        'COD_PERSONA'      => intval($obj->COD_PERSONA),
                        'PRIMER_APELLIDO'  => $obj->PRIMER_APELLIDO,
                        'SEGUNDO_APELLIDO' => $obj->SEGUNDO_APELLIDO,
                        'PRIMER_NOMBRE'    => $obj->PRIMER_NOMBRE,
                        'SEGUNDO_NOMBRE'   => $obj->SEGUNDO_NOMBRE,
                        'CEDULA'           => $obj->CEDULA,

                    );

                } while ($obj = oci_fetch_object($stmt));

            } else {
                $arr[] = false;
            }

        } else {
            $ok = false;
        }

        oci_free_statement($stmt); // Liberar los recursos asociados a una sentencia o cursor

        oci_close($this->conectar_Oracle());

        $url = explode('/', $http->getPathInfo());

        $paging = array(
            'prev' => ($offset == 1) ? '/' . $url[1] . '/' . $url[2] . '/' . 1 : '/' . $url[1] . '/' . $url[2] . '/' . ($offset - 1),
            'page' => $http->getPathInfo(),
            'next' => ($offset == 1) ? '/' . $url[1] . '/' . $url[2] . '/' . 2 : '/' . $url[1] . '/' . $url[2] . '/' . ($offset + 1),

        );

        if ($offset == 1) {
            unset($paging['prev']);
        }

        return array(
            'status' => $ok,
            'data'   => $arr,
            'limit'  => intval($limit),
            'paging' => $paging,
        );
    }

    # VERIFICAR SI TIENE YA CLAVE CASO CONTRARIO REGISTRAR CLAVE

    public function getPass($FK_PERSONA = '')
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        # Query
        $sql = "SELECT AAS_CLAVES_WEB.*, ROWNUM as ROWNUM_ FROM AAS_CLAVES_WEB WHERE PK_FK_PERSONA='$FK_PERSONA' ";

        # Execute
        $stmt = $this->_conexion->query($sql);

        # Datos de usuario cuenta activa
        $user = $stmt->fetch();

        # Cuenta no esta registrada electrónicamente
        if ($user === false) {
            return array(
                'status'    => false,
                'message'   => 'Cuenta electrónica no esta registrada.',
                'errorCode' => 4006,
            );
        }

        # Array de datos de usuario
        $arr['PASS']   = $user['CLAVE'];
        $arr['EMAIL']  = $user['CORREO'];
        $arr['ESTADO'] = $user['ESTADO'];

        # Cuenta electrónica esta registrada pero no activa
        if ($arr['ESTADO'] == 'I') {
            $e     = explode('@', $arr['EMAIL']);
            $EMAIL = Helper\Strings::hiddenString($e[0]) . '@' . Helper\Strings::hiddenString($e[1], 4, 2);
            return array(
                'status'    => false,
                'message'   => '¡Error! Cuenta electrónica sin activar. Active su cuenta mediante el correo enviado a: ' . $EMAIL,
                'EMAIL'     => $EMAIL,
                'errorCode' => 4007,
            );
        }

        # Cuenta si esta registrada electrónicamente y esta activada
        return array(
            'status' => true,
            'pass'   => $arr['PASS'],
        );

    }

    public function getAuth($cedula = '1711322451'): array
    {

        # VALIDACIONDE FORMATO DE CEDULA
        $error = $this->errorsValidacion($cedula);
        if (!is_bool($error)) {
            return $error;
        }

        # Conectar base de datos
        $this->conectar_Oracle();

        # Query
        $sql = "SELECT WEB_VW_LOGIN.*, ROWNUM as ROWNUM_ FROM WEB_VW_LOGIN WHERE CC='$cedula' OR RUC='$cedula' OR PASAPORTE='$cedula' ";

        # Execute
        $stmt = $this->_conexion->query($sql);

        # Cerrar conexion
        $this->_conexion->close();

        # Datos de usuario cuenta activa
        $user = $stmt->fetch();

        if (false === $user) {

            return array(
                'status'    => false,
                'message'   => 'El número de cédula ingresado presenta algún problema o no existe en BDD GEMA',
                'errorCode' => 4004,
            );
        }

        # Setear variables de usuario login api
        $arr[] = array(

            'DNI' => $user['CC'],
            'NHC' => $user['NHCL'],
            'COD' => $user['COD_PERSONA'],
            'PTE' => intval($user['PTE']),
            'MED' => intval($user['MED']),
            'PRO' => intval($user['PRO']),
            # DEFINIR LÓGICA PARA PACIETNES VIP
            'VIP' => intval($user['VIP']),

        );

        # Verificar si tiene registro electrónico
        $gestPass = $this->getPass($arr[0]['COD']);

        if (false === $gestPass['status']) {

            $d = array(
                'status'    => true,
                'account'   => $gestPass['status'],
                'message'   => $gestPass['message'],
                'data'      => $arr,
                'errorCode' => $gestPass['errorCode'],
            );
            # SOLO SI TIENE EMAIL EL CAMPO
            if (isset($gestPass['EMAIL'])) {
                $d['EMAIL'] = $gestPass['EMAIL'];
            }
            return $d;
        }

        return array(
            'status'  => true,
            'account' => $gestPass['status'],
            'data'    => $arr,
        );

    }

    private function erroresRegistroWeb(array $user)
    {

        $COD   = $user['COD'];
        $EMAIL = $user['EMAIL'];

        try {

            # paciente ya tiene cuenta electrónica
            $sql = "SELECT AAS_CLAVES_WEB.*, ROWNUM as ROWNUM_ FROM AAS_CLAVES_WEB WHERE PK_FK_PERSONA='$COD'";

            $stmt = oci_parse($this->conectar_Oracle(), $sql); // Preparar la sentencia
            $ok   = oci_execute($stmt); // Ejecutar la sentencia
            $arr  = true;

            if ($ok == true) {

                if ($obj = oci_fetch_object($stmt)) {
                    $arr = true;
                } else {
                    $arr = false;
                }

            } else {
                $ok = false;
            }

            oci_free_statement($stmt); // Liberar los recursos asociados a una sentencia o cursor
            oci_close($this->conectar_Oracle());

            # validar si es RUC
            if ($arr) {
                throw new ModelsException('!Error! Usuario ya tiene una cuenta electrónica registrada.', 4010);
            }

            $emails = $this->getPacienteAuth($COD);

            # Arrasy de emails del usuario
            $ems = array();

            foreach ($emails['data'][0]['EMAILS'] as $key => $value) {
                $ems[] = $value['FIELD'];
            }

            if (in_array($EMAIL, $ems)) {
                return true;
            } else {
                throw new ModelsException('¡Error! Correo electrónico no esta asociado al usuario.', 4011);
            }

            return false;
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }
    }

    public function registroWeb(array $user)
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        # Execute

        $queryBuilder = $this->_conexion->createQueryBuilder();

        $COD   = $user['COD'];
        $PASS  = $user['PASS'];
        $EMAIL = $user['EMAIL'];
        $TOKEN = $user['TOKEN'];

        $queryBuilder
            ->insert('AAS_CLAVES_WEB')
            ->values(
                array(
                    'PK_FK_PERSONA'  => '?',
                    'CLAVE'          => '?',
                    'CLAVE_ANTERIOR' => '?',
                    'CORREO'         => '?',
                    'FECHA_REGISTRO' => '?',
                    'ESTADO'         => '?',
                )
            )
            ->setParameter(0, $COD)
            ->setParameter(1, $PASS)
            ->setParameter(2, $TOKEN)
            ->setParameter(3, $EMAIL)
            ->setParameter(4, date('d-m-Y'))
            ->setParameter(7, 'I')

        ;

        # Cerrar conexion
        $this->_conexion->close();

        return array('status' => $queryBuilder);
    }

    public function unsubscribeWeb($COD)
    {

        $sql = "DELETE FROM AAS_CLAVES_WEB WHERE PK_FK_PERSONA='$COD'";

        $stmt = oci_parse($this->conectar_Oracle(), $sql); // Preparar la sentencia
        $ok   = oci_execute($stmt); // Ejecutar la sentencia
        oci_free_statement($stmt); // Liberar los recursos asociados a una sentencia o cursor
        oci_close($this->conectar_Oracle());

        return array('status' => $ok);
    }

    public function getAuth_TOKEN_LOSTPASS(string $token)
    {

        try {

            # Query
            $sql = "SELECT * FROM AAS_CLAVES_WEB WHERE CLAVE_ANTERIOR='" . $token . "&req=lostpass' AND ESTADO='A'";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # Cerrar conexion
            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $user = $stmt->fetch();

            # validar si es RUC
            if (false != $user) {
                return array('status' => true);
            } else {
                throw new ModelsException('¡Error! No existe un registro válido.', 4014);
            }

            return false;
        } catch (ModelsException $e) {
            return array(
                'status'    => false,
                'message'   => $e->getMessage(),
                'errorCode' => $e->getCode(),
            );
        }
    }

    public function getAuth_TOKEN(string $token)
    {

        try {

            # Query
            $sql = "SELECT * FROM AAS_CLAVES_WEB WHERE CLAVE_ANTERIOR='$token' AND ESTADO='I'";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # Cerrar conexion
            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $user = $stmt->fetch();

            # validar si es RUC
            if (false != $user) {
                return array('status' => true);
            } else {
                throw new ModelsException('¡Error! No existe un registro válido.', 4014);
            }

            return false;
        } catch (ModelsException $e) {
            return array(
                'status'    => false,
                'message'   => $e->getMessage(),
                'errorCode' => $e->getCode(),
            );
        }
    }

    public function getAuth_ACTIVE_ACCOUNT_LOSTPASS(string $token)
    {

        try {

            $sql = "UPDATE AAS_CLAVES_WEB SET CLAVE_ANTERIOR='$token' WHERE CLAVE_ANTERIOR='" . $token . "&req=lostpass' ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # Cerrar conexion
            $this->_conexion->close();

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    public function getAuth_ACTIVE_ACCOUNT(string $token)
    {

        try {

            $sql = "UPDATE AAS_CLAVES_WEB SET CLAVE_ANTERIOR='', ESTADO='A' WHERE CLAVE_ANTERIOR='$token'";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # Cerrar conexion
            $this->_conexion->close();

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    public function updatePaciente(): array
    {
        global $http;

        $PRIMER_APELLIDO = strtoupper($http->request->get('PRIMER_APELLIDO'));
        $NCHU            = $http->request->get('NCHU');

        $sql  = "UPDATE CAD_VW_PACIENTES SET PRIMER_APELLIDO='" . $PRIMER_APELLIDO . "' WHERE NHCL='" . $NCHU . "'";
        $stmt = oci_parse($this->conectar_Oracle(), $sql); // Preparar la sentencia
        $ok   = oci_execute($stmt); // Ejecutar la sentencia
        oci_free_statement($stmt); // Liberar los recursos asociados a una sentencia o cursor
        oci_close($this->conectar_Oracle());

        return array(
            'status'  => $ok,
            'message' => 'Paciente actualizado.',

        );
    }

/**
 * __construct()
 */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
    }
}
