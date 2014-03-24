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
$data = $server->loadState();
$notification = $server->loadNotification();
?>
<html>
<head>
<title>PagSeguro - Ambiente de Testes</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" type="text/css" href="css/main.css">
</head>

<body>
	<div id="wrap">
		<div id="main">
			<div id="header">Ambiente de Testes PagSeguro <span id="header-github"><a href="https://github.com/bcarneiro/pagseguro-ambiente-testes">https://github.com/bcarneiro/pagseguro-ambiente-testes</a></span></div>
			<div>
			<?php if ($data) { ?>
				<h3>Paramêtros Recebidos</h3>
				<ul>
				<?php
				foreach($data as $key => $value) {
					echo "<li>$key = '$value'</li>";
				}
				?>
				<p class="warning">
				<?php if (!$server->isDataConsistent()) { ?>
				Atenção: os seguintes paramêtros são obrigatórios mas não foram enviados: <?php echo $server->getMissingParameters(); ?>
				<?php } ?>
				</p>
				</ul>
				<?php if ($notification) { ?>
				<h3 class="yellow">Notificação enviada</h3>
				<p>Notificação enviada para <a href="<?php echo $server->getNotificationUrl(); ?>"><?php echo $server->getNotificationUrl(); ?></a>. Dados enviados:</p>
				<ul>
					<li>notificationCode: <?php echo $notification['notificationCode']; ?></li>
					<li>notificationType: <?php echo $notification['notificationType']; ?></li>
				</ul>
				<p>Resposta do seu servidor:</p>
				<div id="notification-response"><?php echo htmlspecialchars($notification['response']); ?></div>
				<p>Para ler a notificação envie um pedido GET para o endereço <a href="http://<?php echo $server->getCurrentHost(); ?>/notifications.php"><?php echo $server->getCurrentHost(); ?>/notifications.php</a> com os campos notificationCode, email (ignorado) e token (ignorado).</p>
				<p>Exemplo: <a href="http://<?php echo $server->getCurrentHost(); ?>/notifications.php?notificationCode=<?php echo $notification['notificationCode']; ?>&email=teste@test.com&token=MEU-TOKEN-FALSO"><?php echo $server->getCurrentHost(); ?>/notifications.php?notificationCode=<?php echo $notification['notificationCode']; ?>&email=teste@test.com&token=MEU-TOKEN-FALSO</a></p><br>
				<p>Dados da transação:</p>
				<ul>
					<li>Status: <?php echo $notification['transactionStatus']; ?> (<?php echo $notification['transactionTextStatus']; ?>)</li>	
				</ul>	
				<?php } ?>
				<h3>Enviar nova Notificação</h3>
				<p>Uma notificação será enviada para o endereço <a href="<?php echo $server->getNotificationUrl(); ?>"><?php echo $server->getNotificationUrl(); ?></a>.</p>
				<form method="post" action="notifications.php">
					<p>
						<label>Status:</label>
						<select name="notificationStatus">
							<option value="1">Aguardando pagamento</option>
							<option value="2">Em análise</option>
							<option value="3">Paga</option>
							<option value="4">Disponível</option>
							<option value="5">Em disputa</option>
							<option value="6">Devolvida</option>
							<option value="7">Cancelada</option>
						</select>
					</p>
					<p>
						<label>Status:</label>
						<select name="notificationType">
							<option value="1">transaction</option>
						</select>
					</p>
					<p>
						<input type="submit" value="Enviar">
					</p>
				</form>
				<h3 class="red">Apagar dados e notificação</h3>
				<p>Irá apagar os dados guardados como os paramêtros de ordem e notificações.</p>
				<form method="post" action="wipe.php">
					<p>
						<input type="submit" value="Limpar todos os dados">
					</p>
				</form>
			<?php } else { ?>
				
				<h2>Tutorial - pagseguro-ambiente-testes</h2>
				<p>Este software tem o objetivo de auxiliar o desenvolvedor a testar sua implementação PagSeguro de forma prática. É possível enviar o seu carrinho de compras do PagSeguro e simular o sistema de notificações. Atualmente, o <a href="http://blogpagseguro.com.br/2012/05/testando-o-recebimento-de-notificacoes/">jeito atual recomendado pelo PagSeguro</a> é que se crie um vendedor falso e que então que o desenvolvedor compre manualmente produtos, via boleto ou cartão de crédito, o que se torna imprático na maioria das vezes. Este método impede o desenvolvedor de usar sua máquina local para testar o sistema de notificações, pois obviamente não é possível enviar uma notificação para um endereço local como 127.0.0.1. Além disso não é possível simular uma venda bem sucedida (a não ser que você realmente compre o produto). Com este sistema você conseguirá simular todos os tipos de notificações do PagSeguro de forma rápida e prática.</p>
				<br>
				<p>Para iniciar o ambiente de testes é necessário que você primeiro envie os dados do seu carrinho de compras. Portanto em vez de enviar os dados para o PagSeguro, você enviará para está pagina <a href="http://<?php echo $server->getCurrentHost(); ?>/checkout.php"><?php echo $server->getCurrentHost(); ?>/checkout.php</a>. Mais informações de quais dados são esperados e como funciona o carrinho de compras do PagSeguro você poder ler <a href="https://pagseguro.uol.com.br/desenvolvedor/carrinho_proprio.jhtml#rmcl">aqui</a>. Não se esqueça também de alterar o arquivo PagSeguroServer.php e configurar as variáveis $notification_domain e $notification_page (seu endereço para receber notificações).</p>
				<br>
				<p>Exemplo: 
				<?php if($PAGSEGURO_API_VERSION == "v1") { ?>
					<a href="http://<?php echo $server->getCurrentHost(); ?>/checkout.php?tipo=CP&moeda=BRL&email_cobranca=turm@test.com&item_id_1=1&item_descr_1=Computador%20bacana&item_quant_1=1&item_valor_1=100.00&item_id_2=2&item_descr_2=Mais%20um%20computador&item_quant_2=2&item_valor_2=150.00"><?php echo $server->getCurrentHost(); ?>/checkout.php?tipo=CP&moeda=BRL&ema...&item_valor_2=150.00</a></p><br>
				<?php } else { ?>
					<a href="http://<?php echo $server->getCurrentHost(); ?>/checkout.php?cy=BRL&currency=BRL&email=turm@test.com&itemId1=1&itemDescription1=Computador%20bacana&itemQuantity1=1&itemAmount1=100.00&token=LKJHASJI&redirectURL="><?php echo $server->getCurrentHost(); ?>/checkout.php?tipo=CP&moeda=BRL&ema...&itemAmount1=150.00</a></p><br>
				<?php } ?>

				<!-- &item_id_2=2&item_descr_2=Mais%20um%20computador&item_quant_2=2&item_valor_2=150.00 -->
				<p>Tem alguma dúvida, problema ou sugestão? Quer contribuir? Estamos no Github, envie seu feedback para <a href="https://github.com/bcarneiro/pagseguro-ambiente-testes">pagseguro-ambiente-testes</a>!</p>
				
				<h3>Links para documentacão</h3>
				<ul>
					<li><a href="https://github.com/bcarneiro/pagseguro-ambiente-testes">pagseguro-ambiente-testes</a></li>
					<li><a href="https://pagseguro.uol.com.br/desenvolvedor/carrinho_proprio.jhtml#rmcl">Carrinho de Compras do PagSeguro</a></li>
					<li><a href="https://pagseguro.uol.com.br/v2/guia-de-integracao/api-de-notificacoes.html">API de Notificações do PagSeguro</a></li>
				</ul>
			<?php } ?>
			</div>
		</div>
	</div>

	<div id="footer">
	<span>Este software é gratuito e não está associado com o PagSeguro. PagSeguro é uma marca registrada da empresa UOL. Este ambiente de testes não é afiliado com a UOL e portanto não é um produto oficial do PagSeguro.</span>
	</div>
</body>

</html>
