<?php
/**
 * Copyright © 2015 Inchoo d.o.o.
 * created by Zoran Salamun(zoran.salamun@inchoo.net)
 */
namespace Nochexapc\Nochex\Controller\Apc;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;

class Apc extends \Magento\Framework\App\Action\Action
{

	
   public function execute()
    {
	
	$logger = $this->_objectManager->get('\Psr\Log\LoggerInterface');
	
	$order_id = $this->getRequest()->getPost('order_id');
	$APCstatus = $this->getRequest()->getPost('status');
	$transaction_id = $this->getRequest()->getPost('transaction_id');
	$amount = $this->getRequest()->getPost('amount'); 	
		
	
		$resource = $this->_objectManager->get('Magento\Framework\App\ResourceConnection');
		$connection = $resource->getConnection();
	
		$saletableName = $resource->getTableName('sales_order');
		$sql = 'Update ' . $saletableName . ' Set status = "complete", total_paid = "'.$amount.'"  where entity_id = "'.$order_id.'"';
		$logger->addDebug('Query 2: ' .$sql);	
		$connection->query($sql);


$logger->addDebug('post variables =' . $order_id . ' -- ' . $APCstatus . "--" . $transaction_id . "" . $amount);	
		 
// Get the POST information from Nochex server
$postvars = http_build_query($_POST);

// Set parameters for the email
	$url = "https://www.nochex.com/apcnet/apc.aspx";
	 
	$headers  = 'MIME-Version: 1.0' . "\r\n";
	$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
	$headers .= 'From: james.lugton@nochex.com' . "\r\n";

	//// Curl code to post variables back
	$ch = curl_init(); // Initialise the curl tranfer
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_VERBOSE, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Host: www.nochex.com"));
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postvars); // Set POST fields 
	curl_setopt ($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1); // set openSSL version variable to CURL_SSLVERSION_TLSv1
	$output = curl_exec($ch); // Post back
	curl_close($ch);

			
	// Put the variables in a printable format for the email
	$debug = "IP -> " . $_SERVER['REMOTE_ADDR'] ."\r\n\r\n<br/>POST DATA:\r\n"; 
	foreach($_POST as $Index => $Value) 
	$debug .= "".$Index ."->". $Value."\r\n<br/>"; 
	$debug .= "\r\n<br/>RESPONSE:\r\n$output";

	$salepaytable = $resource->getTableName('sales_order_payment');
	$sale2tableName = $resource->getTableName('sales_order_grid');
	$payaddtableName = $resource->getTableName('sales_payment_transaction');
	

	
//If statement
	if (!strstr($output, "AUTHORISED")) {  // searches response to see if AUTHORISED is present if it isn’t a failure message is displayed
	
		$msg = "APC was not AUTHORISED, this was a " . $APCstatus . ", and the transaction id for this transaction is: ".$transaction_id;
		
		$sql1 = "Update " . $sale2tableName . " Set status = 'complete' where entity_id = '".$order_id."'";
		$logger->addDebug('Query 1: ' .$sql1);	
		$connection->query($sql1);
		
		$sql = 'Update ' . $salepaytable . ' Set amount_paid="'.$amount.'", last_trans_id = "'.$transaction_id.'", additional_data = "'.$msg.'"  where parent_id = "'.$order_id.'"';
		$logger->addDebug('Query 2: ' .$sql . " - msg: " . $msg);	
		$connection->query($sql);	
		
	
	} else { 
		
		$msg = "APC was AUTHORISED, this was a " . $APCstatus . ", and the transaction id for this transaction is: ".$transaction_id;
		
		$sql1 = "Update " . $sale2tableName . " Set status = 'complete' where entity_id = '".$order_id."'";
		$logger->addDebug('Query 1: ' .$sql1);			
		$connection->query($sql1);
		
		
		$sql = 'Update ' . $salepaytable . ' Set amount_paid="'.$amount.'", last_trans_id = "'.$transaction_id.'", additional_data = "'.$msg.'"  where parent_id = "'.$order_id.'"';
		$logger->addDebug('Query 2: ' .$sql . " - msg: " . $msg);	
		$connection->query($sql);		
	}


		
    }
}