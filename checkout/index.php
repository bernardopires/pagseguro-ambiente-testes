<?php
ini_set('display_errors','On');
error_reporting(E_ALL);

include '../PagSeguroServer.php';
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
