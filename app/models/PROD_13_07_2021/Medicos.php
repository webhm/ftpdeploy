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
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Models\Traits\DBModel;
use Ocrend\Kernel\Router\IRouter;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

/**
 * Modelo Odbc GEMA -> Medicos
 */

class Medicos extends Models implements IModels
{
    use DBModel;

    # Variables de clase
    private $val = null;
    private $lang = 'es';
    private $sortCategory = null;
    private $sortField = 'ROWNUM_';
    private $filterField = null;
    private $searchType = null;
    private $sortType = 'desc'; # desc
    private $offset = 1;
    private $limit = 25;
    private $searchField = null;
    private $startDate = null;
    private $endDate = null;
    private $foto_dummy = 'assets/doctores/doc.jpg';
    private $_conexion = null;
    private $_medicos_first_load = false;
    private $medicos_load = array();

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

    }

    private function getAuthorizationn()
    {

        try {

            global $http;

            $token = $http->headers->get("Authorization");

            $auth = new Model\Auth;
            $key = $auth->GetData($token);

            $this->USER = $key;

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
                $endDate = $this->endDate;

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

            if ($this->searchType != null) {
                $this->searchType = $this->searchType;
            }

            # Setear valores para busquedas dividadas
            if (stripos($this->searchField, ' ')) {
                $this->searchField = str_replace(' ', '%', $this->searchField);
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
                $endDate = $this->endDate;

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

            if ($this->searchType != null) {
                $this->searchType = $this->searchType;
            }

            return false;
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    public function getPerfil_Medico_Buscador($COD_MEDICO): array
    {

        try {

            global $config;

            # set query cod_medico

            # CAPTURA VALROES ACADEMICOS PARA NUEVO PERFIL MEDICO

            $sql = " SELECT * FROM edm_medicos_dat_comercial WHERE cod_medico_num = '" . $COD_MEDICO . "' AND PUBLICA_WEB='S'  ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $cod_medico_num = $stmt->fetch();

            if (false === $cod_medico_num) {
                throw new ModelsException('Error No existen elementos.', 4080);
            }

            $COD_MEDICO = $cod_medico_num['PK_FK_MEDICO'];

            # Extraer valores

            $sql = " SELECT * FROM WEB_VW_DATOS_MEDICOS WHERE COD_MEDICO = '" . $COD_MEDICO . "'  ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $medico = $stmt->fetch();

            if (false === $medico) {
                throw new ModelsException('Error No existen elementos.', 4080);
            }

            # Setear datos del perfil
            $medico['MAIL'] = explode('|', $medico['MAIL']);
            $medico['TLF_OFICINA'] = $this->sanear_string($medico['TLF_OFICINA']);
            $medico['COD_ESPECIALIDAD'] = (int) $medico['COD_ESPECIALIDAD'];
            $medico['COD_MEDICO'] = (int) $medico['COD_MEDICO'];
            $medico['NOMBRE_MEDICO'] = $medico['NOMBRES'] . ' ' . $medico['APELLIDOS'];

            unset($medico['COD_PERSONA']);
            unset($medico['ROWNUM_']);
            unset($medico['AREA_INTERES']);
            unset($medico['NOMBRES']);
            unset($medico['APELLIDOS']);
            unset($medico['COD_PCTE_CEDULA']);
            unset($medico['NUM_PCTE_CEDULA']);
            unset($medico['COD_PCTE_RUC']);
            unset($medico['NUM_PCTE_RUC']);

            #TILDAR ESPECIAIDAD
            if ($this->lang == 'en') {

                $especialidades = array();
                if (strpos($medico['ESPECIALIDAD'], '/')) {

                    foreach (explode('/', $medico['ESPECIALIDAD']) as $k => $val) {
                        $especialidades[] = $this->traslateEspecialidad(trim($val)) . ' ';
                    }

                    $medico['ESPECIALIDAD'] = implode(' / ', $especialidades);

                } else {

                    $medico['ESPECIALIDAD'] = $this->traslateEspecialidad($medico['ESPECIALIDAD']);

                }

            } else {

                $especialidades = array();
                if (strpos($medico['ESPECIALIDAD'], '/')) {

                    foreach (explode('/', $medico['ESPECIALIDAD']) as $k => $val) {
                        $especialidades[] = $this->defaultLang(trim($val)) . ' ';
                    }

                    $medico['ESPECIALIDAD'] = implode(' / ', $especialidades);

                } else {

                    $medico['ESPECIALIDAD'] = $this->defaultLang($medico['ESPECIALIDAD']);

                }

            }

            # Parsear celulares
            $celulares = array();
            foreach (explode(';', $medico['TLF_CELULAR']) as $k => $v) {
                $celulares[] = Helper\Strings::remove_spaces(substr($v, 2));
            }

            $medico['TLF_CELULAR'] = implode(' ', $celulares);

            $medico['DIRECCION'] = $this->sanear_string($medico['DIRECCION']);

            $medico['DIRECCION_CASA'] = $this->sanear_string($medico['DIRECCION_CASA']);

            # setear valores de red social y web
            $medico['RED_SOCIAL'] = (is_null($medico['RED_SOCIAL'])) ? array() : explode('|', $medico['RED_SOCIAL']);

            $red_social = array();

            foreach ($medico['RED_SOCIAL'] as $k) {

                # Siempre y cuando el valor del array contenga valores
                if (count($medico['RED_SOCIAL']) != 0) {
                    $k = explode('-', $k);
                    $red_social[str_replace(' ', '_', $k[0])] = (stripos($k[1], 'http://') || stripos($k[1], 'https://')) ? explode(':', $k[1])[0] . ':' . explode(':', $k[1])[1] : $k[1];
                }

            }

            $medico['RED_SOCIAL'] = $red_social;

            # OBTENER IMAGEN FOTO DE MEDICOCOD_MEDICO

            $foto_png = 'assets/doctores/perfil/' . $medico['COD_MEDICO'] . '.png';

            $foto_medico_png = file_exists($foto_png);

            if ($foto_medico_png) {
                $medico['FOTO'] = $config['api']['url'] . $foto_png;
            } else {
                $medico['FOTO'] = false;
            }

            # CAPTURA VALROES ACADEMICOS PARA NUEVO PERFIL MEDICO

            $sql = " SELECT * FROM edm_medicos_dat_comercial WHERE cod_medico_num = '" . $COD_MEDICO . "'  ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $ac_medico = $stmt->fetch();

            if ($ac_medico['PUBLICA_WEB'] == 'S') {

                $medico['ESTRACTO'] = (strlen(trim($ac_medico['ESTRACTO'])) == 0) ? null : trim($ac_medico['ESTRACTO']);
                $medico['EXPERTICIA'] = (strlen(trim($ac_medico['EXPERTICIA'])) == 0) ? null : trim($ac_medico['EXPERTICIA']);
                $medico['FORMACION_ACADEMICA'] = (strlen(trim($ac_medico['FORMACION_ACADEMICA'])) == 0) ? null : trim($ac_medico['FORMACION_ACADEMICA']);
                $medico['SOCIEDADES_MEDICAS'] = (strlen(trim($ac_medico['SOCIEDADES_ACADEMICAS'])) == 0) ? null : trim($ac_medico['SOCIEDADES_ACADEMICAS']);
                $medico['PREMIOS_DISTINCIONES'] = (strlen(trim($ac_medico['PREMIOS_DISTINCIONES'])) == 0) ? null : trim($ac_medico['PREMIOS_DISTINCIONES']);
                $medico['PUBLICACIONES'] = (strlen(trim($ac_medico['PUBLICACIONES'])) == 0) ? null : trim($ac_medico['PUBLICACIONES']);
                $medico['OTROS_ENTRENAMIENTOS'] = (strlen(trim($ac_medico['OTROS_ENTRENAMIENTOS'])) == 0) ? null : trim($ac_medico['OTROS_ENTRENAMIENTOS']);

            } else {

                $medico['ESTRACTO'] = null;
                $medico['EXPERTICIA'] = null;
                $medico['FORMACION_ACADEMICA'] = null;
                $medico['SOCIEDADES_MEDICAS'] = null;
                $medico['PREMIOS_DISTINCIONES'] = null;
                $medico['PUBLICACIONES'] = null;
                $medico['OTROS_ENTRENAMIENTOS'] = null;

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $medico,
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status' => true,
                    'data' => [],
                    'message' => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getPerfil_Medico($COD_PERSONA): array
    {

        try {

            global $config;

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Query
            $queryBuilder
                ->select('*')
                ->from('WEB_VW_DATOS_MEDICOS')
                ->where('COD_PERSONA = :COD_PERSONA')
                ->setParameter('COD_PERSONA', $COD_PERSONA)
            ;

            # Execute
            $stmt = $queryBuilder->execute();

            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $medico = $stmt->fetch();

            if (false === $medico) {
                throw new ModelsException('Error No existen elementos.', 4080);

            }

            # Setear datos del perfil
            $medico['MAIL'] = explode('|', $medico['MAIL']);
            $medico['TLF_OFICINA'] = $this->sanear_string($medico['TLF_OFICINA']);
            $medico['COD_ESPECIALIDAD'] = (int) $medico['COD_ESPECIALIDAD'];
            $medico['COD_MEDICO'] = (int) $medico['COD_MEDICO'];
            $medico['NOMBRE_MEDICO'] = $medico['NOMBRES'] . ' ' . $medico['APELLIDOS'];

            unset($medico['COD_PERSONA']);
            unset($medico['ROWNUM_']);
            unset($medico['AREA_INTERES']);
            unset($medico['NOMBRES']);
            unset($medico['APELLIDOS']);

            # TILDAR ESPECIALIDAD

            # Parsear celulares
            $celulares = array();
            foreach (explode(';', $medico['TLF_CELULAR']) as $k => $v) {
                $celulares[] = Helper\Strings::remove_spaces(substr($v, 2));
            }

            $medico['TLF_CELULAR'] = implode(' ', $celulares);

            $medico['DIRECCION'] = $this->sanear_string($medico['DIRECCION_CASA']);

            $medico['DIRECCION_CASA'] = $this->sanear_string($medico['DIRECCION_CASA']);

            # setear valores de red social y web
            $medico['RED_SOCIAL'] = (is_null($medico['RED_SOCIAL'])) ? array() : explode('|', $medico['RED_SOCIAL']);

            $red_social = array();

            foreach ($medico['RED_SOCIAL'] as $k) {

                # Siempre y cuando el valor del array contenga valores
                if (count($medico['RED_SOCIAL']) != 0) {
                    $k = explode('-', $k);
                    $red_social[str_replace(' ', '_', $k[0])] = (stripos($k[1], 'http://') || stripos($k[1], 'https://')) ? explode(':', $k[1])[0] . ':' . explode(':', $k[1])[1] : $k[1];
                }

            }

            $medico['RED_SOCIAL'] = $red_social;

            # OBTENER IMAGEN FOTO DE MEDICOCOD_MEDICO

            $foto_png = 'assets/doctores/perfil/' . $medico['COD_MEDICO'] . '.png';

            $foto_medico_png = file_exists($foto_png);

            if ($foto_medico_png) {
                $medico['FOTO'] = $config['api']['url'] . $foto_png;
            } else {
                $medico['FOTO'] = false;
            }

            # OBTENER IMAGEN FOTO DE MEDICO

            $medico['AREA_INTERES'] = false;

            # Devolver Información
            return array(
                'status' => true,
                'data' => $medico,
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status' => true,
                    'data' => [],
                    'message' => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getSintomasDirectorio()
    {

        try {

            global $http, $config;

            # SETEAR VARIABLES DE CLASE
            $this->setParameters_TEST();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # Devolver array vacio cuando existe sorcategory definido
            if ($this->searchField === null or $this->searchField === '') {
                throw new ModelsException('No existen resultados.', 4080);
            }

            # Conectar base de datos
            $this->conectar_Oracle();

            if ($this->searchField != null) {

                # Devolver valores de sintomas
                $sintomas = $this->getSintomas($this->searchField);

                # return $sintomas;

                if (false === $sintomas) {

                    throw new ModelsException('No existen resultados.', 4080);

                } else {

                    $sintomas = implode(",", $sintomas);
                    # return $sintomas;

                    # Siempre y cuando sitomas este disponible
                    if ($sintomas != '') {
                        $sql = " SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE COD_ESPECIALIDAD IN ($sintomas) AND CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC ";
                    } else {
                        throw new ModelsException('No existen resultados.', 4080);
                    }

                }

            } else {

                # Devolver todos los resultados
                $sql = "SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC ";

            }

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # Ya no existe resultadso
            if (false === $data) {
                throw new ModelsException('No existen resultados.', 4080);
            }

            # Datos de usuario cuenta activa
            $medicos = array();

            # SETEO DE MEDICOS OBJERTO
            foreach ($data as $key) {

                # Setear datos del perfil
                $key['MAIL'] = explode('|', $key['MAIL']);
                $key['TLF_OFICINA'] = explode('|', $key['TLF_OFICINA'])[0];
                $key['COD_ESPECIALIDAD'] = (int) $key['COD_ESPECIALIDAD'];
                $key['COD_MEDICO'] = (int) $key['COD_MEDICO'];
                $key['SINTOMAS'] = $this->getSintomasMedico($this->searchField, $key['COD_ESPECIALIDAD']);
                $key['NOMBRE_MEDICO'] = $key['NOMBRES'] . ' ' . $key['APELLIDOS'];

                unset($key['COD_PERSONA']);
                unset($key['ROWNUM_']);
                unset($key['NOMBRES']);
                unset($key['APELLIDOS']);

                $especialidades = array();
                if (strpos($key['ESPECIALIDAD'], '/')) {

                    foreach (explode('/', $key['ESPECIALIDAD']) as $k => $val) {
                        $especialidades[] = $this->defaultLang(trim($val)) . ' ';
                    }

                    $key['ESPECIALIDAD'] = implode(' / ', $especialidades);

                } else {

                    $key['ESPECIALIDAD'] = $this->defaultLang($key['ESPECIALIDAD']);

                }

                # Parsear celulares
                $celulares = array();
                foreach (explode(';', $key['TLF_CELULAR']) as $k => $v) {
                    $celulares[] = Helper\Strings::remove_spaces(substr($v, 2));
                }

                $key['TLF_CELULAR'] = $celulares[0];

                $key['DIRECCION'] = $this->sanear_string($key['DIRECCION']);

                $key['DIRECCION_CASA'] = $this->sanear_string($key['DIRECCION_CASA']);

                # setear valores de red social y web
                $key['RED_SOCIAL'] = (is_null($key['RED_SOCIAL'])) ? array() : explode('|', $key['RED_SOCIAL']);

                $red_social = array();

                foreach ($key['RED_SOCIAL'] as $k) {

                    # Siempre y cuando el valor del array contenga valores
                    if (count($key['RED_SOCIAL']) != 0) {
                        $k = explode('-', $k);
                        $red_social[str_replace(' ', '_', $k[0])] = $k[1];
                    }

                }

                $key['RED_SOCIAL'] = $red_social;

                # OBTENER IMAGEN FOTO DE MEDICOCOD_MEDICO
                $foto_medico = file_exists('assets/doctores/' . $key['COD_MEDICO'] . '.jpg');
                # OBTENER IMAGEN FOTO DE MEDICO
                $key['FOTO'] = ($foto_medico) ? $config['api']['url'] . 'assets/doctores/' . $key['COD_MEDICO'] . '.jpg' : false;

                $medicos[] = $key;

            }

            // RESULTADO DE CONSULTA

            # Order by asc to desc y mezclar array de resultados de busqueda medicos
            $MEDICOS = $this->get_Order_Pagination($medicos);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->get_page($MEDICOS, $this->offset, $this->limit),
                'total' => count($medicos),
                'limit' => intval($this->limit),
                'offset' => intval($this->offset),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status' => true,
                    'data' => [],
                    'total' => 0,
                    'message' => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getDirectorioMedicos_Rand()
    {

        try {

            global $http, $config;

            # SETEAR VARIABLES DE CLASE
            $this->setParameters_TEST();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # Conectar base de datos
            $this->conectar_Oracle();

            if ($this->searchField != null) {

                $this->searchField = mb_strtoupper($this->searchField);

                if ($this->searchField == 'ALERGOLOGIA') {

                    $sql = " SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE COD_MEDICO IN ('215','252') ";

                } elseif ($this->searchField == 'ODONTOLOGIA') {

                    # Devolver valores por especialidad de medicos
                    $sql = " SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE COD_MEDICO
                    IN ('010349','678','347')  ORDER BY APELLIDOS ASC ";

                } elseif ($this->searchField == 'ODONTOPEDIATRIA') {

                    # Devolver valores por especialidad de medicos
                    $sql = " SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE COD_MEDICO
                    IN ('347')  ORDER BY APELLIDOS ASC ";

                } elseif ($this->searchField == 'ACARDIOLOGIA') {

                    # Devolver valores por especialidad de medicos
                    $sql = " SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE COD_MEDICO
                    IN ('010452','30','10764','10791','294','010792','522','549','010454','010079','643')  ORDER BY APELLIDOS ASC ";

                } elseif ($this->searchField == 'ELECTROFISIOLOGIA') {

                    # Devolver valores por especialidad de medicos
                    $sql = " SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE COD_MEDICO
                    IN ('10792','643')  ORDER BY APELLIDOS ASC ";

                } elseif ($this->searchField == 'HEMODINAMICA') {

                    # Devolver valores por especialidad de medicos
                    $sql = " SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE COD_MEDICO
                    IN ('30','10791','522')  ORDER BY APELLIDOS ASC ";

                } elseif ($this->searchField == 'ONCOLOGIA') {

                    # Devolver valores por especialidad de medicos
                    $sql = " SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE COD_MEDICO
                    IN ('010361','463','134','010381','225','316','401','10595','10747','10099','010280','48')  ORDER BY APELLIDOS ASC ";

                } elseif ($this->searchField == 'UROLOGIA') {

                    # Devolver valores por especialidad de medicos
                    $sql = " SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_
                    FROM WEB_VW_DATOS_MEDICOS
                    WHERE (NOMBRES_COMPLETOS LIKE '%$this->searchField%' OR DESC_ESPECIALIDAD_PRIN LIKE '$this->searchField%' OR ESPECIALIDAD_SEC LIKE '%$this->searchField%') AND CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC ";

                } elseif ($this->searchField == 'NEUROLOGIA') {

                    # Devolver valores por especialidad de medicos
                    $sql = " SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_
                    FROM WEB_VW_DATOS_MEDICOS
                    WHERE (NOMBRES_COMPLETOS LIKE '%$this->searchField%' OR DESC_ESPECIALIDAD_PRIN LIKE '$this->searchField%' OR ESPECIALIDAD_SEC LIKE '%$this->searchField%') AND CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC ";

                } else {

                    /*
                    # Buscar especialidad
                    if (false != $this->buscarEspe(mb_strtoupper($this->searchField))) {

                    $sql = "SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE ESPECIALIDAD = '$this->searchField' AND CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC ";

                    } else {

                    # Devolver valores por nombres o apellidos de doctor
                    $sql = " SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE (NOMBRES LIKE '%$this->searchField%' OR APELLIDOS LIKE '%$this->searchField%' OR NOMBRES_COMPLETOS LIKE '%$this->searchField%' OR ESPECIALIDAD LIKE '%$this->searchField%') AND CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC ";

                    }
                     */

                    # Devolver valores por nombres o apellidos de doctor
                    # v1.0 buscador // 08/12/2020
                    /*
                    $sql = " SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_
                    FROM WEB_VW_DATOS_MEDICOS
                    WHERE (NOMBRES_COMPLETOS LIKE '%$this->searchField%' OR ESPECIALIDAD LIKE '%$this->searchField%') AND CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC ";
                     */

                    # Busca todos los medicos segun su especialidad y subespecialidad
                    # v2.0
                    # Devolver valores por nombres o apellidos de doctor

                    $sql = " SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_
                      FROM WEB_VW_DATOS_MEDICOS
                      WHERE (NOMBRES_COMPLETOS LIKE '%$this->searchField%' OR DESC_ESPECIALIDAD_PRIN LIKE '%$this->searchField%' OR ESPECIALIDAD_SEC LIKE '%$this->searchField%') AND CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC ";

                }

            } elseif ($this->sortCategory != null && $this->searchType == true) {

                $this->sortCategory = mb_strtoupper($this->sortCategory);

                # Devolver valores por especialidad de medicos
                $sql = "SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE ESPECIALIDAD = '$this->sortCategory' AND CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC ";

            } elseif ($this->sortCategory != null && $this->searchType == false) {

                $this->sortCategory = mb_strtoupper($this->sortCategory);

                # Devolver valores por especialidad de medicos
                $sql = "SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE ESPECIALIDAD like '%$this->sortCategory%' AND CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC ";

            } else {

                # Devolver todos los resultados
                $sql = "SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC";

            }

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # Ya no existe resultadso
            if (false === $data) {
                throw new ModelsException('No existen resultados.', 4080);
            }

            # Datos de usuario cuenta activa
            $medicos = array();

            # SETEO DE MEDICOS OBJERTO
            foreach ($data as $key) {

                # Setear datos del perfil
                $key['MAIL'] = explode('|', $key['MAIL']);
                $key['TLF_OFICINA'] = $this->sanear_string($key['TLF_OFICINA']);
                $key['TITULADO_EN'] = $this->sanear_string($key['TITULADO_EN']);
                $key['COD_ESPECIALIDAD'] = (int) $key['COD_ESPECIALIDAD'];
                $key['COD_MEDICO'] = (int) $key['COD_MEDICO'];
                $key['NOMBRE_MEDICO'] = $key['NOMBRES'] . ' ' . $key['APELLIDOS'];

                unset($key['COD_PERSONA']);
                unset($key['ROWNUM_']);
                unset($key['NOMBRES']);
                unset($key['APELLIDOS']);

                #TILDAR ESPECIAIDAD
                if ($this->lang == 'en') {

                    $especialidades = array();
                    if (strpos($key['ESPECIALIDAD'], '/')) {

                        foreach (explode('/', $key['ESPECIALIDAD']) as $k => $val) {
                            $especialidades[] = $this->traslateEspecialidad(trim($val)) . ' ';
                        }

                        $key['ESPECIALIDAD'] = implode(' / ', $especialidades);

                    } else {

                        $key['ESPECIALIDAD'] = $this->traslateEspecialidad($key['ESPECIALIDAD']);

                    }

                } else {

                    $especialidades = array();
                    if (strpos($key['ESPECIALIDAD'], '/')) {

                        foreach (explode('/', $key['ESPECIALIDAD']) as $k => $val) {
                            $especialidades[] = $this->defaultLang(trim($val)) . ' ';
                        }

                        $key['ESPECIALIDAD'] = implode(' / ', $especialidades);

                    } else {

                        $key['ESPECIALIDAD'] = $this->defaultLang($key['ESPECIALIDAD']);

                    }

                }

                # Parsear celulares
                $celulares = array();
                foreach (explode(';', $key['TLF_CELULAR']) as $k => $v) {
                    $celulares[] = Helper\Strings::remove_spaces(substr($v, 2));
                }

                $key['TLF_CELULAR'] = implode(' ', $celulares);

                $key['DIRECCION'] = $this->sanear_string($key['DIRECCION']);

                $key['DIRECCION_CASA'] = $this->sanear_string($key['DIRECCION_CASA']);

                # setear valores de red social y web
                $key['RED_SOCIAL'] = (is_null($key['RED_SOCIAL'])) ? array() : explode('|', $key['RED_SOCIAL']);

                $red_social = array();

                foreach ($key['RED_SOCIAL'] as $k) {

                    # Siempre y cuando el valor del array contenga valores
                    if (count($key['RED_SOCIAL']) != 0) {
                        $k = explode('-', $k);
                        $red_social[str_replace(' ', '_', $k[0])] =
                        (stripos($k[1], 'http://') || stripos($k[1], 'https://')) ? explode(':', $k[1])[0] . ':' . explode(':', $k[1])[1] : $k[1];
                    }

                }

                $key['RED_SOCIAL'] = $red_social;

                # OBTENER IMAGEN FOTO DE MEDICOCOD_MEDICO
                $foto_jpg = 'assets/doctores/' . $key['COD_MEDICO'] . '.jpg';

                $foto_medico_jpg = file_exists($foto_jpg);

                if ($foto_medico_jpg) {
                    $key['FOTO'] = $config['api']['url'] . $foto_jpg;
                } else {
                    $key['FOTO'] = false;
                }

                # Eliminar dcotores que no tengan foto direccion medica requeimiento especial

                $key['AREA_INTERES'] = false;

                if ($key['TELECONSULTA'] == 'TRUE') {
                    $key['TELECONSULTA'] = true;
                } else {
                    $key['TELECONSULTA'] = false;
                }

                $medicos[] = $key;

            }

            # filtrar solo los que tiene foto a exepcion del doctor chong

            $_medicos = array();

            foreach ($medicos as $key) {
                if ($key['FOTO'] == false) {
                    unset($key);
                } else {
                    $_medicos[] = $key;
                }
            }

            # Order by asc to desc y mezclar array de resultados de busqueda medicos
            $MEDICOS = $this->get_Order_Pagination($_medicos);

            # auditoria de clientes
            $name_file = $this->Aud();
            $file = 'premedicos/' . $name_file . '_' . $this->lang . '.json';

            if ($this->searchField != null or $this->sortCategory != null) {
                Helper\Files::delete_file($file);
            }

            // RESULTADO DE CONSULTA SI ES PRIMERA CARGA SI ARCHIVO JSON PREGENERADO NO EXISTE
            if ($this->searchField === null and $this->sortCategory === null and false === file_exists($file)) {
                $this->_medicos_first_load = true;
                $json_string = json_encode($this->shuffle_assoc($MEDICOS));
                file_put_contents($file, $json_string);
            }

            # sI EXISTE ARCHIVO PREGENERADO CARGA ARCHIVO
            if (file_exists($file)) {
                $this->_medicos_first_load = true;
            }

            # devolverinformacion dependiendo del rquest

            if ($this->_medicos_first_load) {

                $datos_medicos = file_get_contents($file);
                $json_medicos = json_decode($datos_medicos, true);

                # Devolver Información
                return array(
                    'status' => true,
                    'data' => $this->get_page($json_medicos, $this->offset, $this->limit),
                    'total' => count($json_medicos),
                    'limit' => intval($this->limit),
                    'offset' => intval($this->offset),
                );

            } else {

                # Devolver Información
                return array(
                    'status' => true,
                    'data' => $this->get_page($MEDICOS, $this->offset, $this->limit),
                    'total' => count($MEDICOS),
                    'limit' => intval($this->limit),
                    'offset' => intval($this->offset),

                );

            }

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status' => true,
                    'data' => [],
                    'total' => 0,
                    'message' => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getDirectorioMedicos_Rand_sin_foto()
    {

        try {

            global $http, $config;

            # SETEAR VARIABLES DE CLASE
            $this->setParameters_TEST();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # Conectar base de datos
            $this->conectar_Oracle();

            # Conectar base de datos
            $this->setSpanishOracle();

            if ($this->searchField != null) {

                # Devolver valores por nombres o apellidos de doctor
                $sql = " SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE (NOMBRES LIKE '%$this->searchField%' OR APELLIDOS LIKE '%$this->searchField%' OR NOMBRES_COMPLETOS LIKE '%$this->searchField%' OR ESPECIALIDAD LIKE '%$this->searchField%') AND CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC ";

            } elseif ($this->sortCategory != null) {

                # Setear valores para busquedas dividadas
                if (stripos($this->sortCategory, ' ')) {

                    # Devolver valores por especialidad de medicos
                    $sql = "SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE ESPECIALIDAD LIKE '%$this->sortCategory%' AND CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC ";

                } else {
                    # Devolver valores por especialidad de medicos
                    # $sql = "SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE ESPECIALIDAD = '$this->sortCategory' AND CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC ";
                    $sql = "SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE ESPECIALIDAD LIKE '%$this->sortCategory%' AND CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC ";
                }

            } else {

                # Devolver todos los resultados
                $sql = "SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC";

            }

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # Ya no existe resultadso
            if (false === $data) {
                throw new ModelsException('No existen resultados.', 4080);
            }

            # Datos de usuario cuenta activa
            $medicos = array();

            # SETEO DE MEDICOS OBJERTO
            foreach ($data as $key) {

                $key['COD_MEDICO'] = (int) $key['COD_MEDICO'];

                # OBTENER IMAGEN FOTO DE MEDICOCOD_MEDICO
                $foto_medico = file_exists('assets/doctores/' . $key['COD_MEDICO'] . '.jpg');

                if ($foto_medico == false) {
                    $key['COD_MEDICO'] = (int) $key['COD_MEDICO'];
                    $medicos[] = array(
                        'COD_MEDICO' => $key['COD_MEDICO'],
                        'FOTO' => $foto_medico,
                        'ESPE' => $key['ESPECIALIDAD'],
                    );
                }

            }

            return array(
                'status' => true,
                'data' => $medicos,
                'total' => count($medicos),
                'limit' => intval($this->limit),
                'offset' => intval($this->offset),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status' => true,
                    'data' => [],
                    'total' => 0,
                    'message' => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

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

    }

    public function getDirectorioMedicos()
    {

        try {

            global $http, $config;

            # SETEAR VARIABLES DE CLASE
            $this->setParameters_TEST();

            # return $this->sortCategory;

            # ERRORES DE PETICION
            $this->errorsPagination();

            # Conectar base de datos
            $this->conectar_Oracle();

            if ($this->searchField != null) {

                # Devolver valores por nombres o apellidos de doctor
                $sql = " SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE (NOMBRES LIKE '%$this->searchField%' OR APELLIDOS LIKE '%$this->searchField%' OR ESPECIALIDAD LIKE '%$this->searchField%') AND CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC ";

            } elseif ($this->sortCategory != null) {

                # Devolver valores por especialidad de medicos
                $sql = "SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE ESPECIALIDAD LIKE '%$this->sortCategory%' AND CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC ";

            } else {

                # Devolver todos los resultados
                $sql = "SELECT WEB_VW_DATOS_MEDICOS.*, ROWNUM AS ROWNUM_ FROM WEB_VW_DATOS_MEDICOS WHERE CATEGORIA='ACTIVO' ORDER BY APELLIDOS ASC";

            }

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # Ya no existe resultadso
            if (false === $data) {
                throw new ModelsException('No existen resultados.', 4080);
            }

            # Datos de usuario cuenta activa
            $medicos = array();

            # SETEO DE MEDICOS OBJERTO
            foreach ($data as $key) {

                # Setear datos del perfil
                $key['MAIL'] = explode('|', $key['MAIL']);
                $key['TLF_OFICINA'] = explode('|', $key['TLF_OFICINA'])[0];
                $key['COD_ESPECIALIDAD'] = (int) $key['COD_ESPECIALIDAD'];
                $key['COD_MEDICO'] = (int) $key['COD_MEDICO'];
                $key['NOMBRE_MEDICO'] = $key['NOMBRES'] . ' ' . $key['APELLIDOS'];

                unset($key['COD_PERSONA']);
                unset($key['ROWNUM_']);
                unset($key['NOMBRES']);
                unset($key['APELLIDOS']);

                #TILDAR ESPECIAIDAD
                if ($this->lang == 'en') {

                    $especialidades = array();
                    if (strpos($key['ESPECIALIDAD'], '/')) {

                        foreach (explode('/', $key['ESPECIALIDAD']) as $k => $val) {
                            $especialidades[] = $this->traslateEspecialidad(trim($val)) . ' ';
                        }

                        $key['ESPECIALIDAD'] = implode(' / ', $especialidades);

                    } else {

                        $key['ESPECIALIDAD'] = $this->traslateEspecialidad($key['ESPECIALIDAD']);

                    }

                } else {

                    $especialidades = array();
                    if (strpos($key['ESPECIALIDAD'], '/')) {

                        foreach (explode('/', $key['ESPECIALIDAD']) as $k => $val) {
                            $especialidades[] = $this->defaultLang(trim($val)) . ' ';
                        }

                        $key['ESPECIALIDAD'] = implode(' / ', $especialidades);

                    } else {

                        $key['ESPECIALIDAD'] = $this->defaultLang($key['ESPECIALIDAD']);

                    }

                }

                # Parsear celulares
                $celulares = array();
                foreach (explode(';', $key['TLF_CELULAR']) as $k => $v) {
                    $celulares[] = Helper\Strings::remove_spaces(substr($v, 2));
                }

                $key['TLF_CELULAR'] = implode(' ', $celulares);

                $key['DIRECCION'] = $this->sanear_string($key['DIRECCION']);

                # setear valores de red social y web
                $key['RED_SOCIAL'] = (is_null($key['RED_SOCIAL'])) ? array() : explode('|', $key['RED_SOCIAL']);

                $red_social = array();

                foreach ($key['RED_SOCIAL'] as $k) {

                    # Siempre y cuando el valor del array contenga valores
                    if (count($key['RED_SOCIAL']) != 0) {
                        $k = explode('-', $k);
                        $red_social[str_replace(' ', '_', $k[0])] = $k[1];
                    }

                }

                $key['RED_SOCIAL'] = $red_social;

                # OBTENER IMAGEN FOTO DE MEDICOCOD_MEDICO
                $foto_medico = file_exists('assets/doctores/' . $key['COD_MEDICO'] . '.jpg');
                # OBTENER IMAGEN FOTO DE MEDICO
                $key['FOTO'] = ($foto_medico) ? $config['api']['url'] . 'assets/doctores/' . $key['COD_MEDICO'] . '.jpg' : false;

                $medicos[] = $key;

            }

            # filtrar solo los que tiene foto

            $_medicos = array();

            foreach ($medicos as $key) {
                if ($key['FOTO'] != false) {
                    $_medicos[] = $key;
                }
            }

            # Order by asc to desc y mezclar array de resultados de busqueda medicos
            $MEDICOS = $this->get_Order_Pagination($_medicos);

            $name_file = $this->Aud();
            $file = 'premedicos/' . $name_file . '_' . $this->lang . '.json';

            if ($this->searchField != null or $this->sortCategory != null) {
                Helper\Files::delete_file($file);
            }

            // RESULTADO DE CONSULTA SI ES PRIMERA CARGA SI ARCHIVO JSON PREGENERADO NO EXISTE
            if ($this->searchField === null and $this->sortCategory === null and false === file_exists($file)) {
                $this->_medicos_first_load = true;
                $json_string = json_encode($this->shuffle_assoc($MEDICOS));
                file_put_contents($file, $json_string);
            }

            # sI EXISTE ARCHIVO PREGENERADO CARGA ARCHIVO
            if (file_exists($file)) {
                $this->_medicos_first_load = true;
            }

            # devolverinformacion dependiendo del rquest

            if ($this->_medicos_first_load) {

                $datos_medicos = file_get_contents($file);
                $json_medicos = json_decode($datos_medicos, true);

                # Devolver Información
                return array(
                    'status' => true,
                    'data' => $this->get_page($json_medicos, $this->offset, $this->limit),
                    'total' => count($json_medicos),
                    'limit' => intval($this->limit),
                    'offset' => intval($this->offset),
                );

            } else {

                # Devolver Información
                return array(
                    'status' => true,
                    'data' => $this->get_page($MEDICOS, $this->offset, $this->limit),
                    'total' => count($MEDICOS),
                    'limit' => intval($this->limit),
                    'offset' => intval($this->offset),
                );

            }

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status' => true,
                    'data' => [],
                    'total' => 0,
                    'message' => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getPacientes_Medico()
    {

        try {

            global $http;

            # SETEAR VARIABLES DE CLASE
            $this->setParameters();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # EXTRAER VALOR DEL TOKEN PARA CONSULTA
            $this->getAuthorizationn();

            $codes = implode(',', $this->USER->CP_MED);

            # NO EXITEN RESULTADOS
            $this->notResults($this->USER->CP_MED);

            # CONULTA BDD GEMA
            if ($this->sortField == 'FECHA_ADM' and $this->startDate != null and $this->endDate != null) {

                $sql = "SELECT WEB_VW_PTES_EN_HOSPITAL.*, ROWNUM AS ROWNUM_ FROM WEB_VW_PTES_EN_HOSPITAL WHERE PERSONA_MEDICO IN ($codes) AND $this->sortField >= TO_DATE('$this->startDate', 'dd-mm-yyyy') AND $this->sortField <= TO_DATE('$this->endDate', 'dd-mm-yyyy') ORDER BY $this->sortField $this->sortType ";

            } elseif ($this->searchField != null) {

                $sql = "SELECT WEB_VW_PTES_EN_HOSPITAL.*, ROWNUM AS ROWNUM_ FROM WEB_VW_PTES_EN_HOSPITAL WHERE PERSONA_MEDICO IN ($codes) AND (NOMBRE_MEDICO LIKE '%$this->searchField%' OR HC LIKE '%$this->searchField%' OR NOMBRE_PTE LIKE '%$this->searchField%' OR UBICACION LIKE '%$this->searchField%' OR DIAGNOSTICO LIKE '%$this->searchField%' OR ORIGEN LIKE '%$this->searchField%' OR CLASIFICACION_MEDICO LIKE '%$this->searchField%' OR HC LIKE '%$this->searchField%') ORDER BY $this->sortField $this->sortType ";

            } else {

                $sql = "SELECT WEB_VW_PTES_EN_HOSPITAL.*, ROWNUM AS ROWNUM_ FROM WEB_VW_PTES_EN_HOSPITAL WHERE PERSONA_MEDICO IN ($codes) ORDER BY ROWNUM_ $this->sortType";

            }

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # Ya no existe resultadso
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $pacientes = array();

            foreach ($data as $key) {

                $key['NUM'] = intval($key['ROWNUM_']);
                $key['FECHA_ADM'] = date('d-m-Y', strtotime($key['FECHA_ADM']));
                unset($key['PERSONA_MEDICO']);
                unset($key['ROWNUM_']);

                # Resultado de objeto

                # EXTRAER LOS EXAMNES DE LABORATORIO DEL PACIETNE HOSPITALIZADO
                $key['EXAMENES_LAB'] = $this->getResultadosLab($key['HC']);

                # eeliminar variable cargos de resutado json
                unset($key['CARGOS']);

                $pacientes[] = $key;
            }

            // RESULTADO DE CONSULTA
            $this->notResults($pacientes);

            # Order by asc to desc
            $PACIENTES = $this->get_Order_Pagination($pacientes);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->get_page($PACIENTES, $this->offset, $this->limit),
                'total' => count($pacientes),
                'limit' => intval($this->limit),
                'offset' => intval($this->offset),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status' => true,
                    'data' => [],
                    'total' => 0,
                    'message' => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    private function getResultadosLab($nhc = '')
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        # CONULTA BDD GEMA
        $sql = " SELECT t.pk_numero_transaccion||','||trunc(t.fecha) cargo
            FROM   ccp_transacciones t, cad_admisiones ad
            WHERE  t.fk_paciente='$nhc'
            AND    t.fk_paciente=ad.pk_fk_paciente
            AND    t.fk_admision=ad.pk_numero_admision
            AND    ad.alta_clinica IS NULL
            AND    ad.discriminante IN ('EMA','SAO','HPN')
            AND    t.fk_arcgceco_cc_1||t.fk_arcgceco_cc_2||t.fk_arcgceco_cc_3 = gema.FUN_OBTIENE_PARAMETRO('CLB',sysdate, t.pk_fk_institucion)
            AND    t.pk_fk_arinvtm_tipo_m = 'SC' ";

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $_examenes = $stmt->fetchAll();

        # Si no exite resultadso
        if (false === $_examenes) {
            return array();
        }

        # setear valores de arrays
        $resultados_lab = array();

        foreach ($_examenes as $key) {

            $examenes = explode(',', $key['CARGO']);

            $resultados_lab[] = array(
                'SC' => $examenes[0],
                'FECHA' => date('d-m-Y', strtotime($examenes[1])),
            );
        }

        return $resultados_lab;
    }

    private function getSintomasMedico($sintoma = '', $COD_ESPECIALIDAD = 0)
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        # CONULTA BDD GEMA
        $sql = " SELECT * FROM AAS_SINTOMAS INNER JOIN AAS_SINTOMAS_ESPECIALIDAD ON AAS_SINTOMAS.PK_CODIGO=AAS_SINTOMAS_ESPECIALIDAD.PK_FK_SINTOMA WHERE AAS_SINTOMAS.DESCRIPCION LIKE '%$sintoma%' AND AAS_SINTOMAS_ESPECIALIDAD.PK_FK_ESPECIALIDAD = '$COD_ESPECIALIDAD' ";

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetchAll();

        $sistomas = array();

        if (false === $data) {
            return false;
        }

        foreach ($data as $key) {
            unset($key['PK_CODIGO']);
            $sistomas[] = $key['DESCRIPCION'];
        }

        return $sistomas;
    }

    private function getSintomas($sintoma = '')
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        # CONULTA BDD GEMA
        $sql = " SELECT * FROM AAS_SINTOMAS INNER JOIN AAS_SINTOMAS_ESPECIALIDAD ON AAS_SINTOMAS.PK_CODIGO=AAS_SINTOMAS_ESPECIALIDAD.PK_FK_SINTOMA WHERE AAS_SINTOMAS.DESCRIPCION LIKE '%$sintoma%' ";

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $sintoma = $stmt->fetchAll();

        if (false === $sintoma) {
            return false;
        }

        $valores = array();

        foreach ($sintoma as $key) {
            array_push($valores, "'" . $key['PK_FK_ESPECIALIDAD'] . "'");
        }

        return $valores;
    }

    public function getFoto_Medico($id_pk_codigo_med = 349)
    {
        # CONULTA BDD GEMA
        $sql = " SELECT FOTO_WEB FROM EDM_MEDICOS WHERE PK_CODIGO='$id_pk_codigo_med' ";

        return $img;
    }

    public function insertFoto_Medico($id_pk_codigo_med = 349)
    {
        # CONULTA BDD GEMA

        # $img = 'assets/fcd.png';

        return $this->foto_dummy;

        return true;
    }

# Ordenar array por campo
    public function orderMultiDimensionalArray($toOrderArray, $field, $inverse = 'desc')
    {
        $position = array();
        $newRow = array();
        foreach ($toOrderArray as $key => $row) {
            $position[$key] = $row[$field];
            $newRow[$key] = $row;
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
                $arr[] = $key;
                $NUM--;
            }

            return $arr;

        }

        # SI ES ASCENDENTE

        foreach ($arr_input as $key) {
            $key['NUM'] = $NUM;
            $arr[] = $key;
            $NUM++;
        }

        return $arr;
    }

    private function get_page(array $input, $pageNum, $perPage)
    {
        $start = ($pageNum - 1) * $perPage;
        $end = $start + $perPage;
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

    public function getFotos()
    {

        $fotos = $this->db->select('*', 'fots_medico', null, 'med like "%%"');

        $files = Helper\Files::get_files_in_dir('v1/assets/doctores/');

        $names = array();

        foreach ($files as $key => $value) {

            $name = explode('/', $value);
            $name = explode('copia', $name[3]);
            $name = strtoupper($name[0]);
            $n_ = explode(' ', $name);
            $name_N = (isset($n_[1])) ? $n_[1] . ' ' . $n_[0] : $n_[0];

            $fotos = $this->db->select('*', 'fotos_medicos', null, 'MED LIKE "%' . $name_N . '%"', null);
            if (false != $fotos) {
                $names[] = array(
                    'foto' => $value,
                    'name' => $fotos[0]['MED'],
                    'cod' => $fotos[0]['COD'],
                );
            }

        }

        return $names;

        $fotos = array(

            '850' => 'assets/doctores/850.jpg',
            '939' => 'assets/doctores/939.jpg',
            '10579' => 'assets/doctores/10579.jpg',
            '10791' => 'assets/doctores/10791.jpg',
            '010144' => 'assets/doctores/010144.jpg',
            '424' => 'assets/doctores/424.jpg',
            '010298' => 'assets/doctores/010298.jpg',
            '197' => 'assets/doctores/197.jpg',
            '190' => 'assets/doctores/190.jpg',
            '421' => 'assets/doctores/421.jpg',
            '10627' => 'assets/doctores/10627.jpg',
            '63' => 'assets/doctores/63.jpg',
            '10253' => 'assets/doctores/10253.jpg',

        );
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
        $no_permitidas = array("á", "Á", "%", "é", "í", "ó", "ú", "É", "Í", "Ó", "Ú", "ñ", "À", "Ã", "Ì", "Ò", "Ù", "Ã™", "Ã ", "Ã¨", "Ã¬", "Ã²", "Ã¹", "ç", "Ç", "Ã¢", "ê", "Ã®", "Ã´", "Ã»", "Ã‚", "ÃŠ", "ÃŽ", "Ã”", "Ã›", "ü", "Ã¶", "Ã–", "Ã¯", "Ã¤", "«", "Ò", "Ã", "Ã„", "Ã‹");
        $permitidas = array("a", "A", "", "e", "i", "o", "u", "E", "I", "O", "U", "n", "N", "A", "E", "I", "O", "U", "a", "e", "i", "o", "u", "c", "C", "a", "e", "i", "o", "u", "A", "E", "I", "O", "U", "u", "o", "O", "i", "a", "e", "U", "I", "A", "E");
        $texto = str_replace($no_permitidas, $permitidas, $cadena);
        return $texto;
    }

    private function mostrar_tildes($cadena)
    {
        $pspell_link = pspell_new("es");

        if (pspell_check($pspell_link, $cadena)) {
            $texto = "Ésta es una ortografía correcta";
        } else {
            $texto = "Lo siento, ortografía errónea";
        }
        return $texto;
    }

    private function sanear_string($string)
    {

        $string = trim($string);

        //Esta parte se encarga de eliminar cualquier caracter extraño
        $string = str_replace(
            array(">", "< ", ";", ",", ":", "%", "|", "-", "?", "¿"),
            ' ',
            $string
        );

        /*

        if ($this->lang == 'en') {
        $string = str_replace(
        array("CALLE", "TORRE MEDICA", "CONSULTORIO", "CONS."),
        array('STREET', 'MEDICAL TOWER', 'DOCTOR OFFICE', 'DOCTOR OFFICE'),
        $string
        );
        }

         */

        return trim($string);
    }

    public function Aud()
    {
        $aud = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $aud = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $aud = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $aud = $_SERVER['REMOTE_ADDR'];
        }

        $aud .= @$_SERVER['HTTP_USER_AGENT'];
        $aud .= gethostname();

        return sha1($aud);
    }

    private function defaultLang($especialidad)
    {
        $translator = new Translator('es_EC');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'MEDICINA OCUPACIONAL' => 'MEDICINA OCUPACIONAL',
            'ODONTOPEDIATRIA' => 'ODONTOPEDIATRÍA',
            'INFECTOLOGIA' => 'INFECTOLOGÍA',
            'TERAPIA DEL DOLOR' => 'TERAPIA DEL DOLOR',
            'ANESTESIOLOGIA' => 'ANESTESIOLOGÍA',
            'CARDIOLOGIA' => 'CARDIOLOGÍA',
            'CIRUGIA CARDIOTORACICA' => 'CIRUGÍA CARDIOTORACICA',
            'CIRUGIA GENERAL' => 'CIRUGÍA GENERAL',
            'CIRUGIA MAXILOFACIAL' => 'CIRUGÍA MAXILOFACIAL',
            'PSIQUIATRIA' => 'PSIQUIATRÍA',
            'RADIOLOGIA' => 'RADIOLOGÍA',
            'RADIOTERAPIA' => 'RADIOTERAPIA',
            'UROLOGIA' => 'UROLOGÍA',
            'GINECOLOGIA Y OBSTETRICIA' => 'GINECOLOGÍA Y OBSTETRICIA',
            'PEDIATRIA' => 'PEDIATRÍA',
            'NEONATOLOGIA' => 'NEONATOLOGÍA',
            'LABORATORIO' => 'LABORATORIO',
            'GENETICA HUMANA' => 'GENÉTICA HUMANA',
            'REUMATOLOGIA' => 'REUMATOLOGÍA',
            'MEDICINA FAMILIAR' => 'MEDICINA FAMILIAR',
            'HEMODINAMICA' => 'HEMODINÁMICA',
            'CIRUGÍA PROCTOLOGICA' => 'CIRUGÍA PROCTOLÓGICA',
            'FONIATRIA Y AUDIOLOGIA' => 'FONIATRÍA Y AUDIOLOGÍA',
            'SALUD PUBLICA' => 'SALUD PÚBLICA',
            'IMAGEN' => 'IMAGEN',
            'NEUROLOGIA PEDIATRICA' => 'NEUROLOGÍA PEDIÁTRICA',
            'NEUROFISIOLOGIA' => 'NEUROFISIOLOGÍA',
            'CARDIOLOGIA PEDIATRICA' => 'CARDIOLOGÍA PEDIÁTRICA',
            'INMUNOLOGIA' => 'INMUNOLOGÍA',
            'ALERGOLOGIA' => 'ALERGOLOGÍA',
            'EPIDEMIOLOGIA' => 'EPIDEMIOLOGÍA',
            'DIABETOLOGIA' => 'DIABETOLOGÍA',
            'PSICOLOGIA CLINICA' => 'PSICOLOGÍA CLÍNICA',
            'CUIDADOS INTENSIVOS' => 'CUIDADOS INTENSIVOS',
            'MEDICINA PERINATAL' => 'MEDICINA PERINATAL',
            'ODONTOLOGIA' => 'ODONTOLOGÍA',
            'MEDICINA PULMONAR' => 'MEDICINA PULMONAR',
            'MEDICINA TROPICAL' => 'MEDICINA TROPICAL',
            'FARMACIA' => 'FARMACIA',
            'CIRUGIA ONCOLOGICA' => 'CIRUGÍA ONCOLÓGICA',
            'CIRUGIA PEDIATRICA' => 'CIRUGÍA PEDIÁTRICA',
            'CIRUGIA PLASTICA' => 'CIRUGÍA PLASTICA',
            'CIRUGIA VASCULAR' => 'CIRUGÍA VASCULAR',
            'DERMATOLOGIA' => 'DERMATOLOGÍA',
            // 'ENDOCRINOLOGIA'                           => 'ENDOCRINOLOGÍA',
            'GASTROENTEROLOGIA' => 'GASTROENTEROLOGÍA',
            'HEMATOLOGIA' => 'HEMATOLOGÍA',
            'MEDICINA FISICA O REHABILITACION' => 'MEDICINA FÍSICA O REHABILITACIÓN',
            'MEDICINA INTERNA' => 'MEDICINA INTERNA',
            'MEDICINA NUCLEAR' => 'MEDICINA NUCLEAR',
            'NEFROLOGIA' => 'NEFROLOGÍA',
            'NEUMOLOGIA' => 'NEUMOLOGÍA',
            'NEUROCIRUGIA' => 'NEUROCIRUGÍA',
            'NEUROLOGIA' => 'NEUROLOGÍA',
            'OFTALMOLOGIA' => 'OFTALMOLOGÍA',
            'ONCOLOGIA MEDICA' => 'ONCOLOGÍA MÉDICA',
            'ORTOPEDIA Y TRAUMATOLOGIA' => 'ORTOPEDIA Y TRAUMATOLOGÍA',
            'OTORRINOLARINGOLOGIA' => 'OTORRINOLARINGOLOGÍA',
            'PATOLOGIA' => 'PATOLOGÍA',
            'DPTO.CLINICA' => 'DPTO.CLÍNICA',
            'DPTO.CIRUGIA' => 'DPTO.CIRUGÍA',
            'DPTO.PEDIATRIA' => 'DPTO.PEDIATRÍA',
            'DPTO.GINECOLOGIA' => 'DPTO.GINECOLOGÍA',
            'NO REGISTRADA' => 'NO REGISTRADA',
            'FARMACIA' => 'FARMACIA',
            'CUIDADOS INTENSIVOS PEDIATRICOS' => 'CUIDADOS INTENSIVOS PEDIATRICOS',
            'ONCOLOGIA HEMATOLOGIA' => 'ONCOLOGÍA HEMATOLOGÍA',
            'GASTROENTEROLOGIA PEDIATRICA' => 'GASTROENTEROLOGÍA PEDIÁTRICA',
            'HEMATOLOGIA PEDIATRICA' => 'HEMATOLOGÍA PEDIÁTRICA',
            'ALERGOLOGIA PEDIATRICA' => 'ALERGOLOGIA PEDIÁTRICA',
            'NEFROLOGIA PEDIATRICA' => 'NEFROLOGÍA PEDIÁTRICA',
            'ECOCARDIOGRAFIA' => 'ECOCARDIOGRAFÍA',
            'NUTRICION' => 'NUTRICIÓN',
            'ELECTROFISIOLOGIA' => 'ELECTROFISIOLOGÍA',
            'GERIATRIA' => 'GERIATRÍA',
            'PSICOLOGIA PEDIATRICA' => 'PSICOLOGÍA PEDIÁTRICA',
            'EMERGENCIA' => 'EMERGENCIA',
            'SOPORTE METABOLICO Y TERAPIA NUTRICIONAL' => 'SOPORTE METABÓLICO Y TERAPIA NUTRICIONAL',
            'INFECTOLOGIA PEDIATRICA' => 'INFECTOLOGÍA PEDIÁTRICA',
            'MEDICINA OCUPACIONAL' => 'MEDICINA OCUPACIONAL',
            'ODONTOPEDIATRIA' => 'ODONTOPEDIATRÍA',
            'INFECTOLOGIA' => 'INFECTOLOGÍA',
            'TERAPIA DEL DOLOR' => 'TERAPIA DEL DOLOR',
            'ANESTESIOLOGIA' => 'ANESTESIOLOGÍA',
            'CARDIOLOGIA' => 'CARDIOLOGÍA',
            'CIRUGIA CARDIOTORACICA' => 'CIRUGÍA CARDIOTORÁCICA',
            'CIRUGIA GENERAL' => 'CIRUGÍA GENERAL',
            'CIRUGIA MAXILOFACIAL' => 'CIRUGÍA MAXILOFACIAL',
            'PSIQUIATRIA' => 'PSIQUIATRÍA',
            'RADIOLOGIA' => 'RADIOLOGÍA',
            'RADIOTERAPIA' => 'RADIOTERAPIA',
            'UROLOGIA' => 'UROLOGÍA',
            'GINECOLOGIA Y OBSTETRICIA' => 'GINECOLOGÍA Y OBSTETRICIA',
            'PEDIATRIA' => 'PEDIATRÍA',
            'NEONATOLOGIA' => 'NEONATOLOGÍA',
            'LABORATORIO' => 'LABORATORIO',
            'GENETICA HUMANA' => 'GENÉTICA HUMANA',
            'REUMATOLOGIA' => 'REUMATOLOGÍA',
            'MEDICINA FAMILIAR' => 'MEDICINA FAMILIAR',
            'HEMODINAMICA' => 'HEMODINÁMICA',
            'CIRUGIA PROCTOLOGICA' => 'CIRUGÍA PROCTOLÓGICA',
            'FONIATRIA Y AUDIOLOGIA' => 'FONIATRÍA Y AUDIOLOGÍA',
            'SALUD PUBLICA' => 'SALUD PÚBLICA',
            'IMAGEN' => 'IMAGEN',
            'NEUROLOGIA PEDIATRICA' => 'NEUROLOGÍA PEDIÁTRICA',
            'UROLOGIA PEDIATRICA' => 'UROLOGÍA PEDIÁTRICA',
            'NEUROFISIOLOGIA' => 'NEUROFISIOLOGÍA',
            'CARDIOLOGIA PEDIATRICA' => 'CARDIOLOGÍA PEDIÁTRICA',
            'INMUNOLOGIA' => 'INMUNOLOGÍA',
            'ALERGOLOGIA' => 'ALERGOLOGÍA',
            'EPIDEMIOLOGIA' => 'EPIDEMIOLOGÍA',
            'DIABETOLOGIA' => 'DIABETOLOGÍA',
            'PSICOLOGIA CLINICA' => 'PSICOLOGÍA CLÍNICA',
            'CUIDADOS INTENSIVOS' => 'CUIDADOS INTENSIVOS',
            'MEDICINA PERINATAL' => 'MEDICINA PERINATAL',
            'ODONTOLOGIA' => 'ODONTOLOGÍA',
            'MEDICINA PULMONAR' => 'MEDICINA PULMONAR',
            'MEDICINA TROPICAL' => 'MEDICINA TROPICAL',
            'FARMACIA' => 'FARMACIA',
            'CIRUGIA ONCOLOGICA' => 'CIRUGÍA ONCOLÓGICA',
            'CIRUGIA PEDIATRICA' => 'CIRUGÍA PEDIÁTRICA',
            'CIRUGIA PLASTICA' => 'CIRUGÍA PLÁSTICA',
            'CIRUGIA VASCULAR' => 'CIRUGÍA VASCULAR',
            'DERMATOLOGIA' => 'DERMATOLOGÍA',
            // 'ENDOCRINOLOGIA'                           => 'ENDOCRINOLOGÍA',
            'GASTROENTEROLOGIA' => 'GASTROENTEROLOGÍA',
            'HEMATOLOGIA' => 'HEMATOLOGÍA',
            'MEDICINA FISICA O REHABILITACION' => 'MEDICINA FÍSICA O REHABILITACIÓN',
            'MEDICINA INTERNA' => 'MEDICINA INTERNA',
            'MEDICINA NUCLEAR' => 'MEDICINA NUCLEAR',
            'NEFROLOGIA' => 'NEFROLOGÍA',
            'NEUMOLOGIA' => 'NEUMOLOGÍA',
            'NEUROCIRUGIA' => 'NEUROCIRUGÍA',
            'NEUROLOGIA' => 'NEUROLOGÍA',
            'OFTALMOLOGIA' => 'OFTALMOLOGÍA',
            'ONCOLOGIA MEDICA' => 'ONCOLOGÍA MÉDICA',
            'ORTOPEDIA Y TRAUMATOLOGIA' => 'ORTOPEDIA Y TRAUMATOLOGÍA',
            'OTORRINOLARINGOLOGIA' => 'OTORRINOLARINGOLOGÍA',
            'PATOLOGIA' => 'PATOLOGÍA',
            'DPTO.CLINICA' => 'DPTO.CLÍNICA',
            'DPTO.CIRUGIA' => 'DPTO.CIRUGÍA',
            'DPTO.PEDIATRIA' => 'DPTO.PEDIATRÍA',
            'DPTO.GINECOLOGIA' => 'DPTO.GINECOLOGÍA',
            'NO REGISTRADA' => 'NO REGISTRADA',
            'FARMACIA' => 'FARMACIA',
            'CUIDADOS INTENSIVOS PEDIATRICOS' => 'CUIDADOS INTENSIVOS PEDIÁTRICOS',
            'ONCOLOGIA HEMATOLOGIA' => 'ONCOLOGÍA HEMATOLOGÍA',
            'GASTROENTEROLOGIA PEDIATRICA' => 'GASTROENTEROLOGÍA PEDIÁTRICA',
            'HEMATOLOGIA PEDIATRICA' => 'HEMATOLOGÍA PEDIÁTRICA',
            'ALERGOLOGIA PEDIATRICA' => 'ALERGOLOGÍA PEDIÁTRICA',
            'NEFROLOGIA PEDIATRICA' => 'NEFROLOGÍA PEDIÁTRICA',
            'ECOCARDIOGRAFIA' => 'ECOCARDIOGRAFÍA',
            'NUTRICION' => 'NUTRICIÓN',
            'ELECTROFISIOLOGIA' => 'ELECTROFISIOLOGÍA',
            'GERIATRIA' => 'GERIATRÍA',
            'PSICOLOGIA PEDIATRICA' => 'PSICOLOGÍA PEDIÁTRICA',
            'EMERGENCIA' => 'EMERGENCIA',
            'SOPORTE METABOLICO Y TERAPIA NUTRICIONAL' => 'SOPORTE METABÓLICO Y TERAPIA NUTRICIONAL',
            'INFECTOLOGIA PEDIATRICA' => 'INFECTOLOGÍA PEDIÁTRICA',
            'CIRUGIA CARDIACA PEDIATRICA' => 'CIRUGÍA CARDÍACA PEDIÁTRICA',
            'CIRUGIA CARDIACA ADULTOS' => 'CIRUGÍA CARDÍACA ADULTOS',
        ], 'es_EC');

        return $translator->trans($especialidad);
    }

    private function traslateEspecialidad($especialidad)
    {
        $translator = new Translator('en_US');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'MEDICINA OCUPACIONAL' => 'OCCUPATIONAL MEDICINE',
            'ODONTOPEDIATRIA' => 'PEDIATRIC DENTISTRY',
            'INFECTOLOGIA' => 'INFECTOLOGY',
            'TERAPIA DEL DOLOR' => 'PAIN THERAPY',
            'ANESTESIOLOGIA' => 'ANESTHESIOLOGY',
            'CARDIOLOGIA' => 'CARDIOLOGY',
            'CIRUGIA CARDIOTORACICA' => 'CARDIOTORACIC SURGERY',
            'CIRUGIA GENERAL' => 'GENERAL SURGERY',
            'CIRUGIA MAXILOFACIAL' => 'MAXILLOFACIAL SURGERY',
            'PSIQUIATRIA' => 'PSYCHIATRY',
            'RADIOLOGIA' => 'RADIOLOGY',
            'RADIOTERAPIA' => 'RADIOTHERAPY',
            'UROLOGIA' => 'UROLOGY',
            'GINECOLOGIA Y OBSTETRICIA' => 'GYNECOLOGY AND OBSTETRICS',
            'PEDIATRIA' => 'PEDIATRICS',
            'NEONATOLOGIA' => 'NEONATOLOGY',
            'LABORATORIO' => 'LABORATORY',
            'GENETICA HUMANA' => 'HUMAN GENETICS',
            'REUMATOLOGIA' => 'RHEUMATOLOGY',
            'MEDICINA FAMILIAR' => 'FAMILY MEDICINE',
            'HEMODINAMICA' => 'HEMODYNAMICS',
            'CIRUGIA PROCTOLOGICA' => 'PROCTOLOGICAL SURGERY',
            'FONIATRIA Y AUDIOLOGIA' => 'PHYSIOTRY AND AUDIOLOGY',
            'SALUD PUBLICA' => 'PUBLIC HEALTH',
            'IMAGEN' => 'IMAGE',
            'NEUROLOGIA PEDIATRICA' => 'PEDIATRIC NEUROLOGY',
            'NEUROFISIOLOGIA' => 'NEUROPHYSIOLOGY',
            'CARDIOLOGIA PEDIATRICA' => 'PEDIATRIC CARDIOLOGY',
            'INMUNOLOGIA' => 'IMMUNOLOGY',
            'ALERGOLOGIA' => 'ALLERGOLOGY',
            'EPIDEMIOLOGIA' => 'EPIDEMIOLOGY',
            'DIABETOLOGIA' => 'DIABETOLOGY',
            'PSICOLOGIA CLINICA' => 'CLINICAL PSYCHOLOGY',
            'CUIDADOS INTENSIVOS' => 'INTENSIVE CARE',
            'MEDICINA PERINATAL' => 'PERINATAL MEDICINE',
            'ODONTOLOGIA' => 'ODONTOLOGY',
            'MEDICINA PULMONAR' => 'PULMONARY MEDICINE',
            'MEDICINA TROPICAL' => 'TROPICAL MEDICINE',
            'FARMACIA' => 'PHARMACY',
            'CIRUGIA ONCOLOGICA' => 'ONCOLOGIC SURGERY',
            'CIRUGIA PEDIATRICA' => 'PEDIATRIC SURGERY',
            'CIRUGIA PLASTICA' => 'PLASTIC SURGERY',
            'CIRUGIA VASCULAR' => 'VASCULAR SURGERY',
            'DERMATOLOGIA' => 'DERMATOLOGY',
            // 'ENDOCRINOLOGIA'                           => 'ENDOCRINOLOGY',
            'GASTROENTEROLOGIA' => 'GASTROENTEROLOGY',
            'HEMATOLOGIA' => 'HEMATOLOGY',
            'MEDICINA FISICA O REHABILITACION' => 'PHYSICAL MEDICINE OR REHABILITATION',
            'MEDICINA INTERNA' => 'INTERNAL MEDICINE',
            'MEDICINA NUCLEAR' => 'NUCLEAR MEDICINE',
            'NEFROLOGIA' => 'NEPHROLOGY',
            'NEUMOLOGIA' => 'PNEUMOLOGY',
            'NEUROCIRUGIA' => 'NEUROSURGERY',
            'NEUROLOGIA' => 'NEUROLOGY',
            'OFTALMOLOGIA' => 'OPHTHALMOLOGY',
            'ONCOLOGIA MEDICA' => 'MEDICAL ONCOLOGY',
            'ORTOPEDIA Y TRAUMATOLOGIA' => 'ORTHOPEDICS AND TRAUMATOLOGY',
            'OTORRINOLARINGOLOGIA' => 'OTORHINOLARYNGOLOGY',
            'PATOLOGIA' => 'PATHOLOGY',
            'DPTO.CLINICA' => 'DEPT.CLINIC',
            'DPTO.CIRUGIA' => 'DPTO.CIRUGIA',
            'DPTO.PEDIATRIA' => 'DPTO.PEDIATRIA',
            'DPTO.GINECOLOGIA' => 'DPTO.GINECOLOGIA',
            'NO REGISTRADA' => 'NOT REGISTERED',
            'FARMACIA' => 'PHARMACY',
            'CUIDADOS INTENSIVOS PEDIATRICOS' => 'PEDIATRIC INTENSIVE CARE',
            'ONCOLOGIA HEMATOLOGIA' => 'ONCOLOGY  HEMATOLOGY',
            'GASTROENTEROLOGIA PEDIATRICA' => 'PEDIATRIC GASTROENTEROLOGY',
            'HEMATOLOGIA PEDIATRICA' => 'PEDIATRIC HEMATOLOGY',
            'ALERGOLOGIA PEDIATRICA' => 'PEDIATRIC ALERGOLOGY',
            'NEFROLOGIA PEDIATRICA' => 'PEDIATRIC NEPHROLOGY',
            'ECOCARDIOGRAFIA' => 'ECOCARDIOGRAPHY',
            'NUTRICION' => 'NUTRITION',
            'ELECTROFISIOLOGIA' => 'ELECTROPHYSIOLOGY',
            'GERIATRIA' => 'GERIATRICS',
            'PSICOLOGIA PEDIATRICA' => 'PEDIATRIC PSYCHOLOGY',
            'EMERGENCIA' => 'EMERGENCY',
            'SOPORTE METABOLICO Y TERAPIA NUTRICIONAL' => 'METABOLIC SUPPORT AND NUTRITIONAL THERAPY',
            'INFECTOLOGIA PEDIATRICA' => 'PEDIATRIC INFECTOLOGY',
            'MEDICINA OCUPACIONAL' => 'OCCUPATIONAL MEDICINE',
            'ODONTOPEDIATRIA' => 'PEDIATRIC DENTISTRY',
            'INFECTOLOGIA' => 'INFECTOLOGY',
            'TERAPIA DEL DOLOR' => 'PAIN THERAPY',
            'ANESTESIOLOGIA' => 'ANESTHESIOLOGY',
            'CARDIOLOGIA' => 'CARDIOLOGY',
            'CIRUGIA CARDIOTORACICA' => 'CARDIOTORACIC SURGERY',
            'CIRUGIA GENERAL' => 'GENERAL SURGERY',
            'CIRUGIA MAXILOFACIAL' => 'MAXILLOFACIAL SURGERY',
            'PSIQUIATRIA' => 'PSYCHIATRY',
            'RADIOLOGIA' => 'RADIOLOGY',
            'RADIOTERAPIA' => 'RADIOTHERAPY',
            'UROLOGIA' => 'UROLOGY',
            'GINECOLOGIA Y OBSTETRICIA' => 'GYNECOLOGY AND OBSTETRICS',
            'PEDIATRIA' => 'PEDIATRICS',
            'NEONATOLOGIA' => 'NEONATOLOGY',
            'LABORATORIO' => 'LABORATORY',
            'GENETICA HUMANA' => 'HUMAN GENETICS',
            'REUMATOLOGIA' => 'RHEUMATOLOGY',
            'MEDICINA FAMILIAR' => 'FAMILY MEDICINE',
            'HEMODINAMICA' => 'HEMODYNAMICS',
            'CIRUGIA PROCTOLOGICA' => 'PROCTOLOGICAL SURGERY',
            'FONIATRIA Y AUDIOLOGIA' => 'PHYSIOTRY AND AUDIOLOGY',
            'SALUD PUBLICA' => 'PUBLIC HEALTH',
            'IMAGEN' => 'IMAGE',
            'NEUROLOGIA PEDIATRICA' => 'PEDIATRIC NEUROLOGY',
            'UROLOGIA PEDIATRICA' => 'PEDIATRIC UROLOGY',
            'NEUROFISIOLOGIA' => 'NEUROPHYSIOLOGY',
            'CARDIOLOGIA PEDIATRICA' => 'PEDIATRIC CARDIOLOGY',
            'INMUNOLOGIA' => 'IMMUNOLOGY',
            'ALERGOLOGIA' => 'ALLERGOLOGY',
            'EPIDEMIOLOGIA' => 'EPIDEMIOLOGY',
            'DIABETOLOGIA' => 'DIABETOLOGY',
            'PSICOLOGIA CLINICA' => 'CLINICAL PSYCHOLOGY',
            'CUIDADOS INTENSIVOS' => 'INTENSIVE CARE',
            'MEDICINA PERINATAL' => 'PERINATAL MEDICINE',
            'ODONTOLOGIA' => 'ODONTOLOGY',
            'MEDICINA PULMONAR' => 'PULMONARY MEDICINE',
            'MEDICINA TROPICAL' => 'TROPICAL MEDICINE',
            'FARMACIA' => 'PHARMACY',
            'CIRUGIA ONCOLOGICA' => 'ONCOLOGIC SURGERY',
            'CIRUGIA PEDIATRICA' => 'PEDIATRIC SURGERY',
            'CIRUGIA PLASTICA' => 'PLASTIC SURGERY',
            'CIRUGIA VASCULAR' => 'VASCULAR SURGERY',
            'DERMATOLOGIA' => 'DERMATOLOGY',
            // 'ENDOCRINOLOGIA'                           => 'ENDOCRINOLOGY',
            'GASTROENTEROLOGIA' => 'GASTROENTEROLOGY',
            'HEMATOLOGIA' => 'HEMATOLOGY',
            'MEDICINA FISICA O REHABILITACION' => 'PHYSICAL MEDICINE OR REHABILITATION',
            'MEDICINA INTERNA' => 'INTERNAL MEDICINE',
            'MEDICINA NUCLEAR' => 'NUCLEAR MEDICINE',
            'NEFROLOGIA' => 'NEPHROLOGY',
            'NEUMOLOGIA' => 'PNEUMOLOGY',
            'NEUROCIRUGIA' => 'NEUROSURGERY',
            'NEUROLOGIA' => 'NEUROLOGY',
            'OFTALMOLOGIA' => 'OPHTHALMOLOGY',
            'ONCOLOGIA MEDICA' => 'MEDICAL ONCOLOGY',
            'ORTOPEDIA Y TRAUMATOLOGIA' => 'ORTHOPEDICS AND TRAUMATOLOGY',
            'OTORRINOLARINGOLOGIA' => 'OTORHINOLARYNGOLOGY',
            'PATOLOGIA' => 'PATHOLOGY',
            'DPTO.CLINICA' => 'CLINIC DEPARTMENT',
            'DPTO.CIRUGIA' => 'SURGERY DEPARTMENT',
            'DPTO.PEDIATRIA' => 'PEDIATRIC DEPARTMENT',
            'DPTO.GINECOLOGIA' => 'GYNECOLOGY DEPARTMENT',
            'NO REGISTRADA' => 'NOT REGISTERED',
            'FARMACIA' => 'PHARMACY',
            'CUIDADOS INTENSIVOS PEDIATRICOS' => 'PEDIATRIC INTENSIVE CARE',
            'ONCOLOGIA HEMATOLOGIA' => 'ONCOLOGY  HEMATOLOGY',
            'GASTROENTEROLOGIA PEDIATRICA' => 'PEDIATRIC GASTROENTEROLOGY',
            'HEMATOLOGIA PEDIATRICA' => 'PEDIATRIC HEMATOLOGY',
            'ALERGOLOGIA PEDIATRICA' => 'PEDIATRIC ALERGOLOGY',
            'NEFROLOGIA PEDIATRICA' => 'PEDIATRIC NEPHROLOGY',
            'ECOCARDIOGRAFIA' => 'ECOCARDIOGRAPHY',
            'NUTRICION' => 'NUTRITION',
            'ELECTROFISIOLOGIA' => 'ELECTROPHYSIOLOGY',
            'GERIATRIA' => 'GERIATRICS',
            'PSICOLOGIA PEDIATRICA' => 'PEDIATRIC PSYCHOLOGY',
            'EMERGENCIA' => 'EMERGENCY',
            'SOPORTE METABOLICO Y TERAPIA NUTRICIONAL' => 'METABOLIC SUPPORT AND NUTRITIONAL THERAPY',
            'INFECTOLOGIA PEDIATRICA' => 'PEDIATRIC INFECTOLOGY',
            'CIRUGIA CARDIACA PEDIATRICA' => 'PEDIATRIC CARDIAC SURGERY',
            'CIRUGIA CARDIACA ADULTOS' => 'CARDIAC SURGERY ADULTS',
        ], 'en_US');

        return $translator->trans($especialidad);
    }

    public function buscarEspecialidad($especialidad)
    {
        $especialidades = array(
            'MEDICINA OCUPACIONAL' => 'OCCUPATIONAL MEDICINE',
            'ODONTOPEDIATRIA' => 'PEDIATRIC DENTISTRY',
            'INFECTOLOGIA' => 'INFECTOLOGY',
            'TERAPIA DEL DOLOR' => 'PAIN THERAPY',
            'ANESTESIOLOGIA' => 'ANESTHESIOLOGY',
            'CARDIOLOGIA' => 'CARDIOLOGY',
            'CIRUGIA CARDIOTORACICA' => 'CARDIOTORACIC SURGERY',
            'CIRUGIA GENERAL' => 'GENERAL SURGERY',
            'CIRUGIA MAXILOFACIAL' => 'MAXILLOFACIAL SURGERY',
            'PSIQUIATRIA' => 'PSYCHIATRY',
            'RADIOLOGIA' => 'RADIOLOGY',
            'RADIOTERAPIA' => 'RADIOTHERAPY',
            'UROLOGIA' => 'UROLOGY',
            'GINECOLOGIA Y OBSTETRICIA' => 'GYNECOLOGY AND OBSTETRICS',
            'PEDIATRIA' => 'PEDIATRICS',
            'NEONATOLOGIA' => 'NEONATOLOGY',
            'LABORATORIO' => 'LABORATORY',
            'GENETICA HUMANA' => 'HUMAN GENETICS',
            'REUMATOLOGIA' => 'RHEUMATOLOGY',
            'MEDICINA FAMILIAR' => 'FAMILY MEDICINE',
            'HEMODINAMICA' => 'HEMODYNAMICS',
            'CIRUGIA PROCTOLOGICA' => 'PROCTOLOGICAL SURGERY',
            'FONIATRIA Y AUDIOLOGIA' => 'PHYSIOTRY AND AUDIOLOGY',
            'SALUD PUBLICA' => 'PUBLIC HEALTH',
            'IMAGEN' => 'IMAGE',
            'NEUROLOGIA PEDIATRICA' => 'PEDIATRIC NEUROLOGY',
            'NEUROFISIOLOGIA' => 'NEUROPHYSIOLOGY',
            'CARDIOLOGIA PEDIATRICA' => 'PEDIATRIC CARDIOLOGY',
            'INMUNOLOGIA' => 'IMMUNOLOGY',
            'ALERGOLOGIA' => 'ALLERGOLOGY',
            'EPIDEMIOLOGIA' => 'EPIDEMIOLOGY',
            'DIABETOLOGIA' => 'DIABETOLOGY',
            'PSICOLOGIA CLINICA' => 'CLINICAL PSYCHOLOGY',
            'CUIDADOS INTENSIVOS' => 'INTENSIVE CARE',
            'MEDICINA PERINATAL' => 'PERINATAL MEDICINE',
            'ODONTOLOGIA' => 'ODONTOLOGY',
            'MEDICINA PULMONAR' => 'PULMONARY MEDICINE',
            'MEDICINA TROPICAL' => 'TROPICAL MEDICINE',
            'FARMACIA' => 'PHARMACY',
            'CIRUGIA ONCOLOGICA' => 'ONCOLOGIC SURGERY',
            'CIRUGIA PEDIATRICA' => 'PEDIATRIC SURGERY',
            'CIRUGIA PLASTICA' => 'PLASTIC SURGERY',
            'CIRUGIA VASCULAR' => 'VASCULAR SURGERY',
            'DERMATOLOGIA' => 'DERMATOLOGY',
            // 'ENDOCRINOLOGIA'                           => 'ENDOCRINOLOGY',
            'GASTROENTEROLOGIA' => 'GASTROENTEROLOGY',
            'HEMATOLOGIA' => 'HEMATOLOGY',
            'MEDICINA FISICA O REHABILITACION' => 'PHYSICAL MEDICINE OR REHABILITATION',
            'MEDICINA INTERNA' => 'INTERNAL MEDICINE',
            'MEDICINA NUCLEAR' => 'NUCLEAR MEDICINE',
            'NEFROLOGIA' => 'NEPHROLOGY',
            'NEUMOLOGIA' => 'PNEUMOLOGY',
            'NEUROCIRUGIA' => 'NEUROSURGERY',
            'NEUROLOGIA' => 'NEUROLOGY',
            'OFTALMOLOGIA' => 'OPHTHALMOLOGY',
            'ONCOLOGIA MEDICA' => 'MEDICAL ONCOLOGY',
            'TRAUMATOLOGIA' => 'TRAUMATOLOGY',
            'ORTOPEDIA Y TRAUMATOLOGIA' => 'ORTHOPEDICS AND TRAUMATOLOGY',
            'OTORRINOLARINGOLOGIA' => 'OTORHINOLARYNGOLOGY',
            'PATOLOGIA' => 'PATHOLOGY',
            'DPTO.CLINICA' => 'DEPT.CLINIC',
            'DPTO.CIRUGIA' => 'DPTO.CIRUGIA',
            'DPTO.PEDIATRIA' => 'DPTO.PEDIATRIA',
            'DPTO.GINECOLOGIA' => 'DPTO.GINECOLOGIA',
            'NO REGISTRADA' => 'NOT REGISTERED',
            'FARMACIA' => 'PHARMACY',
            'CUIDADOS INTENSIVOS PEDIATRICOS' => 'PEDIATRIC INTENSIVE CARE',
            'ONCOLOGIA HEMATOLOGIA' => 'ONCOLOGY  HEMATOLOGY',
            'GASTROENTEROLOGIA PEDIATRICA' => 'PEDIATRIC GASTROENTEROLOGY',
            'HEMATOLOGIA PEDIATRICA' => 'PEDIATRIC HEMATOLOGY',
            'ALERGOLOGIA PEDIATRICA' => 'PEDIATRIC ALERGOLOGY',
            'NEFROLOGIA PEDIATRICA' => 'PEDIATRIC NEPHROLOGY',
            'ECOCARDIOGRAFIA' => 'ECOCARDIOGRAPHY',
            'NUTRICION' => 'NUTRITION',
            'ELECTROFISIOLOGIA' => 'ELECTROPHYSIOLOGY',
            'GERIATRIA' => 'GERIATRICS',
            'PSICOLOGIA PEDIATRICA' => 'PEDIATRIC PSYCHOLOGY',
            'EMERGENCIA' => 'EMERGENCY',
            'SOPORTE METABOLICO Y TERAPIA NUTRICIONAL' => 'METABOLIC SUPPORT AND NUTRITIONAL THERAPY',
            'INFECTOLOGIA PEDIATRICA' => 'PEDIATRIC INFECTOLOGY',
            'MEDICINA OCUPACIONAL' => 'OCCUPATIONAL MEDICINE',
            'ODONTOPEDIATRIA' => 'PEDIATRIC DENTISTRY',
            'INFECTOLOGIA' => 'INFECTOLOGY',
            'TERAPIA DEL DOLOR' => 'PAIN THERAPY',
            'ANESTESIOLOGIA' => 'ANESTHESIOLOGY',
            'CARDIOLOGIA' => 'CARDIOLOGY',
            'CIRUGIA CARDIOTORACICA' => 'CARDIOTORACIC SURGERY',
            'CIRUGIA GENERAL' => 'GENERAL SURGERY',
            'CIRUGIA MAXILOFACIAL' => 'MAXILLOFACIAL SURGERY',
            'PSIQUIATRIA' => 'PSYCHIATRY',
            'RADIOLOGIA' => 'RADIOLOGY',
            'RADIOTERAPIA' => 'RADIOTHERAPY',
            'UROLOGIA' => 'UROLOGY',
            'GINECOLOGIA Y OBSTETRICIA' => 'GYNECOLOGY AND OBSTETRICS',
            'PEDIATRIA' => 'PEDIATRICS',
            'NEONATOLOGIA' => 'NEONATOLOGY',
            'LABORATORIO' => 'LABORATORY',
            'GENETICA HUMANA' => 'HUMAN GENETICS',
            'REUMATOLOGIA' => 'RHEUMATOLOGY',
            'MEDICINA FAMILIAR' => 'FAMILY MEDICINE',
            'HEMODINAMICA' => 'HEMODYNAMICS',
            'CIRUGIA PROCTOLOGICA' => 'PROCTOLOGICAL SURGERY',
            'FONIATRIA Y AUDIOLOGIA' => 'PHYSIOTRY AND AUDIOLOGY',
            'SALUD PUBLICA' => 'PUBLIC HEALTH',
            'IMAGEN' => 'IMAGE',
            'NEUROLOGIA PEDIATRICA' => 'PEDIATRIC NEUROLOGY',
            'NEUROFISIOLOGIA' => 'NEUROPHYSIOLOGY',
            'CARDIOLOGIA PEDIATRICA' => 'PEDIATRIC CARDIOLOGY',
            'INMUNOLOGIA' => 'IMMUNOLOGY',
            'ALERGOLOGIA' => 'ALLERGOLOGY',
            'EPIDEMIOLOGIA' => 'EPIDEMIOLOGY',
            'DIABETOLOGIA' => 'DIABETOLOGY',
            'PSICOLOGIA CLINICA' => 'CLINICAL PSYCHOLOGY',
            'CUIDADOS INTENSIVOS' => 'INTENSIVE CARE',
            'MEDICINA PERINATAL' => 'PERINATAL MEDICINE',
            'ODONTOLOGIA' => 'ODONTOLOGY',
            'MEDICINA PULMONAR' => 'PULMONARY MEDICINE',
            'MEDICINA TROPICAL' => 'TROPICAL MEDICINE',
            'FARMACIA' => 'PHARMACY',
            'CIRUGIA ONCOLOGICA' => 'ONCOLOGIC SURGERY',
            'CIRUGIA PEDIATRICA' => 'PEDIATRIC SURGERY',
            'CIRUGIA PLASTICA' => 'PLASTIC SURGERY',
            'CIRUGIA VASCULAR' => 'VASCULAR SURGERY',
            'DERMATOLOGIA' => 'DERMATOLOGY',
            // 'ENDOCRINOLOGIA'                           => 'ENDOCRINOLOGY',
            'GASTROENTEROLOGIA' => 'GASTROENTEROLOGY',
            'HEMATOLOGIA' => 'HEMATOLOGY',
            'MEDICINA FISICA O REHABILITACION' => 'PHYSICAL MEDICINE OR REHABILITATION',
            'MEDICINA INTERNA' => 'INTERNAL MEDICINE',
            'MEDICINA NUCLEAR' => 'NUCLEAR MEDICINE',
            'NEFROLOGIA' => 'NEPHROLOGY',
            'NEUMOLOGIA' => 'PNEUMOLOGY',
            'NEUROCIRUGIA' => 'NEUROSURGERY',
            'NEUROLOGIA' => 'NEUROLOGY',
            'OFTALMOLOGIA' => 'OPHTHALMOLOGY',
            'ONCOLOGIA MEDICA' => 'MEDICAL ONCOLOGY',
            'ORTOPEDIA Y TRAUMATOLOGIA' => 'ORTHOPEDICS AND TRAUMATOLOGY',
            'OTORRINOLARINGOLOGIA' => 'OTORHINOLARYNGOLOGY',
            'PATOLOGIA' => 'PATHOLOGY',
            'DPTO.CLINICA' => 'CLINIC DEPARTMENT',
            'DPTO.CIRUGIA' => 'SURGERY DEPARTMENT',
            'DPTO.PEDIATRIA' => 'PEDIATRIC DEPARTMENT',
            'DPTO.GINECOLOGIA' => 'GYNECOLOGY DEPARTMENT',
            'NO REGISTRADA' => 'NOT REGISTERED',
            'FARMACIA' => 'PHARMACY',
            'CUIDADOS INTENSIVOS PEDIATRICOS' => 'PEDIATRIC INTENSIVE CARE',
            'ONCOLOGIA HEMATOLOGIA' => 'ONCOLOGY  HEMATOLOGY',
            'GASTROENTEROLOGIA PEDIATRICA' => 'PEDIATRIC GASTROENTEROLOGY',
            'HEMATOLOGIA PEDIATRICA' => 'PEDIATRIC HEMATOLOGY',
            'ALERGOLOGIA PEDIATRICA' => 'PEDIATRIC ALERGOLOGY',
            'NEFROLOGIA PEDIATRICA' => 'PEDIATRIC NEPHROLOGY',
            'ECOCARDIOGRAFIA' => 'ECOCARDIOGRAPHY',
            'NUTRICION' => 'NUTRITION',
            'ELECTROFISIOLOGIA' => 'ELECTROPHYSIOLOGY',
            'GERIATRIA' => 'GERIATRICS',
            'PSICOLOGIA PEDIATRICA' => 'PEDIATRIC PSYCHOLOGY',
            'EMERGENCIA' => 'EMERGENCY',
            'SOPORTE METABOLICO Y TERAPIA NUTRICIONAL' => 'METABOLIC SUPPORT AND NUTRITIONAL THERAPY',
            'INFECTOLOGIA PEDIATRICA' => 'PEDIATRIC INFECTOLOGY',
            'CIRUGIA' => 'SURGERY',
            'CIRUGIA CARDIACA PEDIATRICA' => 'PEDIATRIC CARDIAC SURGERY',
            'CIRUGIA CARDIACA ADULTOS' => 'CARDIAC SURGERY ADULTS',
        );

        foreach ($especialidades as $key => $value) {

            if ($value == $especialidad) {

                return $key;
            }

        }

        return false;

    }

    public function buscarEspe($especialidad)
    {
        $especialidades = array(
            'MEDICINA OCUPACIONAL' => 'OCCUPATIONAL MEDICINE',
            'ODONTOPEDIATRIA' => 'PEDIATRIC DENTISTRY',
            'INFECTOLOGIA' => 'INFECTOLOGY',
            'TERAPIA DEL DOLOR' => 'PAIN THERAPY',
            'ANESTESIOLOGIA' => 'ANESTHESIOLOGY',
            'CARDIOLOGIA' => 'CARDIOLOGY',
            'CIRUGIA CARDIOTORACICA' => 'CARDIOTORACIC SURGERY',
            'CIRUGIA GENERAL' => 'GENERAL SURGERY',
            'CIRUGIA MAXILOFACIAL' => 'MAXILLOFACIAL SURGERY',
            'PSIQUIATRIA' => 'PSYCHIATRY',
            'RADIOLOGIA' => 'RADIOLOGY',
            'RADIOTERAPIA' => 'RADIOTHERAPY',
            'UROLOGIA' => 'UROLOGY',
            'GINECOLOGIA Y OBSTETRICIA' => 'GYNECOLOGY AND OBSTETRICS',
            'PEDIATRIA' => 'PEDIATRICS',
            'NEONATOLOGIA' => 'NEONATOLOGY',
            'LABORATORIO' => 'LABORATORY',
            'GENETICA HUMANA' => 'HUMAN GENETICS',
            'REUMATOLOGIA' => 'RHEUMATOLOGY',
            'MEDICINA FAMILIAR' => 'FAMILY MEDICINE',
            'HEMODINAMICA' => 'HEMODYNAMICS',
            'CIRUGIA PROCTOLOGICA' => 'PROCTOLOGICAL SURGERY',
            'FONIATRIA Y AUDIOLOGIA' => 'PHYSIOTRY AND AUDIOLOGY',
            'SALUD PUBLICA' => 'PUBLIC HEALTH',
            'IMAGEN' => 'IMAGE',
            'NEUROLOGIA PEDIATRICA' => 'PEDIATRIC NEUROLOGY',
            'NEUROFISIOLOGIA' => 'NEUROPHYSIOLOGY',
            'CARDIOLOGIA PEDIATRICA' => 'PEDIATRIC CARDIOLOGY',
            'INMUNOLOGIA' => 'IMMUNOLOGY',
            'ALERGOLOGIA' => 'ALLERGOLOGY',
            'EPIDEMIOLOGIA' => 'EPIDEMIOLOGY',
            'DIABETOLOGIA' => 'DIABETOLOGY',
            'PSICOLOGIA CLINICA' => 'CLINICAL PSYCHOLOGY',
            'CUIDADOS INTENSIVOS' => 'INTENSIVE CARE',
            'MEDICINA PERINATAL' => 'PERINATAL MEDICINE',
            'ODONTOLOGIA' => 'ODONTOLOGY',
            'MEDICINA PULMONAR' => 'PULMONARY MEDICINE',
            'MEDICINA TROPICAL' => 'TROPICAL MEDICINE',
            'FARMACIA' => 'PHARMACY',
            'CIRUGIA ONCOLOGICA' => 'ONCOLOGIC SURGERY',
            'CIRUGIA PEDIATRICA' => 'PEDIATRIC SURGERY',
            'CIRUGIA PLASTICA' => 'PLASTIC SURGERY',
            'CIRUGIA VASCULAR' => 'VASCULAR SURGERY',
            'DERMATOLOGIA' => 'DERMATOLOGY',
            // 'ENDOCRINOLOGIA'                           => 'ENDOCRINOLOGY',
            'GASTROENTEROLOGIA' => 'GASTROENTEROLOGY',
            'HEMATOLOGIA' => 'HEMATOLOGY',
            'MEDICINA FISICA O REHABILITACION' => 'PHYSICAL MEDICINE OR REHABILITATION',
            'MEDICINA INTERNA' => 'INTERNAL MEDICINE',
            'MEDICINA NUCLEAR' => 'NUCLEAR MEDICINE',
            'NEFROLOGIA' => 'NEPHROLOGY',
            'NEUMOLOGIA' => 'PNEUMOLOGY',
            'NEUROCIRUGIA' => 'NEUROSURGERY',
            'NEUROLOGIA' => 'NEUROLOGY',
            'OFTALMOLOGIA' => 'OPHTHALMOLOGY',
            'ONCOLOGIA MEDICA' => 'MEDICAL ONCOLOGY',
            'TRAUMATOLOGIA' => 'TRAUMATOLOGY',
            'ORTOPEDIA Y TRAUMATOLOGIA' => 'ORTHOPEDICS AND TRAUMATOLOGY',
            'OTORRINOLARINGOLOGIA' => 'OTORHINOLARYNGOLOGY',
            'PATOLOGIA' => 'PATHOLOGY',
            'DPTO.CLINICA' => 'DEPT.CLINIC',
            'DPTO.CIRUGIA' => 'DPTO.CIRUGIA',
            'DPTO.PEDIATRIA' => 'DPTO.PEDIATRIA',
            'DPTO.GINECOLOGIA' => 'DPTO.GINECOLOGIA',
            'NO REGISTRADA' => 'NOT REGISTERED',
            'FARMACIA' => 'PHARMACY',
            'CUIDADOS INTENSIVOS PEDIATRICOS' => 'PEDIATRIC INTENSIVE CARE',
            'ONCOLOGIA HEMATOLOGIA' => 'ONCOLOGY  HEMATOLOGY',
            'GASTROENTEROLOGIA PEDIATRICA' => 'PEDIATRIC GASTROENTEROLOGY',
            'HEMATOLOGIA PEDIATRICA' => 'PEDIATRIC HEMATOLOGY',
            'ALERGOLOGIA PEDIATRICA' => 'PEDIATRIC ALERGOLOGY',
            'NEFROLOGIA PEDIATRICA' => 'PEDIATRIC NEPHROLOGY',
            'ECOCARDIOGRAFIA' => 'ECOCARDIOGRAPHY',
            'NUTRICION' => 'NUTRITION',
            'ELECTROFISIOLOGIA' => 'ELECTROPHYSIOLOGY',
            'GERIATRIA' => 'GERIATRICS',
            'PSICOLOGIA PEDIATRICA' => 'PEDIATRIC PSYCHOLOGY',
            'EMERGENCIA' => 'EMERGENCY',
            'SOPORTE METABOLICO Y TERAPIA NUTRICIONAL' => 'METABOLIC SUPPORT AND NUTRITIONAL THERAPY',
            'INFECTOLOGIA PEDIATRICA' => 'PEDIATRIC INFECTOLOGY',
            'MEDICINA OCUPACIONAL' => 'OCCUPATIONAL MEDICINE',
            'ODONTOPEDIATRIA' => 'PEDIATRIC DENTISTRY',
            'INFECTOLOGIA' => 'INFECTOLOGY',
            'TERAPIA DEL DOLOR' => 'PAIN THERAPY',
            'ANESTESIOLOGIA' => 'ANESTHESIOLOGY',
            'CARDIOLOGIA' => 'CARDIOLOGY',
            'CIRUGIA CARDIOTORACICA' => 'CARDIOTORACIC SURGERY',
            'CIRUGIA GENERAL' => 'GENERAL SURGERY',
            'CIRUGIA MAXILOFACIAL' => 'MAXILLOFACIAL SURGERY',
            'PSIQUIATRIA' => 'PSYCHIATRY',
            'RADIOLOGIA' => 'RADIOLOGY',
            'RADIOTERAPIA' => 'RADIOTHERAPY',
            'UROLOGIA' => 'UROLOGY',
            'GINECOLOGIA Y OBSTETRICIA' => 'GYNECOLOGY AND OBSTETRICS',
            'PEDIATRIA' => 'PEDIATRICS',
            'NEONATOLOGIA' => 'NEONATOLOGY',
            'LABORATORIO' => 'LABORATORY',
            'GENETICA HUMANA' => 'HUMAN GENETICS',
            'REUMATOLOGIA' => 'RHEUMATOLOGY',
            'MEDICINA FAMILIAR' => 'FAMILY MEDICINE',
            'HEMODINAMICA' => 'HEMODYNAMICS',
            'CIRUGIA PROCTOLOGICA' => 'PROCTOLOGICAL SURGERY',
            'FONIATRIA Y AUDIOLOGIA' => 'PHYSIOTRY AND AUDIOLOGY',
            'SALUD PUBLICA' => 'PUBLIC HEALTH',
            'IMAGEN' => 'IMAGE',
            'NEUROLOGIA PEDIATRICA' => 'PEDIATRIC NEUROLOGY',
            'NEUROFISIOLOGIA' => 'NEUROPHYSIOLOGY',
            'CARDIOLOGIA PEDIATRICA' => 'PEDIATRIC CARDIOLOGY',
            'INMUNOLOGIA' => 'IMMUNOLOGY',
            'ALERGOLOGIA' => 'ALLERGOLOGY',
            'EPIDEMIOLOGIA' => 'EPIDEMIOLOGY',
            'DIABETOLOGIA' => 'DIABETOLOGY',
            'PSICOLOGIA CLINICA' => 'CLINICAL PSYCHOLOGY',
            'CUIDADOS INTENSIVOS' => 'INTENSIVE CARE',
            'MEDICINA PERINATAL' => 'PERINATAL MEDICINE',
            'ODONTOLOGIA' => 'ODONTOLOGY',
            'MEDICINA PULMONAR' => 'PULMONARY MEDICINE',
            'MEDICINA TROPICAL' => 'TROPICAL MEDICINE',
            'FARMACIA' => 'PHARMACY',
            'CIRUGIA ONCOLOGICA' => 'ONCOLOGIC SURGERY',
            'CIRUGIA PEDIATRICA' => 'PEDIATRIC SURGERY',
            'CIRUGIA PLASTICA' => 'PLASTIC SURGERY',
            'CIRUGIA VASCULAR' => 'VASCULAR SURGERY',
            'DERMATOLOGIA' => 'DERMATOLOGY',
            // 'ENDOCRINOLOGIA'                           => 'ENDOCRINOLOGY',
            'GASTROENTEROLOGIA' => 'GASTROENTEROLOGY',
            'HEMATOLOGIA' => 'HEMATOLOGY',
            'MEDICINA FISICA O REHABILITACION' => 'PHYSICAL MEDICINE OR REHABILITATION',
            'MEDICINA INTERNA' => 'INTERNAL MEDICINE',
            'MEDICINA NUCLEAR' => 'NUCLEAR MEDICINE',
            'NEFROLOGIA' => 'NEPHROLOGY',
            'NEUMOLOGIA' => 'PNEUMOLOGY',
            'NEUROCIRUGIA' => 'NEUROSURGERY',
            'NEUROLOGIA' => 'NEUROLOGY',
            'OFTALMOLOGIA' => 'OPHTHALMOLOGY',
            'ONCOLOGIA MEDICA' => 'MEDICAL ONCOLOGY',
            'ORTOPEDIA Y TRAUMATOLOGIA' => 'ORTHOPEDICS AND TRAUMATOLOGY',
            'OTORRINOLARINGOLOGIA' => 'OTORHINOLARYNGOLOGY',
            'PATOLOGIA' => 'PATHOLOGY',
            'DPTO.CLINICA' => 'CLINIC DEPARTMENT',
            'DPTO.CIRUGIA' => 'SURGERY DEPARTMENT',
            'DPTO.PEDIATRIA' => 'PEDIATRIC DEPARTMENT',
            'DPTO.GINECOLOGIA' => 'GYNECOLOGY DEPARTMENT',
            'NO REGISTRADA' => 'NOT REGISTERED',
            'FARMACIA' => 'PHARMACY',
            'CUIDADOS INTENSIVOS PEDIATRICOS' => 'PEDIATRIC INTENSIVE CARE',
            'ONCOLOGIA HEMATOLOGIA' => 'ONCOLOGY  HEMATOLOGY',
            'GASTROENTEROLOGIA PEDIATRICA' => 'PEDIATRIC GASTROENTEROLOGY',
            'HEMATOLOGIA PEDIATRICA' => 'PEDIATRIC HEMATOLOGY',
            'ALERGOLOGIA PEDIATRICA' => 'PEDIATRIC ALERGOLOGY',
            'NEFROLOGIA PEDIATRICA' => 'PEDIATRIC NEPHROLOGY',
            'ECOCARDIOGRAFIA' => 'ECOCARDIOGRAPHY',
            'NUTRICION' => 'NUTRITION',
            'ELECTROFISIOLOGIA' => 'ELECTROPHYSIOLOGY',
            'GERIATRIA' => 'GERIATRICS',
            'PSICOLOGIA PEDIATRICA' => 'PEDIATRIC PSYCHOLOGY',
            'EMERGENCIA' => 'EMERGENCY',
            'SOPORTE METABOLICO Y TERAPIA NUTRICIONAL' => 'METABOLIC SUPPORT AND NUTRITIONAL THERAPY',
            'INFECTOLOGIA PEDIATRICA' => 'PEDIATRIC INFECTOLOGY',
            'CIRUGIA' => 'SURGERY',
            'CIRUGIA CARDIACA PEDIATRICA' => 'PEDIATRIC CARDIAC SURGERY',
            'CIRUGIA CARDIACA ADULTOS' => 'CARDIAC SURGERY ADULTS',
        );

        foreach ($especialidades as $key => $value) {

            if ($key == $especialidad) {

                return $key;
            }

        }

        return false;

    }

    private function notResults(array $data)
    {
        if (count($data) == 0) {
            throw new ModelsException('No existe más resultados.', 4080);
        }
    }

    public function setFotos()
    {
        $fotos = Helper\Files::get_files_in_dir('./assets/editadas/');

        $_fotos = array();

        /*

        foreach ($fotos as $key => $value) {

        $archivo = explode('/', $value);

        $med_medico = strtoupper(str_replace('.png', '', $archivo[3]));

        $sql = " SELECT C.PRIMER_NOMBRE || ' ' || C.SEGUNDO_NOMBRE || ' ' || C.PRIMER_APELLIDO || ' ' || C.SEGUNDO_APELLIDO AS NOMBRES_COMPLETOS, A.PK_FK_MEDICO AS COD_MEDICO
        FROM EDM_MEDICOS_DAT_COMERCIAL A, EDM_MEDICOS B, BAB_PERSONAS C
        WHERE A.PK_FK_MEDICO = B.PK_CODIGO AND
        B.FK_PERSONA = C.PK_CODIGO AND
        C.PRIMER_NOMBRE || ' ' || C.SEGUNDO_NOMBRE || ' ' || C.PRIMER_APELLIDO || ' ' || C.SEGUNDO_APELLIDO LIKE '%" . trim($med_medico) . "%'  ";
        # Conectar base de datos
        $this->conectar_Oracle();
        # Execute
        $stmt = $this->_conexion->query($sql);
        $this->_conexion->close();
        $data = $stmt->fetch();

        if (false != $data) {

        $rename = rename($value, './assets/editadas/' . $data['COD_MEDICO'] . '.png');

        if ($rename) {
        Helper\Files::delete_file($value);
        }

        $_fotos[] = array(
        '_medico'    => trim($med_medico),
        'F_medico'   => $archivo[3],
        'rename'     => $rename,
        'cod_medico' => $data['COD_MEDICO'],
        );

        } else {

        $_fotos[] = array(
        '_medico'    => trim($med_medico),
        'F_medico'   => $archivo[3],
        'rename'     => null,
        'cod_medico' => null,
        );
        }

        }

        return $_fotos;

         */

        foreach ($fotos as $key => $value) {

            if (strpos($value, 'png')) {

                $archivo = explode('/', $value);

                $med_medico = strtoupper(str_replace('.png', '', $archivo[3]));

                if (file_exists('./assets/doctores/' . $med_medico . '.png')) {

                    if (file_exists('./assets/doctores/' . $med_medico . '.jpg')) {
                        Helper\Files::delete_file('./assets/doctores/' . $med_medico . '.jpg');
                    }

                }

            }

        }

        return $_fotos;

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
