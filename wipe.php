<?php
ini_set('display_errors','On');
error_reporting(E_ALL);

include 'settings.php';

if ($PAGSEGURO_API_VERSION == 'v1'){
	include 'PagSeguroServer.php';
}
else{
	include 'PagSeguroServer2.php';
}

$server = new PagSeguroServer();
$server->wipe();

// redirect to index
$host = $server->getCurrentHost();
header("Location: http://$host/");
?>
