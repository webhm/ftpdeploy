<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models\agendaMV;

use app\models\agendaMV as Model;
use DateTime;
use Doctrine\DBAL\DriverManager;
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Citas
 */

class Citas extends Models implements IModels
{
    # Variables de clase
    private $pte = null;
    private $urlAuthApi = "http://172.16.253.63:9090/auth/realms/mvapi/protocol/openid-connect/token";
    private $tipoBusqueda = null;
    private $sortField = 'ROWNUM_';
    private $sortType = 'desc'; # desc
    private $start = 1;
    private $length = 25;
    private $searchField = null;
    private $startDate = null;
    private $endDate = null;
    private $_conexion = null;
    private $user = null;
    private $access_token = null;
    private $availableServiceId = 0;
    private $covenantId = 2;
    private $covenantPlanId = 2;
    private $cita = null;
    private $escala = null;

    public function crearCita()
    {

        try {

            global $config, $http;

            $seRegistro = false;

            $objCita = $http->request->all();

            $this->cita = $objCita;

            $this->cita['availableServiceId'] = 0;
            $this->cita['covenantId'] = 2;
            $this->cita['covenantPlanId'] = 2;
            $this->cita['isFitting'] = true;
            $this->cita['markingTypeId'] = 0;
            $this->cita['scheduleFormType'] = 'PERSONALLY';
            $this->cita['statusScheduleType'] = 'M';
            $this->cita['specialityId'] = '66';

            # Extraer Escalas
            $this->extraerEscalas();

            # Validación de Disponibilidad De citas
            $this->validarCita();

            # Agendar Cita
            $this->agendarCita();

            $objCita['hashCita'] = Helper\Strings::ocrend_encode($objCita['hashCita'], 0);

            $stsLogPen = "_citas/agendadas/st_" . $objCita['hashCita'] . "_.json";

            if (@file_get_contents($stsLogPen, true) === false) {
                file_put_contents('_citas/agendadas/st_' . $objCita['hashCita'] . '_.json', json_encode($this->cita), LOCK_EX);
                $seRegistro = true;
            }

            if ($seRegistro) {
                return array(
                    'status' => true,
                    'data' => $this->cita,
                    'message' => 'Proceso realizado con éxito.',
                );
            }

            throw new ModelsException('Error en creacion de registro de Cita.');

        } catch (ModelsException $th) {
            return array(
                'status' => true,
                'data' => ['log' => $this->cita],
                'error' => $th->getTraceAsString(),
                'message' => $th->getMessage(),
            );
        }

    }

    public function updateCita()
    {

        try {

            global $config, $http;

            $seRegistro = false;

            $objCita = $http->request->all();

            $this->cita = $objCita;

            $this->cita['availableServiceId'] = 0;
            $this->cita['covenantId'] = 2;
            $this->cita['covenantPlanId'] = 2;
            $this->cita['isFitting'] = true;
            $this->cita['markingTypeId'] = 0;
            $this->cita['scheduleFormType'] = 'PERSONALLY';
            $this->cita['statusScheduleType'] = 'M';
            $this->cita['specialityId'] = '66';

            # reAgendarCita Cita
            $this->reAgendarCita();

            $this->cita['hashCita'] = Helper\Strings::ocrend_encode($this->cita['hashCita'], 0);

            $stsLogPen = "_citas/agendadas/st_" . $this->cita['hashCita'] . "_.json";

            if (@file_get_contents($stsLogPen, true) !== false) {
                @unlink($stsLogPen);
            }

            $this->cita['hashCita'] = $this->cita['newHashCita'];

            # Validación de Disponibilidad De citas
            $this->validarCita();

            # Agendar Cita
            $this->agendarCita();

            $this->cita['hashCita'] = Helper\Strings::ocrend_encode($this->cita['hashCita'], 0);

            $stsLogPen = "_citas/agendadas/st_" . $this->cita['hashCita'] . "_.json";

            if (@file_get_contents($stsLogPen, true) === false) {
                file_put_contents('_citas/agendadas/st_' . $this->cita['hashCita'] . '_.json', json_encode($this->cita), LOCK_EX);
                $seRegistro = true;
            }

            if ($seRegistro) {
                return array(
                    'status' => true,
                    'data' => $this->cita,
                    'message' => 'Proceso realizado con éxito.',
                );
            }

            throw new ModelsException('Error en creacion de registro de Cita.');

        } catch (ModelsException $th) {
            return array(
                'status' => true,
                'data' => ['log' => $this->cita],
                'error' => $th->getTraceAsString(),
                'message' => $th->getMessage(),
            );
        }

    }

    public function cancelarCita()
    {

        try {

            global $config, $http;

            $seRegistro = false;

            $objCita = $http->request->all();

            $this->cita = $objCita;

            $this->cita['availableServiceId'] = 0;
            $this->cita['covenantId'] = 2;
            $this->cita['covenantPlanId'] = 2;
            $this->cita['isFitting'] = true;
            $this->cita['markingTypeId'] = 0;
            $this->cita['scheduleFormType'] = 'PERSONALLY';
            $this->cita['statusScheduleType'] = 'M';
            $this->cita['specialityId'] = '66';

            # Cancelar Cita
            $this->_cancelarCita();

            $objCita['hashCita'] = Helper\Strings::ocrend_encode($objCita['hashCita'], 0);

            $stsLogPen = "_citas/agendadas/st_" . $objCita['hashCita'] . "_.json";

            if (@file_get_contents($stsLogPen, true) !== false) {
                @unlink($stsLogPen);
                $seRegistro = true;
            }

            if ($seRegistro) {
                return array(
                    'status' => true,
                    'data' => $this->cita,
                    'message' => 'Proceso realizado con éxito.',
                );
            }

            throw new ModelsException('Error en creacion de cancelacion de Cita.');

        } catch (ModelsException $th) {
            return array(
                'status' => true,
                'data' => ['log' => $this->cita],
                'error' => $th->getTraceAsString(),
                'message' => $th->getMessage(),
            );
        }

    }

    public function extraerEscalas()
    {

        global $config, $http;

        $this->escala = '2224';

    }

    public function validarCita()
    {

        global $config, $http;

        $hash = $this->cita['hashCita'];

        $fechaDesde = explode('.', $hash)[0];
        $fechaHasta = explode('.', $hash)[1];

        $sql = " SELECT it.cd_it_agenda_central,
            to_char(it.hr_agenda, 'YYYY-MM-DD HH24:MI') as fechaCita, it.*
            from it_agenda_central it
            where it.CD_AGENDA_CENTRAL = '$this->escala' AND
            to_char(it.hr_agenda, 'YYYY-MM-DD HH24:MI') >= '$fechaDesde' AND
            to_char(it.hr_agenda, 'YYYY-MM-DD HH24:MI') <= '$fechaHasta'
            order by it.cd_it_agenda_central asc ";

        # Conectar base de datos
        $this->conectar_Oracle_SML();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetch();

        if ($data == false) {
            throw new ModelsException('Error el intervalo seleccionado no esta disponible.');
        }

    }

    public function agendarCita()
    {

        global $config, $http;

        $hash = $this->cita['hashCita'];

        $fechaDesde = explode('.', $hash)[0];
        $fechaHasta = explode('.', $hash)[1];

        $sql = " SELECT it.cd_it_agenda_central as ID_IT,
            to_char(it.hr_agenda, 'YYYY-MM-DD HH24:MI') as fechaCita, it.*
            from it_agenda_central it
            where it.CD_AGENDA_CENTRAL = '2224' AND
            to_char(it.hr_agenda, 'YYYY-MM-DD HH24:MI') >= '$fechaDesde' AND
            to_char(it.hr_agenda, 'YYYY-MM-DD HH24:MI') <= '$fechaHasta'
            order by it.cd_it_agenda_central asc ";

        # Conectar base de datos
        $this->conectar_Oracle_SML();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetchAll();

        if (count($data) == 0) {

            throw new ModelsException('Error no se peude completar el agendamiento. No existen Citas Disponibles;');

        } else {

            foreach ($data as $index => $key) {

                if ($index !== count($data) - 1) {

                    $this->cita['id'] = $key['ID_IT'];

                    $sts = $this->crearCita_Api();

                    if ($sts == false) {
                        throw new ModelsException('No se pudo procesar la cita => ' . $key['ID_IT'] . '\n' . print_r($this->cita));
                    }

                }

            }
        }

    }

    public function reAgendarCita()
    {

        global $config, $http;

        $hash = $this->cita['hashCita'];

        $fechaDesde = explode('.', $hash)[0];
        $fechaHasta = explode('.', $hash)[1];

        $sql = " SELECT it.cd_it_agenda_central as ID_IT,
            to_char(it.hr_agenda, 'YYYY-MM-DD HH24:MI') as fechaCita, it.*
            from it_agenda_central it
            where it.CD_AGENDA_CENTRAL = '2224' AND
            to_char(it.hr_agenda, 'YYYY-MM-DD HH24:MI') >= '$fechaDesde' AND
            to_char(it.hr_agenda, 'YYYY-MM-DD HH24:MI') <= '$fechaHasta'
            order by it.cd_it_agenda_central asc ";

        # Conectar base de datos
        $this->conectar_Oracle_SML();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetchAll();

        if (count($data) == 0) {

            throw new ModelsException('Error no se peude completar el agendamiento. No existen Citas Disponibles;');

        } else {

            foreach ($data as $index => $key) {

                if ($index !== count($data) - 1) {

                    $this->cita['id'] = $key['ID_IT'];

                    $sts = $this->cancelarCita_Api();

                    if ($sts == false) {
                        throw new ModelsException('No se pudo procesar la cita => ' . $key['ID_IT'] . '\n' . print_r($this->cita));
                    }
                }

            }
        }

    }

    public function _cancelarCita()
    {

        global $config, $http;

        $hash = $this->cita['hashCita'];

        $fechaDesde = explode('.', $hash)[0];
        $fechaHasta = explode('.', $hash)[1];

        $sql = " SELECT it.cd_it_agenda_central as ID_IT,
            to_char(it.hr_agenda, 'YYYY-MM-DD HH24:MI') as fechaCita, it.*
            from it_agenda_central it
            where CD_AGENDA_CENTRAL = 2224 AND
            to_char(it.hr_agenda, 'YYYY-MM-DD HH24:MI') >= '$fechaDesde' AND
            to_char(it.hr_agenda, 'YYYY-MM-DD HH24:MI') <= '$fechaHasta'
            order by cd_it_agenda_central asc ";

        # Conectar base de datos
        $this->conectar_Oracle_SML();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetchAll();

        if (count($data) == 0) {

            throw new ModelsException('Error no se peude completar el agendamiento. No existen Citas Disponibles;');

        } else {

            foreach ($data as $key) {

                $this->cita['id'] = $key['ID_IT'];

                $sts = $this->cancelarCita_Api();

                if ($sts == false) {
                    throw new ModelsException('No se pudo procesar la cita => ' . $key['ID_IT'] . '\n' . print_r($this->cita));
                }

            }
        }

    }

    public function getCitas()
    {

        try {

            $list = Helper\Files::get_files_in_dir('../date/_citas/agendadas/', '.json');
            $citas = array();

            foreach ($list as $key => $val) {

                $content = file_get_contents($val);
                $documento = json_decode($content, true);

                $parseObj = explode('.', $documento['hashCita']);
                $horaInicio = $parseObj[0];
                $horaFinal = $parseObj[1];

                $objHash = Helper\Strings::ocrend_encode($documento['hashCita'], 0);

                $documento['id'] = $objHash;

                $documento['start'] = str_replace(' ', 'T', $horaInicio) . ':00';
                $documento['end'] = str_replace(' ', 'T', $horaFinal) . ':00';
                $documento['description'] = '';
                $documento['title'] = $documento['paciente'];

                unset($documento['loader']);
                $citas[] = $documento;

            }

            return array(
                'status' => true,
                'citasAgendadas' => array(
                    'id' => 1,
                    'backgroundColor' => '#c3edd5',
                    'borderColor' => '#10b759',
                    'events' => $citas,
                ),
            );

        } catch (ModelsException $th) {

            return array(
                'status' => true,
                'citasAgendadas' => array(
                    'id' => 1,
                    'backgroundColor' => '#c3edd5',
                    'borderColor' => '#10b759',
                    'events' => [],
                ),
            );

        }

    }

    public function setAuth()
    {

        $curl = curl_init();
        curl_setopt_array(
            $curl,
            array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_URL => $this->urlAuthApi,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query(
                    array(
                        'grant_type' => 'password',
                        'client_id' => 'swagger',
                        'client_secret' => 'swagger',
                        'username' => 'swagger',
                        'password' => 'swagger',
                    )
                ),
            )
        );

        $response = json_decode(curl_exec($curl));
        curl_close($curl);

        if (isset($response->access_token)) {
            $this->access_token = $response->access_token;
        } else {
            throw new ModelsException('No existe conexión al servidor Api.');
        }

    }

    public function getAgenda()
    {
        try {

            global $config, $http;

            $sql = " SELECT it.cd_it_agenda_central, to_char(it.hr_agenda, 'YYYY-MM-DD') as dia_cita,  to_char(it.hr_agenda, 'HH24:MI:SS') as hora_cita,  it.* from it_agenda_central it
            where CD_AGENDA_CENTRAL = 1876
            order by hr_agenda desc ";

            # Conectar base de datos
            $this->conectar_Oracle_SML();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $citasDisponibles = array();
            $citasAgendadas = array();

            foreach ($data as $key) {

                if (is_null($key['CD_PACIENTE'])) {
                    $time = new DateTime($key['DIA_CITA'] . ' ' . $key['HORA_CITA']);
                    $nuevaHora = strtotime('+15 minutes', $time->getTimestamp());
                    $menosHora = date('Y-m-d', $nuevaHora) . 'T' . date('H:i:s', $nuevaHora);

                    $cita['start'] = $key['DIA_CITA'] . 'T' . $key['HORA_CITA'];
                    $cita['end'] = $menosHora;
                    $cita['id'] = $key['CD_IT_AGENDA_CENTRAL'];
                    $cita['title'] = '';
                    $cita['description'] = '';
                    $cita['stAgendar'] = 0;
                    $citasDisponibles[] = $cita;

                } else {

                    $time = new DateTime($key['DIA_CITA'] . ' ' . $key['HORA_CITA']);
                    $nuevaHora = strtotime('+15 minutes', $time->getTimestamp());
                    $menosHora = date('Y-m-d', $nuevaHora) . 'T' . date('H:i:s', $nuevaHora);

                    $cita['start'] = $key['DIA_CITA'] . 'T' . $key['HORA_CITA'];
                    $cita['end'] = $menosHora;
                    $cita['id'] = $key['CD_IT_AGENDA_CENTRAL'];
                    $cita['title'] = ' - ' . $key['NM_PACIENTE'];
                    $cita['description'] = '';
                    $cita['stAgendar'] = 1;
                    $citasAgendadas[] = $cita;

                }

            }

            # Devolver Información
            return array(
                'status' => true,
                'citasDisponibles' => array(
                    'id' => 1,
                    'backgroundColor' => '#d9e8ff',
                    'borderColor' => '#0168fa',
                    'events' => $citasDisponibles,

                ),
                'citasAgendadas' => array(
                    'id' => 2,
                    'backgroundColor' => '#c3edd5',
                    'borderColor' => '#10b759',
                    'events' => $citasAgendadas,

                ),
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'message' => $e->getMessage(),
                'citasDisponibles' => [],
                'citasAgendadas' => [],
            );

        }

    }

    public function getCita()
    {
        try {

            global $config, $http;

            $idCita = $http->query->get('id');

            $sql = " SELECT it.cd_it_agenda_central, to_char(it.hr_agenda, 'YYYY-MM-DD') as dia_cita,  to_char(it.hr_agenda, 'HH24:MI:SS') as hora_cita,  it.* from it_agenda_central it
            where CD_AGENDA_CENTRAL = 1876 AND CD_IT_AGENDA_CENTRAL = '$idCita'
            order by hr_agenda desc ";

            # Conectar base de datos
            $this->conectar_Oracle_SML();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $citasDisponibles = array();
            $citasAgendadas = array();

            foreach ($data as $key) {

                $time = new DateTime($key['DIA_CITA'] . ' ' . $key['HORA_CITA']);
                $nuevaHora = strtotime('+15 minutes', $time->getTimestamp());
                $menosHora = date('Y-m-d', $nuevaHora) . 'T' . date('H:i:s', $nuevaHora);

                $cita['start'] = $key['DIA_CITA'] . 'T' . $key['HORA_CITA'];
                $cita['end'] = $menosHora;
                $cita['id'] = $key['CD_IT_AGENDA_CENTRAL'];
                $cita['title'] = ' - ' . $key['NM_PACIENTE'];
                $cita['description'] = '';
                $cita['stAgendar'] = 1;
                $citasAgendadas[] = $cita;

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $citasAgendadas[0],
            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'message' => $e->getMessage(),
                'data' => [],
            );

        }

    }

    public function get_Cita()
    {
        try {

            global $http;

            $idCita = $http->query->get('id');

            $this->setAuth();

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://172.16.253.63:8085/api/schedule/v1/1876/item/' . $idCita);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array(
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->access_token,
                )
            );

            $response = json_decode(curl_exec($ch));
            curl_close($ch);

            return array(
                'status' => true,
                'data' => $response,
            );

        } catch (ModelsException $th) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $th->getMessage()
            );

        }

    }

    public function get_Agenda()
    {
        try {

            global $http;

            $idAgenda = $http->query->get('idAgenda');

            $this->setAuth();

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://172.16.253.63:8085/api/schedule/v1/' . $idAgenda);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array(
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->access_token,

                )
            );

            $response = json_decode(curl_exec($ch));
            curl_close($ch);

            return $response;

        } catch (ModelsException $th) {
            return false;
        }

    }

    public function crearCita_Api()
    {

        sleep(1);

        global $http;

        $this->setAuth();

        $cita = $this->cita;

        $idCita = (int) $cita['id'];

        $_datos = json_encode($cita, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'http://172.16.253.63:8085/api/schedule-item/v1/book-hour-schedule-item/' . $idCita);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $_datos);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->access_token,
            )
        );

        $response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 200) {
            # return $response;
            return true;
        } else {
            return false;
        }

        # return $response;

        curl_close($ch);

    }

    public function cancelarCita_Api()
    {

        sleep(1);

        global $http;

        $this->setAuth();

        $cita = $this->cita;

        $idCita = (int) $cita['id'];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'http://172.16.253.63:8085/api/schedule-item/v1/cancel-schedule-item/' . $idCita);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->access_token,
            )
        );

        $response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 200) {
            # return $response;
            return true;
        } else {
            return false;
        }

        # return $response;

        curl_close($ch);

    }

    private function conectar_Oracle_SML()
    {
        global $config;
        $_config = new \Doctrine\DBAL\Configuration();
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle_mv_sml'], $_config);

    }

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();

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

            $this->user = $key;

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function setParameters()
    {

        global $http;

        foreach ($http->request->all() as $key => $value) {
            $this->$key = $value;
        }
    }

    public function getPaciente($nhc)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Devolver todos los resultados
            $sql = " SELECT a.discriminante, TRUNC (a.fecha_admision) fecha_admision, a.pk_numero_admision nro_admision, a.pk_fk_paciente hc, e.fk_persona COD_PERSONA,

            fun_calcula_anios_a_fecha(f.fecha_nacimiento,TRUNC(a.fecha_admision)) edad,

            f.primer_apellido || ' ' || f.segundo_apellido || ' ' || f.primer_nombre || ' ' || f.segundo_nombre nombre_paciente,

            b.pk_fk_medico cod_medico, fun_busca_nombre_medico(b.pk_fk_medico) nombre_medico, d.descripcion especialidad,

            fun_busca_ubicacion_corta(1,a.pk_fk_paciente,a.pk_numero_admision) nro_habitacion,

            fun_busca_diagnostico(1,a.pk_fk_paciente, a.pk_numero_admision) dg_principal

            FROM cad_admisiones a, cad_medicos_admision b, edm_medicos_especialidad c, aas_especialidades d, cad_pacientes e, bab_personas f

            WHERE a.alta_clinica         IS NULL            AND

                  a.pre_admision         = 'N'              AND

                  a.anulado              = 'N'              AND

                  a.discriminante        IN ('HPN','EMA')   AND

                  a.pk_fk_paciente       = b.pk_fk_paciente AND

                  a.pk_numero_admision   = b.pk_fk_admision AND

                  b.clasificacion_medico = 'TRA'            AND

                  b.pk_fk_medico         = c.pk_fk_medico   AND

                  c.principal            = 'S'              AND

                  c.pk_fk_especialidad   = d.pk_codigo      AND

                  a.pk_fk_paciente       = e.pk_nhcl        AND

                  e.fk_persona           = f.pk_codigo      AND

                  e.pk_nhcl =  '$nhc' ";

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            return array(
                'status' => true,
                'data' => $data,
            );

        } catch (ModelsException $e) {
            return array('status' => false, 'data' => [], 'message' => $e->getMessage());
        }

    }

    public function getReportesPEP()
    {

        try {

            global $http;

            $url = $http->request->get('url');

            $data = base64_encode(file_get_contents($url));

            return array(
                'status' => true,
                'data' => $data,
                'message' => 'Proceso realizado con éxito.',
            );

        } catch (\Exception $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage()
            );
        }

    }

    public function getMisPacientes()
    {

        try {

            global $config, $http;

            $this->getAuthorization();

            $codMedico = (int) $this->user->codMedico;

            # seteo de valores para paginacion
            $fecha = date('d-m-Y');

            $this->start = (int) $http->query->get('start');

            $this->length = (int) $http->query->get('length');

            if ($this->start >= 10) {
                $this->length = $this->start + $this->length;
            }

            $_searchField = (bool) $http->query->get('searchField');

            if ($_searchField != false) {

                $this->searchField = $this->quitar_tildes(mb_strtoupper($this->sanear_string($http->query->get('searchField')), 'UTF-8'));

                $this->searchField = str_replace(" ", "%", $this->searchField);

                // Es Residente
                if ($codMedico == "0") {

                    $sql = " SELECT *
                    FROM (
                    SELECT b.*, ROWNUM AS NUM
                    FROM (


                    SELECT a.discriminante, TRUNC (a.fecha_admision) fecha_admision, a.pk_numero_admision nro_admision, a.pk_fk_paciente hc,

                        fun_calcula_anios_a_fecha(f.fecha_nacimiento,TRUNC(a.fecha_admision)) edad,

                        f.primer_apellido || ' ' || f.segundo_apellido || ' ' || f.primer_nombre || ' ' || f.segundo_nombre nombre_paciente,

                        b.pk_fk_medico cod_medico, fun_busca_nombre_medico(b.pk_fk_medico) nombre_medico, d.descripcion especialidad,

                        fun_busca_ubicacion_corta(1,a.pk_fk_paciente,a.pk_numero_admision) nro_habitacion,

                        fun_busca_diagnostico(1,a.pk_fk_paciente, a.pk_numero_admision) dg_principal

                        FROM cad_admisiones a, cad_medicos_admision b, edm_medicos_especialidad c, aas_especialidades d, cad_pacientes e, bab_personas f

                        WHERE a.alta_clinica         IS NULL            AND

                            a.pre_admision         = 'N'              AND

                            a.anulado              = 'N'              AND

                            a.discriminante        IN ('HPN','EMA')   AND

                            a.pk_fk_paciente       = b.pk_fk_paciente AND

                            a.pk_numero_admision   = b.pk_fk_admision AND

                            b.clasificacion_medico = 'TRA'            AND

                            b.pk_fk_medico         = c.pk_fk_medico   AND

                            c.principal            = 'S'              AND

                            c.pk_fk_especialidad   = d.pk_codigo      AND

                            a.pk_fk_paciente       = e.pk_nhcl        AND

                            e.fk_persona           = f.pk_codigo  AND

                                (f.primer_apellido || ' ' || f.segundo_apellido || ' ' || f.primer_nombre || ' ' || f.segundo_nombre LIKE '%$this->searchField%' )

                                ORDER BY a.fecha_admision DESC

                    ) b
                    WHERE ROWNUM <= " . $this->length . "
                    )
                    WHERE NUM > " . $this->start . "
                    ";

                } else {

                    $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (


                    SELECT a.discriminante, TRUNC (a.fecha_admision) fecha_admision, a.pk_numero_admision nro_admision, a.pk_fk_paciente hc,

                    fun_calcula_anios_a_fecha(f.fecha_nacimiento,TRUNC(a.fecha_admision)) edad,

                    f.primer_apellido || ' ' || f.segundo_apellido || ' ' || f.primer_nombre || ' ' || f.segundo_nombre nombre_paciente,

                    b.pk_fk_medico cod_medico, fun_busca_nombre_medico(b.pk_fk_medico) nombre_medico, d.descripcion especialidad, b.clasificacion_medico,

                    fun_busca_ubicacion_corta(1,a.pk_fk_paciente,a.pk_numero_admision) nro_habitacion,

                    fun_busca_diagnostico(1,a.pk_fk_paciente, a.pk_numero_admision) dg_principal

                    FROM cad_admisiones a, cad_medicos_admision b, edm_medicos_especialidad c, aas_especialidades d, cad_pacientes e, bab_personas f

                    WHERE a.alta_clinica         IS NULL            AND

                        a.pre_admision         = 'N'              AND

                        a.anulado              = 'N'              AND

                        a.discriminante        IN ('HPN','EMA')   AND

                        a.pk_fk_paciente       = b.pk_fk_paciente AND

                        a.pk_numero_admision   = b.pk_fk_admision AND

                        b.pk_fk_medico         = c.pk_fk_medico   AND

                        c.principal            = 'S'              AND

                        c.pk_fk_especialidad   = d.pk_codigo      AND

                        a.pk_fk_paciente       = e.pk_nhcl        AND

                        e.fk_persona           = f.pk_codigo   AND

                        b.pk_fk_medico LIKE '%$codMedico' AND

                        (f.primer_apellido || ' ' || f.segundo_apellido || ' ' || f.primer_nombre || ' ' || f.segundo_nombre LIKE '%$this->searchField%' )

                        ORDER BY a.fecha_admision DESC

                ) b
                WHERE ROWNUM <= " . $this->length . "
                )
                WHERE NUM > " . $this->start . "
                ";

                }

            } elseif ($_searchField != false && $this->isRange($http->query->get('search')['value'])) {

                $this->searchField = explode('-', $http->query->get('search')['value']);

                $desde = $this->searchField[1]; // Valores para busqueda de desde rango de fechas
                $hasta = $this->searchField[2]; // valor de busqueda para hasta rango de fechas

                $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (
                    SELECT *
                    FROM WEB3_RESULTADOS_LAB NOLOCK WHERE TOT_SC != TOD_DC
                    AND FECHA >= '$desde'
                    AND FECHA <= '$hasta'
                    ORDER BY SC DESC
                ) b
                WHERE ROWNUM <= " . $this->length . "
                )
                WHERE NUM > " . $this->start . "
                ";

            } else {

                if ($codMedico == "0") {

                    $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (


                    SELECT a.discriminante, TRUNC (a.fecha_admision) fecha_admision, a.pk_numero_admision nro_admision, a.pk_fk_paciente hc,

                        fun_calcula_anios_a_fecha(f.fecha_nacimiento,TRUNC(a.fecha_admision)) edad,

                        f.primer_apellido || ' ' || f.segundo_apellido || ' ' || f.primer_nombre || ' ' || f.segundo_nombre nombre_paciente,

                        b.pk_fk_medico cod_medico, fun_busca_nombre_medico(b.pk_fk_medico) nombre_medico, d.descripcion especialidad,

                        fun_busca_ubicacion_corta(1,a.pk_fk_paciente,a.pk_numero_admision) nro_habitacion,

                        fun_busca_diagnostico(1,a.pk_fk_paciente, a.pk_numero_admision) dg_principal

                        FROM cad_admisiones a, cad_medicos_admision b, edm_medicos_especialidad c, aas_especialidades d, cad_pacientes e, bab_personas f

                        WHERE a.alta_clinica         IS NULL            AND

                            a.pre_admision         = 'N'              AND

                            a.anulado              = 'N'              AND

                            a.discriminante        IN ('HPN','EMA')   AND

                            a.pk_fk_paciente       = b.pk_fk_paciente AND

                            a.pk_numero_admision   = b.pk_fk_admision AND

                            b.clasificacion_medico = 'TRA'            AND

                            b.pk_fk_medico         = c.pk_fk_medico   AND

                            c.principal            = 'S'              AND

                            c.pk_fk_especialidad   = d.pk_codigo      AND

                            a.pk_fk_paciente       = e.pk_nhcl        AND

                            e.fk_persona           = f.pk_codigo   ORDER BY a.fecha_admision DESC

                ) b
                WHERE ROWNUM <= " . $this->length . "
                )
                WHERE NUM > " . $this->start . "
                ";

                } else {

                    $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (



                    SELECT a.discriminante, TRUNC (a.fecha_admision) fecha_admision, a.pk_numero_admision nro_admision, a.pk_fk_paciente hc,

                    fun_calcula_anios_a_fecha(f.fecha_nacimiento,TRUNC(a.fecha_admision)) edad,

                    f.primer_apellido || ' ' || f.segundo_apellido || ' ' || f.primer_nombre || ' ' || f.segundo_nombre nombre_paciente,

                    b.pk_fk_medico cod_medico, fun_busca_nombre_medico(b.pk_fk_medico) nombre_medico, d.descripcion especialidad, b.clasificacion_medico,

                    fun_busca_ubicacion_corta(1,a.pk_fk_paciente,a.pk_numero_admision) nro_habitacion,

                    fun_busca_diagnostico(1,a.pk_fk_paciente, a.pk_numero_admision) dg_principal

                    FROM cad_admisiones a, cad_medicos_admision b, edm_medicos_especialidad c, aas_especialidades d, cad_pacientes e, bab_personas f

                    WHERE a.alta_clinica         IS NULL            AND

                        a.pre_admision         = 'N'              AND

                        a.anulado              = 'N'              AND

                        a.discriminante        IN ('HPN','EMA')   AND

                        a.pk_fk_paciente       = b.pk_fk_paciente AND

                        a.pk_numero_admision   = b.pk_fk_admision AND

                        b.pk_fk_medico         = c.pk_fk_medico   AND

                        c.principal            = 'S'              AND

                        c.pk_fk_especialidad   = d.pk_codigo      AND

                        a.pk_fk_paciente       = e.pk_nhcl        AND

                        e.fk_persona           = f.pk_codigo   AND

                        b.pk_fk_medico LIKE '%$codMedico'  ORDER BY a.fecha_admision DESC

                ) b
                WHERE ROWNUM <= " . $this->length . "
                )
                WHERE NUM > " . $this->start . "
                ";

                }

            }

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $resultados_tra = array();
            $resultados_inter = array();
            $resultados_ema = array();
            $resultados_hpn = array();

            $time = new DateTime();
            $nuevaHora = strtotime('-2 days', $time->getTimestamp());
            $menosHora = date('Y-m-d', $nuevaHora);
            $tiempoControl = strtotime($menosHora);

            $nuevaHoraHosp = strtotime('-300 days', $time->getTimestamp());
            $menosHoraHosp = date('Y-m-d', $nuevaHoraHosp);
            $tiempoControlHosp = strtotime($menosHoraHosp);

            foreach ($data as $key) {

                if ($codMedico == "0") {

                    $fechaFormat = strtotime($key['FECHA_ADMISION']);

                    if ($fechaFormat > $tiempoControl) {

                        if ($key['DISCRIMINANTE'] == 'EMA') {
                            $resultados_ema[] = $key;
                        } else {
                            $resultados_hpn[] = $key;
                        }

                    }

                } else {

                    $fechaFormat = strtotime($key['FECHA_ADMISION']);

                    if ($fechaFormat > $tiempoControlHosp) {
                        if ($key['CLASIFICACION_MEDICO'] == 'TRA') {
                            $resultados_tra[] = $key;
                        } else {
                            $resultados_inter[] = $key;
                        }
                    }

                }

            }

            if ($codMedico == "0") {

                # Devolver Información
                return array(
                    'status' => true,
                    'dataTra' => $resultados_ema,
                    'totalTra' => count($resultados_ema),
                    'dataInter' => $resultados_hpn,
                    'totalInter' => count($resultados_hpn),
                    'codMedico' => $codMedico,

                );

            } else {

                # Devolver Información
                return array(
                    'status' => true,
                    'dataTra' => $resultados_tra,
                    'totalTra' => count($resultados_tra),
                    'dataInter' => $resultados_inter,
                    'totalInter' => count($resultados_inter),
                    'codMedico' => $codMedico,

                );

            }

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'dataTra' => [],
                'dataInter' => [],
                'message' => $e->getMessage(),
                'codMedico' => $codMedico
            );

        }

    }

    public function getResultadosPacientes()
    {

        try {

            global $config, $http;

            $this->getAuthorization();

            $codMedico = $this->user->codMedico;

            # seteo de valores para paginacion
            $fecha = date('d-m-Y');

            $this->start = (int) $http->query->get('start');

            $this->length = (int) $http->query->get('length');

            if ($this->start >= 10) {
                $this->length = $this->start + $this->length;
            }

            $_searchField = (bool) $http->query->get('searchField');

            if ($_searchField != false) {

                $this->searchField = $this->quitar_tildes(mb_strtoupper($this->sanear_string($http->query->get('searchField')), 'UTF-8'));

                $this->searchField = str_replace(" ", "%", $this->searchField);

                $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (


                SELECT a.discriminante, TRUNC (a.fecha_admision) fecha_admision, a.pk_numero_admision nro_admision, a.pk_fk_paciente hc,

                    fun_calcula_anios_a_fecha(f.fecha_nacimiento,TRUNC(a.fecha_admision)) edad,

                    f.primer_apellido || ' ' || f.segundo_apellido || ' ' || f.primer_nombre || ' ' || f.segundo_nombre nombre_paciente,

                    b.pk_fk_medico cod_medico, fun_busca_nombre_medico(b.pk_fk_medico) nombre_medico, d.descripcion especialidad,

                    fun_busca_ubicacion_corta(1,a.pk_fk_paciente,a.pk_numero_admision) nro_habitacion,

                    fun_busca_diagnostico(1,a.pk_fk_paciente, a.pk_numero_admision) dg_principal

                    FROM cad_admisiones a, cad_medicos_admision b, edm_medicos_especialidad c, aas_especialidades d, cad_pacientes e, bab_personas f

                    WHERE a.alta_clinica         IS NULL            AND

                        a.pre_admision         = 'N'              AND

                        a.anulado              = 'N'              AND

                        a.discriminante        IN ('HPN','EMA')   AND

                        a.pk_fk_paciente       = b.pk_fk_paciente AND

                        a.pk_numero_admision   = b.pk_fk_admision AND

                        b.clasificacion_medico = 'TRA'            AND

                        b.pk_fk_medico         = c.pk_fk_medico   AND

                        c.principal            = 'S'              AND

                        c.pk_fk_especialidad   = d.pk_codigo      AND

                        a.pk_fk_paciente       = e.pk_nhcl        AND

                        e.fk_persona           = f.pk_codigo  AND

                            (f.primer_apellido || ' ' || f.segundo_apellido || ' ' || f.primer_nombre || ' ' || f.segundo_nombre LIKE '%$this->searchField%' )

                            ORDER BY a.fecha_admision DESC

                ) b
                WHERE ROWNUM <= " . $this->length . "
                )
                WHERE NUM > " . $this->start . "
                ";

            } elseif ($_searchField != false && $this->isRange($http->query->get('search')['value'])) {

                $this->searchField = explode('-', $http->query->get('search')['value']);

                $desde = $this->searchField[1]; // Valores para busqueda de desde rango de fechas
                $hasta = $this->searchField[2]; // valor de busqueda para hasta rango de fechas

                $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (
                    SELECT *
                    FROM WEB3_RESULTADOS_LAB NOLOCK WHERE TOT_SC != TOD_DC
                    AND FECHA >= '$desde'
                    AND FECHA <= '$hasta'
                    ORDER BY SC DESC
                ) b
                WHERE ROWNUM <= " . $this->length . "
                )
                WHERE NUM > " . $this->start . "
                ";

            } else {

                $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (


                    SELECT a.discriminante, TRUNC (a.fecha_admision) fecha_admision, a.pk_numero_admision nro_admision, a.pk_fk_paciente hc,

                        fun_calcula_anios_a_fecha(f.fecha_nacimiento,TRUNC(a.fecha_admision)) edad,

                        f.primer_apellido || ' ' || f.segundo_apellido || ' ' || f.primer_nombre || ' ' || f.segundo_nombre nombre_paciente,

                        b.pk_fk_medico cod_medico, fun_busca_nombre_medico(b.pk_fk_medico) nombre_medico, d.descripcion especialidad,

                        fun_busca_ubicacion_corta(1,a.pk_fk_paciente,a.pk_numero_admision) nro_habitacion,

                        fun_busca_diagnostico(1,a.pk_fk_paciente, a.pk_numero_admision) dg_principal

                        FROM cad_admisiones a, cad_medicos_admision b, edm_medicos_especialidad c, aas_especialidades d, cad_pacientes e, bab_personas f

                        WHERE a.alta_clinica         IS NULL            AND

                            a.pre_admision         = 'N'              AND

                            a.anulado              = 'N'              AND

                            a.discriminante        IN ('HPN','EMA')   AND

                            a.pk_fk_paciente       = b.pk_fk_paciente AND

                            a.pk_numero_admision   = b.pk_fk_admision AND

                            b.clasificacion_medico = 'TRA'            AND

                            b.pk_fk_medico         = c.pk_fk_medico   AND

                            c.principal            = 'S'              AND

                            c.pk_fk_especialidad   = d.pk_codigo      AND

                            a.pk_fk_paciente       = e.pk_nhcl        AND

                            e.fk_persona           = f.pk_codigo   ORDER BY a.fecha_admision DESC

                ) b
                WHERE ROWNUM <= " . $this->length . "
                )
                WHERE NUM > " . $this->start . "
                ";

            }

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $resultados_tra = array();
            $resultados_inter = array();
            $resultados_ema = array();
            $resultados_hpn = array();

            foreach ($data as $key) {

                if ($codMedico == "0") {

                    if ($key['DISCRIMINANTE'] == 'EMA') {
                        $resultados_ema[] = $key;
                    } else {
                        $resultados_hpn[] = $key;
                    }

                } else {

                    if ($key['CLASIFICACION_MEDICO'] == 'TRA') {
                        $resultados_tra[] = $key;
                    } else {
                        $resultados_inter[] = $key;
                    }

                }

            }

            return array(
                'status' => true,
                'dataTra' => $resultados_ema,
                'totalTra' => count($resultados_ema),
                'dataInter' => $resultados_hpn,
                'totalInter' => count($resultados_hpn),
                'codMedico' => $codMedico,

            );

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'dataTra' => [],
                'dataInter' => [],
                'message' => $e->getMessage(),
                'codMedico' => $codMedico
            );

        }

    }

    public function verLogs()
    {

        try {

            # Set Parametrs
            $this->setParameters();

            # Verificar que no están vacíos
            if (Helper\Functions::e($this->pte, $this->tipoBusqueda)) {
                throw new ModelsException('Ingrese un valor de búsqueda.');
            }

            # Conectar base de datos
            $this->conectar_Oracle();

            if ($this->tipoBusqueda == 'cc') {

                if (!is_numeric($this->pte)) {
                    throw new ModelsException('Valor de búsqueda debe ser númerico.');
                }

                # Devolver todos los resultados
                $sql = "SELECT * FROM  WEB2_VW_LOGIN NOLOCK
                WHERE COD_PTE IS NOT NULL AND CC = '" . $this->pte . "'  OR PASAPORTE = '" . $this->pte . "' ";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $data = $stmt->fetch();

                if (false === $data) {
                    throw new ModelsException('No existe Información.');
                }

                $fk_persona = $data['COD_PTE'];

                # Conectar base de datos
                $this->conectar_Oracle();

                # Devolver todos los resultados
                $sql = " SELECT * FROM  GEMA.WEB_VW_PERSONAS NOLOCK  WHERE FK_PERSONA = '" . $fk_persona . "'   ";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $data = $stmt->fetch();

                if (false === $data) {
                    throw new ModelsException('No existe Información.');

                }

                $data = array($data);

            } else if ($this->tipoBusqueda == 'nhc') {

                if (!is_numeric($this->pte)) {
                    throw new ModelsException('Valor de búsqueda debe ser númerico.');
                }

                # Devolver todos los resultados
                $sql = " SELECT * FROM  GEMA.WEB_VW_PERSONAS NOLOCK  WHERE PK_NHCL ='" . $this->pte . "'   ";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $data = $stmt->fetch();

                if (false === $data) {
                    throw new ModelsException('No existe Información.');

                }

                $data = array($data);

            } else {

                if (!Helper\Strings::only_letters($this->pte)) {
                    throw new ModelsException('Valor de búsqueda debe ser texto.');
                }

                # Devolver todos los resultados
                $sql = " SELECT * FROM  gema.vw_web_pacientes NOLOCK  WHERE APELLIDOS || ' ' || NOMBRES LIKE '%" . $this->pte . "%'   ";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $data = $stmt->fetchAll();

                if (count($data) === 0) {
                    throw new ModelsException('No existe Información.');
                }
            }

            return array(
                'status' => true,
                'message' => 'Paciente encontrado.',
                'tipoBusqueda' => $this->tipoBusqueda,
                'data' => $data,
            );

        } catch (ModelsException $e) {
            return array(
                'status' => false,
                'data' => [],
                'tipoBusqueda' => $this->tipoBusqueda,
                'message' => $e->getMessage()
            );
        }

    }

    public function buscarPaciente()
    {

        try {

            # Set Parametrs
            $this->setParameters();

            $this->getAuthorization();

            # Verificar que no están vacíos
            if (Helper\Functions::e($this->pte, $this->tipoBusqueda)) {
                throw new ModelsException('Ingrese un valor de búsqueda.');
            }

            # Conectar base de datos
            $this->conectar_Oracle();

            if ($this->tipoBusqueda == 'cc') {

                if (!is_numeric($this->pte)) {
                    throw new ModelsException('Valor de búsqueda debe ser númerico.');
                }

                # Devolver todos los resultados
                $sql = "SELECT * FROM  WEB2_VW_LOGIN NOLOCK
                WHERE COD_PTE IS NOT NULL AND CC = '" . $this->pte . "'  OR PASAPORTE = '" . $this->pte . "' ";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $data = $stmt->fetch();

                if (false === $data) {
                    throw new ModelsException('No existe Información.');
                }

                $fk_persona = $data['COD_PTE'];

                # Conectar base de datos
                $this->conectar_Oracle();

                # Devolver todos los resultados
                $sql = " SELECT * FROM  GEMA.WEB_VW_PERSONAS NOLOCK  WHERE FK_PERSONA = '" . $fk_persona . "'   ";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $data = $stmt->fetch();

                if (false === $data) {
                    throw new ModelsException('No existe Información.');

                }

                $data = array($data);

            } else if ($this->tipoBusqueda == 'nhc') {

                if (!is_numeric($this->pte)) {
                    throw new ModelsException('Valor de búsqueda debe ser númerico.');
                }

                # Devolver todos los resultados
                $sql = " SELECT * FROM  GEMA.WEB_VW_PERSONAS NOLOCK  WHERE PK_NHCL ='" . $this->pte . "'   ";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $data = $stmt->fetch();

                if (false === $data) {
                    throw new ModelsException('No existe Información.');

                }

                $data = array($data);

            } else {

                $this->pte = str_replace(" ", "%", $this->pte);

                # Devolver todos los resultados
                $sql = " SELECT * FROM  gema.vw_web_pacientes NOLOCK  WHERE APELLIDOS || ' ' || NOMBRES LIKE '%" . $this->pte . "%'   ";

                # Execute
                $stmt = $this->_conexion->query($sql);

                $this->_conexion->close();

                $data = $stmt->fetchAll();

                if (count($data) === 0) {
                    throw new ModelsException('No existe Información.');
                }
            }

            return array(
                'status' => true,
                'message' => 'Paciente encontrado.',
                'tipoBusqueda' => $this->tipoBusqueda . " - " . $this->pte,
                'data' => $data,
                'user' => $this->user,
            );

        } catch (ModelsException $e) {
            return array(
                'status' => false,
                'data' => [],
                'tipoBusqueda' => $this->tipoBusqueda . " - " . $this->pte,
                'message' => $e->getMessage()
            );
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

    private function isRange($value)
    {

        $pos = strpos($value, 'fechas');

        if ($pos !== false) {
            return true;
        } else {
            return false;
        }

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
