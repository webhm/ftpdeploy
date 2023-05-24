<?php
 try {

    $server         = "172.16.3.247";
    $db_username    = "mchang";
    $db_password    = "1501508480";
    $service_name   = "conclina";
    $port           = 1521;
    $dbtns          = "(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = $server)(PORT = $port)) (CONNECT_DATA = (SERVICE_NAME = $service_name) ))";

    //$this->dbh = new PDO("mysql:host=".$server.";dbname=".dbname, $db_username, $db_password);

   $dbh = new PDO("oci:dbname=" . $dbtns . ";charset=utf8", $db_username, $db_password, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC));

        return $dbh;

} catch (PDOException $e) {
    echo $e->getMessage();
}

?>