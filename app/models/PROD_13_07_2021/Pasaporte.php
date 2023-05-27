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
    private $encargadasPB = "https://prod-44.westus.logic.azure.com:443/workflows/2e4e20051ace4e26befb84af86d77351/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=GcPkDzSxvvSAUpY7t6AYe1HsUNFJf0lMbnMTAuJF3Bc";
    private $encargadasH1 = "https://prod-33.westus.logic.azure.com:443/workflows/0cd7e7d45b95413b938df23e8399cf62/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=nV1t0z69PnyuWN3C-NruzbBRA5p66G2T9w9joTxsVaw";
    private $encargadasH2 = "https://prod-179.westus.logic.azure.com:443/workflows/b6a21b182667413c9647660afa9c0bba/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=VpejijvJANPMwAcS0_aq4lo-zL1xylfCIIZRajsHPZ8";
    private $encargadasC2 = "https://prod-108.westus.logic.azure.com:443/workflows/1dec0a538ff5454e93d9444a9abcf0d9/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=lmHJmUVfQPR908O6VTS-xPIyZib4zvXOtJ0Lq6lVmPM";

    private $medicinaInterna = "https://prod-185.westus.logic.azure.com:443/workflows/d8b2fbf2585642f4a1b45856d96af39e/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=QlGprhOYRtQvj8-HwGLmp7XmkQZSZdn1Mfwyc7x_4BQ";
    private $medicinaCirugia = "https://prod-191.westus.logic.azure.com:443/workflows/6acaa5caa7cf42c2bdd2f3f95ad55885/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=XZrLhbd3Q3y8f8hSjDZO2kYDPKTpqkG2FUD2NY0fo-I";
    private $medicinaGinecologia = "https://prod-20.westus.logic.azure.com:443/workflows/6dc4f714179e4998a85928ece1f4a024/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=uqckvGjXxpWBfrl1UuoDrUX-a85Cfbf55w-7HcfP6do";
    private $medicinaImagenologia = "https://prod-67.westus.logic.azure.com:443/workflows/6c1db97f5de04954934e94f1fbd58287/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=59IkTFlXM_g74jHnvaRJY7utfIT-RKWFUvuyy_ktvR0";
    private $medicinaPediatria = "https://prod-84.westus.logic.azure.com:443/workflows/4a9e9162031449439733e72ffd6d6739/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=KaJUehQwW2qltSyWmCK09q14VcBDmWb6GKshJJhUepc";
    private $medicinaTraunmato = "https://prod-168.westus.logic.azure.com:443/workflows/951a3b8922404caf83ceae698379eccf/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=diL0r3n9wE64b2qz0tZSotWubXtIcOBTOOAOnB9ofDI";

    /**
     * Genera datos de Pasaporte
     *
     * @return array : Con información de éxito/falla al terminar el proceso.
     */
    public function taskPasaporte()
    {
        try {

            $sql = " SELECT * FROM cad_vw_encuesta_planetree WHERE SN_ENCUESTA='N' ";

            # Conectar base de datos
            $this->conectar_Oracle();

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

                $this->pasaportes[] = $data;

                $taskSendPasaportes = $this->taskSendPasaportes();

                return $taskSendPasaportes;

            }

        } catch (ModelsException $e) {

            return $e;

        }
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
            ->setValue('fecha_adm', '?')
            ->setValue('sn_encuesta', '?')
            ->setParameter(0, $pte['nhc'])
            ->setParameter(1, $pte['numAdm'])
            ->setParameter(2, $pte['fechaAdm'])
            ->setParameter(3, 'S')

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
                "pte" => $key['NOMBRE'],
                "fechaAdm" => $key['FECHA_ADMISION'],
                "numAdm" => $key['ADM'],
                "hab" => (!is_null($key['HABITACION']) ? $key['HABITACION'] : 'N/D'),
                "nhc" => $key['HC'],
                "esp" => '',
                'url' => '',
            );

            // PARA cirugía 11 !== IMAGENOLOGIA
            if ($key['COD_DPTO'] == '11' && $key['ESPECIALIDAD'] === 'ORTOPEDIA Y TRAUMATOLOGIA') {

                $pte['esp'] = 'TRAUMATOLOGÍA Y ORTOPEDIA';
                $pte['url'] = $this->medicinaTraunmato;
                $sendDataMedicos = $this->sendDataMedicos($pte);

            }

            // PARA cirugía 11
            if ($key['COD_DPTO'] == '11' && $key['ESPECIALIDAD'] !== 'ORTOPEDIA Y TRAUMATOLOGIA') {

                $pte['esp'] = 'CIRUGÍA';
                $pte['url'] = $this->medicinaCirugia;
                $sendDataMedicos = $this->sendDataMedicos($pte);

            }

            // PARA MEDICINA INTERNA // IMAGENOLOGIA
            if ($key['COD_DPTO'] == '12' && $key['ESPECIALIDAD'] === 'IMAGEN') {

                $pte['esp'] = 'IMAGENOLOGÍA';
                $pte['url'] = $this->medicinaImagenologia;
                $sendDataMedicos = $this->sendDataMedicos($pte);

            }

            // PARA MEDICINA INTERNA !== IMAGEN
            if ($key['COD_DPTO'] == '12' && $key['ESPECIALIDAD'] !== 'IMAGEN') {

                $pte['esp'] = 'MEDICINA INTERNA';
                $pte['url'] = $this->medicinaInterna;
                $sendDataMedicos = $this->sendDataMedicos($pte);

            }

            // PARA GINECOLOGIA 13
            if ($key['COD_DPTO'] == '13') {

                $pte['esp'] = 'GINECOLOGÍA Y OBSTETRICIA';
                $pte['url'] = $this->medicinaGinecologia;
                $sendDataMedicos = $this->sendDataMedicos($pte);

            }

            // PARA PEDIATRIA 14
            if ($key['COD_DPTO'] == '14') {

                $pte['esp'] = 'PEDIATRÍA';
                $pte['url'] = $this->medicinaPediatria;
                $sendDataMedicos = $this->sendDataMedicos($pte);

            }

            // Para Pisos
            $piso = (!is_null($key['HABITACION']) ? $key['HABITACION'] : null);

            if ($piso !== null) {

                $parsePiso = explode(' ', $piso);

                $piso = (int) $parsePiso[0];

                // PB
                if ($piso <= 99) {
                    $pte['url'] = $this->encargadasPB;
                    $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                    $this->actualizarRegistroPasaporte($pte);

                }

                // H1
                if ($piso >= 100 && $piso <= 199) {
                    $pte['url'] = $this->encargadasH1;
                    $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                    $this->actualizarRegistroPasaporte($pte);

                }

                // H2
                if ($piso >= 200 && $piso <= 299) {
                    $pte['url'] = $this->encargadasH2;
                    $sendDataEnfermeras = $this->sendDataEnfermeras($pte);
                    $this->actualizarRegistroPasaporte($pte);

                }

                // C2
                if ($piso >= 400 && $piso <= 499) {
                    $pte['url'] = $this->encargadasC2;
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

    /**
     * __construct()
     */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);

    }
}
