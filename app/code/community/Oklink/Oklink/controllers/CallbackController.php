<?php

class Oklink_Oklink_CallbackController extends Mage_Core_Controller_Front_Action
{        

    public function callbackAction() {

      require_once(Mage::getModuleDir('oklink-php', 'Oklink_Oklink') . "/oklink-php/Oklink.php");
      
      $secret = $_REQUEST['secret'];

      $oklink_order = json_decode(file_get_contents('php://input'));
      $correctSecret = Mage::getStoreConfig('payment/Oklink/callback_secret');

      // To verify this callback is legitimate, we will:
      //   a) check with Oklink the submitted order information is correct.
      $apiKey = Mage::getStoreConfig('payment/Oklink/api_key');
      $apiSecret = Mage::getStoreConfig('payment/Oklink/api_secret');
      $client = Oklink::withApiKey($apiKey, $apiSecret);
      $orderId = $oklink_order->custom;
      $okOrderId = $oklink_order->id;

      if(!$client->checkCallback()) {
        Mage::log("Oklink: incorrect callback signture.");
        header("HTTP/1.1 500 Internal Server Error");
        return;
      }
      
      $order = Mage::getModel('sales/order')->load($orderId);
      if(!$order) {
        Mage::log("Oklink: incorrect callback with incorrect order ID $orderId.");
        header("HTTP/1.1 500 Internal Server Error");
        return;
      }
      
      if($secret !== $correctSecret) {
        Mage::log("Oklink: incorrect callback with incorrect secret parameter $secret.");
        header("HTTP/1.1 500 Internal Server Error");
        return;
      }

      // The callback is legitimate. Update the order's status in the database.
      $payment = $order->getPayment();

      $payment->setTransactionId($okOrderId)
        ->setPreparedMessage("Paid with Oklink order $okOrderId.")
        ->setShouldCloseParentTransaction(true)
        ->setIsTransactionClosed(0);
      if("completed" == $oklink_order->status) {
        $payment->registerCaptureNotification($oklink_order->total_native->amount);
      } else {
        $order->registerCancellation("Oklink order $okOrderId cancelled");
      }
      Mage::dispatchEvent('oklink_callback_received', array('status' => $oklink_order->status, 'order_id' => $orderId));
      $order->save();
    }

}
