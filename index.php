<?php

// print_r(PDO::getAvailableDrivers());


$db = "(DESCRIPTION=(ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = 172.16.3.247)(PORT = 1521)))(CONNECT_DATA=(SID=conclina)))";
$c1 = oci_connect("mchang","1501508480",$db);
print_r($c1);

?>