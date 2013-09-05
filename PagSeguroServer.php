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
    private $required_data = array("currency", "receiverEmail", "itemId", "itemDescription", "itemAmount", "itemQuantity");
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
		$keys_order = implode(' ', array_keys($this->order)); 	
    	foreach ($this->required_data as $key) {
    		if (false === strpos($keys_order, $key)) { 
				array_push($missing_params, $key);
			}
		}
		
		return implode(", ", $missing_params);
    }
    
    private function getOrderItems() {
    	$i = 1;
    	$items = array();
    	while (array_key_exists("item_valor_".$i, $this->order) && array_key_exists("item_quant_".$i, $this->order)) {
    		array_push($items, array("id" => $this->order["item_id_".$i],
							   		  "description" => $this->order["item_descr_".$i],
							   		  "quantity" => $this->order["item_quant_".$i],
							   		  "amount" => $this->order["item_valor_".$i]));
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
    	
    	if (!empty($this->order['ref_transacao'])) $xml->reference = $this->order['ref_transacao']; 

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
		$xml->sender->name = !empty($this->order['cliente_nome']) ? $this->order['cliente_nome'] : "Mauro Turm";
		$xml->sender->email = !empty($this->order['cliente_email']) ? $this->order['cliente_email'] : "mauro@mail.com";
		$xml->sender->phone->areaCode = !empty($this->order['cliente_ddd']) ? $this->order['cliente_ddd'] : "31";
		$xml->sender->phone->number = !empty($this->order['cliente_tel']) ? $this->order['cliente_tel'] : "55555555";
		
		// shipping
		$xml->shipping->address->street = !empty($this->order['cliente_end']) ? $this->order['cliente_end'] : "Av. do Contorno";
		$xml->shipping->address->number = !empty($this->order['cliente_num']) ? $this->order['cliente_num'] : "500";
		$xml->shipping->address->complement = !empty($this->order['cliente_compl']) ? $this->order['cliente_compl'] : "2o Andar";
		$xml->shipping->address->district = !empty($this->order['cliente_bairro']) ? $this->order['cliente_bairro'] : "Funcionários";
		$xml->shipping->address->postalCode = !empty($this->order['cliente_cep']) ? $this->order['cliente_cep'] : "30110039";
		$xml->shipping->address->city = !empty($this->order['cliente_cidade']) ? $this->order['cliente_cidade'] : "Belo Horizonte";
		$xml->shipping->address->state = !empty($this->order['cliente_uf']) ? $this->order['cliente_uf'] : "MG";
		$xml->shipping->address->country = !empty($this->order['cliente_pais']) ? $this->order['cliente_pais'] : "BRA";
		$xml->shipping->type = 3;
		$xml->shipping->cost = "0.00";
		
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
