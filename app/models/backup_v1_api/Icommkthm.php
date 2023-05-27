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
use DateTime;
use Doctrine\DBAL\DriverManager;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;
use SoapClient;

/**
 * Modelo Odbc GEMA -> Icommkthm _> Integracion HM Icommkt
 */

class Icommkthm extends Models implements IModels
{

    # Variables de clase
    private $sortField   = 'ROWNUM_';
    private $filterField = null;
    private $sortType    = 'desc'; # desc
    private $offset      = 1;
    private $limit       = 25;
    private $startDate   = null;
    private $endDate     = null;
    private $_conexion   = null;
    private $apikey      = 'ODk4LTIwNDgtaG9zcGl0YWxtZXRlYw2';
    private $username    = 'hospitalmetec';

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

    }

    private function getAuthorizationn($value)
    {

        try {

            global $http;

            $token = $http->headers->get("Authorization");

            $auth = new Model\Auth;
            $key  = $auth->GetData($token);

            $this->val = $key->data[0]->$value;

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function errorsPagination()
    {

        try {

            if ($this->limit > 25) {
                throw new ModelsException('!Error! Solo se pueden mostrar 100 resultados por página.');
            }

            if ($this->limit == 0 or $this->limit < 0) {
                throw new ModelsException('!Error! {Limit} no puede ser 0 o negativo');
            }

            if ($this->offset == 0 or $this->offset < 0) {
                throw new ModelsException('!Error! {Offset} no puede ser 0 o negativo.');
            }

            return false;
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function setParameters_TEST()
    {

        try {

            global $http;

            foreach ($http->request->all() as $key => $value) {
                $this->$key = $value;
            }

            if ($this->startDate != null and $this->endDate != null) {

                $startDate = $this->startDate;
                $endDate   = $this->endDate;

                $sd = new DateTime($startDate);
                $ed = new DateTime($endDate);

                if ($sd->getTimestamp() > $ed->getTimestamp()) {
                    throw new ModelsException('!Error! Fecha inicial no puede ser mayor a fecha final.');
                }

            }

            if ($this->sortCategory != null) {

                # Si es especialidades en ingles y hace match en array devolver correjido valor
                $espe = $this->buscarEspecialidad(mb_strtoupper($this->sortCategory));

                if ($this->lang == 'en') {
                    if (false != $espe) {
                        $this->sortCategory = mb_strtoupper($this->sanear_string($espe));
                    } else {
                        $this->sortCategory = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->sortCategory), 'UTF-8'));
                    }
                } else {
                    $this->sortCategory = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->sortCategory), 'UTF-8'));

                }

            }

            if ($this->searchField != null) {

                # Si es especialidades en ingles y hace match en array devolver correjido valor
                $espe = $this->buscarEspecialidad(mb_strtoupper($this->searchField));

                if ($this->lang == 'en') {

                    if (false != $espe) {
                        $this->searchField = mb_strtoupper($this->sanear_string($espe));
                    } else {
                        $this->searchField = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->searchField), 'UTF-8'));
                    }

                } else {
                    $this->searchField = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->searchField), 'UTF-8'));

                }

            }

            return false;
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function setParameters()
    {

        try {

            global $http;

            foreach ($http->request->all() as $key => $value) {
                $this->$key = $value;
            }

            if ($this->startDate != null and $this->endDate != null) {

                $startDate = $this->startDate;
                $endDate   = $this->endDate;

                $sd = new DateTime($startDate);
                $ed = new DateTime($endDate);

                if ($sd->getTimestamp() > $ed->getTimestamp()) {
                    throw new ModelsException('!Error! Fecha inicial no puede ser mayor a fecha final.');
                }

            }

            if ($this->sortCategory != null) {

                $this->sortCategory = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->sortCategory), 'UTF-8'));

            }

            if ($this->searchField != null) {

                $this->searchField = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->searchField), 'UTF-8'));

            }

            return false;
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    public function createPieza()
    {
        try {

            $client = new SoapClient('http://demo.pe.icommarketing.com/Newsletters.asmx?WSDL',
                array(
                    "encoding"   => "ISO-8859-1",
                    'wsdl_cache' => 0,
                    'trace'      => 1,
                ));

        } catch (SoapFault $fault) {
            return '<h2>Constructor error</h2><pre>' . $fault->faultstring . '</pre>';
        }

        $html = 'HTML';

        $html = utf8_decode($html);

        $param = array(
            "ApiKey"         => $this->apikey,
            "UserName"       => $this->username,
            "Campaign"       => "NEWSLATTER HM",
            "NewsletterName" => "DEMO API",
            "Content"        => $html,
            "PlainText"      => "test",
        );

        try {
            $result = $client->__soapCall("CreateHTML", array($param));
        } catch (Exception $e) {}

        return '<h2>Response</h2><pre>' . htmlspecialchars($client->__getLastResponse(), ENT_QUOTES) . '</pre>';

    }

    public function contactIcommktEncuestas(array $pte): array
    {
        $apiKey     = $this->apikey;
        $profileKey = 'MjM1Njcz0';
        $stringData = array(
            'ProfileKey' => $profileKey,
            'Contact'    => array(
                'Email'        => $pte['EMAIL'],
                'CustomFields' => array(
                    array('Key' => 'titulo', 'Value' => $pte['TITULO']),
                    array('Key' => 'pte', 'Value' => $pte['PTE']),
                    array('Key' => 'fecha', 'Value' => $pte['FECHA_ALTA_CLINICA']),
                    array('Key' => 'atencion', 'Value' => $pte['ATENCION']),
                    array('Key' => 'status', 'Value' => $pte['STATUS_API']),
                ),
            ),
        );

        $data = json_encode($stringData);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.icommarketing.com/Contacts/SaveContact.Json/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            'Authorization: ' . $apiKey . ':0',
            'Access-Control-Allow-Origin: *')
        );

        $result = curl_exec($ch);
        curl_close($ch);
        $resultobj = json_decode($result);

        return array('res' => $resultobj);
        // print_r($resultobj->{'SaveContactJsonResult'}->{'StatusCode'});
    }

/**
 * __construct()
 */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);

    }
}
