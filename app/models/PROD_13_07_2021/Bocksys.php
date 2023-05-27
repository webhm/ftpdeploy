<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.

SELECT
TO_CHAR(A_US_APPOINTMENT.APPOINTMENT_START , 'DD/MM/YYYY hh24:mi') AS FECHA_EXAMEN,
A_US_PATIENT_BASIC.PAT_LAST_NAME AS APELLIDOS,
A_US_PATIENT_BASIC.PAT_FIRST_NAME AS NOMBRES,
A_US_PATIENT_BASIC.PAT_TELEPHONE AS TELEFONO,
A_US_PATIENT_BASIC.PAT_TELEPHONE_MOBILE AS CELULAR,
A_US_APPOINTMENT.APPOINTMENT_BOOK AS AREA
FROM MEDORA.A_US_APPOINTMENT A_US_APPOINTMENT, MEDORA.A_US_PATIENT_BASIC A_US_PATIENT_BASIC
WHERE A_US_APPOINTMENT.PATIENT_KEY = A_US_PATIENT_BASIC.PATIENT_KEY
AND to_char(A_US_APPOINTMENT.APPOINTMENT_START, 'DD/MM/YYYY hh24:mi:ss')>= '26/06/2019 00:00:00'
AND to_char(A_US_APPOINTMENT.APPOINTMENT_START, 'DD/MM/YYYY hh24:mi:ss')<= '26/06/2019 23:59:59'
AND (A_US_APPOINTMENT.APPOINTMENT_BOOK LIKE 'AE%' OR A_US_APPOINTMENT.APPOINTMENT_BOOK LIKE 'HM%')

 */

namespace app\models;

use app\models as Model;
use Doctrine\DBAL\DriverManager;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Bocksys
 */

class Bocksys extends Models implements IModels
{

    private $sortField = 'ROWNUM';
    private $sortType = 'desc'; # desc
    private $offset = 1;
    private $limit = 25;
    private $mensaje_recordatorio_imagen = 'Le recordamos su cita el %dia% a las %hora%. En %area%. Gracias por preferirnos. Hospital Metropolitano. ';

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['buxys'], $_config);

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

    public function getColaboradores()
    {

        try {

            # CONULTA BDD GEMA
            $sql = " SELECT * FROM VINTRANET WHERE EMPRESA = 'HOSPITAL METROPOLITANO' OR EMPRESA = 'METRORED' ";

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # Cerrar conexion
            $this->_conexion->close();

            # Datos
            $data = $stmt->fetchAll();

            if (count($data) == 0) {
                throw new ModelsException('No existe resultados.', 4080);
            }

            # Dta de Citas
            $colaboradores = array();

            foreach ($data as $key) {
                $colaboradores[] = array(
                    'EMPRESA' => $key['EMPRESA'],
                    'CEDULA' => $key['CEDIDE_MF'],
                    'NOMBRE' => $key['NOMFAV_MF'],
                    'FECHA_NACIMIENTO' => $key['FECNAC_MF'],
                    'AREA' => $key['AREA'],
                    'CARGO' => $key['CARGO'],
                    'EMAIL' => $key['EMAIL_MF'],
                    'EXT' => $key['FAX_MF'],

                );
            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $colaboradores,
                'total' => count($colaboradores),
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

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
