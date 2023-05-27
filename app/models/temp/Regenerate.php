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
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Regenerate
 */
class Regenerate extends Models implements IModels
{

    /**
     * Realiza la generación de una nuevo user_token para las peticiones
     *
     * @return array : Con información de la nueva user_token éxito/falla al de la peticion.
     */
    public function generateUser_Token(): array
    {
        global $http;

        $auth               = new Model\Auth;
        $data['USER_TOKEN'] = $http->headers->get("Authorization");
        $data['APP_TOKEN']  = $http->request->get('APP_TOKEN');

        return $auth->regenerateUser_Token($data);
    }

/**
 * __construct()
 */
    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
    }
}
