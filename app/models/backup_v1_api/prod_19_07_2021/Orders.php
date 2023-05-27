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
use PDO;

/**
 * Modelo Odbc GEMA -> Orders -> Pedidos Electrónicos
 */

class Orders extends Models implements IModels
{

    # Variables de clase
    private $USER      = null;
    private $sortType  = 'desc'; # desc
    private $offset    = 1;
    private $limit     = 25;
    private $sortField = 'ROWNUM_';
    private $errors    = null;

    # varaiables para registro de pedido electrónico
    private $pedido_fk_tipo_pedido      = null;
    private $pedido_fk_medico           = null;
    private $pedido_id_pte              = null;
    private $pedido_tipo_doc            = null;
    private $pedido_primer_apellido_pte = null;
    private $pedido_primer_nombre_pte   = true;
    private $pedido_fecha_nacimiento    = null;
    private $pedido_sexo                = null;
    private $pedido_estado_civil        = null;
    private $pedido_obs                 = null;
    private $pedido_pvp_articulo        = null;

    # Variables de conexion
    private $_conexion = null;

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();

        //..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

    }

    private function getAuthorizationn()
    {

        try {

            global $http;

            $token = $http->headers->get("Authorization");

            $auth = new Model\Auth;
            $key  = $auth->GetData($token);

            $this->USER = $key;

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function setParameters_GET()
    {

        global $http, $config;

        $this->errors = $config['errors'];

        foreach ($http->request->all() as $key => $value) {
            $this->$key = $value;
        }

        if ($this->sortField != null and $this->sortField = 'ID_PEDIDO') {
            $this->sortField = 'PK_CODIGO';
        }

    }

    private function setParameters_Order()
    {

        global $http, $config;

        $this->errors = $config['errors'];

        $id_pte   = strtoupper($http->request->get('id_pte'));
        $tipo_doc = strtoupper($http->request->get('tipo_doc'));

        if (Helper\Functions::e($id_pte, $tipo_doc)) {
            throw new ModelsException($this->errors['notParameters']['message'], $this->errors['notParameters']['code']);
        }

        foreach ($http->request->all() as $key => $value) {
            $paramPagoWeb        = 'pedido_' . $key;
            $this->$paramPagoWeb = $value;
        }

    }

    private function errorsPagination()
    {

        if ($this->limit > 25) {
            throw new ModelsException('!Error! Solo se pueden mostrar 100 resultados por página.');
        }

        if ($this->limit == 0 or $this->limit < 0) {
            throw new ModelsException('!Error! {Limit} no puede ser 0 o negativo');
        }

        if ($this->offset == 0 or $this->offset < 0) {
            throw new ModelsException('!Error! {Offset} no puede ser 0 o negativo.');
        }
    }

    private function getAccount()
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        # Devolver todos los resultados
        $sql = "SELECT * FROM WEB2_VW_LOGIN
        WHERE CC = '" . $this->pedido_id_pte . "' OR RUC LIKE '" . $this->pedido_id_pte . "%'
        OR PASAPORTE LIKE '" . $this->pedido_id_pte . "%' ";

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
                    $COD_PERSONA = $value['COD_PTE'];
                } elseif (!is_null($value['COD_MED'])) {
                    $COD_PERSONA = $value['COD_MED'];
                }
            }

            # SETEAR VALORES PARA DEFINICION PARA PROVEEDOR
            if ($roles['PRO'] === 1 && $roles['PTE'] === 0 && $roles['MED'] === 0) {
                $COD_PERSONA = $value['COD_PROV'];
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
            array('DNI' => $this->pedido_id_pte, 'COD_PERSONA' => (int) $COD_PERSONA),
            $roles, $_cp_pacientes, $_cp_medicos, $_cp_proveedores
        );

        # Ultima validacion antes de proseguir

        if ($roles['PTE'] === 0 && $roles['MED'] === 0 && $roles['PRO'] === 0) {
            throw new ModelsException($this->errors['notExistedGema']['message'], $this->errors['notExistedGema']['code']);
        }

        # Imprimir valores
        return $res;

    }

    public function getPaciente_New_Pedido()
    {
        try {

            # Setear valores de clase
            $this->setParameters_Order();

            # Veriicar y estraer datos de validacion gema de paciente
            $USER = $this->getAccount();

            # SETEAR CODIGO DE PERSONA PARA BUSQUEDA EN BAD PERSONAS
            $COD_PERSONA = $USER['COD_PERSONA'];

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Query
            $queryBuilder
                ->select('pk_codigo AS COD_PERSONA,cedula,pasaporte,primer_nombre,segundo_nombre,primer_apellido,segundo_apellido,estado_civil,sexo,fecha_nacimiento')
                ->from('bab_personas')
                ->where('pk_codigo = :pk_codigo')
                ->setParameter('pk_codigo', '' . $COD_PERSONA . '')
            ;

            # Execute
            $stmt = $queryBuilder->execute();

            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $pte = $stmt->fetch();

            # No existen datos del pacciente
            if ($pte === false) {
                throw new ModelsException($this->errors['notParameters']['message'], $this->errors['notParameters']['code']);
            }

            $pte['ID_PTE'] = $this->pedido_id_pte;

            unset($pte['CEDULA']);
            unset($pte['PASAPORTE']);

            $fecha_nacimiento = $pte['FECHA_NACIMIENTO'];

            # Setear para de finir despuyes
            $this->pedido_fecha_nacimiento = $fecha_nacimiento;

            # Setear fecha de nacimiento
            $pte['FECHA_NACIMIENTO'] = date('d-m-Y', strtotime($fecha_nacimiento));

            $pacientes = new Model\Pacientes;

            $EMAIL_ACCOUNT = $pacientes->getEmailAccount($COD_PERSONA);

            $pte['EMAIL_ACCOUNT'] = $EMAIL_ACCOUNT;

            unset($pte['COD_PERSONA']);

            return array(
                'status' => true,
                'data'   => $pte,
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status'    => true,
                    'data'      => [],
                    'message'   => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    private function setParametersPedidoElectronicoWeb()
    {

        global $http, $config;

        # Obtener los datos $_POST
        $data = $http->request->all();
        $obs  = $http->request->get('obs');

        if (Helper\Functions::e($obs)) {
            throw new ModelsException($this->errors['notParameters']['message'], $this->errors['notParameters']['code']);
        }

        foreach ($data['item'] as $key => $value) {
            if (Helper\Functions::e($value)) {
                throw new ModelsException($this->errors['notParameters']['message'], $this->errors['notParameters']['code']);
            }
        }

        # Setear valores de clase par ala insercion

        $pte = $this->getPaciente_New_Pedido();

        # Seetar variables de configuracion para el registrod el pedido
        $this->pedido_primer_nombre_pte   = $pte['data']['PRIMER_NOMBRE'];
        $this->pedido_primer_apellido_pte = $pte['data']['PRIMER_APELLIDO'];
        $this->pedido_estado_civil        = $pte['data']['ESTADO_CIVIL'];
        $this->pedido_sexo                = $pte['data']['SEXO'];
        $this->pedido_fecha_nacimiento    = date('d/M/Y', strtotime($this->pedido_fecha_nacimiento));
        $this->pedido_obs                 = $obs;

    }

    private function setParametersPedidoElectronicoWeb_Farma()
    {

        global $http, $config;

        # Obtener los datos $_POST
        $data = $http->request->all();
        $obs  = $http->request->get('obs');

        if (Helper\Functions::e($obs)) {
            throw new ModelsException($this->errors['notParameters']['message'], $this->errors['notParameters']['code']);
        }

        foreach ($data['item'] as $key => $value) {

            if (Helper\Functions::e($value['id']) || Helper\Functions::e($value['quantity'])) {
                throw new ModelsException($this->errors['notParameters']['message'], $this->errors['notParameters']['code']);
            }

            # validar stock de medcamentos
            $this->getStockMedicamentos($value['id'], $value['quantity']);

        }

        # Setear valores de clase par ala insercion

        $pte = $this->getPaciente_New_Pedido();

        # Seetar variables de configuracion para el registrod el pedido
        $this->pedido_primer_nombre_pte   = $pte['data']['PRIMER_NOMBRE'];
        $this->pedido_primer_apellido_pte = $pte['data']['PRIMER_APELLIDO'];
        $this->pedido_estado_civil        = $pte['data']['ESTADO_CIVIL'];
        $this->pedido_sexo                = $pte['data']['SEXO'];
        $this->pedido_fecha_nacimiento    = date('d/M/Y', strtotime($this->pedido_fecha_nacimiento));
        $this->pedido_obs                 = $obs;

    }

    private function getValorArticulo($articulo)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Query
            $queryBuilder
                ->select('PVP')
                ->from('WEB_VW_TARIFARIO_PMQ')
                ->where('ARTI = :ARTI')
                ->setParameter('ARTI', '' . $articulo . '')
            ;

            # Execute
            $result = $queryBuilder->execute();

            $_articulo = $result->fetch();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! No existe elarticulo ' . $articulo, 4001);
            }

            $this->pedido_pvp_articulo = $_articulo['PVP'];

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    private function insertArticuloPedido($secuencial, $id_pedido, $articulo)
    {

        try {

            $this->getValorArticulo($articulo);

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Query
            $queryBuilder
                ->insert('ccp_det_pedidos_medico')
                ->values(
                    array(
                        'pk_linea'     => '?',
                        'pk_fk_pedido' => '?',
                        'examen'       => '?',
                        'cantidad'     => '?',
                        'pvp'          => '?',
                        'cod_artic'    => '?',
                    )
                )
                ->setParameter(0, $secuencial)
                ->setParameter(1, $id_pedido)
                ->setParameter(2, $articulo)
                ->setParameter(3, 1)
                ->setParameter(4, $this->pedido_pvp_articulo)
                ->setParameter(5, $articulo)
            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! No se pudo registrar el articulo ' . $articulo, 4001);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    private function insertArticuloPedidoFarma($secuencial, $id_pedido, $articulo, $cantidad)
    {

        try {

            $this->getValorArticulo($articulo);

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Query
            $queryBuilder
                ->insert('ccp_det_pedidos_medico')
                ->values(
                    array(
                        'pk_linea'     => '?',
                        'pk_fk_pedido' => '?',
                        'examen'       => '?',
                        'cantidad'     => '?',
                        'pvp'          => '?',
                        'cod_artic'    => '?',
                    )
                )
                ->setParameter(0, $secuencial)
                ->setParameter(1, $id_pedido)
                ->setParameter(2, $articulo)
                ->setParameter(3, $cantidad)
                ->setParameter(4, (float) ($this->pedido_pvp_articulo * $cantidad))
                ->setParameter(5, $articulo)
            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! No se pudo registrar el articulo ' . $articulo, 4001);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    private function getCod_Med()
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        # Devolver todos los resultados
        $sql = "SELECT pk_codigo as COD from edm_medicos WHERE FK_PERSONA = '" . $this->USER->CP_MED[0] . "'";

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetch();

        if (false === $data) {
            return 0;
        }

        $this->pedido_fk_medico = $data['COD'];

    }

    public function registroPedidoElectronicoWeb_LAB()
    {

        try {

            global $http;

            # Setear valores de clase
            $this->setParameters_Order();

            # EXTRAER VALOR DEL TOKEN PARA PROCESO EXTRAE EL CODIGO DEL MEDICO QUE REGISTRA EL PEDIDO
            $this->getAuthorizationn();

            # SETEAR VARIABLES DE CLASE
            $this->setParametersPedidoElectronicoWeb();

            # SETEAR CODIGO DE MEDICO
            $this->getCod_Med();

            # Consulta SQL
            $sql = "CALL pro_ins_pedido_web(
            '" . $this->pedido_fk_medico . "',
            '" . $this->pedido_tipo_doc . "',
            '" . $this->pedido_id_pte . "',
            '" . $this->pedido_primer_nombre_pte . "',
            '" . $this->pedido_primer_apellido_pte . "',
            '" . $this->pedido_sexo . "',
            '" . $this->pedido_estado_civil . "',
            '" . $this->pedido_fecha_nacimiento . "',
            '" . $this->pedido_obs . "',
            'WEB','10',:vn_sec,:vc_error)";

            # Conectar base de datos
            $this->conectar_Oracle();
            # Execute
            $stmt = $this->_conexion->prepare($sql);

            $stmt->bindParam(':vn_sec', $vn_sec, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 10);
            $stmt->bindParam(':vc_error', $vc_error, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 2000);

            # Datos de usuario cuenta activa
            $result = $stmt->execute();

            $this->_conexion->close();

            if (false == $result) {
                throw new ModelsException('¡Error! No se pudo registrar los datos del pedido electrónico. ', 4001);
            }

            # inserciond d eitems de registro de examen
            $items = $http->request->all();

            $i = 1;
            foreach ($items['item'] as $key => $value) {

                # inserciond d eitems de registro de examen
                $this->insertArticuloPedido($i, $vn_sec, $value);

                $i++;

            }

            # Pedido electrónico registrado con éxito
            return array(
                'status'  => true,
                'message' => 'Pedido electrónico de Laboratorio se registró con éxito.',
                #'data'    => $vn_sec,
            );

            # return (int) $vn_sec;

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    public function registroPedidoElectronicoWeb_IMAGEN()
    {

        try {

            global $http;

            # Setear valores de clase
            $this->setParameters_Order();

            # EXTRAER VALOR DEL TOKEN PARA PROCESO EXTRAE EL CODIGO DEL MEDICO QUE REGISTRA EL PEDIDO
            $this->getAuthorizationn();

            # SETEAR VARIABLES DE CLASE
            $this->setParametersPedidoElectronicoWeb();

            # SETEAR CODIGO DE MEDICO
            $this->getCod_Med();

            # Consulta SQL
            $sql = "CALL pro_ins_pedido_web(
            '" . $this->pedido_fk_medico . "',
            '" . $this->pedido_tipo_doc . "',
            '" . $this->pedido_id_pte . "',
            '" . $this->pedido_primer_nombre_pte . "',
            '" . $this->pedido_primer_apellido_pte . "',
            '" . $this->pedido_sexo . "',
            '" . $this->pedido_estado_civil . "',
            '" . $this->pedido_fecha_nacimiento . "',
            '" . $this->pedido_obs . "',
            'WEB','11',:vn_sec,:vc_error)";

            # Conectar base de datos
            $this->conectar_Oracle();
            # Execute
            $stmt = $this->_conexion->prepare($sql);

            $stmt->bindParam(':vn_sec', $vn_sec, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 10);
            $stmt->bindParam(':vc_error', $vc_error, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 2000);

            # Datos de usuario cuenta activa
            $result = $stmt->execute();

            $this->_conexion->close();

            if (false == $result) {
                throw new ModelsException('¡Error! No se pudo registrar los datos del pedido electrónico. ', 4001);
            }

            # inserciond d eitems de registro de examen
            $items = $http->request->all();

            $i = 1;

            foreach ($items['item'] as $key => $value) {

                # inserciond d eitems de registro de examen
                $this->insertArticuloPedido($i, $vn_sec, $value);
                $i++;

            }

            # Pedido electrónico registrado con éxito
            return array(
                'status'  => true,
                'message' => 'Pedido electrónico de Imagen se registró con éxito.',
                # 'data'    => $vn_sec,
            );

            # return (int) $vn_sec;

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    public function registroPedidoElectronicoWeb_MED()
    {

        try {

            global $http;

            # Setear valores de clase
            $this->setParameters_Order();

            # EXTRAER VALOR DEL TOKEN PARA PROCESO EXTRAE EL CODIGO DEL MEDICO QUE REGISTRA EL PEDIDO
            $this->getAuthorizationn();

            # SETEAR VARIABLES DE CLASE
            $this->setParametersPedidoElectronicoWeb_Farma();

            # SETEAR CODIGO DE MEDICO
            $this->getCod_Med();

            # Consulta SQL
            $sql = "CALL pro_ins_pedido_web(
            '" . $this->pedido_fk_medico . "',
            '" . $this->pedido_tipo_doc . "',
            '" . $this->pedido_id_pte . "',
            '" . $this->pedido_primer_nombre_pte . "',
            '" . $this->pedido_primer_apellido_pte . "',
            '" . $this->pedido_sexo . "',
            '" . $this->pedido_estado_civil . "',
            '" . $this->pedido_fecha_nacimiento . "',
            '" . $this->pedido_obs . "',
            'WEB','12',:vn_sec,:vc_error)";

            # Conectar base de datos
            $this->conectar_Oracle();
            # Execute
            $stmt = $this->_conexion->prepare($sql);

            $stmt->bindParam(':vn_sec', $vn_sec, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 10);
            $stmt->bindParam(':vc_error', $vc_error, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 2000);

            # Datos de usuario cuenta activa
            $result = $stmt->execute();

            $this->_conexion->close();

            if (false == $result) {
                throw new ModelsException('¡Error! No se pudo registrar los datos del pedido electrónico. ', 4001);
            }

            # inserciond d eitems de registro de examen
            $items = $http->request->all();

            $i = 1;

            $data_items = (array) $items['item'];

            foreach ($data_items as $key => $value) {

                # inserciond d eitems de registro de examen
                $this->insertArticuloPedidoFarma($i, $vn_sec, $value['id'], $value['quantity']);
                $i++;

            }

            # Pedido electrónico registrado con éxito
            return array(
                'status'  => true,
                'message' => 'Pedido electrónico de Farmacia se registró con éxito.',
                # 'data'    => $vn_sec,
            );

            # return (int) $vn_sec;

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    private function notResults(array $data)
    {
        if (count($data) == 0) {
            throw new ModelsException('No existe más resultados.', 4080);
        }
    }

    public function getPedidos_Medicos_Paciente()
    {

        try {

            # SETEAR VARIABLES DE CLASE
            $this->setParameters_GET();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # EXTRAER DNI
            $this->getAuthorizationn();

            # CONULTA BDD GEMA

            $DNI = $this->USER->DNI;

            $sql = "SELECT PK_CODIGO,OBSERVACION,PRIMER_APELLIDO_PTE,PRIMER_NOMBRE_PTE,PRIMER_APELLIDO_MED,PRIMER_NOMBRE_MED,ID_PTE,FK_TIPO_PEDIDO, FK_MEDICO, ESTADO, FECHA, SC_RELACIONADO, ROWNUM AS ROWNUM_ FROM ccp_cab_pedidos_medico WHERE ID_PTE='$DNI' ORDER BY $this->sortField $this->sortType ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # Cerrar conexion
            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # Ya no existe resultadso
            $this->notResults($data);

            $origenes = array(
                '10' => 'Laboratorio',
                '11' => 'Imagen',
                '12' => 'Medicación',
            );

            $estados = array(
                'IN' => 'Ingresado',
                'PR' => 'Procesado',
                'PA' => 'Pagado',

            );

            $pedidos = array();

            # Parseo de valores de ordenes medicas
            foreach ($data as $key) {

                $examenes = $this->getExamenesLabPedidos($key['ESTADO'], $key['SC_RELACIONADO']);

                #  VERIFICAR ESTADO PAGADO
                $estado_pagp_pedido = $this->getEstadoPagoPedido($key['PK_CODIGO']);

                if ($key['ESTADO'] == 'IN' && false != $estado_pagp_pedido) {
                    $key['ESTADO'] = 'PA'; # code...
                }

                $key['NUM']          = $key['ROWNUM_'];
                $key['ID_PEDIDO']    = (int) $key['PK_CODIGO'];
                $key['ORIGEN']       = $origenes[$key['FK_TIPO_PEDIDO']];
                $key['COD_MEDICO']   = $key['FK_MEDICO'];
                $key['ESTADO']       = $estados[$key['ESTADO']];
                $key['MEDICO']       = $key['PRIMER_APELLIDO_MED'] . ' ' . $key['PRIMER_NOMBRE_MED'];
                $key['EXAMENES_LAB'] = $examenes;
                $key['PRECIO']       = $this->getValorTotalPedido($key['PK_CODIGO'], $key['FK_TIPO_PEDIDO']);
                $key['FECHA']        = date('d-m-Y', strtotime($key['FECHA']));
                $key['DETALLE']      = $this->getDetallePedido($key['PK_CODIGO'], $key['FK_TIPO_PEDIDO']);

                unset($key['ROWNUM_']);
                unset($key['PK_CODIGO']);
                unset($key['FK_MEDICO']);
                unset($key['SC_RELACIONADO']);
                unset($key['FK_TIPO_PEDIDO']);
                unset($key['PRIMER_APELLIDO_MED']);
                unset($key['PRIMER_NOMBRE_MED']);

                $pedidos[] = $key;
            }

            // RESULTADO DE CONSULTA

            $PEDIDOS = $this->get_Order_Pagination($pedidos);

            # Devolver Información
            return array(
                'status' => true,
                'data'   => $this->get_page($PEDIDOS, $this->offset, $this->limit),
                'total'  => count($pedidos),
                'limit'  => intval($this->limit),
                'offset' => intval($this->offset),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status'    => true,
                    'data'      => [],
                    'message'   => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getPedidos_Medicos()
    {

        try {

            # SETEAR VARIABLES DE CLASE
            $this->setParameters_GET();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # setear codigo persona
            $this->getAuthorizationn();

            # Extraer datos de medicos
            $this->getCod_Med();

            $sql = "SELECT PK_CODIGO,OBSERVACION,PRIMER_APELLIDO_PTE,PRIMER_NOMBRE_PTE,PRIMER_APELLIDO_MED,PRIMER_NOMBRE_MED,ID_PTE, FK_TIPO_PEDIDO,FK_MEDICO,ESTADO,FECHA,SC_RELACIONADO,ROWNUM AS ROWNUM_ FROM
            ccp_cab_pedidos_medico WHERE FK_MEDICO = '" . $this->pedido_fk_medico . "' ORDER BY $this->sortField $this->sortType ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # Cerrar conexion
            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # Ya no existe resultadso
            $this->notResults($data);

            # rESPONSE DATA

            $origenes = array(
                '10' => 'Laboratorio',
                '11' => 'Imagen',
                '12' => 'Medicación',
            );

            $estados = array(
                'IN' => 'Ingresado',
                'PR' => 'Procesado',
                'PA' => 'Pagado',
            );

            $pedidos = array();

            # Parseo de valores de ordenes medicas
            foreach ($data as $key) {

                $examenes = $this->getExamenesLabPedidos($key['ESTADO'], $key['SC_RELACIONADO']);

                $key['NUM']          = $key['ROWNUM_'];
                $key['ID_PEDIDO']    = (int) $key['PK_CODIGO'];
                $key['ORIGEN']       = $origenes[$key['FK_TIPO_PEDIDO']];
                $key['COD_MEDICO']   = $key['FK_MEDICO'];
                $key['ESTADO']       = $estados[$key['ESTADO']];
                $key['MEDICO']       = $key['PRIMER_APELLIDO_MED'] . ' ' . $key['PRIMER_NOMBRE_MED'];
                $key['EXAMENES_LAB'] = $examenes;
                $key['PRECIO']       = $this->getValorTotalPedido($key['PK_CODIGO'], $key['FK_TIPO_PEDIDO']);
                $key['FECHA']        = date('d-m-Y', strtotime($key['FECHA']));
                $key['DETALLE']      = $this->getDetallePedido($key['PK_CODIGO'], $key['FK_TIPO_PEDIDO']);

                unset($key['ROWNUM_']);
                unset($key['PK_CODIGO']);
                unset($key['FK_MEDICO']);
                unset($key['SC_RELACIONADO']);

                $pedidos[] = $key;
            }

            // RESULTADO DE CONSULTA

            $PEDIDOS = $this->get_Order_Pagination($pedidos);

            # Devolver Información
            return array(
                'status' => true,
                'data'   => $this->get_page($PEDIDOS, $this->offset, $this->limit),
                'total'  => count($pedidos),
                'limit'  => intval($this->limit),
                'offset' => intval($this->offset),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status'    => true,
                    'data'      => [],
                    'message'   => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    private function getEstadoPagoPedido($FK_PEDIDO)
    {

        # CONULTA BDD GEMA
        $sql = "SELECT FK_PEDIDO FROM
         CCF_ABONOS_GENERADOS_WEB WHERE FK_PEDIDO='$FK_PEDIDO' AND PROCESADO='S' ";

        # Conectar base de datos
        $this->conectar_Oracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $data = $stmt->fetch();

        # Cerrar conexion
        $this->_conexion->close();

        # Ya no existe resultadso
        if (false == $data) {
            return false;
        }

        return true;
    }

    private function getExamenesLabPedidos($estado, $sc_relacionado)
    {

        if ($estado === 'IN') {
            return false;
        }

        # CONULTA BDD GEMA
        $sql = "SELECT FECHA, PK_NUMERO_TRANSACCION FROM
         CCP_TRANSACCIONES
         WHERE PK_NUMERO_TRANSACCION='$sc_relacionado' AND PK_FK_ARINVTM_TIPO_M='SC' ";

        # Conectar base de datos
        $this->conectar_Oracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $data = $stmt->fetchAll();

        # Ya no existe resultadso
        if (count($data) == 0) {
            return false;
        }

        # Cerrar conexion
        $this->_conexion->close();

        $examanes = array();

        # Parseo de valores de ordenes medicas
        foreach ($data as $key) {

            $examanes[] = array(
                'FECHA' => date('d-m-Y', strtotime($key['FECHA'])),
                'SC'    => $key['PK_NUMERO_TRANSACCION'],
            );
        }

        return $examanes;
    }

    private function getDetallePedido($id_pedido, $tipo_pedido)
    {
        # CONULTA BDD GEMA
        $sql = "SELECT PVP, COD_ARTIC, CANTIDAD FROM
            ccp_det_pedidos_medico WHERE PK_FK_PEDIDO='$id_pedido'";

        # Conectar base de datos
        $this->conectar_Oracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $data = $stmt->fetchAll();

        # Ya no existe resultadso
        if (count($data) == 0) {
            return '';
        }

        # Cerrar conexion
        $this->_conexion->close();

        $detalle = array();

        # Parseo de valores de ordenes medicas
        foreach ($data as $key) {

            if ($tipo_pedido == '12') {

                $detalle[] = array(
                    'item'   => $key['COD_ARTIC'],
                    'examen' => 'Cantidad: ' . $key['CANTIDAD'] . ' de ' . $this->getDetalle_Articulo($key['COD_ARTIC']),
                );

            } else {

                $detalle[] = array(
                    'item'   => $key['COD_ARTIC'],
                    'examen' => $this->getDetalle_Articulo($key['COD_ARTIC']),
                );

            }

        }

        return $detalle;
    }

    private function getValorTotalPedido($id_pedido, $tipo_pedido)
    {
        # CONULTA BDD GEMA
        $sql = "SELECT PVP, CANTIDAD FROM
            ccp_det_pedidos_medico WHERE PK_FK_PEDIDO='$id_pedido'";

        # Conectar base de datos
        $this->conectar_Oracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $data = $stmt->fetchAll();

        # Ya no existe resultadso
        if (count($data) == 0) {
            return 0;
        }

        # Cerrar conexion
        $this->_conexion->close();

        $valorTotal = 0;

        # Parseo de valores de ordenes medicas
        foreach ($data as $key) {

            # sI ES PEDIDOD E FARMACIA
            if ($tipo_pedido == '12') {

                $valorTotal = $valorTotal + ((float) $key['PVP'] * $key['CANTIDAD']);

            } else {
                $valorTotal = $valorTotal + (float) $key['PVP'];

            }

        }

        return $valorTotal;
    }

    public function getDetalle_Pedidos()
    {

        try {

            global $http;

            $id_pedido = $http->request->get('id_pedido');

            if (Helper\Functions::e($id_pedido)) {
                throw new ModelsException('No estan definidos todos los parametros para este request', 4001);
            }

            # SETEAR VARIABLES DE CLASE
            $this->setParameters();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # CONULTA BDD GEMA
            $sql = "SELECT ccp_det_pedidos_medico.*, ROWNUM AS ROWNUM_ FROM
            ccp_det_pedidos_medico WHERE PK_FK_PEDIDO='$id_pedido' ORDER BY ROWNUM_ $this->sortType ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # Cerrar conexion
            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # Ya no existe resultadso
            $this->notResults($data);

            $pedido = array();

            # Parseo de valores de ordenes medicas
            foreach ($data as $key) {

                $key['ID']        = $key['PK_LINEA'];
                $key['ID_PEDIDO'] = $key['PK_FK_PEDIDO'];
                $key['ARTICULO']  = $key['COD_ARTIC'];
                unset($key['PK_LINEA']);
                unset($key['ROWNUM_']);
                unset($key['PK_FK_PEDIDO']);
                unset($key['COD_ARTIC']);

                $pedido[] = $key;

            }

            // RESULTADO DE CONSULTA

            $PEDIDO = $this->get_Order_Pagination($pedido);

            # Devolver Información
            return array(
                'status' => true,
                'data'   => $this->get_page($PEDIDO, $this->offset, $this->limit),
                'total'  => count($pedido),
                'limit'  => intval($this->limit),
                'offset' => intval($this->offset),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status'    => true,
                    'data'      => [],
                    'message'   => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    # ABONOS PROCESADOS

    public function getAbonos_Procesados()
    {

        try {

            # SETEAR VARIABLES DE CLASE
            $this->setParameters_GET();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # setear codigo persona
            $this->getAuthorizationn();

            # setear DNI DE USUARIO
            $DNI = $this->USER->DNI;

            # CONULTA BDD GEMA
            $sql = " SELECT FK_TRANSACCION AS TRX, FECHA_REGISTRO,IDENTIFICACION_PACIENTE,PRIMER_APELLIDO,SEGUNDO_APELLIDO,PRIMER_NOMBRE,SEGUNDO_NOMBRE,MONTO,FK_ARINDA_NO_ARTI,ROWNUM FROM CCF_ABONOS_GENERADOS_WEB WHERE IDENTIFICACION_TITULAR = '$DNI' AND PROCESADO='S' AND FK_PEDIDO IS NULL ORDER BY ROWNUM $this->sortType ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # Cerrar conexion
            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # Ya no existe resultadso
            $this->notResults($data);

            $abonos = array();

            # Parseo de valores de ordenes medicas
            foreach ($data as $key) {

                $detalle = $this->getDetalle_Articulo($key['FK_ARINDA_NO_ARTI']);

                $key['FECHA_REGISTRO'] = date('d-m-Y', strtotime($key['FECHA_REGISTRO']));
                $key['ID_PTE']         = $key['IDENTIFICACION_PACIENTE'];
                $key['ID_ARTI']        = $key['FK_ARINDA_NO_ARTI'];
                $key['DETALLE']        = (false != $detalle) ? $detalle : '';
                $key['ESTADO']         = $this->getEstado_Abono($key['TRX']);
                $key['ID_TRX']         = $key['TRX'];

                unset($key['ROWNUM']);
                unset($key['IDENTIFICACION_PACIENTE']);
                unset($key['FK_ARINDA_NO_ARTI']);
                unset($key['TRX']);

                $abonos[] = $key;

            }

            // RESULTADO DE CONSULTA

            $ABONOS = $this->get_Order_Pagination($abonos);

            # Devolver Información
            return array(
                'status' => true,
                'data'   => $this->get_page($ABONOS, $this->offset, $this->limit),
                'total'  => count($abonos),
                'limit'  => intval($this->limit),
                'offset' => intval($this->offset),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status'    => true,
                    'data'      => [],
                    'message'   => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    private function getEstado_Abono($id_trx)
    {

        # setear codigo persona
        $this->getAuthorizationn();

        # CONULTA BDD GEMA
        $sql = "SELECT a.pk_numero_transaccion_credito as trx
                from ccf_abonos_generados_web t, ccf_documentos_credito a
                where t.fk_transaccion = a.pk_numero_transaccion_credito
                and nvl(a.anulado,'N') = 'N' and nvl(a.facturado,'N') = 'N'
                and t.identificacion_titular='" . $this->USER->DNI . "' and a.pk_numero_transaccion_credito='$id_trx'";

        # Conectar base de datos
        $this->conectar_Oracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $data = $stmt->fetch();

        # Cerrar conexion
        $this->_conexion->close();

        # Ya no existe resultadso
        if (false == $data) {
            return '';
        }

        return 'Procesado';

    }

    private function getStockMedicamentos($cod_articulo, $cantidad)
    {

        # CONULTA BDD GEMA
        $sql = "SELECT fun_verifica_stock('" . $cod_articulo . "','01','04') as stock from dual";

        # Conectar base de datos
        $this->conectar_Oracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        # Datos-
        $data = $stmt->fetch();

        # Cerrar conexion
        $this->_conexion->close();

        # Descripciond e item
        $item = $this->getDetalle_Articulo($cod_articulo);

        # Ya no existe resultadso
        if (false === $data) {
            throw new ModelsException('El medicamento ' . $item . ' no esta disponible en stock.', 4001);
        }

        # Ya no existe resultadso
        if ($data['STOCK'] < $cantidad) {

            throw new ModelsException('La cantidad del medicamento ' . $item . ' no esta disponible en stock. Disponible: ' . $data['STOCK'] . '.', 4001);
        }

    }

    private function getDetalle_Articulo($id_pqt)
    {
        # CONULTA BDD GEMA
        $sql = "SELECT DESCRIPCION,PVP,ARTICULO,ROWNUM FROM WEB_VW_TARIFARIO_PMQ WHERE ARTI='$id_pqt'";

        # Conectar base de datos
        $this->conectar_Oracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $data = $stmt->fetch();

        # Cerrar conexion
        $this->_conexion->close();

        # Ya no existe resultadso
        if (false == $data) {
            return false;
        }

        # Retronar valores
        return $data['ARTICULO'];
    }

# Ordenar array por campo
    public function orderMultiDimensionalArray($toOrderArray, $field, $inverse = 'desc')
    {
        $position = array();
        $newRow   = array();
        foreach ($toOrderArray as $key => $row) {
            $position[$key] = $row[$field];
            $newRow[$key]   = $row;
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

    private function get_Order_Pagination(array $arr_input)
    {
        # SI ES DESCENDENTE

        $arr = array();
        $NUM = 1;

        if ($this->sortType == 'desc') {

            $NUM = count($arr_input);
            foreach ($arr_input as $key) {
                $key['NUM'] = $NUM;
                $arr[]      = $key;
                $NUM--;
            }

            return $arr;

        }

        # SI ES ASCENDENTE

        foreach ($arr_input as $key) {
            $key['NUM'] = $NUM;
            $arr[]      = $key;
            $NUM++;
        }

        return $arr;
    }

    private function get_page(array $input, $pageNum, $perPage)
    {
        $start = ($pageNum - 1) * $perPage;
        $end   = $start + $perPage;
        $count = count($input);

        // Conditionally return results
        if ($start < 0 || $count <= $start) {
            // Page is out of range
            return array();
        } else if ($count <= $end) {
            // Partially-filled page
            return array_slice($input, $start);
        } else {
            // Full page
            return array_slice($input, $start, $end - $start);
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
