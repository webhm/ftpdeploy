<?php

/*
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models;

use app\models as Model;
use Doctrine\DBAL\DriverManager;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Pasaporte
 */
class Pasaporte extends Models implements IModels
{

    private $pasaportes = false;
    private $encargadasPB = "https://prod-25.westus.logic.azure.com:443/workflows/c92c1adddb5b4fcf89914bb58356fbee/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=ZybOvC3qeSaDQSulZuP0FOORSj8_0dwJhpNyibdzB-0";
    private $encargadasH1 = "https://prod-91.westus.logic.azure.com:443/workflows/1be6b97bf7c6449abf1b6ca108dcea26/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=cPalWuccA_9_SFi4b56l_VFB3e_GZdnwMPrtOAMoP5A";
    private $encargadasH2 = "https://prod-63.westus.logic.azure.com:443/workflows/f692d507953b4bb3805659316e46ddcc/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=jowPoar-wbw64_AhMcE5oXwU9NcFZye4UHg6i5xayvw";
    private $encargadasC2 = "https://prod-70.westus.logic.azure.com:443/workflows/1657ad512a564f2f85cbd40429f2a1aa/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=kOyGXd13SC5Nzt7woVNDibDghAqap0Qe_FT5RiEc_n8";
    private $encargadasCOVID = "https://prod-93.westus.logic.azure.com:443/workflows/97f0f5e851174c2aab7d0e9d6b622807/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=kA50FYRB9uNPKOuvS2-yxoQ1EpHKIkw37lsXJgk2M5k";

    private $medicinaInterna = "https://prod-185.westus.logic.azure.com:443/workflows/d8b2fbf2585642f4a1b45856d96af39e/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=QlGprhOYRtQvj8-HwGLmp7XmkQZSZdn1Mfwyc7x_4BQ";
    private $medicinaCirugia = "https://prod-191.westus.logic.azure.com:443/workflows/6acaa5caa7cf42c2bdd2f3f95ad55885/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=XZrLhbd3Q3y8f8hSjDZO2kYDPKTpqkG2FUD2NY0fo-I";
    private $medicinaGinecologia = "https://prod-20.westus.logic.azure.com:443/workflows/6dc4f714179e4998a85928ece1f4a024/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=uqckvGjXxpWBfrl1UuoDrUX-a85Cfbf55w-7HcfP6do";
    private $medicinaImagenologia = "https://prod-67.westus.logic.azure.com:443/workflows/6c1db97f5de04954934e94f1fbd58287/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=59IkTFlXM_g74jHnvaRJY7utfIT-RKWFUvuyy_ktvR0";
    private $medicinaPediatria = "https://prod-84.westus.logic.azure.com:443/workflows/4a9e9162031449439733e72ffd6d6739/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=KaJUehQwW2qltSyWmCK09q14VcBDmWb6GKshJJhUepc";
    private $medicinaTraunmato = "https://prod-168.westus.logic.azure.com:443/workflows/951a3b8922404caf83ceae698379eccf/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=diL0r3n9wE64b2qz0tZSotWubXtIcOBTOOAOnB9ofDI";

    private $encargadasPB_R = "https://prod-49.westus.logic.azure.com:443/workflows/c1201e4cabcc421683543ed4bbca376e/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=x1eCSKrgyJ6F7XHsPJQtMpPUBR8mD2PHOzMDpRmbupM";
    private $encargadasH1_R = "https://prod-24.westus.logic.azure.com:443/workflows/1c01dc6f27ed4f00a86368c5247cc6a9/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=m3I4K2Z6lbKpcFwk19Ku3r-DSPsyZup1_jHhnB4LcEM";
    private $encargadasH2_R = "https://prod-19.westus.logic.azure.com:443/workflows/77023d177e414231a561a629eecb03dc/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=mkfdN2RqjoK1EErmAgv8McKmNANqfk6RFKdOXl5jiKo";
    private $encargadasC2_R = "https://prod-04.westus.logic.azure.com:443/workflows/978de8c35d5f4c10b57d22018cc4feb8/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=BojI1YYdcoiozfHVLL5wyxvExwUD7QjH2lLezwIIqwQ";
    private $encargadasCOVID_R = "https://prod-82.westus.logic.azure.com:443/workflows/130719ac471744048b19869fd6ab0187/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=RkvoluOVFKCFfaDoAZuUHmYASzcPxnY_Tntl4ekkZ1g";

    /**
     * Genera datos de Pasaporte
     *
     * @return array : Con información de éxito/falla al terminar el proceso.
     */
    public function generateTaskPasaporte()
    {
        try {

            global $config, $http;

            $dia = date('d-m-Y');

            $nhc = $http->request->get('nhc');

            // $sql = " SELECT * FROM cad_vw_encuesta_planetree WHERE FECHA_ADMISION = '$dia' AND SN_ENCUESTA='N' AND SN_ENCUESTA_MED IS NULL AND HABITACION IS NOT NULL AND HC != '85194201' ";

            $sql = " SELECT * FROM cad_vw_encuesta_planetree WHERE HC = '$nhc'  ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Conectar base de datos
            $this->setSpanishOracleV2();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # CERRAR CONEXION
            $this->_conexion->close();

            # VERIFICAR RESULTADOS
            $data = $stmt->fetch();

            #  return $data;

            if (false === $data) {

                return false;

            } else {

                $data['nhc'] = $data['HC'];

                $getStatus = $this->getStatusPasaportePaciente($data);

                # return $getStatus;

                if ($getStatus['status']) {

                    $dataReingreso = (array) $getStatus['data'][0];

                    $data['idPasaporte'] = (string) $dataReingreso['ID'];

                    $this->pasaportes[] = $data;

                    $taskSendPasaportes = $this->taskSendPasaportesReingresoBeta();

                    return $taskSendPasaportes;

                } else {

                    $this->pasaportes[] = $data;

                    $taskSendPasaportes = $this->taskSendPasaportes();

                    return $taskSendPasaportes;

                }

            }

        } catch (ModelsException $e) {

            return $e;

        }
    }

    /**
     * Genera datos de Pasaporte
     *
     * @return array : Con información de éxito/falla al terminar el proceso.
     */
    public function taskPasaporte()
    {
        try {

            $dia = date('d-m-Y');

            // $sql = " SELECT * FROM cad_vw_encuesta_planetree WHERE FECHA_ADMISION = '$dia' AND SN_ENCUESTA='N' AND SN_ENCUESTA_MED IS NULL AND HABITACION IS NOT NULL AND HC != '85194201' ";

            $sql = " SELECT * FROM cad_vw_encuesta_planetree WHERE FECHA_ADMISION = '$dia' AND SN_ENCUESTA='N' AND SN_ENCUESTA_MED IS NULL AND HABITACION IS NOT NULL  ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Conectar base de datos
            $this->setSpanishOracleV2();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # CERRAR CONEXION
            $this->_conexion->close();

            # VERIFICAR RESULTADOS
            $data = $stmt->fetch();

            #  return $data;

            if (false === $data) {

                return false;

            } else {

                $data['nhc'] = $data['HC'];

                $getStatus = $this->getStatusPasaportePaciente($data);

                # return $getStatus;

                if ($getStatus['status']) {

                    $dataReingreso = (array) $getStatus['data'][0];

                    $data['idPasaporte'] = (string) $dataReingreso['ID'];

                    $this->pasaportes[] = $data;

                    $taskSendPasaportes = $this->taskSendPasaportesReingresoBeta();

                    return $taskSendPasaportes;

                } else {

                    $this->pasaportes[] = $data;

                    $taskSendPasaportes = $this->taskSendPasaportes();

                    return $taskSendPasaportes;

                }

            }

        } catch (ModelsException $e) {

            return $e;

        }
    }

    private function setSpanishOracleV2()
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

    public function taskPasaporteBeta()
    {
        try {

            $sql = " SELECT * FROM cad_vw_encuesta_planetree WHERE HC='86135801'  ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Conectar base de datos
            $this->setSpanishOracleV2();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # CERRAR CONEXION
            $this->_conexion->close();

            # VERIFICAR RESULTADOS
            $data = $stmt->fetch();

            #  return $data;

            if (false === $data) {

                return false;

            } else {

                $data['nhc'] = $data['HC'];

                $getStatus = $this->getStatusPasaportePaciente($data);

                if ($getStatus['status']) {

                    $dataReingreso = (array) $getStatus['data'][0];

                    $data['idPasaporte'] = (string) $dataReingreso['ID'];

                    $this->pasaportes[] = $data;

                    $taskSendPasaportes = $this->taskSendPasaportesReingresoBeta();

                    return $taskSendPasaportes;

                } else {

                    $this->pasaportes[] = $data;

                    $this->pasaportes[] = $getStatus['data'][0];

                    # $taskSendPasaportes = $this->taskSendPasaportesBeta();

                    return $this->pasaportes;

                }

            }

        } catch (ModelsException $e) {

            return $e;

        }
    }

    private function actualizarRegistroPasaporteMed($pte)
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        # Set españish language oracle Citas Agendadas
        $this->setSpanishOracle();

        # QueryBuilder
        $queryBuilder = $this->_conexion->createQueryBuilder();

        # QUERY PARA INSERTAR

        $queryBuilder
            ->insert('cad_encuesta_planetree')
            ->setValue('hc', '?')
            ->setValue('adm', '?')
            ->setValue('sn_encuesta', '?')
            ->setValue('fecha_adm', '?')
            ->setParameter(0, $pte['nhc'])
            ->setParameter(1, $pte['numAdm'])
            ->setParameter(2, 'S')
            ->setParameter(3, date('d/m/Y H:i'))
        ;

        # EXECUTE
        $stmt = $queryBuilder->execute();

        $this->_conexion->close();

        # return true;

    }

    private function actualizarRegistroPasaporte($pte)
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        # Set españish language oracle Citas Agendadas
        $this->setSpanishOracle();

        # QueryBuilder
        $queryBuilder = $this->_conexion->createQueryBuilder();

        # QUERY PARA INSERTAR
        $queryBuilder
            ->insert('cad_encuesta_planetree')
            ->setValue('hc', '?')
            ->setValue('adm', '?')
            ->setValue('sn_encuesta', '?')
            ->setValue('fecha_adm', '?')
            ->setParameter(0, $pte['nhc'])
            ->setParameter(1, $pte['numAdm'])
            ->setParameter(2, 'S')
            ->setParameter(3, date('d/m/Y H:i'))

        ;

        # EXECUTE
        $stmt = $queryBuilder->execute();

        $this->_conexion->close();

        # return true;

    }

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();

        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA NMETROAMB
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

        $sql = " alter session set NLS_DATE_FORMAT = 'DD/MM/YYYY HH24:MI' ";
        # Execute
        $stmt = $this->_conexion->query($sql);

    }

    public function taskSendPasaportes()
    {
        global $config;

        if (false === $this->pasaportes) {
            return false;
        }

        $i = 0;

        $logs = array();

        foreach ($this->pasaportes as $key) {

            $pte = array(
                "pte" => ($key['MV'] == 'SI' ? "MV: " : "") . $key['NOMBRE'],
                "fechaAdm" => $key['FECHA_ADMISION'],
                "numAdm" => $key['ADM'],
                "hab" => $key['HABITACION'],
                "nhc" => $key['HC'],
                "esp" => '',
                'url' => '',
                "medicoTratante" => $key['NOMBRE_MEDICO'],
            );

            if (is_null($key['COD_DPTO'])) {

                $pte['esp'] = 'N/D';

            }

            // PARA cirugía 11 !== IMAGENOLOGIA
            if ($key['COD_DPTO'] == '11' && $key['ESPECIALIDAD'] === 'ORTOPEDIA Y TRAUMATOLOGIA') {

                $pte['esp'] = 'TRAUMATOLOGÍA Y ORTOPEDIA';

            }

            // PARA cirugía 11
            if ($key['COD_DPTO'] == '11' && $key['ESPECIALIDAD'] !== 'ORTOPEDIA Y TRAUMATOLOGIA') {

                $pte['esp'] = 'CIRUGÍA';

            }

            // PARA MEDICINA INTERNA // IMAGENOLOGIA
            if ($key['COD_DPTO'] == '12' && $key['ESPECIALIDAD'] === 'IMAGEN') {

                $pte['esp'] = 'IMAGENOLOGÍA';

            }

            // PARA MEDICINA INTERNA !== IMAGEN
            if ($key['COD_DPTO'] == '12' && $key['ESPECIALIDAD'] !== 'IMAGEN') {

                $pte['esp'] = 'MEDICINA INTERNA';

            }

            // PARA GINECOLOGIA 13
            if ($key['COD_DPTO'] == '13') {

                $pte['esp'] = 'GINECOLOGÍA Y OBSTETRICIA';

            }

            // PARA PEDIATRIA 14
            if ($key['COD_DPTO'] == '14') {

                $pte['esp'] = 'PEDIATRÍA';

            }

            // Para Pisos
            $piso = $key['HABITACION'];

            if ($piso !== null) {

                $parsePiso = explode(' ', $piso);

                if (strpos($parsePiso[0], 'C2')) {

                    if ($piso == 'C2') {
                        $pte['url'] = $this->encargadasC2;
                        $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                        $this->actualizarRegistroPasaporte($pte);
                    }

                } else if (strpos($parsePiso[0], 'U.C.I.')) {

                    if ($piso == 'U.C.I.') {
                        $pte['url'] = $this->encargadasPB;
                        $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                        $this->actualizarRegistroPasaporte($pte);
                    }

                } else {

                    $piso = (int) $parsePiso[0];

                    // PB
                    if ($piso <= 34) {
                        $pte['url'] = $this->encargadasPB;
                        $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                        $this->actualizarRegistroPasaporte($pte);
                    } else if ($piso >= 36 && $piso <= 55) {
                        $pte['url'] = $this->encargadasCOVID;
                        $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                        $this->actualizarRegistroPasaporte($pte);
                    } else if ($piso >= 56 && $piso <= 99) {
                        $pte['url'] = $this->encargadasPB;
                        $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                        $this->actualizarRegistroPasaporte($pte);
                    } else if ($piso >= 100 && $piso <= 199) {
                        $pte['url'] = $this->encargadasH1;
                        $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                        $this->actualizarRegistroPasaporte($pte);
                    } else if ($piso >= 200 && $piso <= 299) {
                        $pte['url'] = $this->encargadasH2;
                        $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                        $this->actualizarRegistroPasaporte($pte);
                    } else if ($piso >= 400 && $piso <= 499) {
                        $pte['url'] = $this->encargadasC2;
                        $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                        $this->actualizarRegistroPasaporte($pte);
                    }

                }

            }

            $logs[] = $pte;

            $i++;

        }

        return $logs;

    }

    public function taskSendPasaportesReingresoBeta()
    {
        global $config;

        if (false === $this->pasaportes) {
            return false;
        }

        $i = 0;

        $logs = array();

        foreach ($this->pasaportes as $key) {

            $pte = array(
                "pte" => ($key['MV'] == 'SI' ? "MV: " : "") . $key['NOMBRE'],
                "fechaAdm" => $key['FECHA_ADMISION'],
                "numAdm" => $key['ADM'],
                "hab" => $key['HABITACION'],
                "nhc" => $key['HC'],
                "esp" => '',
                'url' => '',
                "medicoTratante" => $key['NOMBRE_MEDICO'],
                'idPasaporte' => $key['idPasaporte'],
            );

            if (is_null($key['COD_DPTO'])) {

                $pte['esp'] = 'N/D';

            }

            // PARA cirugía 11 !== IMAGENOLOGIA
            if ($key['COD_DPTO'] == '11' && $key['ESPECIALIDAD'] === 'ORTOPEDIA Y TRAUMATOLOGIA') {

                $pte['esp'] = 'TRAUMATOLOGÍA Y ORTOPEDIA';

            }

            // PARA cirugía 11
            if ($key['COD_DPTO'] == '11' && $key['ESPECIALIDAD'] !== 'ORTOPEDIA Y TRAUMATOLOGIA') {

                $pte['esp'] = 'CIRUGÍA';

            }

            // PARA MEDICINA INTERNA // IMAGENOLOGIA
            if ($key['COD_DPTO'] == '12' && $key['ESPECIALIDAD'] === 'IMAGEN') {

                $pte['esp'] = 'IMAGENOLOGÍA';

            }

            // PARA MEDICINA INTERNA !== IMAGEN
            if ($key['COD_DPTO'] == '12' && $key['ESPECIALIDAD'] !== 'IMAGEN') {

                $pte['esp'] = 'MEDICINA INTERNA';

            }

            // PARA GINECOLOGIA 13
            if ($key['COD_DPTO'] == '13') {

                $pte['esp'] = 'GINECOLOGÍA Y OBSTETRICIA';

            }

            // PARA PEDIATRIA 14
            if ($key['COD_DPTO'] == '14') {

                $pte['esp'] = 'PEDIATRÍA';

            }

            // Para Pisos
            $piso = $key['HABITACION'];

            if ($piso !== null) {

                $parsePiso = explode(' ', $piso);

                if (strpos($parsePiso[0], 'C2')) {

                    if ($piso == 'C2') {
                        $pte['url'] = $this->encargadasC2;
                        $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                        $this->actualizarRegistroPasaporte($pte);
                    }

                } else if (strpos($parsePiso[0], 'U.C.I.')) {

                    if ($piso == 'U.C.I.') {
                        $pte['url'] = $this->encargadasPB;
                        $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                        $this->actualizarRegistroPasaporte($pte);
                    }

                } else {

                    $piso = (int) $parsePiso[0];

                    // PB
                    if ($piso <= 34) {
                        $pte['url'] = $this->encargadasPB;
                        $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                        $this->actualizarRegistroPasaporte($pte);
                    } else if ($piso >= 36 && $piso <= 55) {
                        $pte['url'] = $this->encargadasCOVID;
                        $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                        $this->actualizarRegistroPasaporte($pte);
                    } else if ($piso >= 56 && $piso <= 99) {
                        $pte['url'] = $this->encargadasPB;
                        $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                        $this->actualizarRegistroPasaporte($pte);
                    } else if ($piso >= 100 && $piso <= 199) {
                        $pte['url'] = $this->encargadasH1;
                        $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                        $this->actualizarRegistroPasaporte($pte);
                    } else if ($piso >= 200 && $piso <= 299) {
                        $pte['url'] = $this->encargadasH2;
                        $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                        $this->actualizarRegistroPasaporte($pte);
                    } else if ($piso >= 400 && $piso <= 499) {
                        $pte['url'] = $this->encargadasC2;
                        $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                        $this->actualizarRegistroPasaporte($pte);
                    }

                }

            }

            $logs[] = $pte;

            $i++;

        }

        return $logs;

    }

    public function taskSendPasaportesBeta()
    {
        global $config;

        if (false === $this->pasaportes) {
            return false;
        }

        $i = 0;

        $logs = array();

        foreach ($this->pasaportes as $key) {

            $pte = array(
                "pte" => ($key['MV'] == 'SI' ? "MV: " : "") . $key['NOMBRE'],
                "fechaAdm" => $key['FECHA_ADMISION'],
                "numAdm" => $key['ADM'],
                "hab" => $key['HABITACION'],
                "nhc" => $key['HC'],
                "esp" => '',
                'url' => '',
                "medicoTratante" => $key['NOMBRE_MEDICO'],
            );

            // PARA cirugía 11 !== IMAGENOLOGIA
            if ($key['COD_DPTO'] == '11' && $key['ESPECIALIDAD'] === 'ORTOPEDIA Y TRAUMATOLOGIA') {

                $pte['esp'] = 'TRAUMATOLOGÍA Y ORTOPEDIA';

            }

            // PARA cirugía 11
            if ($key['COD_DPTO'] == '11' && $key['ESPECIALIDAD'] !== 'ORTOPEDIA Y TRAUMATOLOGIA') {

                $pte['esp'] = 'CIRUGÍA';

            }

            // PARA MEDICINA INTERNA // IMAGENOLOGIA
            if ($key['COD_DPTO'] == '12' && $key['ESPECIALIDAD'] === 'IMAGEN') {

                $pte['esp'] = 'IMAGENOLOGÍA';

            }

            // PARA MEDICINA INTERNA !== IMAGEN
            if ($key['COD_DPTO'] == '12' && $key['ESPECIALIDAD'] !== 'IMAGEN') {

                $pte['esp'] = 'MEDICINA INTERNA';

            }

            // PARA GINECOLOGIA 13
            if ($key['COD_DPTO'] == '13') {

                $pte['esp'] = 'GINECOLOGÍA Y OBSTETRICIA';

            }

            // PARA PEDIATRIA 14
            if ($key['COD_DPTO'] == '14') {

                $pte['esp'] = 'PEDIATRÍA';

            }

            // Para Pisos
            $piso = $key['HABITACION'];

            if ($piso !== null) {

                $parsePiso = explode(' ', $piso);

                $piso = (int) $parsePiso[0];

                // PB
                if ($piso <= 34) {
                    $pte['url'] = $this->encargadasPB;
                    $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                    $this->actualizarRegistroPasaporte($pte);
                } else if ($piso >= 36 && $piso <= 55) {
                    $pte['url'] = $this->encargadasCOVID;
                    $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                    $this->actualizarRegistroPasaporte($pte);
                } else if ($piso >= 56 && $piso <= 99) {
                    $pte['url'] = $this->encargadasPB;
                    $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                    $this->actualizarRegistroPasaporte($pte);
                } else if ($piso >= 100 && $piso <= 199) {
                    $pte['url'] = $this->encargadasH1;
                    $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                    $this->actualizarRegistroPasaporte($pte);

                } else if ($piso >= 200 && $piso <= 299) {
                    $pte['url'] = $this->encargadasH2;
                    $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                    $this->actualizarRegistroPasaporte($pte);

                } else if ($piso >= 400 && $piso <= 499) {
                    $pte['url'] = $this->encargadasC2;
                    $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                    $this->actualizarRegistroPasaporte($pte);
                } else if ($piso == 'U.C.I.') {
                    $pte['url'] = $this->encargadasPB;
                    $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                    $this->actualizarRegistroPasaporte($pte);
                }

            }

            $logs[] = $pte;

            $i++;

        }

        return $logs;

    }

    public function sendDataEnfermeras($dataPte = array())
    {

        global $config;

        $stringData = $dataPte;

        $data = json_encode($stringData, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $dataPte['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json',
        ));

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return false;
            $resultobj = curl_error($ch);
        }
        curl_close($ch);
        $resultobj = json_decode($result);

        return true;

    }

    public function sendDataEnfermerasReingreso($dataPte = array())
    {

        global $config;

        $stringData = $dataPte;

        $data = json_encode($stringData, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $dataPte['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json',
        ));

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return false;
            $resultobj = curl_error($ch);
        }
        curl_close($ch);
        $resultobj = json_decode($result);

        return true;

    }

    public function sendDataMedicos($dataPte = array())
    {

        global $config;

        $stringData = $dataPte;

        $data = json_encode($stringData, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $dataPte['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json',
        ));

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return false;
            $resultobj = curl_error($ch);
        }
        curl_close($ch);
        $resultobj = json_decode($result);

        return true;

    }

    public function getStatusPasaportePaciente($dataPte = array())
    {

        global $config;

        $stringData = $dataPte;

        # return $stringData;

        $data = json_encode($stringData, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://prod-107.westus.logic.azure.com:443/workflows/3a79ed4054a946b1b1b75899f60dcc51/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=tglD--1ZtEzte_7nfuf14radsctt5TNoNZ4YZcwj7-s');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json',
        ));

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return false;
            $resultobj = curl_error($ch);
        }
        curl_close($ch);

        $resultobj = json_decode($result);

        $count = count((array) $resultobj->value);

        if (isset($resultobj->value) && $count !== 0) {

            return array('status' => true, 'data' => $resultobj->value);
        }

        return array('status' => false, 'data' => []);

    }

    /**
     * __construct()
     */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);

    }
}
