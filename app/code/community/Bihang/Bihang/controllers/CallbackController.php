<?php

class Bihang_Bihang_CallbackController extends Mage_Core_Controller_Front_Action
{        

    public function callbackAction() {

      require_once(Mage::getModuleDir('bihang-php', 'Bihang_Bihang') . "/bihang-php/Bihang.php");
      
      $secret = $_REQUEST['secret'];

      $bihang_order = json_decode(file_get_contents('php://input'));
      $correctSecret = Mage::getStoreConfig('payment/Bihang/callback_secret');

      // To verify this callback is legitimate, we will:
      //   a) check with Bihang the submitted order information is correct.
      $apiKey = Mage::getStoreConfig('payment/Bihang/api_key');
      $apiSecret = Mage::getStoreConfig('payment/Bihang/api_secret');
      $client = Bihang::withApiKey($apiKey, $apiSecret);
      $orderId = $bihang_order->custom;
      $okOrderId = $bihang_order->id;

      if(!$client->checkCallback()) {
        Mage::log("Bihang: incorrect callback signture.");
        header("HTTP/1.1 500 Internal Server Error");
        return;
      }
      
      $order = Mage::getModel('sales/order')->load($orderId);
      if(!$order) {
        Mage::log("Bihang: incorrect callback with incorrect order ID $orderId.");
        header("HTTP/1.1 500 Internal Server Error");
        return;
      }
      
      if($secret !== $correctSecret) {
        Mage::log("Bihang: incorrect callback with incorrect secret parameter $secret.");
        header("HTTP/1.1 500 Internal Server Error");
        return;
      }

      // The callback is legitimate. Update the order's status in the database.
      $payment = $order->getPayment();

      $payment->setTransactionId($okOrderId)
        ->setPreparedMessage("Paid with Bihang order $okOrderId.")
        ->setShouldCloseParentTransaction(true)
        ->setIsTransactionClosed(0);
      if("completed" == $bihang_order->status) {
        $payment->registerCaptureNotification($bihang_order->total_native->amount);
      } else {
        $order->registerCancellation("Bihang order $okOrderId cancelled");
      }
      Mage::dispatchEvent('bihang_callback_received', array('status' => $bihang_order->status, 'order_id' => $orderId));
      $order->save();
    }

}
