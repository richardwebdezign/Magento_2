<?php
/**
 * Copyright © 2019 Nochex
 * created by Nochex
 */
namespace Nochexapc\Nochex\Controller\Apc;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\InvalidRequestException;
 
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
  
  
  
class Apc extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{

	
	
 public function __construct(
        \Magento\Framework\App\Action\Context $context
    ) {

		if (interface_exists("\Magento\Framework\App\CsrfAwareActionInterface")) {
            $request = $this->getRequest();
            if ($request instanceof HttpRequest && $request->isPost() && empty($request->getParam('form_key'))) {
                $formKey = $this->_objectManager->get(\Magento\Framework\Data\Form\FormKey::class);
                $request->setParam('form_key', $formKey->getFormKey());
            }
        }
		
        parent::__construct($context);
		
		/*$this->orderSender = $orderSender;
        $this->_orderFactory = $orderFactory;*/
    }
		
	 public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
	
	    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

	
   public function execute()
    {
	
	$order_id = $this->getRequest()->getPost('custom');
	
	

	
	
  /*
$invoiceId= $this->_objectManager->create('\Magento\Sales\Model\order_invoice_api')->create($orders->getIncrementId(),null,null,false,true);
$this->_objectManager->create('\Magento\Sales\Model\order_invoice_api')->capture($invoiceId);*/


	if(isset($order_id)){

	
	$APCstatus = $this->getRequest()->getPost('status');
	$transaction_id = $this->getRequest()->getPost('transaction_id');
	$amount = $this->getRequest()->getPost('amount'); 	
		
	// Get the POST information from Nochex server
	$postvars = http_build_query($this->getRequest()->getPost());

	// Set parameters for the email
	$url = "https://www.nochex.com/apcnet/apc.aspx";
	 
	$headers  = 'MIME-Version: 1.0' . "\r\n";
	$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
	$headers .= 'From: APC@nochex.com' . "\r\n";

	//// Curl code to post variables back
	$ch = curl_init(); // Initialise the curl tranfer
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postvars); // Set POST fields
	$output = curl_exec($ch); // Post back
	curl_close($ch);
	
			
	// Put the variables in a printable format for the email
	$debug = "IP -> " . $_SERVER['REMOTE_ADDR'] ."\r\n\r\n<br/>POST DATA:\r\n"; 
	foreach($_POST as $Index => $Value) 
	$debug .= "".$Index ."->". $Value."\r\n<br/>"; 
	$debug .= "\r\n<br/>RESPONSE:\r\n$output";

		
	//If statement
	if (!strstr($output, "AUTHORISED")) {  // searches response to see if AUTHORISED is present if it isn’t a failure message is displayed
	
		$msg = "APC was not AUTHORISED, this was a " . $APCstatus . ", and the transaction id for this transaction is: ".$transaction_id;
		
	} else { 
		
		$msg = "APC was AUTHORISED, this was a " . $APCstatus . ", and the transaction id for this transaction is: ".$transaction_id;
				
	}
	
		$logger = $this->_objectManager->get('\Psr\Log\LoggerInterface');
		$resource = $this->_objectManager->get('Magento\Framework\App\ResourceConnection');
		$connection = $resource->getConnection();
			
		$salepaytable = $resource->getTableName('sales_order_payment');
		$sale2tableName = $resource->getTableName('sales_order_grid');
		$payaddtableName = $resource->getTableName('sales_payment_transaction');
	
		$saletableName = $resource->getTableName('sales_order');
		$sql = 'Update ' . $saletableName . ' Set status = "processing", base_total_paid = "'.$amount.'" , total_paid = "'.$amount.'"  where entity_id = "'.$order_id.'"';
		$logger->addDebug('Query 2: ' .$sql);	
		$connection->query($sql);
		
		$sql1 = "Update " . $sale2tableName . " Set status = 'processing' where entity_id = '".$order_id."'";
		$logger->addDebug('Query 1: ' .$sql1);			
		$connection->query($sql1);		
		
		$sql = 'Update ' . $salepaytable . ' Set amount_paid="'.$amount.'", last_trans_id = "'.$transaction_id.'", additional_data = "'.$msg.'"  where parent_id = "'.$order_id.'"';
		$logger->addDebug('Query 2: ' .$sql . " - msg: " . $msg);	
		$connection->query($sql);		
		
			
	$orders = $this->_objectManager->create('\Magento\Sales\Model\Order')->load($order_id);				
	$emailSender = $this->_objectManager->create('\Magento\Sales\Model\Order\Email\Sender\OrderSender');
	$emailSender->send($orders);	
	
	$invoice = $this->_objectManager->create('\Magento\Sales\Model\Service\InvoiceService')->prepareInvoice($orders);
    $invoice->register();
    $invoice->save();
	
        /*$this->_order = $this->_orderFactory->create()->loadByIncrementId($order_id);
		*/
		
}else{

echo "APC";
}

	exit();
		
    }
}
