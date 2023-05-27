<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models\ptes;

use app\models\ptes as Model;
use DateTime;
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
    private $urlApiImagen = 'https://api.hospitalmetropolitano.org/v2/pacientes/resultado/i/?id=';
    private $urlApiPDF = '/resultado/i/';
    private $urlApiViewer = '/viewer';
    private $_log = null;

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

    private function conectar_Oracle_MV()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();

        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle_mv'], $_config);

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

    public function getResultadosImg($nhc)
    {

        try {

            global $config, $http;

            $this->setParameters();

            $sql = " SELECT medora.VW_RESULTADOS_WEB_PTES.*, TO_CHAR(medora.VW_RESULTADOS_WEB_PTES.PROCEDURE_START, 'YYYY-MM-DD HH24:MM') AS FECHA_RES  FROM medora.VW_RESULTADOS_WEB_PTES WHERE PAT_PID_NUMBER = '$nhc' AND REPORT_STATUS = 'f' ORDER BY PROCEDURE_KEY DESC ";

            # Conectar base de datos
            $this->conectar_Medora();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            if (count($data) === 0) {
                throw new ModelsException('El Paciente todavía no tiene resultados disponibles.');
            }

            # Datos de usuario cuenta activa
            $resultados = array();

            foreach ($data as $key) {

                $hashReport = Helper\Strings::ocrend_encode($key['REPORT_KEY'], 'hm');
                $hashEstudio = Helper\Strings::ocrend_encode($key['PROCEDURE_KEY'], 'temp');
                $token = Helper\Strings::ocrend_encode(time(), 'hm');

                $k['ID_ESTUDIO'] = $key['PROCEDURE_KEY'];
                $k['NHC'] = $key['PAT_PID_NUMBER'];
                $k['FECHA'] = date('d-m-Y H:i', strtotime($key['FECHA_RES']));
                $k['ESTUDIO'] = $key['PROCEDURE_NAME'];
                $k['MEDICO'] = $key['REF_SOURCE_NAME'];
                $k['fecha_'] = $key['FECHA_RES'];
                $k['urlPdf'] = $this->urlApiImagen . $hashReport;
                $k['deep_link'] = $this->urlApiPDF . $hashReport;
                $k['viewer'] = $this->urlApiViewer . '/' . $hashEstudio;

                $resultados[] = $k;

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $resultados,
                'total' => count($resultados),
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

        }

    }

    public function getResultados()
    {

        try {

            global $config, $http;

            $nhc = $http->query->get('nhc');

            $numeroHistoriaClinica = $this->getNHC('79964401');

            $sql = " SELECT * FROM VW_RESULTADOS_WEB_PTES WHERE PAT_PID_NUMBER = '$numeroHistoriaClinica' AND TRUNC(PROCEDURE_START) >= '01-01-2015'  ORDER BY PROCEDURE_KEY DESC  ";

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

                $hashEstudio = Helper\Strings::ocrend_encode($key['PROCEDURE_KEY'], 'temp');
                $token = Helper\Strings::ocrend_encode(time(), 'temp');

                $k['NHC'] = $key['PAT_PID_NUMBER'];
                $k['FECHA'] = $key['PROCEDURE_START'];
                $k['ESTUDIO'] = $key['PROCEDURE_NAME'];
                $k['MEDICO'] = $key['REF_SOURCE_CONTACT_NAME'];

                $k['URL'] = $this->urlApiImagen . $hashEstudio;

                $resultados[] = $k;

            }

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $resultados,
                'total' => count($resultados),
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
                'errorCode' => $e->getCode(),

            );

        }

    }

    public function getMedicoSolicitanteMV($numPedido)
    {

        $_numPedido = substr($numPedido, 2);
        $numPedido = $_numPedido;

        $sql = " SELECT p.nm_prestador
        from itpre_med a, ped_rx b, prestador p
        where a.cd_pre_med = b.cd_pre_med and cd_ped_rx = '$numPedido' and p.cd_prestador = a.cd_prestador ";

        # Conectar base de datos
        $this->conectar_Oracle_MV();

        $this->setSpanishOracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $dataEstudio = $stmt->fetch();

        if ($dataEstudio === false) {
            return false;
        }

        if ($dataEstudio['NM_PRESTADOR'] === '') {
            return false;
        }

        return $dataEstudio['NM_PRESTADOR'];
    }

    public function getInformeEstudio2($idEstudio)
    {

        try {

            global $config, $http;

            # Verificar que no están vacíos
            if (Helper\Functions::e($idEstudio)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion.');
            }

            $hashEstudio = Helper\Strings::ocrend_encode($idEstudio, 'hm');

            $destination = "../../v2/pacientes/docs/img/" . $hashEstudio . ".pdf";
            $doc = file_exists($destination);

            if ($doc) {

                $urlPdf = 'https://api.hospitalmetropolitano.org/v2/pacientes/d/' . $hashEstudio . '.pdf';

                return array(
                    'status' => true,
                    'data' => $urlPdf,
                );
            }

            $sql = " SELECT * FROM medora.A_US_REPORT_TEXT WHERE REPORT_KEY = '$idEstudio' ";

            # SELECT * FROM A_US_REPORT_TEXT WHERE REPORT_KEY = '232095'

            # Conectar base de datos
            $this->conectar_Medora();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataEstudio = $stmt->fetchAll();

            if ($dataEstudio == false) {
                throw new ModelsException('No existe más resultados1.', 4080);
            }

            $rtfContent = '';

            foreach ($dataEstudio as $key) {
                $rtfContent .= trim($key['REPORT_TEXT']);
            }

            $sql = " SELECT * FROM medora.VW_RESULTADOS_WEB_PTES WHERE REPORT_KEY = '$idEstudio' ";

            # Conectar base de datos
            $this->conectar_Medora();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataPte = $stmt->fetch();

            # return $dataPte;

            if ($dataPte == false) {
                throw new ModelsException('No existe más resultados2.' . $idEstudio, 4080);
            }

            if ($dataPte['REPORT_STATUS'] !== 'f') {
                throw new ModelsException('Informe no disponible.', 4080);
            }

            # Medico Solictante MV
            /*
            $medicoSolicitante = $this->getMedicoSolicitanteMV($dataPte['REQUEST_NO']);
            if ($medicoSolicitante !== false) {
            $dataPte['REF_SOURCE_NAME'] = $medicoSolicitante;
            }
             */

            $edad = $this->calcular_edad($dataPte['PAT_BIRTH_DATE']);

            # 2 Firmas
            if ($dataPte['SIGNER_CODE'] !== $dataPte['READER_CODE_INTENDED']) {

                $firmaMedico = "../../assets/risfirmas/" . $dataPte['SIGNER_CODE'] . ".jpg";

                if (@file_get_contents($firmaMedico, true) === false) {
                    throw new ModelsException('Proceso no se completo con éxito. Inconsistencia de firma: 2:1: [' . $dataPte['SIGNER_CODE'] . ']');
                }

                $_file_medico = base64_encode(file_get_contents($firmaMedico));

                $firmaMedico2 = "../../assets/risfirmas/" . $dataPte['READER_CODE_INTENDED'] . ".jpg";

                if (@file_get_contents($firmaMedico2, true) === false) {
                    throw new ModelsException('Proceso no se completo con éxito. Inconsistencia de firma: 2:2: [' . $dataPte['READER_CODE_INTENDED'] . ']');
                }

                $_file_medico2 = base64_encode(file_get_contents($firmaMedico2));

            } else {
                $firmaMedico = "../../assets/risfirmas/" . $dataPte['SIGNER_CODE'] . ".jpg";

                if (@file_get_contents($firmaMedico, true) === false) {
                    throw new ModelsException('Proceso no se completo con éxito. Inconsistencia de firma: 1:1: [' . $dataPte['SIGNER_CODE'] . ']');
                }

                $_file_medico = base64_encode(file_get_contents($firmaMedico));
            }

            # Parametros para enviar al destinatario
            # 2 Firmas
            if ($dataPte['SECTION_CODE'] == 'HM-ES' || $dataPte['SECTION_CODE'] == 'HM-ES1' || $dataPte['SECTION_CODE'] == 'HM-ES2' || $dataPte['SECTION_CODE'] == 'HM-ES2') {

                # $nameTemplate = 'INFORME_ENDOS_V1';

                #  throw new ModelsException('-1');

                if ($dataPte['SIGNER_CODE'] !== $dataPte['READER_CODE_INTENDED']) {
                    $nameTemplate = 'RIS_ENDO_MV_V3_2FIRMAS';
                } else {
                    $nameTemplate = 'RIS_ENDO_MV_V3';

                }

            } else {

                if ($dataPte['SIGNER_CODE'] !== $dataPte['READER_CODE_INTENDED']) {
                    $nameTemplate = 'RIS_MV_V3_2FIRMAS';
                } else {
                    $nameTemplate = 'RIS_MV_V3';
                }

            }

            # 2 Firmas
            if ($dataPte['SIGNER_CODE'] !== $dataPte['READER_CODE_INTENDED']) {
                $data = array(
                    'plantilla' => $nameTemplate,
                    'elementos' => array(
                        array(
                            'idVariablePlantilla' => 'NOMBRE_PTE',
                            'tipo' => 'text',
                            'dato' => $dataPte['PAT_FIRST_NAME'] . ' ' . $dataPte['PAT_LAST_NAME'],
                        ),
                        array(
                            'idVariablePlantilla' => 'HC',
                            'tipo' => 'text',
                            'dato' => $dataPte['PAT_PID_NUMBER'],
                        ),
                        array(
                            'idVariablePlantilla' => 'EDAD',
                            'tipo' => 'text',
                            'dato' => (string) $edad . ' Año(s)',
                        ),
                        array(
                            'idVariablePlantilla' => 'NRO_PRUEBA',
                            'tipo' => 'text',
                            'dato' => $dataPte['PROCEDURE_KEY'],
                        ),
                        array(
                            'idVariablePlantilla' => 'MEDICO',
                            'tipo' => 'text',
                            'dato' => ($dataPte['REF_SOURCE_NAME'] == null) ? 'N/D' : $dataPte['REF_SOURCE_NAME'],
                        ),
                        array(
                            'idVariablePlantilla' => 'ORIGEN',
                            'tipo' => 'text',
                            'dato' => 'Consulta Externa',
                        ),
                        array(
                            'idVariablePlantilla' => 'NRO_HAB',
                            'tipo' => 'text',
                            'dato' => 'N/D',
                        ),
                        array(
                            'idVariablePlantilla' => 'FECHA_ESTUDIO',
                            'tipo' => 'text',
                            'dato' => $dataPte['PROCEDURE_START'],
                        ),
                        array(
                            'idVariablePlantilla' => 'STATUS_ESTUDIO',
                            'tipo' => 'text',
                            'dato' => $dataPte['REPORT_STATUS'],
                        ),
                        array(
                            'idVariablePlantilla' => 'COD_MEDICO',
                            'tipo' => 'text',
                            'dato' => $dataPte['SIGNER_CODE'],
                        ),
                        array(
                            'idVariablePlantilla' => 'FIRMA_MEDICO',
                            'tipo' => 'image',
                            'dato' => "data:image/png;base64," . $_file_medico,
                        ),
                        array(
                            'idVariablePlantilla' => 'COD_MEDICO_2',
                            'tipo' => 'text',
                            'dato' => $dataPte['READER_CODE_INTENDED'],
                        ),
                        array(
                            'idVariablePlantilla' => 'FIRMA_MEDICO_2',
                            'tipo' => 'image',
                            'dato' => "data:image/png;base64," . $_file_medico2,
                        ),
                        array(
                            'idVariablePlantilla' => 'CUERPO',
                            'tipo' => 'rtf',
                            'dato' => base64_encode($rtfContent),
                        ),
                    ),
                );
            } else {
                $data = array(
                    'plantilla' => $nameTemplate,
                    'elementos' => array(
                        array(
                            'idVariablePlantilla' => 'NOMBRE_PTE',
                            'tipo' => 'text',
                            'dato' => $dataPte['PAT_FIRST_NAME'] . ' ' . $dataPte['PAT_LAST_NAME'],
                        ),
                        array(
                            'idVariablePlantilla' => 'HC',
                            'tipo' => 'text',
                            'dato' => $dataPte['PAT_PID_NUMBER'],
                        ),
                        array(
                            'idVariablePlantilla' => 'EDAD',
                            'tipo' => 'text',
                            'dato' => (string) $edad . ' Año(s)',
                        ),
                        array(
                            'idVariablePlantilla' => 'NRO_PRUEBA',
                            'tipo' => 'text',
                            'dato' => $dataPte['PROCEDURE_KEY'],
                        ),
                        array(
                            'idVariablePlantilla' => 'MEDICO',
                            'tipo' => 'text',
                            'dato' => ($dataPte['REF_SOURCE_NAME'] == null) ? 'N/D' : $dataPte['REF_SOURCE_NAME'],
                        ),
                        array(
                            'idVariablePlantilla' => 'ORIGEN',
                            'tipo' => 'text',
                            'dato' => 'Consulta Externa',
                        ),
                        array(
                            'idVariablePlantilla' => 'NRO_HAB',
                            'tipo' => 'text',
                            'dato' => 'N/D',
                        ),
                        array(
                            'idVariablePlantilla' => 'FECHA_ESTUDIO',
                            'tipo' => 'text',
                            'dato' => $dataPte['PROCEDURE_START'],
                        ),
                        array(
                            'idVariablePlantilla' => 'STATUS_ESTUDIO',
                            'tipo' => 'text',
                            'dato' => $dataPte['REPORT_STATUS'],
                        ),
                        array(
                            'idVariablePlantilla' => 'COD_MEDICO',
                            'tipo' => 'text',
                            'dato' => $dataPte['SIGNER_CODE'],
                        ),
                        array(
                            'idVariablePlantilla' => 'FIRMA_MEDICO',
                            'tipo' => 'image',
                            'dato' => "data:image/png;base64," . $_file_medico,
                        ),
                        array(
                            'idVariablePlantilla' => 'CUERPO',
                            'tipo' => 'rtf',
                            'dato' => base64_encode($rtfContent),
                        ),
                    ),
                );
            }

            $generate = $this->sendProcessPedidoImg2($data, $idEstudio);

            if ($generate) {

                $hashEstudio = Helper\Strings::ocrend_encode($idEstudio, 'hm');

                $urlPdf = 'https://api.hospitalmetropolitano.org/v2/pacientes/d/' . $hashEstudio . '.pdf';

                return array(
                    'status' => true,
                    'data' => $urlPdf,
                );

            } else {
                throw new ModelsException('Proceso de generacion de documento no se completo con éxito. ');
            }
            # Devolver Información

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
                'logs' => $this->_log
            );

        }

    }

    public function getInformeEstudio_MTR($idEstudio)
    {

        try {

            global $config, $http;

            # Verificar que no están vacíos
            if (Helper\Functions::e($idEstudio)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion.');
            }

            $hashEstudio = Helper\Strings::ocrend_encode($idEstudio, 'hm');

            $destination = "../../v2/pacientes/docs/img/" . $hashEstudio . ".pdf";
            $doc = file_exists($destination);

            if ($doc) {

                $urlPdf = 'https://api.hospitalmetropolitano.org/v2/pacientes/d/' . $hashEstudio . '.pdf';

                return array(
                    'status' => true,
                    'data' => $urlPdf,
                );
            }

            $sql = " SELECT * FROM medora.A_US_REPORT_TEXT WHERE REPORT_KEY = '$idEstudio' ";

            # SELECT * FROM A_US_REPORT_TEXT WHERE REPORT_KEY = '232095'

            # Conectar base de datos
            $this->conectar_Medora();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataEstudio = $stmt->fetchAll();

            if ($dataEstudio == false) {
                throw new ModelsException('No existe más resultados1.', 4080);
            }

            $rtfContent = '';

            foreach ($dataEstudio as $key) {
                $rtfContent .= trim($key['REPORT_TEXT']);
            }

            $sql = " SELECT * FROM medora.VW_RESULTADOS_WEB_PTES WHERE REPORT_KEY = '$idEstudio' ";

            # Conectar base de datos
            $this->conectar_Medora();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataPte = $stmt->fetch();

            # return $dataPte;

            if ($dataPte == false) {
                throw new ModelsException('No existe más resultados2.' . $idEstudio, 4080);
            }

            if ($dataPte['REPORT_STATUS'] !== 'f') {
                throw new ModelsException('Informe no disponible.', 4080);
            }

            # Medico Solictante MV
            /*
            $medicoSolicitante = $this->getMedicoSolicitanteMV($dataPte['REQUEST_NO']);
            if ($medicoSolicitante !== false) {
            $dataPte['REF_SOURCE_NAME'] = $medicoSolicitante;
            }
             */

            $edad = $this->calcular_edad($dataPte['PAT_BIRTH_DATE']);

            # 2 Firmas
            if ($dataPte['SIGNER_CODE'] !== $dataPte['READER_CODE_INTENDED']) {

                $firmaMedico = "../../assets/risfirmas/" . $dataPte['SIGNER_CODE'] . ".jpg";

                if (@file_get_contents($firmaMedico, true) === false) {
                    throw new ModelsException('Proceso no se completo con éxito. Inconsistencia de firma: 2:1: [' . $dataPte['SIGNER_CODE'] . ']');
                }

                $_file_medico = base64_encode(file_get_contents($firmaMedico));

                $firmaMedico2 = "../../assets/risfirmas/" . $dataPte['READER_CODE_INTENDED'] . ".jpg";

                if (@file_get_contents($firmaMedico2, true) === false) {
                    throw new ModelsException('Proceso no se completo con éxito. Inconsistencia de firma: 2:2: [' . $dataPte['READER_CODE_INTENDED'] . ']');
                }

                $_file_medico2 = base64_encode(file_get_contents($firmaMedico2));

            } else {
                $firmaMedico = "../../assets/risfirmas/" . $dataPte['SIGNER_CODE'] . ".jpg";

                if (@file_get_contents($firmaMedico, true) === false) {
                    throw new ModelsException('Proceso no se completo con éxito. Inconsistencia de firma: 1:1: [' . $dataPte['SIGNER_CODE'] . ']');
                }

                $_file_medico = base64_encode(file_get_contents($firmaMedico));
            }

            # Parametros para enviar al destinatario
            if ($dataPte['SIGNER_CODE'] !== $dataPte['READER_CODE_INTENDED']) {
                $nameTemplate = 'RIS_METRORED_V3_2FIRMAS';
            } else {
                $nameTemplate = 'RIS_METRORED_V3';
            }

            # 2 Firmas
            if ($dataPte['SIGNER_CODE'] !== $dataPte['READER_CODE_INTENDED']) {
                $data = array(
                    'plantilla' => $nameTemplate,
                    'elementos' => array(
                        array(
                            'idVariablePlantilla' => 'NOMBRE_PTE',
                            'tipo' => 'text',
                            'dato' => $dataPte['PAT_FIRST_NAME'] . ' ' . $dataPte['PAT_LAST_NAME'],
                        ),
                        array(
                            'idVariablePlantilla' => 'HC',
                            'tipo' => 'text',
                            'dato' => $dataPte['PAT_PID_NUMBER'],
                        ),
                        array(
                            'idVariablePlantilla' => 'EDAD',
                            'tipo' => 'text',
                            'dato' => (string) $edad . ' Año(s)',
                        ),
                        array(
                            'idVariablePlantilla' => 'NRO_PRUEBA',
                            'tipo' => 'text',
                            'dato' => $dataPte['PROCEDURE_KEY'],
                        ),
                        array(
                            'idVariablePlantilla' => 'MEDICO',
                            'tipo' => 'text',
                            'dato' => ($dataPte['REF_SOURCE_NAME'] == null) ? 'N/D' : $dataPte['REF_SOURCE_NAME'],
                        ),
                        array(
                            'idVariablePlantilla' => 'ORIGEN',
                            'tipo' => 'text',
                            'dato' => 'Consulta Externa',
                        ),
                        array(
                            'idVariablePlantilla' => 'NRO_HAB',
                            'tipo' => 'text',
                            'dato' => 'N/D',
                        ),
                        array(
                            'idVariablePlantilla' => 'FECHA_ESTUDIO',
                            'tipo' => 'text',
                            'dato' => $dataPte['PROCEDURE_START'],
                        ),
                        array(
                            'idVariablePlantilla' => 'STATUS_ESTUDIO',
                            'tipo' => 'text',
                            'dato' => $dataPte['REPORT_STATUS'],
                        ),
                        array(
                            'idVariablePlantilla' => 'COD_MEDICO',
                            'tipo' => 'text',
                            'dato' => $dataPte['SIGNER_CODE'],
                        ),
                        array(
                            'idVariablePlantilla' => 'FIRMA_MEDICO',
                            'tipo' => 'image',
                            'dato' => "data:image/png;base64," . $_file_medico,
                        ),
                        array(
                            'idVariablePlantilla' => 'COD_MEDICO_2',
                            'tipo' => 'text',
                            'dato' => $dataPte['READER_CODE_INTENDED'],
                        ),
                        array(
                            'idVariablePlantilla' => 'FIRMA_MEDICO_2',
                            'tipo' => 'image',
                            'dato' => "data:image/png;base64," . $_file_medico2,
                        ),
                        array(
                            'idVariablePlantilla' => 'CUERPO',
                            'tipo' => 'rtf',
                            'dato' => base64_encode($rtfContent),
                        ),
                    ),
                );
            } else {
                $data = array(
                    'plantilla' => $nameTemplate,
                    'elementos' => array(
                        array(
                            'idVariablePlantilla' => 'NOMBRE_PTE',
                            'tipo' => 'text',
                            'dato' => $dataPte['PAT_FIRST_NAME'] . ' ' . $dataPte['PAT_LAST_NAME'],
                        ),
                        array(
                            'idVariablePlantilla' => 'HC',
                            'tipo' => 'text',
                            'dato' => $dataPte['PAT_PID_NUMBER'],
                        ),
                        array(
                            'idVariablePlantilla' => 'EDAD',
                            'tipo' => 'text',
                            'dato' => (string) $edad . ' Año(s)',
                        ),
                        array(
                            'idVariablePlantilla' => 'NRO_PRUEBA',
                            'tipo' => 'text',
                            'dato' => $dataPte['PROCEDURE_KEY'],
                        ),
                        array(
                            'idVariablePlantilla' => 'MEDICO',
                            'tipo' => 'text',
                            'dato' => ($dataPte['REF_SOURCE_NAME'] == null) ? 'N/D' : $dataPte['REF_SOURCE_NAME'],
                        ),
                        array(
                            'idVariablePlantilla' => 'ORIGEN',
                            'tipo' => 'text',
                            'dato' => 'Consulta Externa',
                        ),
                        array(
                            'idVariablePlantilla' => 'NRO_HAB',
                            'tipo' => 'text',
                            'dato' => 'N/D',
                        ),
                        array(
                            'idVariablePlantilla' => 'FECHA_ESTUDIO',
                            'tipo' => 'text',
                            'dato' => $dataPte['PROCEDURE_START'],
                        ),
                        array(
                            'idVariablePlantilla' => 'STATUS_ESTUDIO',
                            'tipo' => 'text',
                            'dato' => $dataPte['REPORT_STATUS'],
                        ),
                        array(
                            'idVariablePlantilla' => 'COD_MEDICO',
                            'tipo' => 'text',
                            'dato' => $dataPte['SIGNER_CODE'],
                        ),
                        array(
                            'idVariablePlantilla' => 'FIRMA_MEDICO',
                            'tipo' => 'image',
                            'dato' => "data:image/png;base64," . $_file_medico,
                        ),
                        array(
                            'idVariablePlantilla' => 'CUERPO',
                            'tipo' => 'rtf',
                            'dato' => base64_encode($rtfContent),
                        ),
                    ),
                );
            }

            $generate = $this->sendProcessPedidoImg2($data, $idEstudio);

            if ($generate) {

                $hashEstudio = Helper\Strings::ocrend_encode($idEstudio, 'hm');

                $urlPdf = 'https://api.hospitalmetropolitano.org/v2/pacientes/d/' . $hashEstudio . '.pdf';

                return array(
                    'status' => true,
                    'data' => $urlPdf,
                );

            } else {
                throw new ModelsException('Proceso no se completo con éxito.');
            }
            # Devolver Información

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage()
            );

        }

    }

    public function getInformeEstudio3($idEstudio)
    {

        try {

            global $config, $http;

            # Verificar que no están vacíos
            if (Helper\Functions::e($idEstudio)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion.');
            }

            $sql = " SELECT * FROM medora.VW_RESULTADOS_WEB_PTES WHERE PROCEDURE_KEY = '$idEstudio' ";

            # Conectar base de datos
            $this->conectar_Medora();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataEstudio = $stmt->fetch();

            $idEstudio = $dataEstudio['REPORT_KEY'];

            $sql = " SELECT * FROM medora.A_US_REPORT_TEXT WHERE REPORT_KEY = '$idEstudio' ";

            # SELECT * FROM A_US_REPORT_TEXT WHERE REPORT_KEY = '232095'

            # Conectar base de datos
            $this->conectar_Medora();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataEstudio = $stmt->fetchAll();

            if ($dataEstudio == false) {
                throw new ModelsException('No existe más resultados1.', 4080);
            }

            $rtfContent = '';

            foreach ($dataEstudio as $key) {
                $rtfContent .= $key['REPORT_TEXT'];
            }

            $sql = " SELECT * FROM medora.VW_RESULTADOS_WEB_PTES WHERE REPORT_KEY = '$idEstudio' ";

            # Conectar base de datos
            $this->conectar_Medora();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataPte = $stmt->fetch();

            # return $dataPte;

            if ($dataPte == false) {
                throw new ModelsException('No existe más resultados2.' . $idEstudio, 4080);
            }

            if ($dataPte['REPORT_STATUS'] !== 'f') {
                throw new ModelsException('Informe no disponible.', 4080);
            }

            $edad = $this->calcular_edad($dataPte['PAT_BIRTH_DATE']);

            $firmaMedico = "../assets/risfirmas/" . $dataPte['SIGNER_CODE'] . ".jpg";

            if (@file_get_contents($firmaMedico, true) === false) {
                throw new ModelsException('Proceso no se completo con éxito. Inconsistencia de firma');
            }

            $_file_medico = base64_encode(file_get_contents($firmaMedico));

            # Parametros para enviar al destinatario

            if ($dataPte['SECTION_CODE'] == 'HM-ES' || $dataPte['SECTION_CODE'] == 'HM-ES1' || $dataPte['SECTION_CODE'] == 'HM-ES2' || $dataPte['SECTION_CODE'] == 'HM-ES2') {
                $nameTemplate = 'INFORME_ENDOS_V1';
                #  throw new ModelsException('-1');
            } else {
                $nameTemplate = 'INFORME_RIS_V7';
            }

            $data = array(
                'plantilla' => $nameTemplate,
                'elementos' => array(
                    array(
                        'idVariablePlantilla' => 'NOMBRE_PTE',
                        'tipo' => 'text',
                        'dato' => $dataPte['PAT_FIRST_NAME'] . ' ' . $dataPte['PAT_LAST_NAME'],
                    ),
                    array(
                        'idVariablePlantilla' => 'HC',
                        'tipo' => 'text',
                        'dato' => $dataPte['PAT_PID_NUMBER'],
                    ),
                    array(
                        'idVariablePlantilla' => 'EDAD',
                        'tipo' => 'text',
                        'dato' => (string) $edad,
                    ),
                    array(
                        'idVariablePlantilla' => 'NRO_PRUEBA',
                        'tipo' => 'text',
                        'dato' => $dataPte['PROCEDURE_KEY'],
                    ),
                    array(
                        'idVariablePlantilla' => 'MEDICO',
                        'tipo' => 'text',
                        'dato' => ($dataPte['REF_SOURCE_NAME'] == null) ? 'N/D' : $dataPte['REF_SOURCE_NAME'],
                    ),
                    array(
                        'idVariablePlantilla' => 'ORIGEN',
                        'tipo' => 'text',
                        'dato' => 'Consulta Externa',
                    ),
                    array(
                        'idVariablePlantilla' => 'NRO_HAB',
                        'tipo' => 'text',
                        'dato' => 'N/D',
                    ),
                    array(
                        'idVariablePlantilla' => 'FECHA_ESTUDIO',
                        'tipo' => 'text',
                        'dato' => $dataPte['PROCEDURE_START'],
                    ),
                    array(
                        'idVariablePlantilla' => 'STATUS_ESTUDIO',
                        'tipo' => 'text',
                        'dato' => $dataPte['REPORT_STATUS'],
                    ),
                    array(
                        'idVariablePlantilla' => 'COD_MEDICO',
                        'tipo' => 'text',
                        'dato' => $dataPte['SIGNER_CODE'],
                    ),
                    array(
                        'idVariablePlantilla' => 'FIRMA_MEDICO',
                        'tipo' => 'image',
                        'dato' => "data:image/png;base64," . $_file_medico,
                    ),
                    array(
                        'idVariablePlantilla' => 'CUERPO',
                        'tipo' => 'rtf',
                        'dato' => base64_encode($rtfContent),
                    ),
                ),
            );

            $generate = $this->sendProcessPedidoImg2($data, $idEstudio);

            # return $generate;

            if ($generate) {

                $hashEstudio = Helper\Strings::ocrend_encode($idEstudio, 'temp');

                return array(
                    'status' => true,
                    'codMedico' => $dataPte['REF_SOURCE_CODE'],
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

            $sql = "SELECT * FROM medora.VW_RESULTADOS_WEB_PTES WHERE REPORT_KEY = '$idEstudio' ";

            # SELECT * FROM A_US_REPORT_TEXT WHERE REPORT_KEY = '232095'

            # Conectar base de datos
            $this->conectar_Medora();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $dataPte = $stmt->fetch();

            # return $dataPte;

            if ($dataPte == false) {
                throw new ModelsException('No existe más resultados2.' . $idEstudio, 4080);
            }

            if ($dataPte['REPORT_STATUS'] !== 'f') {
                throw new ModelsException('Informe no disponible.', 4080);
            }

            $edad = $this->calcular_edad($dataPte['PAT_BIRTH_DATE']);

            $firmaMedico = "../assets/risfirmas/" . $dataPte['SIGNER_CODE'] . ".jpg";

            if (@file_get_contents($firmaMedico, true) === false) {
                throw new ModelsException('Proceso no se completo con éxito. Inconsistencia de firma');
            }

            $_file_medico = base64_encode(file_get_contents($firmaMedico));

            $data = array(
                'idEstudio' => $idEstudio,
                'fechaEstudio' => $dataPte['PROCEDURE_START'],
                'codMedico' => $dataPte['SIGNER_CODE'],
                'medico' => ($dataPte['REF_SOURCE_NAME'] == null) ? 'N/D' : $dataPte['REF_SOURCE_NAME'],
                'cuerpo' => $dataEstudio['BEFUND_ASCII_TEXT'],
                'nroPrueba' => $dataPte['PROCEDURE_KEY'],
                'nroHab' => 'N/D',
                // 'origen' => $dataPte['SECTION_CODE'],
                'origen' => 'Consulta Externa',
                'nombrePte' => $dataPte['PAT_FIRST_NAME'] . ' ' . $dataPte['PAT_LAST_NAME'],
                'statusStudio' => $dataPte['REPORT_STATUS'],
                'hc' => $dataPte['PAT_PID_NUMBER'],
                'edad' => (string) $edad,
                'firmaMedico' => $_file_medico,
            );

            if ($dataPte['SECTION_CODE'] == 'HM-ES' || $dataPte['SECTION_CODE'] == 'HM-ES1' || $dataPte['SECTION_CODE'] == 'HM-ES2' || $dataPte['SECTION_CODE'] == 'HM-ES2') {
                $generate = $this->sendProcessPedidoEndoscopia($data);
            } else {
                $generate = $this->sendProcessPedidoImg($data);
            }

            #  $generate = $this->sendProcessPedidoImg($data);

            if ($generate) {

                $hashEstudio = Helper\Strings::ocrend_encode($idEstudio, 'temp');

                return array(
                    'status' => true,
                    'codMedico' => $dataPte['REF_SOURCE_CODE'],
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

    public function sendProcessInforme(array $data)
    {

        $_datos = json_encode($data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.hospitalmetropolitano.org/v1/task/informe/' . $data['idEstudio']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $_datos);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
            )
        );

        $response = curl_exec($ch);

        curl_close($ch);

        $json = json_decode($response, true);

        return $json;

    }

    public function getTasksFirmarInformes_Fecha()
    {

        try {

            global $config;

            # SETEAR VARIABLES DE CLASE
            $this->setParameters();

            $sql = " SELECT * FROM GEMA.LOGS_NOTIFICACIONES_IMAGEN
            WHERE STATUS_REGISTRADO = '1'
            AND STATUS_GENERADO IS NULL
            AND STATUS_ENVIADO IS NULL
            AND FECHA_REGISTRADO >  '27/10/2021'  AND FECHA_REGISTRADO <  '15/11/2021'  ORDER BY ID_STUDIO ASC ";

            //  1700185067

            //  $sql = " SELECT * FROM GEMA.LOGS_NOTIFICACIONES_IMAGEN WHERE ID_STUDIO = '1030761' ";

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            if (false == $data) {
                throw new ModelsException('No existe resultados para procesar.');
            }

            $dataEstudio = array(
                'idEstudio' => $data['ID_STUDIO'],
            );

            #  return $dataEstudio;

            $sts = $this->getInformeEstudio3($dataEstudio['idEstudio']);

            # return $sts;

            if ($sts['status']) {

                $dataEstudio = array(
                    'ID_STUDIO' => $data['ID_STUDIO'],
                    'idEstudio' => $data['ID_STUDIO'],
                );

                # Actulizar estado firmado
                $this->actualizarRegistroLogs($dataEstudio);

                return $sts;

            } else {

                if ($sts['message'] == '-1') {

                    $dataEstudio = array(
                        'ID_STUDIO' => $data['ID_STUDIO'],
                        'idEstudio' => $data['ID_STUDIO'],
                    );

                    $this->actualizarRegistroEndoscopia($dataEstudio);

                    return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idEstudio']);

                } else {

                    $dataEstudio = array(
                        'ID_STUDIO' => $data['ID_STUDIO'],
                        'idEstudio' => $data['ID_STUDIO'],
                    );

                    $this->actualizarRegistroSinFirmaLogs($dataEstudio);

                    return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idEstudio']);

                }

            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getTasksFirmarInformes()
    {

        try {

            global $config;

            # SETEAR VARIABLES DE CLASE
            $this->setParameters();

            $sql = " SELECT * FROM GEMA.LOGS_NOTIFICACIONES_IMAGEN
            WHERE STATUS_REGISTRADO = '1'
            AND STATUS_GENERADO IS NULL
            AND STATUS_ENVIADO IS NULL
            AND FECHA_REGISTRADO >= '15/11/2021' ORDER BY ID_STUDIO ASC ";

            //  1700185067

            //  $sql = " SELECT * FROM GEMA.LOGS_NOTIFICACIONES_IMAGEN WHERE ID_STUDIO = '1031349' ";

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            if (false == $data) {
                throw new ModelsException('No existe resultados para procesar.');
            }

            $dataEstudio = array(
                'idEstudio' => $data['ID_STUDIO'],
            );

            #  return $dataEstudio;

            $sts = $this->getInformeEstudio3($dataEstudio['idEstudio']);

            # return $sts;

            if ($sts['status']) {

                $dataEstudio = array(
                    'ID_STUDIO' => $data['ID_STUDIO'],
                    'idEstudio' => $data['ID_STUDIO'],
                );

                # Actulizar estado firmado
                $this->actualizarRegistroLogs($dataEstudio);

                return $sts;

            } else {

                if ($sts['message'] == '-1') {

                    $dataEstudio = array(
                        'ID_STUDIO' => $data['ID_STUDIO'],
                        'idEstudio' => $data['ID_STUDIO'],
                    );

                    $this->actualizarRegistroEndoscopia($dataEstudio);

                    return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idEstudio']);

                } else {

                    $dataEstudio = array(
                        'ID_STUDIO' => $data['ID_STUDIO'],
                        'idEstudio' => $data['ID_STUDIO'],
                    );

                    $this->actualizarRegistroSinFirmaLogs($dataEstudio);

                    return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idEstudio']);

                }

            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    private function actualizarRegistroSinFirmaLogs($dataInforme)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            $queryBuilder
                ->update('GEMA.LOGS_NOTIFICACIONES_IMAGEN', 'u')
                ->set('u.STATUS_GENERADO', '?')
                ->set('u.FECHA_GENERADO', '?')
                ->where('u.ID_STUDIO=?')
                ->setParameter(0, (int) 2)
                ->setParameter(1, date('d/m/Y'))
                ->setParameter(2, (string) $dataInforme['ID_STUDIO'])
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

    private function actualizarRegistroEndoscopia($dataInforme)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            $queryBuilder
                ->update('GEMA.LOGS_NOTIFICACIONES_IMAGEN', 'u')
                ->set('u.STATUS_GENERADO', '?')
                ->set('u.FECHA_GENERADO', '?')
                ->set('u.STATUS_ENVIADO', '?')
                ->set('u.FECHA_ENVIADO', '?')
                ->where('u.ID_STUDIO=?')
                ->setParameter(0, (int) 1)
                ->setParameter(1, date('d/m/Y'))
                ->setParameter(2, (int) 1)
                ->setParameter(3, date('d/m/Y'))
                ->setParameter(4, (string) $dataInforme['ID_STUDIO'])
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

    private function actualizarRegistroLogs($dataInforme)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            $queryBuilder
                ->update('GEMA.LOGS_NOTIFICACIONES_IMAGEN', 'u')
                ->set('u.STATUS_GENERADO', '?')
                ->set('u.FECHA_GENERADO', '?')
                ->where('u.ID_STUDIO=?')
                ->setParameter(0, (int) 1)
                ->setParameter(1, date('d/m/Y'))
                ->setParameter(2, (string) $dataInforme['ID_STUDIO'])
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

    public function getTaskInformeEstudio($idEstudio)
    {

        try {

            global $config, $http;

            # Verificar que no están vacíos
            if (Helper\Functions::e($idEstudio)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion.');
            }

            $sql = "SELECT *
            FROM medora.BEFUND_ASCII_TEXT_CLOB BEFUND_ASCII_TEXT_CLOB
            WHERE BEFUND_ASCII_TEXT_CLOB.BEFUND_UBEID='$idEstudio' ";

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
            a_us_performed_procedures.REF_SOURCE_CONTACT_NAME,
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

                if ($pageNo == 1) {

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

        $data = json_encode(
            array(
                'idEstudio' => $idEstudio,
                'correoElectronico' => $correoElectronico,
                'fechaCaducidad' => $fechaCaducidad,
                'mensaje' => $mensaje,
            )
        );

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.imagen.hospitalmetropolitano.org/v1/share");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Accept: application/json',
                'Content-Type: application/json',
            )
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
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
            )
        );

        $response = curl_exec($ch);

        // Se cierra el recurso CURL y se liberan los recursos del sistema
        file_put_contents('../v1/downloads/' . $data['idEstudio'] . '.pdf', $response);

        curl_close($ch);

        return true;

    }

    public function sendProcessPedidoImg2(array $data, string $idEstudio)
    {

        $_datos = json_encode($data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'http://20.36.155.214/report2pdf');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $_datos);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Accept: application/json',
                'Content-Type: application/json',
            )
        );

        $response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 200) {

            $data['idEstudio'] = $idEstudio;

            $hashEstudio = Helper\Strings::ocrend_encode($idEstudio, 'hm');

            // Se cierra el recurso CURL y se liberan los recursos del sistema
            file_put_contents('../../v2/pacientes/docs/img/' . $hashEstudio . '.pdf', $response);

            return true;

        } else {

            $this->_log = $response;

            return false;
        }

        # return $response;

        curl_close($ch);

    }

    public function sendProcessPedidoEndoscopia(array $data)
    {

        $_datos = json_encode($data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://prod-93.westus.logic.azure.com:443/workflows/d3a8a11edb784f07a9629b41d8d45b36/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=lY5fyAVM2P516igj595p8dVLAfSqk36C3zdQxvXCkUY');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $_datos);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
            )
        );

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
