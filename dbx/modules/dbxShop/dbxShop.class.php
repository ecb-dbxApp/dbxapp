<?php
namespace dbx\dbxShop;

class dbxShop {

   private function service() {
      return dbx()->get_include_obj('dbxShopService', 'dbxShop');
   }

   private function alert(string $type, string $message): string {
      $type = in_array($type, array('success', 'warning', 'danger', 'info'), true) ? $type : 'info';
      return dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|alert-' . $type, array(
         'msg' => $message,
      ));
   }

   public function run() {
      $run = dbx()->get_modul_var('dbx_run1', 'catalog', 'parameter');

      switch ($run) {
         case '':
         case 'start':
         case 'catalog':
            return $this->service()->catalog();

         case 'product':
         case 'detail':
            return $this->service()->product();

         case 'cart':
            return $this->service()->cart();

         case 'checkout':
            return $this->service()->checkout();

         case 'paypal_start':
            return $this->service()->paypal_start();

         case 'paypal_return':
            return $this->service()->paypal_return();

         case 'paypal_cancel':
            return $this->service()->paypal_cancel();

         case 'amazon_pay_return':
            return $this->service()->amazon_pay_return();

         case 'amazon_pay_cancel':
            return $this->service()->amazon_pay_cancel();

         case 'order':
         case 'orders':
            return $this->service()->orders();

         case 'invoice_pdf':
            return $this->service()->invoice_pdf();

         case 'channel_webhook':
            return $this->service()->channel_webhook();

         case 'legal':
         case 'terms':
            return $this->service()->legal();

         case 'return':
         case 'returns':
         case 'withdrawal':
            return $this->service()->withdrawal();

         default:
            return $this->alert('warning', 'Shop-Aufruf (' . htmlspecialchars((string) $run, ENT_QUOTES, 'UTF-8') . ') ist noch nicht definiert.');
      }
   }
}
?>
