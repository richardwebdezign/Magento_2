<?php
/**
 *
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Nochexapc\Nochex\Controller\Onepage;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\PaymentException;
use Nochex\Nochexapc\Model\Nochex;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;

class Success extends \Magento\Checkout\Controller\Onepage
{
    /**
     * Order success action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
	
	$lastOrderId = $this->getOnepage()->getCheckout()->getLastOrderId();

	$resource = $this->_objectManager->get('Magento\Framework\App\ResourceConnection');
	$connection = $resource->getConnection();
	$saletableName = $resource->getTableName('sales_order_payment');
	
	$IDsql = "Select method FROM " . $saletableName . " Where parent_id = '". $lastOrderId ."'";
		
	$payment_method = $connection->fetchAll($IDsql);
	
	$resource = $this->_objectManager->get('Magento\Framework\App\ResourceConnection');
	$connection = $resource->getConnection();
	$saletable = $resource->getTableName('sales_order');
	
	$ID1sql = "Select status FROM " . $saletable . " Where entity_id = '". $lastOrderId ."'";
		
	$billing_id = $connection->fetchAll($ID1sql);
	
	
	if($payment_method[0]["method"] == "nochex" & $billing_id[0]['status'] == "pending"){
	
	return $this->resultRedirectFactory->create()->setPath('nochex/success/success/');
		
	}else{
        $session = $this->getOnepage()->getCheckout();
        if (!$this->_objectManager->get('Magento\Checkout\Model\Session\SuccessValidator')->isValid()) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }
        $session->clearQuote();
        //@todo: Refactor it to match CQRS
        $resultPage = $this->resultPageFactory->create();
        $this->_eventManager->dispatch(
            'checkout_onepage_controller_success_action',
            ['order_ids' => [$session->getLastOrderId()]]
        );
			
	return $resultPage;
	}
		
		
    }
}
