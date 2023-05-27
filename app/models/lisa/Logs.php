<?php

/*
 * Hospital Metropolitano
 *
 */

namespace app\models\lisa;

use app\models\lisa as Model;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Logs
 */
class Logs extends Models implements IModels
{
    # Variables de clase
    public $timestamoLog = 0;
    public $typeLog = null;
    public $log = [];
    public $dirIngresados = '../../beta/v1/lisa/pedidos/ingresados/logs/';
    public $dirRetenidos = '../../beta/v1/lisa/pedidos/retenidos/logs/';
    public $dirError = '../../beta/v1/lisa/pedidos/errores/logs/';
    public $dirEnviados = '../../beta/v1/lisa/pedidos/enviados/logs/';

    public function nuevoLog()
    {
        global $config;

        #Crear un nuevo Log del Sistema segun el tipo

        # Log de Error de App
        if ($this->typeLog == 1) {

            file_put_contents($this->dirError . $this->log['idLog'] . '.json', json_encode($this->log), LOCK_EX);
            chmod($this->dirError, 0777);

        }

        # Log de Error Php
        if ($this->typeLog == 0) {

            file_put_contents($this->dirError . $this->log['idLog'] . '.json', json_encode($this->log), LOCK_EX);
            chmod($this->dirError, 0777);

        }

        # Log de Ingreso
        if ($this->typeLog == 2) {

            file_put_contents($this->dirIngresados . $this->log['idLog'] . '.json', json_encode($this->log), LOCK_EX);
            chmod($this->dirIngresados, 0777);

        }

        # Log de Retenidos
        if ($this->typeLog == 3) {

            file_put_contents($this->dirRetenidos . $this->log['idLog'] . '.json', json_encode($this->log), LOCK_EX);
            chmod($this->dirRetenidos, 0777);

        }

        # Log de Enviados
        if ($this->typeLog == 4) {

            file_put_contents($this->dirEnviados . $this->log['idLog'] . '.json', json_encode($this->log), LOCK_EX);
            chmod($this->dirRetenidos, 0777);

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
