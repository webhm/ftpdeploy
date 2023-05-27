<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models\nss\imagen;

use DateTime;
use Doctrine\DBAL\DriverManager;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Extraer
 */

class Extraer extends Models implements IModels
{
    # Variables de clase
    private $urlApiImagen = 'https://pacientes.hospitalmetropolitano.org/';
    private $urlApiViewer = 'https://api.imagen.hospitalmetropolitano.org/';
    private $urlViewer = 'https://imagen.hmetro.med.ec/zfp?Lights=on&mode=proxy#view&ris_exam_id=';
    private $keyImagen = '&un=WEBAPI&pw=lEcfvZxzlXTsfimMMonmVZZ15IqsgEcdV%2forI8EUrLY%3d';

    private function conectar_Medora()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
        //..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracleMedora'], $_config);

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

    public function getExtraerInformes_Hosp()
    {

        try {

            global $config, $http;

            $time = new DateTime();
            $menosDosDias = date('d/m/Y H:i', strtotime('-2 days', $time->getTimestamp()));
            $menosUnDia = date('d/m/Y H:i', strtotime('-1 days', $time->getTimestamp()));

            $sql = " SELECT * FROM VW_RESULTADOS_WEB_PTES WHERE REPORT_VERIFICATION_DATE >= TO_DATE('" . $menosDosDias . "', 'DD/MM/YYYY HH24:MI')
            AND REPORT_VERIFICATION_DATE <= TO_DATE('" . $menosUnDia . "','DD/MM/YYYY HH24:MI')
            AND REPORT_STATUS = 'f' AND ADMISSION_TYPE IN ('S','P') ";

            # Conectar base de datos
            $this->conectar_Medora();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # Datos de usuario cuenta activa
            $resultados = array();

            foreach ($data as $key) {

                $key['PAT_FIRST_NAME'] = strtoupper($key['PAT_FIRST_NAME']);
                $key['PAT_LAST_NAME'] = strtoupper($key['PAT_LAST_NAME']);

                $k['numeroHistoriaClinica'] = $key['PAT_PID_NUMBER'];
                $k['fechaEstudio'] = $key['PROCEDURE_START'];
                $k['descEstudio'] = $key['PROCEDURE_NAME'];
                $k['numeroEstudio'] = $key['PROCEDURE_KEY'];
                $k['numeroReporte'] = $key['REPORT_KEY'];
                $k['paciente'] = $key['PAT_FIRST_NAME'] . " " . $key['PAT_LAST_NAME'];
                $k['codigoMedico'] = $key['SIGNER_CODE'];
                $k['servicio'] = $key['SECTION_CODE'];
                $k['tipoAtencion'] = $key['ADMISSION_TYPE'];
                $k['fechaProceso'] = time();
                $k['statusProceso'] = 0;
                $k['filtrosProceso'] = array();
                $k['correosElectronicos'] = array();
                $k['logsEnvio'] = array();

                $this->crearRegistro($k);
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

    public function getExtraerInformes_MTR()
    {

        try {

            global $config, $http;

            $time = new DateTime();
            $nuevaHora = strtotime('-1 day', $time->getTimestamp());
            $menosHora = date('d/m/Y H:i', $nuevaHora);

            $sql = " SELECT *
            FROM VW_RESULTADOS_WEB_PTES
            WHERE REPORT_VERIFICATION_DATE >= TO_DATE('" . $menosHora . "', 'DD/MM/YYYY HH24:MI')
            AND REPORT_VERIFICATION_DATE <= TO_DATE('" . date('d/m/Y H:i') . "','DD/MM/YYYY HH24:MI')
            AND REPORT_STATUS = 'f' AND (SECTION_CODE LIKE 'MR-%' OR SECTION_CODE LIKE 'MT-%')";

            # Conectar base de datos
            $this->conectar_Medora();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # Datos de usuario cuenta activa
            $resultados = array();

            foreach ($data as $key) {

                $key['PAT_FIRST_NAME'] = strtoupper($key['PAT_FIRST_NAME']);
                $key['PAT_LAST_NAME'] = strtoupper($key['PAT_LAST_NAME']);

                $k['numeroHistoriaClinica'] = $key['PAT_PID_NUMBER'];
                $k['fechaEstudio'] = $key['PROCEDURE_START'];
                $k['descEstudio'] = $key['PROCEDURE_NAME'];
                $k['numeroEstudio'] = $key['PROCEDURE_KEY'];
                $k['numeroReporte'] = $key['REPORT_KEY'];
                $k['paciente'] = $key['PAT_FIRST_NAME'] . " " . $key['PAT_LAST_NAME'];
                $k['codigoMedico'] = $key['SIGNER_CODE'];
                $k['servicio'] = $key['SECTION_CODE'];
                $k['tipoAtencion'] = $key['ADMISSION_TYPE'];
                $k['fechaProceso'] = time();
                $k['statusProceso'] = 0;
                $k['filtrosProceso'] = array();
                $k['correosElectronicos'] = array();
                $k['logsEnvio'] = array();

                $this->crearRegistro_MTR($k);
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

    public function getExtraerInformes()
    {

        try {

            global $config, $http;

            $time = new DateTime();
            $nuevaHora = strtotime('-35 minutes', $time->getTimestamp());
            $menosHora = date('d/m/Y H:i', $nuevaHora);

            $sql = " SELECT *
            FROM VW_RESULTADOS_WEB_PTES
            WHERE REPORT_VERIFICATION_DATE >= TO_DATE('" . $menosHora . "', 'DD/MM/YYYY HH24:MI')
            AND REPORT_VERIFICATION_DATE <= TO_DATE('" . date('d/m/Y H:i') . "','DD/MM/YYYY HH24:MI')
            AND REPORT_STATUS = 'f' AND ADMISSION_TYPE NOT IN ('S','P') ";

            # Conectar base de datos
            $this->conectar_Medora();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # Datos de usuario cuenta activa
            $resultados = array();

            foreach ($data as $key) {

                $key['PAT_FIRST_NAME'] = strtoupper($key['PAT_FIRST_NAME']);
                $key['PAT_LAST_NAME'] = strtoupper($key['PAT_LAST_NAME']);

                $k['numeroHistoriaClinica'] = $key['PAT_PID_NUMBER'];
                $k['fechaEstudio'] = $key['PROCEDURE_START'];
                $k['descEstudio'] = $key['PROCEDURE_NAME'];
                $k['numeroEstudio'] = $key['PROCEDURE_KEY'];
                $k['numeroReporte'] = $key['REPORT_KEY'];
                $k['paciente'] = $key['PAT_FIRST_NAME'] . " " . $key['PAT_LAST_NAME'];
                $k['codigoMedico'] = $key['SIGNER_CODE'];
                $k['servicio'] = $key['SECTION_CODE'];
                $k['tipoAtencion'] = $key['ADMISSION_TYPE'];
                $k['fechaProceso'] = time();
                $k['statusProceso'] = 0;
                $k['filtrosProceso'] = array();
                $k['correosElectronicos'] = array();
                $k['logsEnvio'] = array();

                $this->crearRegistro($k);
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

    public function crearRegistro($documento)
    {

        $stsLogPen = "imagen/ingresados/hmetro/st_" . $documento['numeroEstudio'] . "_" . $documento['fechaEstudio'] . "_pendiente_.json";

        if (@file_get_contents($stsLogPen, true) === false) {

            file_put_contents('imagen/ingresados/hmetro/st_' . $documento['numeroEstudio'] . '_' . $documento['fechaEstudio'] . '_pendiente_.json', json_encode($documento), LOCK_EX);

        }

    }

    public function crearRegistro_MTR($documento)
    {

        $stsLogPen = "imagen/logs/metrored/st_" . $documento['numeroEstudio'] . "_" . $documento['fechaEstudio'] . "_pendiente_.json";

        if (@file_get_contents($stsLogPen, true) === false) {

            file_put_contents('imagen/logs/metrored/st_' . $documento['numeroEstudio'] . '_' . $documento['fechaEstudio'] . '_pendiente_.json', json_encode($documento), LOCK_EX);
            file_put_contents('imagen/ingresados/metrored/st_' . $documento['numeroEstudio'] . '_' . $documento['fechaEstudio'] . '_pendiente_.json', json_encode($documento), LOCK_EX);

        }

    }

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
    }
}
