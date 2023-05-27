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
use Endroid\QrCode\QrCode;
use Exception;
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;
use setasign\Fpdi\Fpdi;
use SoapClient;

/**
 * Modelo Laboratorio
 */
class Laboratorio extends Models implements IModels
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

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

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

        $fecha = date('d-m-Y');
        $nuevafecha = strtotime('-1 year', strtotime($fecha));

        # SETEAR FILTRO HASTA TRES MESES
        $this->tresMeses = date('d-m-Y', $nuevafecha);

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

    public function getResultadosLab(): array
    {

        try {

            # SETEAR VARIABLES DE CLASE
            $this->setParameters();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # EXTRAER VALOR DEL TOKEN PARA CONSULTA
            $this->getAuthorization();

            $codes = implode(',', $this->USER->CP_PTE);

            # NO EXITEN RESULTADOS
            $this->notResults($this->USER->CP_PTE);

            # CONULTA BDD GEMA
            if ($this->startDate != null and $this->endDate != null) {

                $sql = "SELECT WEB2_RESULTADOS_LAB.*, ROWNUM AS ROWNUM_ FROM WEB2_RESULTADOS_LAB WHERE COD_PERSONA IN ($codes) AND FECHA >= TO_DATE('$this->startDate', 'dd-mm-yyyy') AND FECHA <= TO_DATE('$this->endDate', 'dd-mm-yyyy') AND TOT_SC!=TOD_DC AND FECHA >= TO_DATE('$this->tresMeses', 'dd-mm-yyyy') ORDER BY ROWNUM_  $this->sortType ";

            } elseif ($this->sortField == 'FECHA' and $this->searchField == '') {

                $sql = "SELECT WEB2_RESULTADOS_LAB.*, ROWNUM AS ROWNUM_ FROM WEB2_RESULTADOS_LAB WHERE COD_PERSONA IN ($codes) AND TOT_SC!=TOD_DC AND FECHA >= TO_DATE('$this->tresMeses', 'dd-mm-yyyy') ORDER BY $this->sortField $this->sortType ";

            } elseif ($this->searchField != null and $this->searchField != '') {

                $sql = "SELECT WEB2_RESULTADOS_LAB.*, ROWNUM AS ROWNUM_ FROM WEB2_RESULTADOS_LAB WHERE COD_PERSONA IN ($codes) AND (ORIGEN LIKE '%$this->searchField%' OR ADMISION LIKE '%$this->searchField%') AND TOT_SC!=TOD_DC AND FECHA >= TO_DATE('$this->tresMeses', 'dd-mm-yyyy') ORDER BY ROWNUM_ $this->sortType ";

            } else {

                $sql = "SELECT WEB2_RESULTADOS_LAB.*, ROWNUM AS ROWNUM_ FROM WEB2_RESULTADOS_LAB WHERE COD_PERSONA IN ($codes) AND TOT_SC!=TOD_DC AND FECHA >= TO_DATE('02-04-2019', 'dd-mm-yyyy') ORDER BY $this->sortField $this->sortType ";
            }

            $sql = "SELECT WEB2_RESULTADOS_LAB.*, ROWNUM AS ROWNUM_ FROM WEB2_RESULTADOS_LAB WHERE COD_PERSONA IN ($codes) AND TOT_SC!=TOD_DC AND FECHA >= TO_DATE('02-04-2019', 'dd-mm-yyyy') ORDER BY $this->sortField $this->sortType ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Conectar base de datos
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

                $resultados[] = $key;
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
                # 'dataddd' => $http->request->all(),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status' => true,
                    'data' => [],
                    'message' => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    # Metodo LOGIN webservice laboratorio ROCHE
    public function wsLab_LOGIN_PCR()
    {

        try {

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'zdk.ws.wSessions.wsdl.xml');

            $Login = $client->Login(array(
                "pstrUserName" => "CWMETRO",
                "pstrPassword" => "CWM3TR0",
            ));

            # Guaradar  KEY de session WS
            $this->pstrSessionKey = $Login->LoginResult;

            # Retorna KEY de session WS
            # return $Login->LoginResult;

        } catch (SoapFault $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }

    }

    # Metodo LOGIN webservice laboratorio ROCHE
    public function wsLab_LOGIN()
    {

        try {

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'zdk.ws.wSessions.wsdl.xml');

            $Login = $client->Login(array(
                "pstrUserName" => "CONSULTA",
                "pstrPassword" => "CONSULTA1",
            ));

            # Guaradar  KEY de session WS
            $this->pstrSessionKey = $Login->LoginResult;

            # Retorna KEY de session WS
            return $Login->LoginResult;

        } catch (SoapFault $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }

    }

    # Metodo LOGOUT webservice laboratorio ROCHE
    public function wsLab_LOGOUT()
    {

        try {

            # INICIAR SESSION
            # $this->wsLab_LOGIN();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'zdk.ws.wSessions.wsdl.xml');

            $Logout = $client->Logout(array(
                "pstrSessionKey" => $this->pstrSessionKey,
            ));

            return $Logout->LogoutResult;

        } catch (SoapFault $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }

    }

    # Metodo wReports webservice laboratorio ROCHEdevuelve el resultado pdf del paciente
    public function getInformeResultadoPCR(string $SC, string $FECHA)
    {

        try {

            # INICIAR SESSION

            $FECHA_final = explode('-', $FECHA);

            $FECHA = $FECHA_final[2] . '-' . $FECHA_final[1] . '-' . $FECHA_final[0];

            $this->wsLab_LOGIN_PCR();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wResults.xml', array('soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ));

            $Preview = $client->GetResults(array(
                'pstrSessionKey' => $this->pstrSessionKey,
                'pstrSampleID' => $SC, # '0015052333',
                'pstrRegisterDate' => $FECHA,
            ));

            $this->wsLab_LOGOUT();

            # return array('status' => true, 'data' => '&');

            #  return $Preview;

            if (!isset($Preview->GetResultsResult)) {
                throw new ModelsException('Error 2 => Resultado no disponible.');
            }

            # REVISAR SI EXISTEN PRUEBAS NO DISPONIBLES
            $listaPruebas = $Preview->GetResultsResult->Orders->LISOrder->LabTests->LISLabTest;

            if (!isset($Preview->GetResultsResult->Orders->LISOrder->MotiveDesc)) {
                $MotiveDesc = 'LABORATORIO';
            } else {
                $MotiveDesc = $Preview->GetResultsResult->Orders->LISOrder->MotiveDesc;
            }

            $i = 0;

            $lista = array();

            if (is_array($listaPruebas)) {
                foreach ($listaPruebas as $key) {
                    $lista[] = array(
                        'TestID' => $key->TestID,
                        'TestStatus' => $key->TestStatus,
                        'TestName' => $key->TestName,
                        'MotiveDesc' => $MotiveDesc,
                    );
                }
            } else {
                $lista[] = array(
                    'TestID' => $listaPruebas->TestID,
                    'TestStatus' => $listaPruebas->TestStatus,
                    'TestName' => $listaPruebas->TestName,
                    'MotiveDesc' => $MotiveDesc,
                );
            }

            # return $lista;
            $esPcr = false;

            foreach ($lista as $k) {

                // 9720
                if ($k['TestID'] == '9720') {
                    $esPcr = true;
                }

            }

            if ($esPcr) {

                $copyPCR = $this->getCopyPCR($SC, $FECHA);

                return array('status' => true, 'data' => $copyPCR['urlDoc']);

            } else {
                throw new ModelsException('Error 3 => Resultado no disponible.PruEba no es PCR.', 3);
            }

        } catch (\Exception $e) {

            $this->wsLab_LOGOUT();

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => 2);

        } catch (ModelsException $b) {

            return array('status' => false, 'message' => $b->getMessage(), 'errorCode' => $b->getCode());

        }
    }

    # Metodo wReports webservice laboratorio ROCHEdevuelve el resultado pdf del paciente
    public function wsLab_GET_REPORT_PDF_PCR(string $SC, string $FECHA)
    {

        try {

            # INICIAR SESSION
            $this->wsLab_LOGIN_PCR();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wReports.wsdl.xml');

            # $FECHA_final = explode('-', $FECHA);

            $Preview = $client->Preview(array(
                "pstrSessionKey" => $this->pstrSessionKey,
                "pstrSampleID" => $SC, # '0015052333',
                "pstrRegisterDate" => $FECHA, # $FECHA_final[2] . '-' . $FECHA_final[1] . '-' . $FECHA_final[0], # '2018-11-05',
                "pstrFormatDescription" => 'SARS',
                "pstrPrintTarget" => 'Destino por defecto',
            ));

            # CERRAR SESSION POR LICENCIAS HSF
            $this->wsLab_LOGOUT();

            # No existe documento

            if (!isset($Preview->PreviewResult)) {
                throw new ModelsException('Error 0 => No existe el documento solicitado.');
            }

            # No existe documento

            if (isset($Preview->PreviewResult) or $Preview->PreviewResult == '0') {

                if ($Preview->PreviewResult == '0') {

                    throw new ModelsException('Error 1 => No existe el documento solicitado.');

                } else {

                    return array(
                        'status' => true,
                        'data' => $Preview->PreviewResult,
                    );

                }

            }

            #
            throw new ModelsException('Error 2 => No existe el documento solicitado.');

        } catch (SoapFault $e) {

            if ($e->getCode() == 0) {
                return array('status' => false, 'message' => $e->getMessage());
            } else {
                return array('status' => false, 'message' => $e->getMessage());

            }

        } catch (ModelsException $b) {

            if ($b->getCode() == 0) {
                return array('status' => false, 'message' => $b->getMessage());
            } else {
                return array('status' => false, 'message' => $b->getMessage());

            }
        }

    }

    # Metodo wReports webservice laboratorio ROCHEdevuelve el resultado pdf del paciente
    public function getCopyPCR(string $SC, string $FECHA)
    {

        try {

            $doc_resultado = $this->wsLab_GET_REPORT_PDF_PCR($SC, $FECHA);

            // No existe documeneto
            if (!$doc_resultado['status']) {
                throw new ModelsException($doc_resultado['message']);
            }

            $id_resultado = Helper\Strings::ocrend_encode($SC, 'SC');

            $url = $doc_resultado['data'];
            $destination = "../lis/v1/downloads/resultados/PCR/" . $SC . ".pdf";
            $fp = fopen($destination, 'w+');
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_exec($ch);
            curl_close($ch);

            fclose($fp);

            // Generate codigo QR

            # return array('status' => true, 'urlDoc' => '&&');

            $destination = "../lis/v1/downloads/resultados/PCR/" . $SC . ".pdf";

            $urlPdf = 'https://api.hospitalmetropolitano.org/lis/v1/qrs/resultados/' . $id_resultado . '.pdf';

            # Generate QR CODE
            $qrCode = new QrCode($urlPdf);
            $qrCode->setLogoPath('../lis/v1/downloads/resultados/PCR/QRS/hm.png');

            // Save it to a file
            $qrCode->writeFile('../lis/v1/downloads/resultados/PCR/QRS/' . $id_resultado . '.png');

            $qrImage = '../lis/v1/downloads/resultados/PCR/QRS/' . $id_resultado . '.png';

            $qrAcess = '../lis/v1/downloads/resultados/PCR/QRS/acess.png';

            // Editar template PDF QR

            $pdf = new Fpdi();

            $staticIds = array();
            $pageCount = $pdf->setSourceFile($destination);
            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $staticIds[$pageNumber] = $pdf->importPage($pageNumber);
            }

            // get the page count of the uploaded file
            $pageCount = $pdf->setSourceFile($destination);

            // let's track the page number for the filler page
            $fillerPageCount = 1;
            // import the uploaded document page by page
            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {

                if ($fillerPageCount == 1) {
                    // add the current filler page
                    $pdf->AddPage();
                    $pdf->useTemplate($staticIds[$fillerPageCount]);
                    $pdf->Image($qrImage, 5, 237, 40, 40);
                    // QR ACESS
                    $pdf->Image($qrAcess, 46, 237.1, 40, 39);
                }

                // update the filler page number or reset it
                $fillerPageCount++;
                if ($fillerPageCount > count($staticIds)) {
                    $fillerPageCount = 1;
                }
            }

            $newDestination = "../lis/v1/downloads/resultados/PCR/" . $id_resultado . ".qr.pdf";

            $pdf->Output('F', $newDestination);

            return array(
                'status' => true,
                'urlDoc' => $urlPdf,
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
            );
        }

    }

    # Metodo wReports webservice laboratorio ROCHEdevuelve el resultado pdf del paciente
    public function wsLab_GET_REPORT_PDF(string $SC, string $FECHA)
    {

        try {

            # INICIAR SESSION
            $this->wsLab_LOGIN();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wReports.wsdl.xml');

            $FECHA_final = explode('-', $FECHA);

            $Preview = $client->Preview(array(
                "pstrSessionKey" => $this->pstrSessionKey,
                "pstrSampleID" => $SC, # '0015052333',
                "pstrRegisterDate" => $FECHA_final[2] . '-' . $FECHA_final[1] . '-' . $FECHA_final[0], # '2018-11-05',
                "pstrFormatDescription" => 'METROPOLITANO',
                "pstrPrintTarget" => 'Destino por defecto',
            ));

            # CERRAR SESSION POR LICENCIAS HSF
            $this->wsLab_LOGOUT();

            # No existe documento

            if (!isset($Preview->PreviewResult)) {
                throw new ModelsException('No existe el documento solicitado.', 4080);
            }

            # No existe documento

            if (isset($Preview->PreviewResult) or $Preview->PreviewResult == '0') {

                if ($Preview->PreviewResult == '0') {

                    throw new ModelsException('No existe el documento solicitado.', 4080);

                } else {

                    return array(
                        'status' => true,
                        'data' => array('_PDF' => str_replace("172.16.2.221:9390", "resultadosweb.hospitalmetropolitano.org:9390", $Preview->PreviewResult),
                        ),
                    );

                }

            }

            #
            throw new ModelsException('No existe el documento solicitado.', 4080);

        } catch (SoapFault $e) {

            if ($e->getCode() == 0) {
                return array('success' => false, 'message' => 'No existe el documento solicitado.', 'errorCode' => 4080);
            } else {
                return array('success' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

            }

        } catch (ModelsException $b) {

            if ($b->getCode() == 0) {
                return array('success' => false, 'message' => 'No existe el documento solicitado.', 'errorCode' => 4080);
            } else {
                return array('success' => false, 'message' => $b->getMessage(), 'errorCode' => $b->getCode());

            }
        }

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

        return $arr; // 1000302446
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

/**
 * __construct()
 */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
    }
}
