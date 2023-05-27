<?php
namespace app\models\hmetro;

class Conexion
{
    private $servidor;
    private $servidorDesa;
    private $usuarioDesa;
    private $usuario;
    private $contrasenia;
    private $contraseniaDesa;
    private $basedatos;
    private $conn;

    public function __construct()
    {
        global $config;
        /*

        $driver = $config['database']['drivers']['oracle_produccion'];

        $this->servidor = "(DESCRIPTION=( ADDRESS_LIST= (ADDRESS= (PROTOCOL=TCP) (HOST=" . $driver['host'] . ") (PORT=" . $driver['port'] . ")))( CONNECT_DATA= (SID=" . $driver['dbname'] . ") ))";
        $this->usuario = $driver['user'];
        $this->contrasenia = $driver['password'];

         */

        $driverDesa = $config['database']['drivers']['oracleReliv'];
        $this->servidorDesa = "(DESCRIPTION=( ADDRESS_LIST= (ADDRESS= (PROTOCOL=TCP) (HOST=" . $driverDesa['host'] . ") (PORT=" . $driverDesa['port'] . ")))( CONNECT_DATA= (SID=" . $driverDesa['dbname'] . ") ))";
        $this->usuarioDesa = $driverDesa['user'];
        $this->contraseniaDesa = $driverDesa['password'];
    }

    public function conectar()
    {
        $this->conn = oci_new_connect($this->usuario, $this->contrasenia, $this->servidor, 'AL32UTF8');
    }

    public function conectarDesa()
    {
        $this->conn = oci_new_connect($this->usuarioDesa, $this->contraseniaDesa, $this->servidorDesa, 'AL32UTF8');
    }

    public function cerrar()
    {
        if ($this->conn != null) {
            oci_close($this->conn);
        }
    }

    public function getConexion()
    {
        return $this->conn;
    }
}
