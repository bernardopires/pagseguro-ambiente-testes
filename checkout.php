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

if (!empty($_GET) || !empty($_POST)) {
	if (empty($_GET))
		$data = $_POST;
	else
		$data = $_GET;

	$server->saveState($data);
}
else {
	die("Nenhum dado recebido.");
}

// redirect to index
$host = $server->getCurrentHost();
header("Location: http://$host/");
?>
