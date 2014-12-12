<?php

class Oklink_Oklink_CallbackController extends Mage_Core_Controller_Front_Action
{        

    public function callbackAction() {

      require_once(Mage::getModuleDir('oklink-php', 'Oklink_Oklink') . "/oklink-php/Oklink.php");
      
      $secret = $_REQUEST['secret'];
      $postBody = json_decode(file_get_contents('php://input'));
      $correctSecret = Mage::getStoreConfig('payment/Oklink/callback_secret');

      // To verify this callback is legitimate, we will:
      //   a) check with Oklink the submitted order information is correct.
      $apiKey = Mage::getStoreConfig('payment/Oklink/api_key');
      $apiSecret = Mage::getStoreConfig('payment/Oklink/api_secret');
      $client = Oklink::withApiKey($apiKey, $apiSecret);
      $cbOrderId = $postBody->id;
      $orderInfo = $client->detailOrder($cbOrderId);
      if(!$orderInfo) {
        Mage::log("Oklink: incorrect callback with incorrect Oklink order ID $cbOrderId.");
        header("HTTP/1.1 500 Internal Server Error");
        return;
      }
      
      //   b) using the verified order information, check which order the transaction was for using the custom param.
      $orderId = $orderInfo->id;
      $order = Mage::getModel('sales/order')->load($orderId);
      if(!$order) {
        Mage::log("Oklink: incorrect callback with incorrect order ID $orderId.");
        header("HTTP/1.1 500 Internal Server Error");
        return;
      }
      
      //   c) check the secret URL parameter.
      if($secret !== $correctSecret) {
        Mage::log("Oklink: incorrect callback with incorrect secret parameter $secret.");
        header("HTTP/1.1 500 Internal Server Error");
        return;
      }

      // The callback is legitimate. Update the order's status in the database.
      $payment = $order->getPayment();
      $payment->setTransactionId($cbOrderId)
        ->setPreparedMessage("Paid with Oklink order $cbOrderId.")
        ->setShouldCloseParentTransaction(true)
        ->setIsTransactionClosed(0);
        
      if("completed" == $orderInfo->status) {
        $payment->registerCaptureNotification($orderInfo->total_native->cents / 100);
      } else {
        $cancelReason = $postBody->cancellation_reason;
        $order->registerCancellation("Oklink order $cbOrderId cancelled: $cancelReason");
      }

      Mage::dispatchEvent('oklink_callback_received', array('status' => $orderInfo->status, 'order_id' => $orderId));
      $order->save();
    }

}
