<?php
ini_set('display_errors','On');
error_reporting(E_ALL);

include 'settings.php';

if ($PAGSEGURO_API_VERSION == 'v1')
	include 'PagSeguroServer.php';
else
	include 'PagSeguroServer2.php';

$server = new PagSeguroServer();

if (!empty($_POST)) {
	$server->sendNotification($_POST['notificationStatus'], $_POST['notificationType']);
	
	// redirect to index
	$host = $server->getCurrentHost();
	header("Location: http://$host/");
}

// reading transaction
if (!empty($_GET)) {
	$notification = $server->loadNotification();
	
	if ($_GET['notificationCode'] != $notification['notificationCode']) {
		die("Codigo da notificacao enviado '".$_GET['notificationCode']."' nao bate com o esperado '".$notification['notificationCode']."'.");
	}
	
	header("Content-Type:text/xml");
	echo $server->readTransaction();
}
?>
