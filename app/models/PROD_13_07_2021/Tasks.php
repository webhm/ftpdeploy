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
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Tasks
 */
class Tasks extends Models implements IModels
{

    private $fecha = null;

    public function deleteTempMedicos()
    {

        # SETEAR FECHA
        $fecha      = date('d-m-Y');
        $nuevafecha = strtotime('-1 day', strtotime($fecha));

        # SETEAR FILTRO HASTA TRES MESES
        $this->fecha = date('d-m-Y', $nuevafecha);

        # RECORRER

        $files = Helper\Files::get_files_in_dir('../v1/premedicos/', 'json');

        $data = array();

        foreach ($files as $key) {

            $f = date('d-m-Y', strtotime(Helper\Files::date_file($key)));

            if ($f == $this->fecha) {

                # Eliminar archivos temporales

                $del = Helper\Files::delete_file($key);

                # Seteo de variables para print
                $data[] = array(
                    'file' => $key, 'del' => $del,
                );

            }

        }

        return array('status' => true, 'total' => $data);

    }

    /**
     * __construct()
     */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
    }
}
