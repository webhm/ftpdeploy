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
use DateTime;
use Doctrine\DBAL\DriverManager;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;
use SoapClient;

/**
 * Modelo Odbc GEMA -> Icommkthm _> Integracion HM Icommkt
 */

class Icommkthm extends Models implements IModels
{

    # Variables de clase
    private $sortField = 'ROWNUM_';
    private $filterField = null;
    private $sortType = 'desc'; # desc
    private $offset = 1;
    private $limit = 25;
    private $startDate = null;
    private $endDate = null;
    private $_conexion = null;
    private $apikey = 'ODk4LTIwNDgtaG9zcGl0YWxtZXRlYw2';
    private $username = 'hospitalmetec';

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

    }

    private function getAuthorizationn($value)
    {

        try {

            global $http;

            $token = $http->headers->get("Authorization");

            $auth = new Model\Auth;
            $key = $auth->GetData($token);

            $this->val = $key->data[0]->$value;

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function errorsPagination()
    {

        try {

            if ($this->limit > 25) {
                throw new ModelsException('!Error! Solo se pueden mostrar 100 resultados por página.');
            }

            if ($this->limit == 0 or $this->limit < 0) {
                throw new ModelsException('!Error! {Limit} no puede ser 0 o negativo');
            }

            if ($this->offset == 0 or $this->offset < 0) {
                throw new ModelsException('!Error! {Offset} no puede ser 0 o negativo.');
            }

            return false;
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function setParameters_TEST()
    {

        try {

            global $http;

            foreach ($http->request->all() as $key => $value) {
                $this->$key = $value;
            }

            if ($this->startDate != null and $this->endDate != null) {

                $startDate = $this->startDate;
                $endDate = $this->endDate;

                $sd = new DateTime($startDate);
                $ed = new DateTime($endDate);

                if ($sd->getTimestamp() > $ed->getTimestamp()) {
                    throw new ModelsException('!Error! Fecha inicial no puede ser mayor a fecha final.');
                }

            }

            if ($this->sortCategory != null) {

                # Si es especialidades en ingles y hace match en array devolver correjido valor
                $espe = $this->buscarEspecialidad(mb_strtoupper($this->sortCategory));

                if ($this->lang == 'en') {
                    if (false != $espe) {
                        $this->sortCategory = mb_strtoupper($this->sanear_string($espe));
                    } else {
                        $this->sortCategory = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->sortCategory), 'UTF-8'));
                    }
                } else {
                    $this->sortCategory = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->sortCategory), 'UTF-8'));

                }

            }

            if ($this->searchField != null) {

                # Si es especialidades en ingles y hace match en array devolver correjido valor
                $espe = $this->buscarEspecialidad(mb_strtoupper($this->searchField));

                if ($this->lang == 'en') {

                    if (false != $espe) {
                        $this->searchField = mb_strtoupper($this->sanear_string($espe));
                    } else {
                        $this->searchField = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->searchField), 'UTF-8'));
                    }

                } else {
                    $this->searchField = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->searchField), 'UTF-8'));

                }

            }

            return false;
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function setParameters()
    {

        try {

            global $http;

            foreach ($http->request->all() as $key => $value) {
                $this->$key = $value;
            }

            if ($this->startDate != null and $this->endDate != null) {

                $startDate = $this->startDate;
                $endDate = $this->endDate;

                $sd = new DateTime($startDate);
                $ed = new DateTime($endDate);

                if ($sd->getTimestamp() > $ed->getTimestamp()) {
                    throw new ModelsException('!Error! Fecha inicial no puede ser mayor a fecha final.');
                }

            }

            if ($this->sortCategory != null) {

                $this->sortCategory = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->sortCategory), 'UTF-8'));

            }

            if ($this->searchField != null) {

                $this->searchField = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->searchField), 'UTF-8'));

            }

            return false;
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    public function createPieza()
    {
        try {

            $client = new SoapClient('http://demo.pe.icommarketing.com/Newsletters.asmx?WSDL',
                array(
                    "encoding" => "ISO-8859-1",
                    'wsdl_cache' => 0,
                    'trace' => 1,
                ));

        } catch (SoapFault $fault) {
            return '<h2>Constructor error</h2><pre>' . $fault->faultstring . '</pre>';
        }

        $html = 'HTML';

        $html = utf8_decode($html);

        $param = array(
            "ApiKey" => $this->apikey,
            "UserName" => $this->username,
            "Campaign" => "NEWSLATTER HM",
            "NewsletterName" => "DEMO API",
            "Content" => $html,
            "PlainText" => "test",
        );

        try {
            $result = $client->__soapCall("CreateHTML", array($param));
        } catch (Exception $e) {}

        return '<h2>Response</h2><pre>' . htmlspecialchars($client->__getLastResponse(), ENT_QUOTES) . '</pre>';

    }

    public function contactIcommktEncuestas(array $pte): array
    {
        $apiKey = $this->apikey;
        $profileKey = 'MjM1Njcz0';
        $stringData = array(
            'ProfileKey' => $profileKey,
            'Contact' => array(
                'Email' => $pte['EMAIL'],
                'CustomFields' => array(
                    array('Key' => 'titulo', 'Value' => $pte['TITULO']),
                    array('Key' => 'pte', 'Value' => $pte['PTE']),
                    array('Key' => 'fecha', 'Value' => $pte['FECHA_ALTA_CLINICA']),
                    array('Key' => 'atencion', 'Value' => $pte['ATENCION']),
                    array('Key' => 'status', 'Value' => $pte['STATUS_API']),
                ),
            ),
        );

        $data = json_encode($stringData);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.icommarketing.com/Contacts/SaveContact.Json/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            'Authorization: ' . $apiKey . ':0',
            'Access-Control-Allow-Origin: *')
        );

        $result = curl_exec($ch);
        curl_close($ch);
        $resultobj = json_decode($result);

        return array('res' => $resultobj);
        // print_r($resultobj->{'SaveContactJsonResult'}->{'StatusCode'});
    }

    private function setSpanishOracle()
    {

        # 71001 71101
        $sql = "alter session set NLS_LANGUAGE = 'LATIN AMERICAN SPANISH'";
        # Execute
        $stmt = $this->_conexion->query($sql);

        $sql = "alter session set NLS_TERRITORY = 'ECUADOR'";
        # Execute
        $stmt = $this->_conexion->query($sql);

        $sql = " alter session set NLS_DATE_FORMAT = 'DD-MM-YYYY' ";
        # Execute
        $stmt = $this->_conexion->query($sql);

    }

    public function getPacientesIcommkt_Emergencia()
    {

        try {

            global $config, $http;

            $sql = " SELECT * FROM pacientes_datos_totales WHERE ENVIADO = 'N' AND NUM_ADMISIONES_EMA IS NOT NULL AND FECH_ULT_ADMISION_EMA >= '01-06-2022' ORDER BY HC DESC ";

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # ExecutE
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataPacientes = $stmt->fetch();

            foreach ($dataPacientes as $key => &$value) {
                if (is_null($dataPacientes[$key])) {
                    $dataPacientes[$key] = 'N/D';
                }
            }

            # return $dataPacientes;

            $stage = $this->contactIcommkt_Pacientes_Emergencia($dataPacientes);

            if ($stage['res']->SaveContactJsonResult->StatusCode == 1) {
                $this->updateRegistroLogs($dataPacientes, 'Emergencia');
            } else {
                $this->updateRegistroErrorLogs($dataPacientes);
            }

            return $stage;

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
            );

        }

    }

    public function getPacientesIcommkt_Laboratorio()
    {

        try {

            global $config, $http;

            $sql = " SELECT * FROM pacientes_datos_totales WHERE ENVIADO = 'N' AND NUM_ADMISIONES_LAB IS NOT NULL AND FECH_ULT_ADMISION_LAB >= '01-06-2022' ORDER BY HC DESC ";

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # ExecutE
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataPacientes = $stmt->fetch();

            foreach ($dataPacientes as $key => &$value) {
                if (is_null($dataPacientes[$key])) {
                    $dataPacientes[$key] = 'N/D';
                }
            }

            # return $dataPacientes;

            $stage = $this->contactIcommkt_Pacientes_Laboratorio($dataPacientes);

            if ($stage['res']->SaveContactJsonResult->StatusCode == 1) {
                $this->updateRegistroLogs($dataPacientes, 'Laboratorio');
            } else {
                $this->updateRegistroErrorLogs($dataPacientes);
            }

            return $stage;

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
            );

        }

    }

    public function getPacientesIcommkt_Chequeos()
    {

        try {

            global $config, $http;

            $sql = " SELECT * FROM pacientes_datos_totales WHERE ENVIADO = 'N' AND NUM_ADMISIONES_CHQ IS NOT NULL AND FECH_ULT_ADMISION_CHQ >= '01-06-2022' ORDER BY HC DESC ";

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # ExecutE
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataPacientes = $stmt->fetch();

            foreach ($dataPacientes as $key => &$value) {
                if (is_null($dataPacientes[$key])) {
                    $dataPacientes[$key] = 'N/D';
                }
            }

            # return $dataPacientes;

            $stage = $this->contactIcommkt_Pacientes_Chequeos($dataPacientes);

            if ($stage['res']->SaveContactJsonResult->StatusCode == 1) {
                $this->updateRegistroLogs($dataPacientes, 'Chequeos');
            } else {
                $this->updateRegistroErrorLogs($dataPacientes);
            }

            return $stage;

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
            );

        }

    }

    public function getPacientesIcommkt_Imagen()
    {

        try {

            global $config, $http;

            $sql = " SELECT * FROM pacientes_datos_totales WHERE ENVIADO = 'N' AND NUM_ADMISIONES_IMAGEN IS NOT NULL AND FECH_ULT_ADMISION_IMAGEN >= '01-06-2022' ORDER BY HC DESC ";

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # ExecutE
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataPacientes = $stmt->fetch();

            foreach ($dataPacientes as $key => &$value) {
                if (is_null($dataPacientes[$key])) {
                    $dataPacientes[$key] = 'N/D';
                }
            }

            # return $dataPacientes;

            $stage = $this->contactIcommkt_Pacientes_Imagen($dataPacientes);

            if ($stage['res']->SaveContactJsonResult->StatusCode == 1) {
                $this->updateRegistroLogs($dataPacientes, 'Imagen');
            } else {
                $this->updateRegistroErrorLogs($dataPacientes);
            }

            return $stage;

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
            );

        }

    }

    public function getPacientesIcommkt()
    {

        try {

            global $config, $http;

            $sql = " SELECT * FROM pacientes_datos_totales WHERE ENVIADO = 'N' AND NUM_ADMISIONES_CP IS NOT NULL AND FECH_ULT_ADMISION_CP >= '01-06-2022' ORDER BY HC DESC ";

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # ExecutE
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataPacientes = $stmt->fetch();

            foreach ($dataPacientes as $key => &$value) {
                if (is_null($dataPacientes[$key])) {
                    $dataPacientes[$key] = 'N/D';
                }
            }

            # return $dataPacientes;

            $stage = $this->contactIcommkt_Pacientes($dataPacientes);

            if ($stage['res']->SaveContactJsonResult->StatusCode == 1) {
                $this->updateRegistroLogs($dataPacientes, 'Hospital');
            } else {
                $this->updateRegistroErrorLogs($dataPacientes);
            }

            return $stage;

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
            );

        }

    }

    // Actualizar resultado pendeinte por no estar completo
    private function updateRegistroErrorLogs($dataInforme)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Query
            # Insertar nuevo registro de cuenta electrónica.
            $queryBuilder
                ->update('pacientes_datos_totales', 'u')
                ->set('u.ENVIADO', '?')
                ->where('u.HC = ?')
                ->setParameter(0, 'E')
                ->setParameter(1, $dataInforme['HC'])
            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    // Actualizar resultado pendeinte por no estar completo
    private function updateRegistroLogs($dataInforme, $tipoUpdate)
    {

        try {

            if ($tipoUpdate == 'Emergencia') {

                # Conectar base de datos
                $this->conectar_Oracle();

                $this->setSpanishOracle();

                # QueryBuilder
                $queryBuilder = $this->_conexion->createQueryBuilder();

                # Query
                # Insertar nuevo registro de cuenta electrónica.
                $queryBuilder
                    ->update('pacientes_datos_totales', 'u')
                    ->set('u.ENVIADO', '?')
                    ->where('u.HC = ?')
                    ->andWhere('u.NUM_ADMISIONES_EMA IS NOT NULL')
                    ->setParameter(0, 'S')
                    ->setParameter(1, $dataInforme['HC'])
                ;

                # Execute
                $result = $queryBuilder->execute();

                $this->_conexion->close();

                if (false === $result) {
                    throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
                }

            }

            if ($tipoUpdate == 'Laboratorio') {

                # Conectar base de datos
                $this->conectar_Oracle();

                $this->setSpanishOracle();

                # QueryBuilder
                $queryBuilder = $this->_conexion->createQueryBuilder();

                # Query
                # Insertar nuevo registro de cuenta electrónica.
                $queryBuilder
                    ->update('pacientes_datos_totales', 'u')
                    ->set('u.ENVIADO', '?')
                    ->where('u.HC = ?')
                    ->andWhere('u.NUM_ADMISIONES_LAB IS NOT NULL')
                    ->setParameter(0, 'S')
                    ->setParameter(1, $dataInforme['HC'])
                ;

                # Execute
                $result = $queryBuilder->execute();

                $this->_conexion->close();

                if (false === $result) {
                    throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
                }

            }

            if ($tipoUpdate == 'Chequeos') {

                # Conectar base de datos
                $this->conectar_Oracle();

                $this->setSpanishOracle();

                # QueryBuilder
                $queryBuilder = $this->_conexion->createQueryBuilder();

                # Query
                # Insertar nuevo registro de cuenta electrónica.
                $queryBuilder
                    ->update('pacientes_datos_totales', 'u')
                    ->set('u.ENVIADO', '?')
                    ->where('u.HC = ?')
                    ->andWhere('u.NUM_ADMISIONES_CHQ IS NOT NULL')
                    ->setParameter(0, 'S')
                    ->setParameter(1, $dataInforme['HC'])
                ;

                # Execute
                $result = $queryBuilder->execute();

                $this->_conexion->close();

                if (false === $result) {
                    throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
                }

            }

            if ($tipoUpdate == 'Imagen') {

                # Conectar base de datos
                $this->conectar_Oracle();

                $this->setSpanishOracle();

                # QueryBuilder
                $queryBuilder = $this->_conexion->createQueryBuilder();

                # Query
                # Insertar nuevo registro de cuenta electrónica.
                $queryBuilder
                    ->update('pacientes_datos_totales', 'u')
                    ->set('u.ENVIADO', '?')
                    ->where('u.HC = ?')
                    ->andWhere('u.NUM_ADMISIONES_IMAGEN IS NOT NULL')
                    ->setParameter(0, 'S')
                    ->setParameter(1, $dataInforme['HC'])
                ;

                # Execute
                $result = $queryBuilder->execute();

                $this->_conexion->close();

                if (false === $result) {
                    throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
                }

            }

            if ($tipoUpdate == 'Hospital') {

                # Conectar base de datos
                $this->conectar_Oracle();

                $this->setSpanishOracle();

                # QueryBuilder
                $queryBuilder = $this->_conexion->createQueryBuilder();

                # Query
                # Insertar nuevo registro de cuenta electrónica.
                $queryBuilder
                    ->update('pacientes_datos_totales', 'u')
                    ->set('u.ENVIADO', '?')
                    ->where('u.HC = ?')
                    ->andWhere('u.NUM_ADMISIONES_EMA IS NULL')
                    ->setParameter(0, 'S')
                    ->setParameter(1, $dataInforme['HC'])
                ;

                # Execute
                $result = $queryBuilder->execute();

                $this->_conexion->close();

                if (false === $result) {
                    throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
                }

            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    public function contactIcommkt_Pacientes(array $pte): array
    {
        $apiKey = $this->apikey;
        $profileKey = 'NzEyMDc50';
        $stringData = array(
            'ProfileKey' => $profileKey,
            'Contact' => array(
                'Email' => $pte['EMAILS'], // minusculas y no emails
                'CustomFields' => array(
                    array('Key' => 'TIPO', 'Value' => $pte['TIPO']),
                    array('Key' => 'HC', 'Value' => $pte['HC']),
                    array('Key' => 'PRIMER_APELLIDO', 'Value' => $pte['PRIMER_APELLIDO']),
                    array('Key' => 'SEGUNDO_APELLIDO', 'Value' => $pte['SEGUNDO_APELLIDO']),
                    array('Key' => 'PRIMER_NOMBRE', 'Value' => $pte['PRIMER_NOMBRE']),
                    array('Key' => 'FECHA_NACIMIENTO', 'Value' => date('d/m/Y', strtotime($pte['FECHA_NACIMIENTO']))),
                    array('Key' => 'EDAD', 'Value' => $pte['EDAD']),
                    array('Key' => 'CELULAR', 'Value' => $pte['CELULAR']),
                    array('Key' => 'PAIS', 'Value' => $pte['PAIS']),
                    array('Key' => 'PROVINCIA', 'Value' => $pte['PROVINCIA']),
                    array('Key' => 'CANTON', 'Value' => $pte['CANTON']),
                    array('Key' => 'OPT_IN', 'Value' => $pte['OPT_IN']),
                    array('Key' => 'DESCRIPCION_HOSP', 'Value' => $pte['DESCRIPCION_HOSP']),
                    array('Key' => 'NUM_ADMISIONES_HOSP', 'Value' => $pte['NUM_ADMISIONES_HOSP']),
                    array('Key' => 'FECH_ULT_ADMISION_HOSP', 'Value' => date('d/m/Y', strtotime($pte['FECH_ULT_ADMISION_HOSP']))),
                    array('Key' => 'FAMACIA', 'Value' => $pte['FAMACIA']),
                    array('Key' => 'HOSPITAL', 'Value' => $pte['HOSPITAL']),
                    array('Key' => 'HONORARIOS', 'Value' => $pte['HONORARIOS']),
                    array('Key' => 'FECHA_INICIAL', 'Value' => date('d/m/Y', strtotime($pte['FECHA_INICIAL']))),
                    array('Key' => 'FECHA_FINAL', 'Value' => date('d/m/Y', strtotime($pte['FECHA_FINAL']))),
                    array('Key' => 'FECHA_PROCESO', 'Value' => date('d/m/Y', strtotime($pte['FECHA_PROCESO']))),
                    array('Key' => 'GENERO', 'Value' => $pte['GENERO']),
                    array('Key' => 'FUENTE', 'Value' => $pte['FUENTE']),
                    array('Key' => 'NUM_ADMISIONES_SAO', 'Value' => $pte['NUM_ADMISIONES_SAO']),
                    array('Key' => 'FECH_ULT_ADMISION_SAO', 'Value' => date('d/m/Y', strtotime($pte['FECH_ULT_ADMISION_SAO']))),
                    array('Key' => 'HOSPITAL_SAO', 'Value' => $pte['HOSPITAL_SAO']),
                    array('Key' => 'HONORARIOS_SAO', 'Value' => $pte['HONORARIOS_SAO']),
                    array('Key' => 'DESCRIPCION_SAO', 'Value' => $pte['DESCRIPCION_SAO']),
                    array('Key' => 'FARMACIA_SAO', 'Value' => $pte['FARMACIA_SAO']),
                    array('Key' => 'NUM_ADMISIONES_CP', 'Value' => $pte['NUM_ADMISIONES_CP']),
                    array('Key' => 'FECH_ULT_ADMISION_CP', 'Value' => date('d/m/Y', strtotime($pte['FECH_ULT_ADMISION_CP']))),
                    array('Key' => 'DESCRIPCION_CP', 'Value' => $pte['DESCRIPCION_CP']),
                    array('Key' => 'HOSPITAL_CP', 'Value' => $pte['HOSPITAL_CP']),
                    array('Key' => 'FARMACIA_CP', 'Value' => $pte['FARMACIA_CP']),
                    array('Key' => 'HONORARIOS_CP', 'Value' => $pte['HONORARIOS_CP']),
                    array('Key' => 'NUM_ADMISIONES_OF', 'Value' => $pte['NUM_ADMISIONES_OF']),
                    array('Key' => 'FECH_ULT_ADMISION_OF', 'Value' => date('d/m/Y', strtotime($pte['FECH_ULT_ADMISION_OF']))),
                    array('Key' => 'DESCRIPCION_OF', 'Value' => $pte['DESCRIPCION_OF']),
                    array('Key' => 'HOSPITAL_OF', 'Value' => $pte['HOSPITAL_OF']),
                    array('Key' => 'FARMACIA_OF', 'Value' => $pte['FARMACIA_OF']),
                    array('Key' => 'HONORARIOS_OF', 'Value' => $pte['HONORARIOS_OF']),
                    array('Key' => 'NUM_ADMISIONES_DR', 'Value' => $pte['NUM_ADMISIONES_DR']),
                    array('Key' => 'FECH_ULT_ADMISION_DR', 'Value' => date('d/m/Y', strtotime($pte['FECH_ULT_ADMISION_DR']))),
                    array('Key' => 'DESCRIPCION_DR', 'Value' => $pte['DESCRIPCION_DR']),
                    array('Key' => 'HOSPITAL_DR', 'Value' => $pte['HOSPITAL_DR']),
                    array('Key' => 'FARMACIA_DR', 'Value' => $pte['FARMACIA_DR']),
                    array('Key' => 'HONORARIOS_DR', 'Value' => $pte['HONORARIOS_DR']),
                    array('Key' => 'NUM_ADMISIONES_CD', 'Value' => $pte['NUM_ADMISIONES_CD']),
                    array('Key' => 'FECH_ULT_ADMISION_CD', 'Value' => date('d/m/Y', strtotime($pte['FECH_ULT_ADMISION_CD']))),
                    array('Key' => 'DESCRIPCION_CD', 'Value' => $pte['DESCRIPCION_CD']),
                    array('Key' => 'HOSPITAL_CD', 'Value' => $pte['HOSPITAL_CD']),
                    array('Key' => 'FARMACIA_CD', 'Value' => $pte['FARMACIA_CD']),
                    array('Key' => 'HONORARIOS_CD', 'Value' => $pte['HONORARIOS_CD']),
                    array('Key' => 'NUM_ADMISIONES_MF', 'Value' => $pte['NUM_ADMISIONES_MF']),
                    array('Key' => 'FECH_ULT_ADMISION_MF', 'Value' => date('d/m/Y', strtotime($pte['FECH_ULT_ADMISION_MF']))),
                    array('Key' => 'DESCRIPCION_MF', 'Value' => $pte['DESCRIPCION_MF']),
                    array('Key' => 'HOSPITAL_MF', 'Value' => $pte['HOSPITAL_MF']),
                    array('Key' => 'FARMACIA_MF', 'Value' => $pte['FARMACIA_MF']),
                    array('Key' => 'HONORARIOS_MF', 'Value' => $pte['HONORARIOS_MF']),
                    array('Key' => 'TIENE_SEGURO', 'Value' => $pte['TIENE_SEGURO']),

                ),
            ),
        );

        $data = json_encode($stringData);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.icommarketing.com/Contacts/SaveContact.Json/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            'Authorization: ' . $apiKey . ':0',
            'Access-Control-Allow-Origin: *')
        );

        $result = curl_exec($ch);
        curl_close($ch);
        $resultobj = json_decode($result);

        return array('res' => $resultobj);
        // print_r($resultobj->{'SaveContactJsonResult'}->{'StatusCode'});
    }

    public function contactIcommkt_Pacientes_Emergencia(array $pte): array
    {
        $apiKey = $this->apikey;
        $profileKey = 'NzEyMDkz0';
        $stringData = array(
            'ProfileKey' => $profileKey,
            'Contact' => array(
                'Email' => $pte['EMAILS'], // minusculas y no emails
                'CustomFields' => array(
                    array('Key' => 'TIPO', 'Value' => $pte['TIPO']),
                    array('Key' => 'HC', 'Value' => $pte['HC']),
                    array('Key' => 'PRIMER_APELLIDO', 'Value' => $pte['PRIMER_APELLIDO']),
                    array('Key' => 'SEGUNDO_APELLIDO', 'Value' => $pte['SEGUNDO_APELLIDO']),
                    array('Key' => 'PRIMER_NOMBRE', 'Value' => $pte['PRIMER_NOMBRE']),
                    array('Key' => 'FECHA_NACIMIENTO', 'Value' => date('d/m/Y', strtotime($pte['FECHA_NACIMIENTO']))),
                    array('Key' => 'EDAD', 'Value' => $pte['EDAD']),
                    array('Key' => 'CELULAR', 'Value' => $pte['CELULAR']),
                    array('Key' => 'PAIS', 'Value' => $pte['PAIS']),
                    array('Key' => 'PROVINCIA', 'Value' => $pte['PROVINCIA']),
                    array('Key' => 'CANTON', 'Value' => $pte['CANTON']),
                    array('Key' => 'OPT_IN', 'Value' => $pte['OPT_IN']),
                    array('Key' => 'FECHA_INICIAL', 'Value' => date('d/m/Y', strtotime($pte['FECHA_INICIAL']))),
                    array('Key' => 'FECHA_FINAL', 'Value' => date('d/m/Y', strtotime($pte['FECHA_FINAL']))),
                    array('Key' => 'FECHA_PROCESO', 'Value' => date('d/m/Y', strtotime($pte['FECHA_PROCESO']))),
                    array('Key' => 'GENERO', 'Value' => $pte['GENERO']),
                    array('Key' => 'FUENTE', 'Value' => $pte['FUENTE']),
                    array('Key' => 'NUM_ADMISIONES_EMA', 'Value' => $pte['NUM_ADMISIONES_EMA']),
                    array('Key' => 'FECH_ULT_ADMISION_EMA', 'Value' => date('d/m/Y', strtotime($pte['FECH_ULT_ADMISION_EMA']))),
                    array('Key' => 'HOSPITAL_EMA', 'Value' => $pte['HOSPITAL_EMA']),
                    array('Key' => 'HONORARIOS_EMA', 'Value' => $pte['HONORARIOS_EMA']),
                    array('Key' => 'DESCRIPCION_EMA', 'Value' => $pte['DESCRIPCION_EMA']),
                    array('Key' => 'FARMACIA_EMA', 'Value' => $pte['FARMACIA_EMA']),
                    array('Key' => 'TIENE_SEGURO', 'Value' => $pte['TIENE_SEGURO']),
                ),
            ),
        );

        $data = json_encode($stringData);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.icommarketing.com/Contacts/SaveContact.Json/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            'Authorization: ' . $apiKey . ':0',
            'Access-Control-Allow-Origin: *')
        );

        $result = curl_exec($ch);
        curl_close($ch);
        $resultobj = json_decode($result);

        return array('res' => $resultobj);
        // print_r($resultobj->{'SaveContactJsonResult'}->{'StatusCode'});
    }

    public function contactIcommkt_Pacientes_Laboratorio(array $pte): array
    {
        $apiKey = $this->apikey;
        $profileKey = 'NzEyMDkx0';
        $stringData = array(
            'ProfileKey' => $profileKey,
            'Contact' => array(
                'Email' => $pte['EMAILS'], // minusculas y no emails
                'CustomFields' => array(
                    array('Key' => 'TIPO', 'Value' => $pte['TIPO']),
                    array('Key' => 'HC', 'Value' => $pte['HC']),
                    array('Key' => 'PRIMER_APELLIDO', 'Value' => $pte['PRIMER_APELLIDO']),
                    array('Key' => 'SEGUNDO_APELLIDO', 'Value' => $pte['SEGUNDO_APELLIDO']),
                    array('Key' => 'PRIMER_NOMBRE', 'Value' => $pte['PRIMER_NOMBRE']),
                    array('Key' => 'FECHA_NACIMIENTO', 'Value' => date('d/m/Y', strtotime($pte['FECHA_NACIMIENTO']))),
                    array('Key' => 'EDAD', 'Value' => $pte['EDAD']),
                    array('Key' => 'CELULAR', 'Value' => $pte['CELULAR']),
                    array('Key' => 'PAIS', 'Value' => $pte['PAIS']),
                    array('Key' => 'PROVINCIA', 'Value' => $pte['PROVINCIA']),
                    array('Key' => 'CANTON', 'Value' => $pte['CANTON']),
                    array('Key' => 'OPT_IN', 'Value' => $pte['OPT_IN']),
                    array('Key' => 'FECHA_INICIAL', 'Value' => date('d/m/Y', strtotime($pte['FECHA_INICIAL']))),
                    array('Key' => 'FECHA_FINAL', 'Value' => date('d/m/Y', strtotime($pte['FECHA_FINAL']))),
                    array('Key' => 'FECHA_PROCESO', 'Value' => date('d/m/Y', strtotime($pte['FECHA_PROCESO']))),
                    array('Key' => 'GENERO', 'Value' => $pte['GENERO']),
                    array('Key' => 'FUENTE', 'Value' => $pte['FUENTE']),
                    array('Key' => 'NUM_ADMISIONES_LAB', 'Value' => $pte['NUM_ADMISIONES_LAB']),
                    array('Key' => 'DESCRIPCION_LAB', 'Value' => $pte['DESCRIPCION_LAB']),
                    array('Key' => 'FECH_ULT_ADMISION_LAB', 'Value' => date('d/m/Y', strtotime($pte['FECH_ULT_ADMISION_LAB']))),
                    array('Key' => 'LABORATORIO_DE_ENDOCRINOLOGIA', 'Value' => $pte['LABORATORIO_DE_ENDOCRINOLOGIA']),
                    array('Key' => 'LABORATORIO_DE_GENETICA', 'Value' => $pte['LABORATORIO_DE_GENETICA']),
                    array('Key' => 'LABORATORIO_DE_INMUNOLOGIA', 'Value' => $pte['LABORATORIO_DE_INMUNOLOGIA']),
                    array('Key' => 'LABORATORIO_DE_QUIMICA', 'Value' => $pte['LABORATORIO_DE_QUIMICA']),
                    array('Key' => 'LABORATORIO_GASTROENTEROLOGIA', 'Value' => $pte['LABORATORIO_GASTROENTEROLOGIA']),
                    array('Key' => 'LABORATORIO_HEMATOLOGIA', 'Value' => $pte['LABORATORIO_HEMATOLOGIA']),
                    array('Key' => 'LABORATORIO_MICROBIOLOGIA', 'Value' => $pte['LABORATORIO_MICROBIOLOGIA']),
                    array('Key' => 'TIENE_SEGURO', 'Value' => $pte['TIENE_SEGURO']),
                ),
            ),
        );

        $data = json_encode($stringData);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.icommarketing.com/Contacts/SaveContact.Json/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            'Authorization: ' . $apiKey . ':0',
            'Access-Control-Allow-Origin: *')
        );

        $result = curl_exec($ch);
        curl_close($ch);
        $resultobj = json_decode($result);

        return array('res' => $resultobj);
        // print_r($resultobj->{'SaveContactJsonResult'}->{'StatusCode'});
    }

    public function contactIcommkt_Pacientes_Chequeos(array $pte): array
    {
        $apiKey = $this->apikey;
        $profileKey = 'NzEyMDg40';
        $stringData = array(
            'ProfileKey' => $profileKey,
            'Contact' => array(
                'Email' => $pte['EMAILS'], // minusculas y no emails
                'CustomFields' => array(
                    array('Key' => 'TIPO', 'Value' => $pte['TIPO']),
                    array('Key' => 'HC', 'Value' => $pte['HC']),
                    array('Key' => 'PRIMER_APELLIDO', 'Value' => $pte['PRIMER_APELLIDO']),
                    array('Key' => 'SEGUNDO_APELLIDO', 'Value' => $pte['SEGUNDO_APELLIDO']),
                    array('Key' => 'PRIMER_NOMBRE', 'Value' => $pte['PRIMER_NOMBRE']),
                    array('Key' => 'FECHA_NACIMIENTO', 'Value' => date('d/m/Y', strtotime($pte['FECHA_NACIMIENTO']))),
                    array('Key' => 'EDAD', 'Value' => $pte['EDAD']),
                    array('Key' => 'CELULAR', 'Value' => $pte['CELULAR']),
                    array('Key' => 'PAIS', 'Value' => $pte['PAIS']),
                    array('Key' => 'PROVINCIA', 'Value' => $pte['PROVINCIA']),
                    array('Key' => 'CANTON', 'Value' => $pte['CANTON']),
                    array('Key' => 'OPT_IN', 'Value' => $pte['OPT_IN']),
                    array('Key' => 'FECHA_INICIAL', 'Value' => date('d/m/Y', strtotime($pte['FECHA_INICIAL']))),
                    array('Key' => 'FECHA_FINAL', 'Value' => date('d/m/Y', strtotime($pte['FECHA_FINAL']))),
                    array('Key' => 'FECHA_PROCESO', 'Value' => date('d/m/Y', strtotime($pte['FECHA_PROCESO']))),
                    array('Key' => 'GENERO', 'Value' => $pte['GENERO']),
                    array('Key' => 'FUENTE', 'Value' => $pte['FUENTE']),
                    array('Key' => 'NUM_ADMISIONES_CHQ', 'Value' => $pte['NUM_ADMISIONES_CHQ']),
                    array('Key' => 'DESCRIPCION_CHQ', 'Value' => $pte['DESCRIPCION_CHQ']),
                    array('Key' => 'FECH_ULT_ADMISION_CHQ', 'Value' => date('d/m/Y', strtotime($pte['FECH_ULT_ADMISION_CHQ']))),
                    array('Key' => 'HOSPITAL_CHQ', 'Value' => $pte['HOSPITAL_CHQ']),
                    array('Key' => 'FARMACIA_CHQ', 'Value' => $pte['FARMACIA_CHQ']),
                    array('Key' => 'HONORARIOS_CHQ', 'Value' => $pte['HONORARIOS_CHQ']),
                    array('Key' => 'TIENE_SEGURO', 'Value' => $pte['TIENE_SEGURO']),
                ),
            ),
        );

        $data = json_encode($stringData);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.icommarketing.com/Contacts/SaveContact.Json/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            'Authorization: ' . $apiKey . ':0',
            'Access-Control-Allow-Origin: *')
        );

        $result = curl_exec($ch);
        curl_close($ch);
        $resultobj = json_decode($result);

        return array('res' => $resultobj);
        // print_r($resultobj->{'SaveContactJsonResult'}->{'StatusCode'});
    }

    public function contactIcommkt_Pacientes_Imagen(array $pte): array
    {
        $apiKey = $this->apikey;
        $profileKey = 'NzEyMDc00';
        $stringData = array(
            'ProfileKey' => $profileKey,
            'Contact' => array(
                'Email' => $pte['EMAILS'], // minusculas y no emails
                'CustomFields' => array(
                    array('Key' => 'TIPO', 'Value' => $pte['TIPO']),
                    array('Key' => 'HC', 'Value' => $pte['HC']),
                    array('Key' => 'PRIMER_APELLIDO', 'Value' => $pte['PRIMER_APELLIDO']),
                    array('Key' => 'SEGUNDO_APELLIDO', 'Value' => $pte['SEGUNDO_APELLIDO']),
                    array('Key' => 'PRIMER_NOMBRE', 'Value' => $pte['PRIMER_NOMBRE']),
                    array('Key' => 'FECHA_NACIMIENTO', 'Value' => date('d/m/Y', strtotime($pte['FECHA_NACIMIENTO']))),
                    array('Key' => 'EDAD', 'Value' => $pte['EDAD']),
                    array('Key' => 'CELULAR', 'Value' => $pte['CELULAR']),
                    array('Key' => 'PAIS', 'Value' => $pte['PAIS']),
                    array('Key' => 'PROVINCIA', 'Value' => $pte['PROVINCIA']),
                    array('Key' => 'CANTON', 'Value' => $pte['CANTON']),
                    array('Key' => 'OPT_IN', 'Value' => $pte['OPT_IN']),
                    array('Key' => 'FECHA_INICIAL', 'Value' => date('d/m/Y', strtotime($pte['FECHA_INICIAL']))),
                    array('Key' => 'FECHA_FINAL', 'Value' => date('d/m/Y', strtotime($pte['FECHA_FINAL']))),
                    array('Key' => 'FECHA_PROCESO', 'Value' => date('d/m/Y', strtotime($pte['FECHA_PROCESO']))),
                    array('Key' => 'GENERO', 'Value' => $pte['GENERO']),
                    array('Key' => 'FUENTE', 'Value' => $pte['FUENTE']),
                    array('Key' => 'NUM_ADMISIONES_IMAGEN', 'Value' => $pte['NUM_ADMISIONES_IMAGEN']),
                    array('Key' => 'DESCRIPCION_IMAGEN', 'Value' => $pte['DESCRIPCION_IMAGEN']),
                    array('Key' => 'FECH_ULT_ADMISION_IMAGEN', 'Value' => date('d/m/Y', strtotime($pte['FECH_ULT_ADMISION_IMAGEN']))),
                    array('Key' => 'IMAGEN_MAMOGRAFIA', 'Value' => $pte['IMAGEN_MAMOGRAFIA']),
                    array('Key' => 'RADIOLOGIA_CONVENCIONAL', 'Value' => $pte['RADIOLOGIA_CONVENCIONAL']),
                    array('Key' => 'TOMOGRAFIA_COMPUTARIZADA', 'Value' => $pte['TOMOGRAFIA_COMPUTARIZADA']),
                    array('Key' => 'ULTRASONIDO', 'Value' => $pte['ULTRASONIDO']),
                    array('Key' => 'TIENE_SEGURO', 'Value' => $pte['TIENE_SEGURO']),
                ),
            ),
        );

        $data = json_encode($stringData);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.icommarketing.com/Contacts/SaveContact.Json/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            'Authorization: ' . $apiKey . ':0',
            'Access-Control-Allow-Origin: *')
        );

        $result = curl_exec($ch);
        curl_close($ch);
        $resultobj = json_decode($result);

        return array('res' => $resultobj);
        // print_r($resultobj->{'SaveContactJsonResult'}->{'StatusCode'});
    }

    /**
     * __construct()
     */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);

    }
}
