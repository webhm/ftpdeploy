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
use SoapClient;

/**
 * Modelo Ebilling
 */
class Ebilling extends Models implements IModels
{

    /**
     * Variables privadas
     * @return void
     */

    # Solicitar Facturas e-billing
    final public function erroresFactura($tipo, $doc)
    {

        try {

            $client = new SoapClient('https://hmetropolitano.e-custodia.com.ec/ebillingV3.Consultas/ConsultaDocumento.asmx?wsdl',
                array(
                    # "encoding"     => "ISO-8859-1",
                    'wsdl_cache'   => 0,
                    'trace'        => true,
                    'soap_version' => SOAP_1_2,
                ));

            $fac = explode('-', $doc);

            $docs = $client->wsConsultaDocumento(array(
                "RucEmpresa"      => "1790412113001",
                "TipoDocumento"   => (string) $tipo,
                "Establecimiento" => (string) $fac[0],
                "PtoEmision"      => (string) $fac[1],
                "Secuencial"      => (string) $fac[2],
                "NombreArchivo"   => "?",
            ));

            // Retorna documento en base 64
            if ($docs->Estado == 5) {
                return true;
            } else {
                return array('success' => false, 'message' => $docs->Detalle);
            }

            return false;

        } catch (SoapFault $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }

    }

    # Solicitar Facturas e-billing
    final public function getFactura($tipo, $doc)
    {

        # Control de errores soap facturacion
        $error = $this->erroresFactura($tipo, $doc);
        if (!is_bool($error)) {
            return $error;
        }

        $client = new SoapClient('https://hmetropolitano.e-custodia.com.ec/ebillingV3.Consultas/ConsultaDocumento.asmx?wsdl',
            array(
                # "encoding"     => "ISO-8859-1",
                'wsdl_cache'   => 0,
                'trace'        => true,
                'soap_version' => SOAP_1_2,
            ));

        $fac = explode('-', $doc);

        $docs = $client->wsConsultaDocumentoPDF(array(
            "RucEmpresa"      => "1790412113001",
            "TipoDocumento"   => (string) $tipo,
            "Establecimiento" => (string) $fac[0],
            "PtoEmision"      => (string) $fac[1],
            "Secuencial"      => (string) $fac[2],
            "NombreArchivo"   => "?",
        ));

        // Retorna documento en base 64

        $factura = array(

            'FACT' => 'RIDE_Factura_' . $doc,
            '_PDF' => base64_encode($docs->ArchivoPDF),
            '_XML' => base64_encode($docs->wsConsultaDocumentoPDFResult),
            //'data' => $fac,
            //'tipo' => $tipo,
        );

        return array(
            'status' => true,
            'data'   => $factura,
        );

    }

    # Solicitar Facturas e-billing
    final public function getFacturaDes()
    {

        // 186.101.111.61

        $client = new SoapClient('https://hmetropolitano.e-custodia.com.ec/ebillingV3.Consultas/ConsultaDocumento.asmx?wsdl',
            array(
                "encoding"     => "ISO-8859-1",
                'wsdl_cache'   => 0,
                'trace'        => true,
                'soap_version' => SOAP_1_1,
            ));

        $docs = $client->wsConsultaDocumentoPDF(array(
            "RucEmpresa"      => "1790412113001",
            "TipoDocumento"   => "01",
            "Establecimiento" => "001",
            "PtoEmision"      => "145",
            "Secuencial"      => "000039474",
            "NombreArchivo"   => "?",
        ));

        // Retorna documento en base 64

        return $docs->ArchivoPDF;

    }

# Solicitar Facturas e-billing
    final public function getFunctions()
    {

        try
        {

            $client = new SoapClient('https://hmetropolitano.e-custodia.com.ec/ebillingV3.Consultas/ConsultaDocumento.asmx?wsdl');

            return $client->__getFunctions();

        } catch (SoapFault $fault) {
            return trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);
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
