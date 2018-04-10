<?php
/**
 *
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Nochexapc\Nochex\Controller\Success;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\PaymentException;
use Nochex\Nochexapc\Model\Nochex;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;

class Success extends \Magento\Checkout\Controller\Onepage
{

   /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;
	
 // protected $methodCode = Checkmo::PAYMENT_METHOD_CHECKMO_CODE;
 /**
     * @var CurrentCustomer
     */
    protected $currentCustomer;
	
    public function execute()
    {
    	
    $lastOrderId = $this->getOnepage()->getCheckout()->getLastOrderId();
	
	$quote = $this->getOnepage()->getQuote();

	$customer = $this->_objectManager->get('\Magento\Customer\Model\Session');
	$currcustomer = $this->_objectManager->get('\Magento\Customer\Helper\Session\CurrentCustomer');
	$customerViewHelper = $this->_objectManager->get('\Magento\Customer\Helper\View');
	$checkout = $this->_objectManager->get('\Magento\Checkout\Model\Session');
	$cust = $this->_objectManager->get('\Magento\Customer\Model\Metadata\CustomerMetadata');
	$custAdd = $this->_objectManager->get('\Magento\Quote\Model\BillingAddressManagement');	
	$addRepo = $this->_objectManager->get('\Magento\Customer\Api\AddressRepositoryInterface');		
	$custRepo = $this->_objectManager->get('\Magento\Customer\Api\CustomerRepositoryInterface');
	$PaymentHelper = $this->_objectManager->get('\Magento\Payment\Helper\Data');
	$storeManager = $this->_objectManager->get('\Magento\Store\Model\StoreManagerInterface');
	$checkoutHelper = $this->_objectManager->get('Magento\Checkout\Helper\Data');
	
	$this->method = $PaymentHelper->getMethodInstance("nochex");
	
	//$check = $checkoutHelper->isAllowedGuestCheckout($quote);
	
	$resource = $this->_objectManager->get('Magento\Framework\App\ResourceConnection');
	$connection = $resource->getConnection();
	
	$saletableName = $resource->getTableName('sales_order');
	$saleaddtableName = $resource->getTableName('sales_order_address');
	$saleoddtableName = $resource->getTableName('sales_order_item');
	
	
	$IDsql = "Select billing_address_id, shipping_address_id, grand_total FROM " . $saletableName . " Where entity_id = '".$lastOrderId ."'";
	
	
	$billing_id = $connection->fetchAll($IDsql);
	
	$idProdsql = "Select product_id, name, description, qty_ordered, base_row_total  FROM " . $saleoddtableName . " Where order_id = '".$lastOrderId ."'";
	
	$products = $connection->fetchAll($idProdsql);
	
	$description = "";
	
	$xmlCollection = "<items>";
	foreach ($products as $product){
	
	$description .= "" . $product['product_id'] . ", " . $product['name'] . ", " . number_format($product['qty_ordered'], 0, '', ''). " * " . number_format($product['base_row_total'], 2, '.', ''). ", " . $product['description'];
	
	$xmlCollection .= "<item><id>".$product['product_id']."</id><name>".$product['name']."</name><description>".$product['description']."</description><quantity>".number_format($product['qty_ordered'], 0, '', '')."</quantity><price>".number_format($product['base_row_total'], 2, '.', '')."</price></item>";
	}
	
	$xmlCollection .= "</items>";
	
	
	
	$billsql = "Select * FROM " . $saleaddtableName . " Where entity_id = '".$billing_id[0]['billing_address_id'] ."'";
	$billing_address = $connection->fetchAll($billsql);
		
	$shipsql = "Select * FROM " . $saleaddtableName . " Where entity_id = '".$billing_id[0]['shipping_address_id'] ."'";
	$shipping_address = $connection->fetchAll($shipsql);
		
	
		
	$merchantId = $this->method->getPayableTo();
	$testTransaction = $this->method->getTestTransaction();
		
	$callbackURL = $storeManager->getStore()->getBaseUrl() . "nochex/apc/apc/";
	$successURL = $storeManager->getStore()->getBaseUrl() . "checkout/onepage/success/";
	$cancel_url = $storeManager->getStore()->getBaseUrl(); 
	
	$xml = $this->method->getXmlCollect();
		
	if($xml == 1){
	
	$description = "Order created for ".$lastOrderId;
	
	}else{
	
	$xmlCollection = "";
	
	}
	
 
	
	if ($billing_id[0]['shipping_address_id'] == ""){
	
	$shippingaddress = "<input name=\"delivery_fullname\" type=\"hidden\" value=\"".$billing_address[0]["firstname"].", ".$billing_address[0]["lastname"]."\" /> 
		<input name=\"delivery_address\" type=\"hidden\" value=\"".$billing_address[0]["street"]."\" />
		<input name=\"delivery_city\" type=\"hidden\" value=\"".$billing_address[0]["city"]."\" />
		<input name=\"delivery_postcode\" type=\"hidden\" value=\"".$billing_address[0]["postcode"]."\" />";
	
	}else{
	
	$shippingaddress = "<input name=\"delivery_fullname\" type=\"hidden\" value=\"".$shipping_address[0]["firstname"].", ".$shipping_address[0]["lastname"]."\" /> 
		<input name=\"delivery_address\" type=\"hidden\" value=\"".$shipping_address[0]["street"]."\" />
		<input name=\"delivery_city\" type=\"hidden\" value=\"".$shipping_address[0]["city"]."\" />
		<input name=\"delivery_postcode\" type=\"hidden\" value=\"".$shipping_address[0]["postcode"]."\" />";
	
	}
	

	$resource = $this->_objectManager->get('Magento\Framework\App\ResourceConnection');
	$connection = $resource->getConnection();
	$saletableName = $resource->getTableName('sales_order');
	
	$IDsql = "Select status FROM " . $saletableName . " Where entity_id = '". $lastOrderId ."'";
		
	$payment_method = $connection->fetchAll($IDsql);
	
	
	$tel = preg_replace("/[^0-9]/", "", $billing_address[0]["telephone"]);
	
	if($payment_method[0]['status'] == "pending"){

	echo"<script>window.onload = function(){
  document.forms['co-transparent-form'].submit();
}</script><form class=\"form\" id=\"co-transparent-form\" action=\"https://secure.nochex.com/default.aspx\" method=\"post\">
   
		<input name=\"merchant_id\" type=\"hidden\" value=\"".$merchantId."\"/>
		<input name=\"amount\" type=\"hidden\" value=\"".number_format($billing_id[0]['grand_total'], 2, '.', '')."\" />
		<input name=\"order_id\" type=\"hidden\" value=\"". $lastOrderId ."\" />
		<input name=\"description\" type=\"hidden\" value=\"". $description ."\" />
		<input name=\"xml_item_collection\" type=\"hidden\" value=\"". $xmlCollection ."\" />
				
		<input name=\"test_transaction\" type=\"hidden\" value=\"".$testTransaction."\"/>
		<input name=\"test_success_url\" type=\"hidden\" value=\"".$successURL."\"/>
		<input name=\"success_url\" type=\"hidden\" value=\"".$successURL."\"/>
		<input name=\"callback_url\" type=\"hidden\" value=\"".$callbackURL."\"/>
		<input name=\"cancel_url\" type=\"hidden\" value=\"".$cancel_url."\"/>
	                	
		<input name=\"billing_fullname\" type=\"hidden\" value=\"".$billing_address[0]["firstname"].", ".$billing_address[0]["lastname"]."\" /> 
		<input name=\"billing_address\" type=\"hidden\" value=\"".$billing_address[0]["street"]."\" />
		<input name=\"billing_city\" type=\"hidden\" value=\"".$billing_address[0]["city"]."\" />
		<input name=\"billing_postcode\" type=\"hidden\" value=\"".$billing_address[0]["postcode"]."\" />
		
		".$shippingaddress."
		
		<input name=\"customer_phone_number\" type=\"hidden\" value=\"".$tel."\" />
		<input name=\"email_address\" type=\"hidden\" value=\"".$billing_address[0]["email"]."\" />				
		
				<button type=\"submit\">
                    <span>Place Order</span>
                </button>
        </form>";
		}else{
		
		echo "<h3 style=\"color:red;\">An error occurred, please contact the shop owner!</h3>";
			
		}
		
    }
	

}
