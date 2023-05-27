<?php

error_reporting(E_ALL ^ E_NOTICE);

include('class.oracle.php');

$ora = new ORACLE(); //::CreateInstance("XE", "olton", "yfnfkmz", "RUSSIAN_CIS.AL32UTF8"); 
$ora->Connect("172.16.3.247:1521/conclina", "mchang", "1501508480");

$ora->SetFetchMode(OCI_ASSOC);
$ora->SetAutoCommit(true);

$h = $ora->Select("select sysdate from dual");
$r = $ora->FetchObject($h);

echo "ssss";

print_r($r);

?>