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
use Ocrend\Kernel\Models\Traits\DBModel;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Odbc GEMA -> Especialidades
 */

class Especialidades extends Models implements IModels
{
    use DBModel;

    # Variables de clase
    private $val                 = null;
    private $sortCategory        = null;
    private $sortField           = 'ROWNUM_';
    private $filterField         = null;
    private $sortType            = 'desc'; # desc
    private $offset              = 1;
    private $limit               = 25;
    private $searchField         = null;
    private $startDate           = null;
    private $endDate             = null;
    private $foto_dummy          = 'assets/doctores/doc.jpg';
    private $_conexion           = null;
    private $_medicos_first_load = array();

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

    public function getEspecialidades()
    {

        try {

            global $http;

            # ERRORES DE PETICION
            $this->errorsPagination();

            $sql = "SELECT descripcion, ROWNUM  FROM aas_especialidades ORDER BY descripcion asc";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # Ya no existe resultadso
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $notas = array();

            foreach ($data as $key) {
                # $key['NUM'] = (int) $key['ROWNUM'];
                unset($key['ROWNUM']);
                $notas[] = $key;
            }

            # Setear alergologia
            $notas[] = array('DESCRIPCION' => 'ALERGOLOGIA');

            // RESULTADO DE CONSULTA

            # Devolver Información
            return array(
                'status' => true,
                'data'   => $notas,
                'total'  => count($notas),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status'    => true,
                    'data'      => [],
                    'total'     => 0,
                    'message'   => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

# Ordenar array por campo
    public function orderMultiDimensionalArray($toOrderArray, $field, $inverse = 'desc')
    {
        $position = array();
        $newRow   = array();
        foreach ($toOrderArray as $key => $row) {
            $position[$key] = $row[$field];
            $newRow[$key]   = $row;
        }
        if ($inverse == 'desc') {
            arsort($position);
        } else {
            asort($position);
        }
        $returnArray = array();
        foreach ($position as $key => $pos) {
            $returnArray[] = $newRow[$key];
        }
        return $returnArray;
    }

    private function get_Order_Pagination(array $arr_input)
    {
        # SI ES DESCENDENTE

        $arr = array();
        $NUM = 1;

        if ($this->sortType == 'desc') {

            $NUM = count($arr_input);
            foreach ($arr_input as $key) {
                $key['NUM'] = $NUM;
                $arr[]      = $key;
                $NUM--;
            }

            return $arr;

        }

        # SI ES ASCENDENTE

        foreach ($arr_input as $key) {
            $key['NUM'] = $NUM;
            $arr[]      = $key;
            $NUM++;
        }

        return $arr;
    }

    private function get_page(array $input, $pageNum, $perPage)
    {
        $start = ($pageNum - 1) * $perPage;
        $end   = $start + $perPage;
        $count = count($input);

        // Conditionally return results
        if ($start < 0 || $count <= $start) {
            // Page is out of range
            return array();
        } else if ($count <= $end) {
            // Partially-filled page
            return array_slice($input, $start);
        } else {
            // Full page
            return array_slice($input, $start, $end - $start);
        }
    }

    public function shuffle_assoc($array)
    {
        $keys = array_keys($array);

        shuffle($keys);

        foreach ($keys as $key) {
            $new[$key] = $array[$key];
        }

        $array = $new;

        return $array;
    }

    private function normaliza($cadena)
    {
        $originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÎÏÐÒÓÔÕÖØÙÚÛÜÝÞ
ßàáâãäåæçèéêëìîïðòóôõöøùúûýýþÿŔŕ';
        $modificadas = 'aaaaaaaceeeeiiiidnoooooouuuuy
bsaaaaaaaceeeeiiiidnoooooouuuyybyRr';
        $cadena = utf8_decode($cadena);
        $cadena = strtr($cadena, utf8_decode($originales), $modificadas);
        return utf8_encode($cadena);
    }

    private function quitar_tildes($cadena)
    {
        $no_permitidas = array("%", "é", "í", "ó", "ú", "É", "Í", "Ó", "Ú", "ñ", "À", "Ã", "Ì", "Ò", "Ù", "Ã™", "Ã ", "Ã¨", "Ã¬", "Ã²", "Ã¹", "ç", "Ç", "Ã¢", "ê", "Ã®", "Ã´", "Ã»", "Ã‚", "ÃŠ", "ÃŽ", "Ã”", "Ã›", "ü", "Ã¶", "Ã–", "Ã¯", "Ã¤", "«", "Ò", "Ã", "Ã„", "Ã‹");
        $permitidas    = array("", "e", "i", "o", "u", "E", "I", "O", "U", "n", "N", "A", "E", "I", "O", "U", "a", "e", "i", "o", "u", "c", "C", "a", "e", "i", "o", "u", "A", "E", "I", "O", "U", "u", "o", "O", "i", "a", "e", "U", "I", "A", "E");
        $texto         = str_replace($no_permitidas, $permitidas, $cadena);
        return $texto;
    }

    private function sanear_string($string)
    {

        $string = trim($string);

        //Esta parte se encarga de eliminar cualquier caracter extraño
        $string = str_replace(
            array(">", "< ", ";", ",", ":", " ", "%"),
            ' ',
            $string
        );

        return trim($string);
    }

    private function notResults(array $data)
    {
        if (count($data) == 0) {
            throw new ModelsException('No existe más resultados.', 4080);
        }
    }

/**
 * __construct()
 */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
        $this->startDBConexion();

    }
}
