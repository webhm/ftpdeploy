<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models\medicos;

use app\models\medicos as Model;
use Doctrine\DBAL\DriverManager;
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Logs
 */
class Logs extends Models implements IModels
{

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();

        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracleNaf'], $_config);

    }

    public function getLogs()
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../medicos/logs/medicos/update/', '.json');

            $i = 0;

            $logs = array();

            // Extraer ORDENES PARA VALIDAR
            foreach ($list as $key => $val) {

                $content = file_get_contents($val);
                $documento = json_decode($content, true);

                if ($documento['data']['medico'] !== null) {

                    $logs[] = array(
                        'medico' => $documento['data']['medico'],
                        'codMedico' => $documento['data']['codMedico'],
                        'user' => $documento['data']['user'],
                        'url' => $documento['url'],
                        'ip' => $documento['ip'],
                        'dataTime' => date('Y-m-d H:i:s', $documento['timestamp']),
                    );
                }

            }

            # Devolver Información
            return $logs;

        } catch (ModelsException $e) {

            return array();

        }

    }

    public function updateLogs()
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../medicos/logs/medicos/', '.json');

            $i = 0;

            $logs = array();

            // Extraer ORDENES PARA VALIDAR
            foreach ($list as $key => $val) {

                if (@file_get_contents($val) !== false) {

                    $content = file_get_contents($val);
                    $documento = json_decode($content, true);

                    if (isset($documento['timestamp']) && isset($documento['data']['codMedico']) && !isset($documento['data']['medico'])) {

                        if ($i == 7) {
                            throw new ModelsException('Se cunple condicion. ' . $i . ' -- ' . $val . ' -- Time:' . date('Y-m-d H:i:s', $documento['timestamp']));
                        }

                        $documento['data']['medico'] = $this->recuperarNombreMedico($documento['data']['codMedico']);

                        $logsfile = '../medicos/logs/medicos/update/log_' . $documento['timestamp'] . '.json';
                        file_put_contents(
                            $logsfile,
                            json_encode($documento)
                        );

                        $logs[] = array(
                            'codMedico' => $documento['data']['codMedico'],
                            'user' => $documento['data']['user'],
                            'url' => $documento['url'],
                            'ip' => $documento['ip'],
                            'dataTime' => date('Y-m-d H:i:s', $documento['timestamp']),
                        );

                        $i++;

                        @unlink($val);

                    } else {

                        @unlink($val);

                    }
                }

            }

            # Devolver Información
            return array('status' => true, 'data' => $logs);

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage());

        }

    }

    public function recuperarNombreMedico($codigoMedico)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Devolver todos los resultados
            $sql = " SELECT distinct mp.no_prove, mp.nombre, mp.cedula, mc.valor correos
            from arcpmp mp, bab_medios_contacto mc
            where mp.no_cia = '01'
            and mp.no_prove LIKE '%$codigoMedico'
            and mp.grupo = '88'
            and mc.pk_fk_persona = mp.codigo_persona
            and mc.fk_tipo_medio_contacto in (7)
            and mc.valor like '%@%'
            order by mp.no_prove ";

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            if ($data !== false) {
                return $data['NOMBRE'];
            }

        } catch (ModelsException $e) {

            return '';

        }

    }

    public function getLogsRecursos()
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Devolver todos los resultados
            $sql = " SELECT * from CP_BITACORA_AUD_APP where ip_acceso = '20.65.92.13' AND timestamp > '2023-02-15' order by timestamp desc ";

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            return $data;

        } catch (ModelsException $e) {

            return array();

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

        $sql = " alter session set NLS_DATE_FORMAT = 'YYYY-MM-DD' ";
        # Execute
        $stmt = $this->_conexion->query($sql);

    }

/**
 * __construct()
 */
    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
    }
}
