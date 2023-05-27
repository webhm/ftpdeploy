<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models\higienizacion;

use app\models\higienizacion as Model;
use Exception;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;
use SoapClient;

/**
 * Modelo Tasks MV - Higienizacion
 */
class Tasks extends Models implements IModels
{
    # Variables de clase
    private $pstrSessionKey = 0;
    private $USER = null;
    private $sortField = 'ROWNUM_';
    private $sortType = 'desc'; # desc
    private $start = 1;
    private $length = 10;
    private $searchField = null;
    private $startDate = null;
    private $endDate = null;
    private $tresMeses = null;
    private $urlPDF = 'https://api.hospitalmetropolitano.org/';

    # Send Estado Cambio de Cama => MV
    public function sendCambiaStatusCama()
    {

        try {

            global $config, $http;

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'WebservicePadrao.xml', array(
                'soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,

            ));

            $Preview = $client->Mensagem(array(
                'Cabecalho' => array(
                    'mensagemID' => "20210106153205",
                    'versaoXML' => "1",
                    'identificacaoCliente' => "VOICE",
                    'servico' => 'TUALIZA_STATUS_LEITO',
                    'dataHora' => '2022-05-31 ' . date("H:i:s"),
                    'empresaOrigem' => "1",
                    'sistemaOrigem' => 'COMPETENCIA',
                    'empresaDestino' => '1',
                    'sistemaDestino' => 'MV',
                    'usuario' => '',
                    'senha' => '',
                ),
                'atualizaLeito' => array(
                    'unidade' => '2',
                    'unidadeDePara' => '',
                    'descUnidade' => '',
                    'leito' => '588',
                    'leitoDePara' => '',
                    'descLeito' => '',
                    'descLeitoResumido' => '',
                    'statusLeito' => 'EH',
                    'statusLeitoDePara' => '',
                    'ramal' => '0',

                ),
            ));

            $xml = simplexml_load_string($Preview);
            $json = json_encode($xml);
            $array = json_decode($json, true);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $array,
            );

        } catch (\Exception $b) {

            return array('status' => false, 'data' => [], 'message' => $b->getMessage());

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

        }

    }

    public function trackHigienizacion()
    {
        global $config, $http;

        $cama = $http->request->get("habitacion");
        $stado = $http->request->get("status");

        return $this->sendCambiaStatusCama_v2($cama, $stado);

    }

    # Send Estado Cambio de Cama => MV => v2
    public function sendCambiaStatusCama_v2($habitacion = "", $stado = "")
    {

        try {

            global $config, $http;

            $code_reg = date("YmdHis");

            $fechaHora = date("Y-m-d H:i:s");

            $webservice_url = "http://172.16.253.11:8184/jintegra-core/services/WebservicePadrao";

            $request_param = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:core="http://core.integracao.mv.com.br">
            <soapenv:Header/>
            <soapenv:Body>
               <core:mensagem>
               <![CDATA[
               <Mensagem>
                 <Cabecalho>
                     <mensagemID>' . $code_reg . '</mensagemID>
                     <versaoXML/>
                     <identificacaoCliente>1</identificacaoCliente>
                     <servico>ATUALIZA_STATUS_LEITO</servico>
                     <dataHora>' . $fechaHora . '</dataHora>
                     <empresaOrigem>1</empresaOrigem>
                     <sistemaOrigem>COMPETENCIA</sistemaOrigem>
                     <empresaDestino>1</empresaDestino>
                     <sistemaDestino>MV</sistemaDestino>
                     <usuario/>
                     <senha/>
                 </Cabecalho>
                 <atualizaLeito>
                     <unidade>1</unidade>
                     <unidadeDePara/>
                     <descUnidade/>
                     <leito>' . $habitacion . '</leito>
                     <leitoDePara/>
                     <descLeito/>
                     <descLeitoResumido/>
                     <statusLeito>' . $stado . '</statusLeito>
                     <statusLeitoDePara/>
                     <ramal>0</ramal>
                 </atualizaLeito>
             </Mensagem>
               ]]>
               </core:mensagem>
            </soapenv:Body>
         </soapenv:Envelope>';

            $headers = array(
                'Content-Type: text/xml; charset=utf-8',
                'Content-Length: ' . strlen($request_param),
            );

            $ch = curl_init($webservice_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request_param);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $data = curl_exec($ch);

            $result = $data;

            if ($result === false) {

                return array(
                    'status' => false,
                    'data' => [],
                    'message' => "CURL error (#%d): %s<br>\n" . curl_errno($ch) . htmlspecialchars(curl_error($ch))
                );

            }

            curl_close($ch);

            return array(
                'status' => true,
                'data' => $result,
                'message' => 'Proceso realizado con éxito.',
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage());

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
