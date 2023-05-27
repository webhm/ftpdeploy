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
 * Modelo ParseFOR008
 */
class ParseFOR008 extends Models implements IModels
{

    private $content = array();
    private $dataFormulario = array();
    private $customErrors = array();

    public function parseDoc($content = array())
    {

        $this->content = $content;

        $this->setParameters();

        $this->setFormulario();

        return array('status' => true, 'dataFormulario' => $this->dataFormulario, 'customErrros' => $this->customErrors, 'c' => $content);

    }

    private function setParameters()
    {

        if (!isset($this->content['formularioParameters'])) {
            $this->customErrors['Parametros'][] = 'Error en objeto (No existe): [formularioParameters]';
        }

        if (!isset($this->content['formularioPagebody'])) {
            $this->customErrors['Parametros'][] = 'Error en objeto (No existe): [formularioPagebody]';
        }

    }

    private function setFormulario()
    {

        $this->dataFormulario = array(
            'SCT_1: REGISTRO DE ADMISION' => $this->getSeccion_1($this->content['formularioPagebody']),
            'SCT_2: INICIO DE ATENCIÓN Y MOTIVO' => $this->getSeccion_2($this->content['formularioPagebody']),
            'SCT_3: ACCIDENTE, VIOLENCIA, INTOXICACIÓN ENVENENAMIENTO O QUEMADURA' => $this->getSeccion_3($this->content['formularioPagebody']),
            'SCT_4: ANTECEDENTES PERSONALES Y FAMILIARES' => $this->getSeccion_4($this->content['formularioPagebody']),

        );

    }

    private function getSeccion_1($seccion = array())
    {
        # Sección 1 REGISTRO DE PACIENTE
        $target = array(
            'tipo_paciente' => null,
            'primero_nombre_paciente' => null,
            'Primer nomb Pcte' => null,
            'segundo_nombre_paciente' => null,
            'identificacion_paciente' => null,
            'direccion_paciente' => null,
            'parroquia_atencion' => null,
            'canto_atencion' => null,
            'provincia_atencion' => null,
            'telefono_paciente' => null,
            'fecha_nacimiento_paciente' => null,
            'ciudad_nacimiento_paciente' => null,
            'pais_paciente' => null,
            'edad_años_paciente' => null,
            'estado_civil_paciente' => null,
            'instruccion_paciente' => null,
            'fecha_admision' => null,
            'barrio_paciente' => null,
            'canton_empresa' => null,
            'zona_paciente' => null,
            'grupo_cultural_paciente' => null,
            'sexo_X_paciente' => null,
            'instruccion_paciente' => null,
            'nombre_avisar_a' => null,
            'parentesco_avisar_a' => null,
            'forma_llegada' => null,
            'fuente_informacion' => null,
            'referido_de_atencion' => null,
        );

        $c = array();
        foreach ($seccion as $key => $val) {
            $c[] = $val;
        }

        $p = array();
        foreach ($c as $k => $v) {
            if (isset($v['hint']) && isset($v['answer'])) {
                $p[] = array(
                    'id' => $v['hint'],
                    'value' => $v['answer'],
                );
            }
        }

        # Validación
        $res = array();
        foreach ($target as $key1 => $val) {
            foreach ($p as $key2) {
                if ($key1 == $key2['id']) {
                    $res[] = $key2;
                    $target[$key1] = $key2['value'];
                }
            }
        }

        # Errores
        foreach ($target as $a => $b) {

            if ($b == null) {

                $this->customErrors[] = 'Error en Formulario (Campos Obligatorios): [SCT_1][' . $a . ']';
                $res[] = array(
                    'id' => $a,
                    'value' => null,
                );

            }

        }

        return $res;

    }

    private function getSeccion_2($seccion = array())
    {
        # Sección 2 INICIO DE ATENCION Y MOTIVO
        $target = array(
            'traumatologico_x' => null,
            'causa_obstetrica_x' => null,
            'causa_clinica_x' => null,
            'causa_quirurgica_x' => null,
            'Tipo sangre pac' => null,
            'custodia_policial' => null,
            'otro_motivo_atencion' => null,
        );

        $c = array();
        foreach ($seccion as $key => $val) {
            $c[] = $val;
        }

        $p = array();
        foreach ($c as $k => $v) {
            if (isset($v['hint']) && isset($v['answer'])) {
                $p[] = array(
                    'id' => $v['hint'],
                    'value' => $v['answer'],
                );
            }
        }

        # Validación
        $res = array();
        foreach ($target as $key1 => $val) {
            foreach ($p as $key2) {
                if ($key1 == $key2['id']) {
                    $res[] = $key2;
                    $target[$key1] = $key2['value'];
                }
            }
        }

        # Errores
        if ($target['traumatologico_x'] == false && $target['causa_obstetrica_x'] == false && $target['causa_clinica_x'] == false && $target['causa_quirurgica_x'] == false) {
            $this->customErrors[] = 'Error en Formulario (Campos Obligatorios): [SCT_2][Al menos un campo debe estar seleccionado Ej: Trauma]';
        }

        if ($target['otro_motivo_atencion'] == null) {
            $this->customErrors[] = 'Error en Formulario (Campos Obligatorios): [SCT_2][Otro Motivo es obligatorio]';
        }

        return $res;

    }

    private function getSeccion_3($seccion = array())
    {
        # Sección 3 ACCIDENTE, VIOLENCIA, INTOXICACIÓN ENVENENAMIENTO O QUEMADURA
        $target = array(
            'traumatologico_x' => null,
            'accidente_transito_x' => null,
            'caida_x' => null,
            'quemadura_x' => null,
            'mordedura_x' => null,
            'ahogamiento_x' => null,
            'cuerpo_extranio_x' => null,
            'aplastamiento_x' => null,
            'otro_accidente_x' => null,
            'arma_fuego_x' => null,
            'arma_c_x' => null,
            'familiar_x' => null,
            'abuso_fisico_x' => null,
            'abuso_sicologico_x' => null,
            'abuso_sexual_x' => null,
            'otra_violencia_x' => null,
            'intoxicacion_alcoholica' => null,
            'intoxicacion_alimentaria_x' => null,
            'intoxicacion_drogas_x' => null,
            'envenenamiento_x' => null,
            'picadura_x' => null,
            'anafilaxia_x' => null,

        );

        $c = array();
        foreach ($seccion as $key => $val) {
            $c[] = $val;
        }

        $p = array();
        foreach ($c as $k => $v) {
            if (isset($v['hint']) && isset($v['answer'])) {
                $p[] = array(
                    'id' => $v['hint'],
                    'value' => $v['answer'],
                );
            }
        }

        # Validación
        $res = array();
        foreach ($target as $key1 => $val) {
            foreach ($p as $key2) {
                if ($key1 == $key2['id']) {
                    $res[] = $key2;
                    $target[$key1] = $key2['value'];
                }
            }
        }

        # Errores
        if ($target['traumatologico_x']) {

            $sts = false;
            unset($target['traumatologico_x']);

            foreach ($target as $a => $b) {
                if ($b == true) {
                    $sts = true;
                }
            }

            if (!$sts) {
                $this->customErrors[] = 'Error en Formulario (Campos Obligatorios): [SCT_3][Al menos un campo debe estar seleccionado Ej: Caida]';
            }

        }

        return $res;

    }

    private function getSeccion_4($seccion = array())
    {
        # Sección 4 ACCIDENTE, VIOLENCIA, INTOXICACIÓN ENVENENAMIENTO O QUEMADURA
        $target = array(
            'traumatologico_x' => null,
            'accidente_transito_x' => null,
            'caida_x' => null,
            'quemadura_x' => null,
            'mordedura_x' => null,
            'ahogamiento_x' => null,
            'cuerpo_extranio_x' => null,
            'aplastamiento_x' => null,
            'otro_accidente_x' => null,
            'arma_fuego_x' => null,
            'arma_c_x' => null,
            'familiar_x' => null,
            'abuso_fisico_x' => null,
            'abuso_sicologico_x' => null,
            'abuso_sexual_x' => null,
            'otra_violencia_x' => null,
            'intoxicacion_alcoholica' => null,
            'intoxicacion_alimentaria_x' => null,
            'intoxicacion_drogas_x' => null,
            'envenenamiento_x' => null,
            'picadura_x' => null,
            'anafilaxia_x' => null,

        );

        $c = array();
        foreach ($seccion as $key => $val) {
            $c[] = $val;
        }

        $p = array();
        foreach ($c as $k => $v) {
            if (isset($v['hint']) && isset($v['answer'])) {
                $p[] = array(
                    'id' => $v['hint'],
                    'value' => $v['answer'],
                );
            }
        }

        # Validación
        $res = array();
        foreach ($target as $key1 => $val) {
            foreach ($p as $key2) {
                if ($key1 == $key2['id']) {
                    $res[] = $key2;
                    $target[$key1] = $key2['value'];
                }
            }
        }

        # Errores
        if ($target['traumatologico_x']) {

            $sts = false;
            unset($target['traumatologico_x']);

            foreach ($target as $a => $b) {
                if ($b == true) {
                    $sts = true;
                }
            }

            if (!$sts) {
                $this->customErrors[] = 'Error en Formulario (Campos Obligatorios): [SCT_3][Al menos un campo debe estar seleccionado Ej: Caida]';
            }

        }

        return $res;

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

/**
 * __construct()
 */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);

    }
}
