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
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;
use setasign\Fpdi\Fpdi;

/**
 * Modelo Imagen
 */
class Imagen extends Models implements IModels
{
    # Variables de clase
    private $pstrSessionKey = 0;
    private $USER = null;
    private $sortField = 'ROWNUM_';
    private $sortType = 'desc'; # desc
    private $offset = 1;
    private $limit = 25;
    private $searchField = null;
    private $startDate = null;
    private $endDate = null;
    private $tresMeses = null;
    private $urlApiImagen = '//api.hospitalmetropolitano.org/v1/';
    private $urlApiViewer = '//api.imagen.hospitalmetropolitano.org/';

    private function conectar_Medora()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracleMedora'], $_config);

    }

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

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

    private function getAuthorization()
    {

        try {

            global $http;

            $token = $http->headers->get("Authorization");

            $auth = new Model\Auth;
            $key = $auth->GetData($token);

            $this->USER = $key;

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function errorsPagination()
    {

        if ($this->limit > 25) {
            throw new ModelsException('!Error! Solo se pueden mostrar 25 resultados por página.');
        }

        if ($this->limit == 0 or $this->limit < 0) {
            throw new ModelsException('!Error! {Limit} no puede ser 0 o negativo');
        }

        if ($this->offset == 0 or $this->offset < 0) {
            throw new ModelsException('!Error! {Offset} no puede ser 0 o negativo.');
        }

    }

    private function setParameters()
    {

        global $http;

        foreach ($http->request->all() as $key => $value) {
            $this->$key = strtoupper($value);
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

    }

    private function setNHC()
    {

        global $http;

        $sql = "SELECT fun_busca_mail_persona(" . $this->USER->CP_PTE[0] . ") as emailsPaciente from dual ";

        # Conectar base de datos
        $this->conectar_Oracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetch();

        return $data;

    }

    private function getNHC($codPersona)
    {

        global $http;

        $sql = "SELECT t.pk_nhcl
        from cad_pacientes t
        where t.fk_persona = '$codPersona' ";

        # Conectar base de datos
        $this->conectar_Oracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetch();

        return $data['PK_NHCL'];

    }

    public function getResultadosImagen(): array
    {

        try {

            global $config;

            # SETEAR VARIABLES DE CLASE
            $this->setParameters();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # EXTRAER VALOR DEL TOKEN PARA CONSULTA
            $this->getAuthorization();

            $codes = implode(',', $this->USER->CP_PTE);

            $userPat = $this->getNHC($this->USER->CP_PTE[0]);

            $fecha = date('d-m-Y');
            $nuevafecha = strtotime('-3 month', strtotime($fecha));

            #SETEAR FILTRO HASTA TRES MESES
            $tresMeses = date('d-m-Y', $nuevafecha);

            $sql = " SELECT A_US_PATIENT_BASIC.PAT_PID_NUMBER,
                A_US_PATIENT_BASIC.PAT_LAST_NAME,
                A_US_PATIENT_BASIC.PAT_FIRST_NAME,
                A_US_PERFORMED_PROCEDURES.SECTION_CODE,
                A_US_PERFORMED_PROCEDURES.PROCEDURE_CODE,
                A_US_PERFORMED_PROCEDURES.PROCEDURE_NAME,
                A_US_PERFORMED_PROCEDURES.REGISTRATION_ARRIVAL,
                A_US_PERFORMED_PROCEDURES.PROCEDURE_START,
                A_US_PERFORMED_PROCEDURES.PROCEDURE_END,
                A_US_REPORT.REPORT_DATE,
                A_US_PERFORMED_PROCEDURES.ADMISSION_TYPE,
                A_US_PATIENT_BASIC.PAT_PID_NUMBER,
                A_US_REPORT.PROCEDURE_KEY,
                A_US_REPORT.REPORT_KEY,
                A_US_REPORT.SIGNER_CODE,
                A_US_REPORT.READER_CODE_INTENDED,
                A_US_PERFORMED_PROCEDURES.SECTION_CODE,
                A_US_PATIENT_BASIC.PAT_BIRTH_DATE,
                A_US_REPORT.REPORT_STATUS,
                A_US_PERFORMED_PROCEDURES.REF_SOURCE_CONTACT_NAME
            FROM MEDORA.A_US_PATIENT_BASIC A_US_PATIENT_BASIC,
                MEDORA.A_US_PERFORMED_PROCEDURES A_US_PERFORMED_PROCEDURES,
                MEDORA.A_US_REPORT A_US_REPORT
            WHERE A_US_PATIENT_BASIC.PATIENT_KEY = A_US_PERFORMED_PROCEDURES.PATIENT_KEY
            AND A_US_PERFORMED_PROCEDURES.PATIENT_KEY = A_US_REPORT.PATIENT_KEY
            AND A_US_PERFORMED_PROCEDURES.PROCEDURE_START = A_US_REPORT.PROCEDURE_START
            AND A_US_PERFORMED_PROCEDURES.PROCEDURE_CODE = A_US_REPORT.PROCEDURE_CODE
            AND A_US_PERFORMED_PROCEDURES.PROCEDURE_START >= TO_DATE('01-01-2018', 'dd-mm-yyyy')
            AND A_US_REPORT.REPORT_STATUS = 'f'
            AND ((A_US_PATIENT_BASIC.PAT_PID_NUMBER = '$userPat' )) ORDER BY A_US_PERFORMED_PROCEDURES.PROCEDURE_START DESC";

            # SELECT * FROM A_US_REPORT_TEXT WHERE REPORT_KEY = '232095' // 60055801

            # Conectar base de datos
            $this->conectar_Medora();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $resultados = array();

            foreach ($data as $key) {

                $hashReport = Helper\Strings::ocrend_encode($key['REPORT_KEY'], 'temp');
                $hashEstudio = Helper\Strings::ocrend_encode($key['PROCEDURE_KEY'], 'temp');
                $token = Helper\Strings::ocrend_encode(time(), 'temp');

                $k['NHC'] = $key['PAT_PID_NUMBER'];
                $k['FECHA'] = $key['PROCEDURE_START'];
                $k['ESTUDIO'] = $key['PROCEDURE_NAME'];
                $k['URL_INFORME'] = $this->urlApiImagen . 'resultado/informe/' . $hashReport . '.pdf';
                $k['URL_ESTUDIO'] = $this->urlApiViewer . 'viewer/' . $hashEstudio . '&key=' . $token;
                # $k['URL_INFORME'] = $config['build']['url'] . 'v1/resultado/informe/' . $hashReport . '.pdf';

                $resultados[] = array_merge($k, $key);

            }

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Order by asc to desc
            $RESULTADOS = $this->get_Order_Pagination($resultados);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->get_page($RESULTADOS, $this->offset, $this->limit),
                'total' => count($resultados),
                'limit' => intval($this->limit),
                'offset' => intval($this->offset),
                'logs' => $userPat,
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status' => true,
                    'data' => [],
                    'message' => $e->getMessage(),
                    'errorCode' => 4080,
                    'logs' => $userPat,

                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getInformeEstudio($idEstudio)
    {

        try {

            global $config, $http;

            # Verificar que no están vacíos
            if (Helper\Functions::e($idEstudio)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion.');
            }

            $sql = "SELECT *
            FROM medora.BEFUND_ASCII_TEXT BEFUND_ASCII_TEXT
            WHERE BEFUND_ASCII_TEXT.BEFUND_UBEID='$idEstudio' ";

            # SELECT * FROM A_US_REPORT_TEXT WHERE REPORT_KEY = '232095'

            # Conectar base de datos
            $this->conectar_Medora();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataEstudio = $stmt->fetch();

            if ($dataEstudio == false) {
                throw new ModelsException('No existe más resultados1.', 4080);
            }

            $sql = "SELECT a_us_patient_basic.pat_pid_number,
            a_us_patient_basic.pat_last_name,
            a_us_patient_basic.pat_first_name,
            a_us_performed_procedures.section_code,
            a_us_performed_procedures.procedure_code,
            a_us_performed_procedures.procedure_name,
            a_us_performed_procedures.procedure_start,
            a_us_performed_procedures.procedure_end,
            a_us_performed_procedures.REF_SOURCE_NAME,
            a_us_report.report_date,
            a_us_performed_procedures.admission_type,
            a_us_report_wordcount.procedure_key,
            a_us_report.report_status,
            a_us_report.report_key,
            a_us_patient_basic.pat_birth_date,
            a_us_report.typist_code,
            a_us_report.signer_code
            FROM medora.a_us_patient_basic A_US_PATIENT_BASIC,
                 medora.a_us_performed_procedures A_US_PERFORMED_PROCEDURES,
                 medora.a_us_report A_US_REPORT,
                 medora.a_us_report_wordcount A_US_REPORT_WORDCOUNT
            WHERE a_us_patient_basic.patient_key = a_us_performed_procedures.patient_key
                 AND a_us_performed_procedures.patient_key = a_us_report.patient_key
                 AND a_us_performed_procedures.procedure_start =  a_us_report.procedure_start
                 AND a_us_performed_procedures.procedure_code = a_us_report.procedure_code
                 AND a_us_report.patient_key = a_us_report_wordcount.patient_key
                 AND a_us_report.procedure_code = a_us_report_wordcount.procedure_code
                 AND a_us_report.procedure_start = a_us_report_wordcount.procedure_start
                 AND a_us_report.report_key = '$idEstudio'
                 AND to_char(a_us_performed_procedures.procedure_start, 'DD/MM/YYYY hh24:mi') >= '15/09/2008 00:00'
                 AND (a_us_performed_procedures.section_code LIKE 'AE%' OR a_us_performed_procedures.section_code LIKE 'HM%')
            ORDER BY a_us_performed_procedures.procedure_start DESC";

            # SELECT * FROM A_US_REPORT_TEXT WHERE REPORT_KEY = '232095'

            # Conectar base de datos
            $this->conectar_Medora();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataPte = $stmt->fetch();

            if ($dataPte == false) {
                throw new ModelsException('No existe más resultados2.' . $idEstudio, 4080);
            }

            if ($dataPte['REPORT_STATUS'] !== 'f') {
                throw new ModelsException('Informe no disponible.', 4080);
            }

            $edad = $this->calcular_edad($dataPte['PAT_BIRTH_DATE']);

            $data = array(
                'idEstudio' => $idEstudio,
                'fechaEstudio' => $dataPte['PROCEDURE_START'],
                'codMedico' => $dataPte['SIGNER_CODE'],
                'medico' => ($dataPte['REF_SOURCE_NAME'] == null) ? 'N/D' : $dataPte['REF_SOURCE_NAME'],
                'cuerpo' => $dataEstudio['BEFUND_ASCII_TEXT'],
                'nroPrueba' => $idEstudio,
                'nroHab' => 'N/D',
                'origen' => $dataPte['SECTION_CODE'],
                'nombrePte' => $dataPte['PAT_FIRST_NAME'] . ' ' . $dataPte['PAT_LAST_NAME'],
                'statusStudio' => $dataPte['REPORT_STATUS'],
                'hc' => $dataPte['PAT_PID_NUMBER'],
                'edad' => (string) $edad,
            );

            if ($dataPte['SECTION_CODE'] == 'HM-ES' || $dataPte['SECTION_CODE'] == 'HM-ES1' || $dataPte['SECTION_CODE'] == 'HM-ES2' || $dataPte['SECTION_CODE'] == 'HM-ES2') {
                $generate = $this->sendProcessPedidoEndoscopia($data);
            } else {
                $generate = $this->sendProcessPedidoImg($data);
            }

            if ($generate) {

                $hashEstudio = Helper\Strings::ocrend_encode($idEstudio, 'temp');

                return array(
                    'status' => true,
                    'codMedico' => $dataPte['TYPIST_CODE'],
                    'data' => array($generate, $dataPte, $dataEstudio),
                    'dataPte' => $dataPte,
                );

            } else {
                throw new ModelsException('Proceso no se completo con éxito.');
            }
            # Devolver Información

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
            );

        }

    }

    public function getTaskInformeEstudio($idEstudio)
    {

        try {

            global $config, $http;

            # Verificar que no están vacíos
            if (Helper\Functions::e($idEstudio)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion.');
            }

            $sql = "SELECT *
            FROM medora.BEFUND_ASCII_TEXT BEFUND_ASCII_TEXT
            WHERE BEFUND_ASCII_TEXT.BEFUND_UBEID='$idEstudio' ";

            # SELECT * FROM A_US_REPORT_TEXT WHERE REPORT_KEY = '232095'

            # Conectar base de datos
            $this->conectar_Medora();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataEstudio = $stmt->fetch();

            if ($dataEstudio == false) {
                throw new ModelsException('No existe más resultados1.', 4080);
            }

            $sql = "SELECT a_us_patient_basic.pat_pid_number,
            a_us_patient_basic.pat_last_name,
            a_us_patient_basic.pat_first_name,
            a_us_performed_procedures.section_code,
            a_us_performed_procedures.procedure_code,
            a_us_performed_procedures.procedure_name,
            a_us_performed_procedures.procedure_start,
            a_us_performed_procedures.procedure_end,
            a_us_performed_procedures.REF_SOURCE_NAME,
            a_us_report.report_date,
            a_us_performed_procedures.admission_type,
            a_us_report_wordcount.procedure_key,
            a_us_report.report_status,
            a_us_report.report_key,
            a_us_patient_basic.pat_birth_date,
            a_us_report.typist_code,
            a_us_report.signer_code
            FROM medora.a_us_patient_basic A_US_PATIENT_BASIC,
                 medora.a_us_performed_procedures A_US_PERFORMED_PROCEDURES,
                 medora.a_us_report A_US_REPORT,
                 medora.a_us_report_wordcount A_US_REPORT_WORDCOUNT
            WHERE a_us_patient_basic.patient_key = a_us_performed_procedures.patient_key
                 AND a_us_performed_procedures.patient_key = a_us_report.patient_key
                 AND A_US_PERFORMED_PROCEDURES.PROCEDURE_START >= TO_DATE('01-01-2018', 'dd-mm-yyyy')
                 AND a_us_performed_procedures.procedure_start =  a_us_report.procedure_start
                 AND a_us_performed_procedures.procedure_code = a_us_report.procedure_code
                 AND a_us_report.patient_key = a_us_report_wordcount.patient_key
                 AND a_us_report.procedure_code = a_us_report_wordcount.procedure_code
                 AND a_us_report.procedure_start = a_us_report_wordcount.procedure_start
                 AND a_us_report.report_key = '$idEstudio'
                 AND to_char(a_us_performed_procedures.procedure_start, 'DD/MM/YYYY hh24:mi') >= '15/09/2008 00:00'
                 AND (a_us_performed_procedures.section_code LIKE 'AE%' OR a_us_performed_procedures.section_code LIKE 'HM%')
            ORDER BY a_us_performed_procedures.procedure_start DESC";

            # SELECT * FROM A_US_REPORT_TEXT WHERE REPORT_KEY = '232095'

            # Conectar base de datos
            $this->conectar_Medora();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataPte = $stmt->fetch();

            if ($dataPte == false) {
                throw new ModelsException('No existe informe ID_REPORT => ' . $idEstudio, 4100);
            }

            if ($dataPte['REPORT_STATUS'] !== 'f') {
                throw new ModelsException('Informe sin firmar ID_REPORT => ' . $idEstudio, 4200);
            }

            $edad = $this->calcular_edad($dataPte['PAT_BIRTH_DATE']);

            $data = array(
                'idEstudio' => $idEstudio,
                'fechaEstudio' => $dataPte['PROCEDURE_START'],
                'codMedico' => $dataPte['SIGNER_CODE'],
                'medico' => ($dataPte['REF_SOURCE_NAME'] == null) ? 'N/D' : $dataPte['REF_SOURCE_NAME'],
                'cuerpo' => $dataEstudio['BEFUND_ASCII_TEXT'],
                'nroPrueba' => $idEstudio,
                'nroHab' => 'N/D',
                'origen' => $dataPte['SECTION_CODE'],
                'nombrePte' => $dataPte['PAT_FIRST_NAME'] . ' ' . $dataPte['PAT_LAST_NAME'],
                'statusStudio' => $dataPte['REPORT_STATUS'],
                'hc' => $dataPte['PAT_PID_NUMBER'],
                'edad' => (string) $edad,
            );

            if ($dataPte['SECTION_CODE'] == 'HM-ES' || $dataPte['SECTION_CODE'] == 'HM-ES1' || $dataPte['SECTION_CODE'] == 'HM-ES2' || $dataPte['SECTION_CODE'] == 'HM-ES2') {
                $generate = $this->sendProcessPedidoEndoscopia($data);
            } else {
                $generate = $this->sendProcessPedidoImg($data);
            }

            if ($generate) {

                $hashEstudio = Helper\Strings::ocrend_encode($idEstudio, 'temp');

                return array(
                    'status' => true,
                    'codMedico' => $dataPte['TYPIST_CODE'],
                    'data' => array($generate, $dataPte, $dataEstudio),
                    'dataPte' => $dataPte,
                );

            } else {
                throw new ModelsException('Proceso con error ID_REPORT => ' . $idEstudio, 4300);
            }
            # Devolver Información

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),

            );

        }

    }

    public function editPDF($archivo, $codMedicoFirma, $dataPte)
    {

        try {

            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile('../v1/downloads/' . $archivo . '.pdf');

            for ($pageNo = 1; $pageNo <= (($pageCount >= 1) ? $pageCount : ($pageCount - 1)); $pageNo++) {

                $pdf->AddPage();
                $template = $pdf->importPage($pageNo);
                $pdf->useTemplate($template);

                if ($dataPte['SIGNER_CODE'] == $dataPte['TYPIST_CODE']) {
                    $firmaMedico = "../assets/risfirmas/" . $dataPte['SIGNER_CODE'] . ".jpg";
                    # Hm Firma Médico SIGNER_CODE
                    $pdf->Image($firmaMedico, 15, 221, 45, 30);
                } else {
                    $firmaMedico = "../assets/risfirmas/" . $dataPte['SIGNER_CODE'] . ".jpg";
                    $firmaMedico2 = "../assets/risfirmas/" . $dataPte['TYPIST_CODE'] . ".jpg";

                    # Hm Firma Médico SIGNER_CODE
                    $pdf->Image($firmaMedico, 15, 221, 45, 30);

                    # Hm Firma Médico READER_CODE_INTENDED
                    $pdf->Image($firmaMedico2, 80, 221, 45, 30);
                }

            }

            $pdf->Output("F", '../v1/downloads/' . $archivo . '.firmado.pdf');

            return true;

        } catch (ModelsException $e) {
            return false;
        }

    }

    public function shareEstudio()
    {

        global $config, $http;

        $idEstudio = $http->request->get('idEstudio');
        $mensaje = $http->request->get('mensaje');
        $correoElectronico = $http->request->get('correoElectronico');
        $fechaCaducidad = $http->request->get('fechaCeducidad');

        # Verificar que no están vacíos
        if (Helper\Functions::e($idEstudio, $correoElectronico, $fechaCaducidad)) {
            throw new ModelsException('Parámetros insuficientes para esta peticion.');
        }

        $stringData = array();

        $data = json_encode(array(
            'idEstudio' => $idEstudio,
            'correoElectronico' => $correoElectronico,
            'fechaCaducidad' => $fechaCaducidad,
            'mensaje' => $mensaje,
        ));

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.imagen.hospitalmetropolitano.org/v1/share");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json')
        );

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $resultobj = curl_error($ch);
        }
        curl_close($ch);
        $resultobj = json_decode($result);

        return $resultobj;

    }

    public function calcular_edad($fecha)
    {
        $dias = explode("-", $fecha, 3);
        $dias = mktime(0, 0, 0, $dias[1], $dias[0], $dias[2]);
        $edad = (int) ((time() - $dias) / 31556926);
        return $edad;
    }

    # Ordenar array por campo
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

    private function get_Order_Pagination(array $arr_input)
    {
        # SI ES DESCENDENTE

        $arr = array();
        $NUM = 1;

        if ($this->sortType == 'desc') {

            $NUM = count($arr_input);
            foreach ($arr_input as $key) {
                $key['NUM'] = $NUM;
                $arr[] = $key;
                $NUM--;
            }

            return $arr;

        }

        # SI ES ASCENDENTE

        foreach ($arr_input as $key) {
            $key['NUM'] = $NUM;
            $arr[] = $key;
            $NUM++;
        }

        return $arr;
    }

    private function get_page(array $input, $pageNum, $perPage)
    {
        $start = ($pageNum - 1) * $perPage;
        $end = $start + $perPage;
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

    private function notResults(array $data)
    {
        if (count($data) == 0) {
            throw new ModelsException('No existe más resultados.', 4080);
        }
    }

    public function sendProcessPedidoImg(array $data)
    {

        $_datos = json_encode($data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://prod-36.westus.logic.azure.com:443/workflows/9bfdb2a982ce40e2b0d2532f547e1704/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=HOgtmb57umWNI_4ADO1916RNKi8v-pkKGQqkoVr7Ijc');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $_datos);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
        ));

        $response = curl_exec($ch);

        // Se cierra el recurso CURL y se liberan los recursos del sistema
        file_put_contents('../v1/downloads/' . $data['idEstudio'] . '.pdf', $response);

        curl_close($ch);

        return true;

    }

    public function sendProcessPedidoEndoscopia(array $data)
    {

        $_datos = json_encode($data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://prod-64.westus.logic.azure.com:443/workflows/0ea3b8109b354197a8548613b8ae33cd/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=c_ms7ymS3jbaIpc7ly39K9emFWdp1TJXPdu_S_6EM3M');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $_datos);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
        ));

        $response = curl_exec($ch);

        // Se cierra el recurso CURL y se liberan los recursos del sistema
        file_put_contents('../v1/downloads/' . $data['idEstudio'] . '.pdf', $response);

        curl_close($ch);

        return true;

    }

/**
 * __construct()
 */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
    }
}
