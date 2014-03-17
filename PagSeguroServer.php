<?php
class PagSeguroServer
{
	/***** SETTINGS *****/
    private $notification_domain = 'localhost';
    private $notification_page = '/notifications/';
    private $notification_port = '80';
    
    
    private $order_filename = 'order.txt';
    private $notification_filename = 'notification.txt';
    private $transaction_filename = 'transaction.xml';
    /***** END OF SETTINGS *****/
    
    /**** DON'T EDIT THE CODE BELOW UNLESS YOU KNOW WHAT YOU ARE DOING ****/
    const NOTIFICATION_CODE_LENGTH = 39;
    const TRANSACTION_CODE_LENGTH = 36;
    private $order;
    private $notification;
    private $required_data = array("token", "currency", "email", "itemId1", "itemDescription1", "itemQuantity1", "itemAmount1","redirectURL");

    private $transaction_possible_status = array(
    	"1" => "Aguardando pagamento",
    	"2" => "Em análise",
    	"3" => "Paga",
    	"4" => "Disponível",
    	"5" => "Em disputa",
    	"6" => "Devolvida",
    	"7" => "Cancelada",
	);
    
    /*********** ORDER RELATED ***********/
  
    public function loadState() {
    	$file_handle = @fopen($this->order_filename, 'r');
    	if ($file_handle) {
			$this->order = unserialize(fread($file_handle, filesize($this->order_filename)));
			fclose($file_handle);
		}
		
		return $this->order;
    }
    
    public function saveState($order) {
    	$this->order = $order;
        $file_handle = fopen($this->order_filename, 'w') or die("can't open file");
		fwrite($file_handle, serialize($this->order));
		fclose($file_handle);
		
		$this->generateTransaction();
    }
    
    public function isDataConsistent() {
    	return $this->getMissingParameters() == "";
    }
    
    public function getMissingParameters() {
    	if (!$this->order)
    		return array();
    		
    	$missing_params = array();
    	foreach ($this->required_data as $key) {
    		if (!array_key_exists($key, $this->order)) 
				array_push($missing_params, $key);
		}
		
		return implode(", ", $missing_params);
    }
    
    private function getOrderItems() {
    	$i = 1;
    	$items = array();
    	while (array_key_exists("itemAmount".$i, $this->order) && array_key_exists("itemQuantity".$i, $this->order)) {
    		array_push($items, array("id" => $this->order["itemId".$i],
							   		  "description" => $this->order["itemDescription".$i],
							   		  "quantity" => $this->order["itemQuantity".$i],
							   		  "amount" => $this->order["itemAmount".$i]));
    		$i += 1;
    	}
    	
    	return $items;
    }
    
    private function calculateOrderTotal($items) {
    	$total = 0;
    	foreach ($items as $item)
    		$total += $item['amount']*$item['quantity'];
    	
    	return $total;
    }
   
   	/********************************************/
    
    /*********** NOTIFICATION RELATED ***********/
   
    
    public function sendNotification($transactionStatus, $notificationType) {
    	$this->notification = array("notificationCode" => $this->generateRandomString(self::NOTIFICATION_CODE_LENGTH), 
    								 "notificationType" => $notificationType,
    								 
    								 "transactionStatus" => $transactionStatus,
    								 "transactionTextStatus" => $this->transaction_possible_status[$transactionStatus],
    								 );
    								 
        $file_handle = fopen($this->notification_filename, 'w') or die("can't open file");
		fwrite($file_handle, serialize($this->notification));
		fclose($file_handle);
		
		$this->updateTransaction($this->notification);
		
		$this->notification['response'] = $this->sendNotificationRequest();
		
		$file_handle = fopen($this->notification_filename, 'w') or die("can't open file");
		fwrite($file_handle, serialize($this->notification));
		fclose($file_handle);
    }
    
    private function sendNotificationRequest() {
		$fp = fsockopen($this->notification_domain, $this->notification_port, $errno, $errstr, 30);
		$paramString = http_build_query($this->notification);
		
		$out = "POST ".$this->notification_page." HTTP/1.1\r\n";     
		$out.= "Host: ".$this->notification_domain."\r\n";
		$out.= "Connection: Close\r\n";
		
		$out.= "Content-Type: application/x-www-form-urlencoded\r\n";     
		$out.= "Content-Length: ".strlen($paramString)."\r\n\r\n";
		$out.= $paramString;
		fwrite($fp, $out);
		$response = stream_get_contents($fp);
		fclose($fp);
		
		return $response;
    }
    
    public function getNotificationUrl() {
    	return $this->notification_domain.$this->notification_page;
    }
    
    public function loadNotification() {
    	$file_handle = @fopen($this->notification_filename, 'r');
    	if ($file_handle) {
			$this->notification = unserialize(fread($file_handle, filesize($this->notification_filename)));
			fclose($file_handle);
		}
		
		return $this->notification;
    }
    
    /********************************************/
    
    /*********** TRANSACTION RELATED ***********/
    
    private function generateTransaction() {
    	$this->loadState();
    	$items = $this->getOrderItems();
    	
    	$xml = new SimpleXMLElement("<transaction/>");

		$xml->date = date("c");
    	$xml->code = $this->generateRandomString(self::TRANSACTION_CODE_LENGTH);
    	if (isset($this->order['reference'])) 
    		$xml->reference = $this->order['reference']; 

    	var_dump($this->order);
		
		$xml->lastEventDate = date("c");
		$xml->paymentMethod->type = 1;
		$xml->paymentMethod->code = 101;
		$xml->grossAmount = number_format($this->calculateOrderTotal($items), 2, '.', '');
		$xml->discountAmount = "0.00";
		$xml->feeAmount = "0.00";
		$xml->netAmount = $xml->grossAmount;
		$xml->extraAmount = "0.00";
		$xml->installmentCount = 1;
		$xml->itemCount = count($items);
		
		// print items
		$itemsRoot = $xml->addChild("items");
		foreach ($items as $item) {
			$child = $itemsRoot->addChild("item");
			$child->id = $item["id"];
			$child->description = $item["description"];
			$child->quantity = $item["quantity"];
			$child->amount = number_format($item["amount"], 2, '.', '');
		}
		
		// sender
		$xml->sender->name = isset($this->order['senderName']) ?: "Mauro Turm";
		$xml->sender->email = isset($this->order['senderEmail']) ?: "mauro@mail.com";
		$xml->sender->phone->areaCode = isset($this->order['senderAreaCode']) ?: "31";
		$xml->sender->phone->number = isset($this->order['senderPhone']) ?: "55555555";
		
		// // shipping
		// $xml->shipping->address->street = $this->order['cliente_end'] ?: "Av. do Contorno";
		// $xml->shipping->address->number = $this->order['cliente_num'] ?: "500";
		// $xml->shipping->address->complement = $this->order['cliente_compl'] ?: "2o Andar";
		// $xml->shipping->address->district = $this->order['cliente_bairro'] ?: "Funcionários";
		// $xml->shipping->address->postalCode = $this->order['cliente_cep'] ?: "30110039";
		// $xml->shipping->address->city = $this->order['cliente_cidade'] ?: "Belo Horizonte";
		// $xml->shipping->address->state = $this->order['cliente_uf'] ?: "MG";
		// $xml->shipping->address->country = $this->order['cliente_pais'] ?: "BRA";
		// $xml->shipping->type = 3;
		// $xml->shipping->cost = "0.00";
		
        // write xml file with proper formatting
        $dom = new DOMDocument('1.0');
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dom->xmlStandalone = true;
		$dom->encoding = "ISO-8859-1";
		$dom->loadXML($xml->asXML());
        
        $file_handle = fopen($this->transaction_filename, 'w') or die("can't open file");
		fwrite($file_handle, $dom->saveXML());
		fclose($file_handle);
    }
    
    private function updateTransaction($notification) {
    	$xml = simplexml_load_file($this->transaction_filename);    
		$xml->type = $notification['notificationType']; 
		$xml->status = $notification['transactionStatus']; 
		if ($notification['transactionStatus'] == 7) $xml->cancellationSource = "INTERNAL";
		
        // write xml file with proper formatting
        $dom = new DOMDocument('1.0');
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dom->xmlStandalone = true;
		$dom->encoding = "ISO-8859-1";
		$dom->loadXML($xml->asXML());
        
        $file_handle = fopen($this->transaction_filename, 'w') or die("can't open file");
		fwrite($file_handle, $dom->saveXML());
		fclose($file_handle);
    }
    
    public function readTransaction() {		
    	$file_handle = @fopen($this->transaction_filename, 'r');
		$transaction = fread($file_handle, filesize($this->transaction_filename));
		fclose($file_handle);
		
		return $transaction;
    }
    
    /********************************************/
    
    /*********** HELPERS ***********/
    
    public function wipe() {
    	unlink($this->order_filename);
    	unlink($this->notification_filename);
    	unlink($this->transaction_filename);
    }
    
    public function getCurrentHost() {
    	$host = $_SERVER['HTTP_HOST'];
		$uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
		return $host.$uri;
    }
    
    private function generateRandomString($length) {
    	$characters = '0123456789ABCDEF-';
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
		    $randomString .= $characters[rand(0, strlen($characters) - 1)];
		}
		return $randomString;
    }
}
?>
