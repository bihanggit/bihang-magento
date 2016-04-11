<?php
 
class Bihang_Bihang_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'Bihang';
 
    /**
     * Is this payment method a gateway (online auth/charge) ?
     */
    protected $_isGateway               = true;
 
    /**
     * Can authorize online?
     */
    protected $_canAuthorize            = true;
 
    /**
     * Can capture funds online?
     */
    protected $_canCapture              = false;
 
    /**
     * Can capture partial amounts online?
     */
    protected $_canCapturePartial       = false;
 
    /**
     * Can refund online?
     */
    protected $_canRefund               = false;
 
    /**
     * Can void transactions online?
     */
    protected $_canVoid                 = false;
 
    /**
     * Can use this payment method in administration panel?
     */
    protected $_canUseInternal          = true;
 
    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout          = true;
 
    /**
     * Is this payment method suitable for multi-shipping checkout?
     */
    protected $_canUseForMultishipping  = true;
 
    /**
     * Can save credit card information for future processing?
     */
    protected $_canSaveCc = false;
  
  
    public function authorize(Varien_Object $payment, $amount) 
    {

      require_once(Mage::getModuleDir('bihang-php', 'Bihang_Bihang') . "/bihang-php/Bihang.php");

      // Step 1: Use the Bihang API to create redirect URL.
      $apiKey = Mage::getStoreConfig('payment/Bihang/api_key');
      $apiSecret = Mage::getStoreConfig('payment/Bihang/api_secret');

      if($apiKey == null || $apiSecret == null) {
        throw new Exception("Before using the Bihang plugin, you need to enter an API Key and Secret in Magento Admin > Configuration > System > Payment Methods > Bihang.");
      }

      $client = Bihang::withApiKey($apiKey, $apiSecret);

      $order = $payment->getOrder();
      $currency = $order->getBaseCurrencyCode();

      $callbackSecret = Mage::getStoreConfig('payment/Bihang/callback_secret');
      if($callbackSecret == "generate") {
        // Important to keep the callback URL a secret
        $callbackSecret = md5('secret_' . mt_rand());
        Mage::getModel('core/config')->saveConfig('payment/Bihang/callback_secret', $callbackSecret)->cleanCache();
        Mage::app()->getStore()->resetConfig();
      }
      
      $successUrl = Mage::getStoreConfig('payment/Bihang/custom_success_url');
      $cancelUrl = Mage::getStoreConfig('payment/Bihang/custom_cancel_url');
      if ($successUrl == false) {
        $successUrl = Mage::getUrl('bihang_bihang'). 'redirect/success/';
      }
      if ($cancelUrl == false) {
        $cancelUrl = Mage::getUrl('bihang_bihang'). 'redirect/cancel/';
      }

      $name = "Order #" . $order['increment_id'];
      $custom = $order->getId();
      $params = array(
            'name' => 'Order #' . $order['increment_id'],
            'price' => $amount,
            'price_currency' => $currency,
            'custom'  => $custom,
            'callback_url' => Mage::getUrl('bihang_bihang'). 'callback/callback/?secret=' . $callbackSecret,
            'success_url' => $successUrl,
          );
      // Generate the code
      try {
        $button = $client->buttonsButton($params)->button;
      } catch (Exception $e) {
          throw new Exception("Could not generate checkout page. Double check your API Key and Secret. " . $e->getMessage());
      }
      $redirectUrl = BihangBase::WEB_BASE."merchant/mPayOrderStemp1.do?buttonid=".$button->id;

      // Step 2: Redirect customer to payment page
      $payment->setIsTransactionPending(true); // Set status to Payment Review while waiting for Bihang postback
      Mage::getSingleton('customer/session')->setRedirectUrl($redirectUrl);
      
      return $this;
    }

    
    public function getOrderPlaceRedirectUrl()
    {
      return Mage::getSingleton('customer/session')->getRedirectUrl();
    }
}
?>
