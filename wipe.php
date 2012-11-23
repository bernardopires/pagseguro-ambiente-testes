<?php
ini_set('display_errors','On');
error_reporting(E_ALL);

include 'PagSeguroServer.php';
$server = new PagSeguroServer();
$server->wipe();

// redirect to index
$host = $server->getCurrentHost();
header("Location: http://$host/");
?>
