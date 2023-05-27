<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models\nss;

use app\models\nss as Model;
use Exception;
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Models\Traits\DBModel;
use Ocrend\Kernel\Router\IRouter;
use SoapClient;

/**
 * Modelo Filtrar
 */
class Filtrar extends Models implements IModels
{

    use DBModel;

    # Variables de clase
    private $reglasEnvio = array();
    private $reglasNoEnvio = array();
    private $ordenes = array();
    private $documento = array();

    public function filtrarOrdenes(): array
    {

        try {

            global $config, $http;

            $this->getReglas();

            if (count($this->reglasEnvio) == 0 && count($this->reglasNoEnvio) == 0) {
                throw new ModelsException('No existe reglas /filtros disponibles para procesar.');
            }

            $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/ingresadas/');

            $i = 0;

            // Extraer ORDENES PARA FILTRAR
            foreach ($list as $key => $val) {

                $content = file_get_contents($val);
                $documento = json_decode($content, true);
                $documento['file'] = $val;

                if ($documento['fechaExamen'] !== date('Y-m-d')) {

                    if ($i <= 2 && $documento['statusFiltro'] == 0) {

                        sleep(1.5);
                        $documento['dataClinica'] = $this->extraerDataClinica($documento);

                        if (count($documento['dataClinica']) == 0) {
                            $documento['dataClinica'] = $this->extraerDataPCR($documento);
                            if (count($documento['dataClinica']) !== 0) {
                                $documento['_PCR'] = 1;
                            }
                        }

                        if (count($documento['dataClinica']) !== 0) {
                            $documento['tipoValidacion'] = 0;
                        }

                        sleep(1.5);
                        $documento['dataMicro'] = $this->extraerDataMicro($documento);

                        if (count($documento['dataClinica']) !== 0 && count($documento['dataMicro']) !== 0) {
                            $documento['tipoValidacion'] = 1;
                        }

                        if (count($documento['dataClinica']) == 0 && count($documento['dataMicro']) == 0) {
                            // 1: Error en extraer Datos se envia a ingresados para reprocesar despues de reproceso
                            $documento['statusFiltro'] = 1;
                        }

                        $this->ordenes[] = $documento;
                        $i++;

                    }

                }

            }

            // Filtrar Ordenes de Envio
            $this->filtrarReglas();

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->ordenes,
                'reglasEnvio' => $this->reglasEnvio,
                'reglasNoEnvio' => $this->reglasNoEnvio,
                'reFiltrado' => $this->verificarStatusFiltrar(),
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function filtrarOrdenesDia(): array
    {

        try {

            global $config, $http;

            $this->getReglas();

            if (count($this->reglasEnvio) == 0 && count($this->reglasNoEnvio) == 0) {
                throw new ModelsException('No existe reglas /filtros disponibles para procesar.');
            }

            $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/ingresadas/');

            $i = 0;

            // Extraer ORDENES PARA FILTRAR
            foreach ($list as $key => $val) {

                $content = file_get_contents($val);
                $documento = json_decode($content, true);
                $documento['file'] = $val;

                if ($documento['fechaExamen'] == date('Y-m-d')) {

                    if ($i <= 2 && $documento['statusFiltro'] == 0) {

                        sleep(1.5);
                        $documento['dataClinica'] = $this->extraerDataClinica($documento);

                        if (count($documento['dataClinica']) == 0) {
                            $documento['dataClinica'] = $this->extraerDataPCR($documento);
                            if (count($documento['dataClinica']) !== 0) {
                                $documento['_PCR'] = 1;
                            }
                        }

                        if (count($documento['dataClinica']) !== 0) {
                            $documento['tipoValidacion'] = 0;
                        }

                        sleep(1.5);
                        $documento['dataMicro'] = $this->extraerDataMicro($documento);

                        if (count($documento['dataClinica']) !== 0 && count($documento['dataMicro']) !== 0) {
                            $documento['tipoValidacion'] = 1;
                        }

                        if (count($documento['dataClinica']) == 0 && count($documento['dataMicro']) == 0) {
                            // 1: Error en extraer Datos se envia a ingresados para reprocesar despues de reproceso
                            $documento['statusFiltro'] = 1;
                        }

                        $this->ordenes[] = $documento;
                        $i++;

                    }

                }

            }

            // Filtrar Ordenes de Envio
            $this->filtrarReglas();

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->ordenes,
                'reglasEnvio' => $this->reglasEnvio,
                'reglasNoEnvio' => $this->reglasNoEnvio,
                'reFiltrado' => $this->verificarStatusFiltrarDia(),
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function verificarStatusFiltrar()
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/ingresadas/');

            $i = 0;

            $cicloCorrido = true;
            $ordenes = array();

            // Extraer ORDENES PARA VALIDAR
            foreach ($list as $key => $val) {

                $content = file_get_contents($val);
                $documento = json_decode($content, true);
                $documento['file'] = $val;

                if ($documento['fechaExamen'] !== date('Y-m-d')) {

                    if ($documento['statusFiltro'] == 0) {
                        $cicloCorrido = false;
                    }

                    $ordenes[] = $documento;

                }

            }

            if ($cicloCorrido) {

                foreach ($ordenes as $k => $v) {

                    $content = file_get_contents($v['file']);
                    $documento = json_decode($content, true);
                    @unlink($documento['file']);
                    $documento['reglasFiltrosEnvio'] = array();
                    $documento['reglasFiltrosNoEnvio'] = array();
                    $documento['correosElectronicos'] = array();
                    $documento['statusFiltro'] = 0;
                    $file = 'ordenes/ingresadas/sc_' . $documento['sc'] . '_' . $documento['fechaExamen'] . '.json';
                    $json_string = json_encode($documento);
                    file_put_contents($file, $json_string);

                }

            }

            return $ordenes;

        } catch (ModelsException $e) {

            throw new ModelsException('No se puede continuar error en Generar Reporoceso de Validación.');

        }

    }

    public function verificarStatusFiltrarDia()
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/ingresadas/');

            $i = 0;

            $cicloCorrido = true;
            $ordenes = array();

            // Extraer ORDENES PARA VALIDAR
            foreach ($list as $key => $val) {

                $content = file_get_contents($val);
                $documento = json_decode($content, true);
                $documento['file'] = $val;

                if ($documento['fechaExamen'] == date('Y-m-d')) {

                    if ($documento['statusFiltro'] == 0) {
                        $cicloCorrido = false;
                    }

                    $ordenes[] = $documento;

                }

            }

            if ($cicloCorrido) {

                foreach ($ordenes as $k => $v) {

                    $content = file_get_contents($v['file']);
                    $documento = json_decode($content, true);
                    @unlink($documento['file']);
                    $documento['reglasFiltrosEnvio'] = array();
                    $documento['reglasFiltrosNoEnvio'] = array();
                    $documento['correosElectronicos'] = array();
                    $documento['statusFiltro'] = 0;
                    $file = 'ordenes/ingresadas/sc_' . $documento['sc'] . '_' . $documento['fechaExamen'] . '.json';
                    $json_string = json_encode($documento);
                    file_put_contents($file, $json_string);

                }

            }

            return $ordenes;

        } catch (ModelsException $e) {

            throw new ModelsException('No se puede continuar error en Generar Reporoceso de Validación.');

        }

    }

    public function filtrarReglas()
    {

        global $config, $http;

        $ordenes = array();

        // Extraer ORDENES PARA FILTRAR
        foreach ($this->ordenes as $key) {

            $this->documento = $key;

            $this->documento['ultimoFiltrado'] = date('Y-m-d H:i:s');

            if ($key['servicio'] !== "") {
                $this->filtrarPor('servicio', 1);
            }

            if ($key['origen'] !== "") {
                $this->filtrarPor('origen', 1);
            }

            if ($key['medico'] !== "") {
                $this->filtrarPor('medico', 1);
            }

            if ($key['motivo'] !== "") {
                $this->filtrarPor('motivo', 1);
            }

            $this->filtrarPor('pruebas', 1);

            // No Envio

            if ($key['servicio'] !== "") {
                $this->filtrarPor('servicio', 0);
            }

            if ($key['origen'] !== "") {
                $this->filtrarPor('origen', 0);
            }

            if ($key['medico'] !== "") {
                $this->filtrarPor('medico', 0);
            }

            if ($key['motivo'] !== "") {
                $this->filtrarPor('motivo', 0);
            }

            $this->filtrarPor('pruebas', 0);

            // Formato QR

            $this->filtrarPor('qr', 1);

            $ordenes[] = $this->documento;

            // Filtrada Para Envío
            if (
                count($this->documento['reglasFiltrosEnvio']) !== 0
                && count($this->documento['correosElectronicos']) !== 0
                && (count($this->documento['dataClinica']) !== 0 || count($this->documento['dataMicro']) !== 0)
                && $this->documento['sc'] == $key['sc']
            ) {

                @unlink($this->documento['file']);
                unset($this->documento['file']);
                $file = 'ordenes/filtradas/sc_' . $this->documento['sc'] . '_' . $this->documento['fechaExamen'] . '.json';
                $json_string = json_encode($this->documento);
                file_put_contents($file, $json_string);

            } else {

                // Filtrada Para No Envío
                @unlink($this->documento['file']);
                unset($this->documento['file']);
                $file = 'ordenes/errores/filtradas/sc_' . $this->documento['sc'] . '_' . $this->documento['fechaExamen'] . '.json';
                $json_string = json_encode($this->documento);
                file_put_contents($file, $json_string);

            }

            if (count($this->documento['dataClinica']) == 0 && count($this->documento['dataMicro']) == 0) {
                // Filtrada Para No Envío
                @unlink($this->documento['file']);
                unset($this->documento['file']);
                $this->documento['statusFiltro'] = 1;
                $file = 'ordenes/ingresadas/sc_' . $this->documento['sc'] . '_' . $this->documento['fechaExamen'] . '.json';
                $json_string = json_encode($this->documento);
                file_put_contents($file, $json_string);
            }

        }

        $this->ordenes = $ordenes;

    }

    public function filtrarPor($tipo, $proceso)
    {

        // Reglas Envio

        if ($tipo == 'qr' && $proceso == 1) {

            // vALIDACIONES pERSONALIZADAS

            foreach ($this->reglasEnvio as $key) {

                if (!is_null($key['xQR'])) {

                    // Validación de Fomratos QRs
                    if ($this->documento['_PCR'] == 1) {

                        $this->agregarReglasEnvio($key);
                        $this->agregarFormatoQR($key);

                    }

                }

            }

        }

        if ($tipo == 'servicio' && $proceso == 1) {

            foreach ($this->reglasEnvio as $key) {

                if (!is_null($key['xServicio'])) {
                    if ($key['xServicio'] == $this->documento['servicio']) {

                        $this->agregarReglasEnvio($key);

                    }
                }

            }

        }

        if ($tipo == 'origen' && $proceso == 1) {

            foreach ($this->reglasEnvio as $key) {

                if (!is_null($key['xOrigen'])) {
                    if ($key['xOrigen'] == $this->documento['origen']) {
                        $this->agregarReglasEnvio($key);

                    }
                }

            }

        }

        if ($tipo == 'medico' && $proceso == 1) {

            foreach ($this->reglasEnvio as $key) {

                if (!is_null($key['xMedico'])) {
                    if ($key['xMedico'] == $this->documento['medico']) {
                        $this->agregarReglasEnvio($key);

                    }
                }

            }

        }

        if ($tipo == 'motivo' && $proceso == 1) {

            foreach ($this->reglasEnvio as $key) {

                if (!is_null($key['xMotivo'])) {
                    if ($key['xMotivo'] == $this->documento['motivo']) {
                        $this->agregarReglasEnvio($key);

                    }
                }

            }

        }

        if ($tipo == 'pruebas' && $proceso == 1) {

            foreach ($this->reglasEnvio as $key) {

                if (!is_null($key['xIdPrueba'])) {

                    if (count($this->documento['dataClinica']) !== 0) {

                        foreach ($this->documento['dataClinica'] as $k) {

                            if (!is_null($key['xIdPrueba'])) {

                                if ($key['xIdPrueba'] == $k['TestID']) {

                                    $this->agregarReglasEnvio($key);

                                }
                            }

                        }

                    }

                    if (count($this->documento['dataMicro']) !== 0) {

                        foreach ($this->documento['dataMicro'] as $k) {

                            if (!is_null($key['xIdPrueba'])) {

                                if ($key['xIdPrueba'] == $k['TestID']) {
                                    $this->agregarReglasEnvio($key);

                                }
                            }

                        }

                    }

                }

            }

        }

        // Reglas No Envio

        if ($tipo == 'servicio' && $proceso == 0) {

            foreach ($this->reglasNoEnvio as $key) {

                if (!is_null($key['xServicio'])) {
                    if ($key['xServicio'] == $this->documento['servicio']) {

                        if ($this->documento['_PCR'] == 0) {
                            $this->agregarReglasNoEnvio($key);
                            $this->clearCorreosElectronicos();
                        }

                    }
                }

            }

        }

        if ($tipo == 'origen' && $proceso == 0) {

            foreach ($this->reglasNoEnvio as $key) {

                if (!is_null($key['xOrigen'])) {
                    if ($key['xOrigen'] == $this->documento['origen']) {
                        if ($this->documento['_PCR'] == 0) {
                            $this->agregarReglasNoEnvio($key);
                            $this->clearCorreosElectronicos();
                        }
                    }
                }

            }

        }

        if ($tipo == 'medico' && $proceso == 0) {

            foreach ($this->reglasNoEnvio as $key) {

                if (!is_null($key['xMedico'])) {
                    if ($key['xMedico'] == $this->documento['medico']) {
                        if ($this->documento['_PCR'] == 0) {
                            $this->agregarReglasNoEnvio($key);
                            $this->clearCorreosElectronicos();
                        }
                    }
                }

            }

        }

        if ($tipo == 'motivo' && $proceso == 0) {

            foreach ($this->reglasNoEnvio as $key) {

                if (!is_null($key['xMotivo'])) {
                    if ($key['xMotivo'] == $this->documento['motivo']) {
                        if ($this->documento['_PCR'] == 0) {
                            $this->agregarReglasNoEnvio($key);
                            $this->clearCorreosElectronicos();
                        }
                    }
                }

            }

        }

        if ($tipo == 'pruebas' && $proceso == 0) {

            foreach ($this->reglasNoEnvio as $key) {

                if (!is_null($key['xIdPrueba'])) {

                    if (count($this->documento['dataClinica']) !== 0) {

                        foreach ($this->documento['dataClinica'] as $k) {

                            if (!is_null($key['xIdPrueba'])) {

                                if ($key['xIdPrueba'] == $k['TestID']) {
                                    if ($this->documento['_PCR'] == 0) {
                                        $this->agregarReglasNoEnvio($key);
                                        $this->clearCorreosElectronicos();
                                    }

                                }
                            }

                        }

                    }

                    if (count($this->documento['dataMicro']) !== 0) {

                        foreach ($this->documento['dataMicro'] as $k) {

                            if (!is_null($key['xIdPrueba'])) {

                                if ($key['xIdPrueba'] == $k['TestID']) {
                                    if ($this->documento['_PCR'] == 0) {
                                        $this->agregarReglasNoEnvio($key);
                                        $this->clearCorreosElectronicos();
                                    }

                                }
                            }

                        }

                    }

                }

            }

        }

    }

    public function agregarFormatoQR($regla)
    {

        $existe = false;
        foreach ($this->documento['formatoQR'] as $key) {

            if ($key['id'] == $regla['id']) {
                $existe = true;
            }

        }

        if (!$existe) {

            $this->documento['formatoQR'][] = $regla;

        }

    }

    public function agregarReglasEnvio($regla)
    {

        $existe = false;
        foreach ($this->documento['reglasFiltrosEnvio'] as $key) {

            if ($key['id'] == $regla['id']) {
                $existe = true;
            }

        }

        if (!$existe) {

            $this->documento['reglasFiltrosEnvio'][] = $regla;

            if (!is_null($regla['envio'])) {
                $direcciones = json_decode($regla['envio'], true);
                $this->documento['correosElectronicos'][] = $direcciones;
            }

        }

    }

    public function agregarReglasNoEnvio($regla)
    {

        $existe = false;
        foreach ($this->documento['reglasFiltrosNoEnvio'] as $key) {

            if ($key['id'] == $regla['id']) {
                $existe = true;
            }

        }

        if (!$existe) {

            $this->documento['reglasFiltrosNoEnvio'][] = $regla;

        }

    }

    public function clearCorreosElectronicos()
    {

        if (count($this->documento['correosElectronicos']) !== 0) {
            foreach ($this->documento['correosElectronicos'] as $key => $val) {

                if ($val['dirrecciones'] == 1) {
                    unset($this->documento['correosElectronicos'][$key]);
                }

            }
        }

    }

    public function getReglas()
    {

        global $config, $http;

        $query = $this->db->select('*', 'filtro_notificaciones_lab', null, "statusFiltro='1'");

        if (false !== $query) {

            foreach ($query as $key) {

                if ($key['eNe'] == 1) {
                    $this->reglasEnvio[] = $key;
                }

                if ($key['eNe'] == 0) {
                    $this->reglasNoEnvio[] = $key;
                }

            }

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

    public function extraerDataPCR($documento)
    {

        try {

            # INICIAR SESSION

            $this->wsLab_LOGIN_PCR();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wResults.xml', array('soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ));

            $Preview = $client->GetResults(array(
                'pstrSessionKey' => $this->pstrSessionKey,
                'pstrSampleID' => $documento['sc'],
                'pstrRegisterDate' => $documento['fechaExamen'],
            ));

            $this->wsLab_LOGOUT();

            if (!isset($Preview->GetResultsResult)) {
                throw new ModelsException('No existe información.');
            }

            $listaPruebas = $Preview->GetResultsResult->Orders->LISOrder->LabTests->LISLabTest;

            $i = 0;

            $lista = array();

            if (is_array($listaPruebas)) {
                foreach ($listaPruebas as $key) {
                    $lista[] = array(
                        'TestID' => $key->TestID,
                        'TestStatus' => $key->TestStatus,
                        'TestName' => $key->TestName,
                        'ultimaValidacion' => '',
                    );
                }
            } else {
                $lista[] = array(
                    'TestID' => $listaPruebas->TestID,
                    'TestStatus' => $listaPruebas->TestStatus,
                    'TestName' => $listaPruebas->TestName,
                    'ultimaValidacion' => '',
                );
            }

            // Validacion Personalizada: ES PRUEBA PCR

            return $lista;

        } catch (\Exception $e) {

            $this->wsLab_LOGOUT();

            return array();

        } catch (ModelsException $b) {

            return array();

        }
    }

    public function extraerDataClinica($documento)
    {

        try {

            # INICIAR SESSION

            $this->wsLab_LOGIN();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wResults.xml', array('soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ));

            $Preview = $client->GetResults(array(
                'pstrSessionKey' => $this->pstrSessionKey,
                'pstrSampleID' => $documento['sc'],
                'pstrRegisterDate' => $documento['fechaExamen'],
            ));

            $this->wsLab_LOGOUT();

            if (!isset($Preview->GetResultsResult)) {
                throw new ModelsException('No existe información.');
            }

            $listaPruebas = $Preview->GetResultsResult->Orders->LISOrder->LabTests->LISLabTest;

            $i = 0;

            $lista = array();

            if (is_array($listaPruebas)) {
                foreach ($listaPruebas as $key) {
                    $lista[] = array(
                        'TestID' => $key->TestID,
                        'TestStatus' => $key->TestStatus,
                        'TestName' => $key->TestName,
                        'ultimaValidacion' => '',
                    );
                }
            } else {
                $lista[] = array(
                    'TestID' => $listaPruebas->TestID,
                    'TestStatus' => $listaPruebas->TestStatus,
                    'TestName' => $listaPruebas->TestName,
                    'ultimaValidacion' => '',
                );
            }

            return $lista;

        } catch (\Exception $e) {

            $this->wsLab_LOGOUT();

            return array();

        } catch (ModelsException $b) {

            return array();

        }
    }

    public function extraerDataMicro($documento)
    {

        try {

            # INICIAR SESSION

            $this->wsLab_LOGIN();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wResults.xml', array('soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ));

            $Preview = $client->GetMicroResults(array(
                'pstrSessionKey' => $this->pstrSessionKey,
                'pstrSampleID' => $documento['sc'],
                'pstrRegisterDate' => $documento['fechaExamen'],
            ));

            $this->wsLab_LOGOUT();

            if (!isset($Preview->GetMicroResultsResult)) {
                throw new ModelsException('No existe información.');
            }

            # REVISAR SI EXISTEN PRUEBAS NO DISPONIBLES
            $listaPruebas = $Preview->GetMicroResultsResult->Orders->LISOrder->MicSpecs->LISMicSpec;

            $i = 0;

            $cultivos = array();

            if (is_array($listaPruebas)) {
                foreach ($listaPruebas as $key) {
                    $cultivos[] = array(
                        'SpecimenName' => $key->SpecimenName,
                        'Tests' => $key->MicTests->LISLabTest,
                    );
                }
            } else {
                $cultivos[] = array(
                    'SpecimenName' => $listaPruebas->SpecimenName,
                    'Tests' => $listaPruebas->MicTests->LISLabTest,

                );
            }

            $lista = array();

            foreach ($cultivos as $k) {

                if (is_array($k['Tests'])) {
                    foreach ($k['Tests'] as $b) {
                        $lista[] = array(
                            'TestID' => $b->TestID,
                            'TestStatus' => $b->TestStatus,
                            'TestName' => $b->TestName,
                            'ultimaValidacion' => '',

                        );
                    }
                } else {
                    $lista[] = array(
                        'TestID' => $k['Tests']->TestID,
                        'TestStatus' => $k['Tests']->TestStatus,
                        'TestName' => $k['Tests']->TestName,
                        'ultimaValidacion' => '',

                    );
                }

            }

            return $lista;

        } catch (\Exception $e) {

            $this->wsLab_LOGOUT();

            return array();

        } catch (ModelsException $b) {

            return array();

        }
    }

    private function quitar_tildes($cadena)
    {
        $no_permitidas = array("%", "é", "í", "ó", "ú", "É", "Í", "Ó", "Ú", "ñ", "À", "Ã", "Ì", "Ò", "Ù", "Ã™", "Ã ", "Ã¨", "Ã¬", "Ã²", "Ã¹", "ç", "Ç", "Ã¢", "ê", "Ã®", "Ã´", "Ã»", "Ã‚", "ÃŠ", "ÃŽ", "Ã”", "Ã›", "ü", "Ã¶", "Ã–", "Ã¯", "Ã¤", "«", "Ò", "Ã", "Ã„", "Ã‹");
        $permitidas = array("", "e", "i", "o", "u", "E", "I", "O", "U", "n", "N", "A", "E", "I", "O", "U", "a", "e", "i", "o", "u", "c", "C", "a", "e", "i", "o", "u", "A", "E", "I", "O", "U", "u", "o", "O", "i", "a", "e", "U", "I", "A", "E");
        $texto = str_replace($no_permitidas, $permitidas, $cadena);
        return $texto;
    }

    private function sanear_string($string)
    {

        $string = trim($string);

        //Esta parte se encarga de eliminar cualquier caracter extraño
        $string = str_replace(
            array(">", "< ", ";", ",", ":", "%", "|", "-", "/"),
            ' ',
            $string
        );

        return trim($string);
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
        $this->startDBConexion();

    }
}
