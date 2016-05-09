<?php
class ModelPaymentPayTPV extends Model {

    public $url_paytpv = "https://secure.paytpv.com/gateway/bnkgateway.php";

    public function getMethod($address, $total) {
      $this->load->language('payment/paytpv');

      
      $method_data = array();

      $status = true;

      if ($status) {
        $method_data = array(
          'code'       => 'paytpv',
          'title'      => $this->language->get('text_title'),
          'terms'      => '',
          'sort_order' => $this->config->get('paytpv_sort_order')
        );
      }

      return $method_data;
    }

    public function gerUrlPAYTPV(){
      return $this->url_paytpv;
    }

    public function getCards($customer_id) {

      $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "paytpv_customer WHERE id_customer = '" . (int)$customer_id . "'");

      $card_data = array();

      foreach ($query->rows as $row) {

        $card_data[] = array(
          'paytpv_iduser' => $row['paytpv_iduser'],
          'paytpv_tokenuser' => $row['paytpv_tokenuser'],
          'paytpv_cc' => $row['paytpv_cc'],
          'paytpv_brand' => $row['paytpv_cc'],
          'card_desc' => $row['card_desc']
        );
      }
      return $card_data;
    }

    public function getCard($customer_id,$paytpv_iduser) {

      $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "paytpv_customer WHERE id_customer = '" . (int)$customer_id . "' and paytpv_iduser=". $paytpv_iduser);

      $card_data = array();

      foreach ($query->rows as $row) {

        $card_data = array(
          'paytpv_iduser' => $row['paytpv_iduser'],
          'paytpv_tokenuser' => $row['paytpv_tokenuser'],
          'paytpv_cc' => $row['paytpv_cc'],
          'paytpv_brand' => $row['paytpv_cc'],
          'card_desc' => $row['card_desc']
        );
      }
      return $card_data;
    }

    public function saveOrderInfo($customer_id,$order_id,$paytpv_agree){

      $this->db->query("UPDATE " . DB_PREFIX . "paytpv_order SET paytpvagree = " . $paytpv_agree . " where order_id=" . $order_id);

    }

    public function getOrderInfo($order_id){

      $query= $this->db->query("SELECT * FROM " . DB_PREFIX . "paytpv_order where order_id = " . $order_id);
      $row = current($query->rows);
      return $row;

    }

    public function updateOrder($data){

      $this->db->query("
        UPDATE `" . DB_PREFIX . "paytpv_order`
        SET `paytpv_iduser` = '" . $this->db->escape($data['IdUser']) . "',
          `paytpv_tokenuser` = '" . $this->db->escape($data['TokenUser']) . "',
          `transaction_type` = '" . $this->db->escape($data['TransactionType']) . "',
          `authcode` = '" . $this->db->escape($data['AuthCode']) . "',
          `amount` = '" . $this->db->escape($data['Amount']) . "',
          `result` = '" . $this->db->escape($data['Response']) . "',
          `date` = NOW()
        WHERE `order_id` = '" . $this->db->escape($data['Order']) . "'
      ");

    }

    public function addCard($paytpv_iduser,$paytpv_tokenuser,$paytpv_cc,$paytpv_brand,$paytpv_carddesc,$id_customer) {
      $paytpv_cc = '************' . substr($paytpv_cc, -4);
      $card_data = $this->getCard($id_customer,$paytpv_iduser);
      if (empty($card_data))
        $this->db->query("INSERT INTO " . DB_PREFIX . "paytpv_customer SET id_customer = '" . $id_customer . "', paytpv_iduser = '" . $paytpv_iduser . "', paytpv_tokenuser = '" . $paytpv_tokenuser . "', paytpv_cc = '" . $paytpv_cc . "', paytpv_brand = '" . $paytpv_brand . "', card_desc = '" . $paytpv_carddesc.  "', date = '" . date('Y-m-d H:i:s') . "'");
    }

    public function addOrderAgree($order_id,$customer_id) {
      $this->db->query("INSERT INTO `" . DB_PREFIX . "paytpv_order` SET `order_id` = '" . (int)$order_id . "', id_customer = '" . (int)$customer_id . "'");
    }

    public function deleteCard($paytpv_iduser) {
      $this->db->query("DELETE FROM " . DB_PREFIX . "paytpv_customer WHERE paytpv_iduser = '" . (int)$paytpv_iduser . "'");
    }


    public function recurringPayment($item, $vendor_tx_code) {

    $this->load->model('checkout/recurring');
    $this->load->model('payment/sagepay_direct');
    //trial information
    if ($item['recurring_trial'] == 1) {
      $price = $item['recurring_trial_price'];
      $trial_amt = $this->currency->format($this->tax->calculate($item['recurring_trial_price'], $item['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency'], false, false) * $item['quantity'] . ' ' . $this->session->data['currency'];
      $trial_text = sprintf($this->language->get('text_trial'), $trial_amt, $item['recurring_trial_cycle'], $item['recurring_trial_frequency'], $item['recurring_trial_duration']);
    } else {
      $price = $item['recurring_price'];
      $trial_text = '';
    }

    $recurring_amt = $this->currency->format($this->tax->calculate($item['recurring_price'], $item['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency'], false, false) * $item['quantity'] . ' ' . $this->session->data['currency'];
    $recurring_description = $trial_text . sprintf($this->language->get('text_recurring'), $recurring_amt, $item['recurring_cycle'], $item['recurring_frequency']);

    if ($item['recurring_duration'] > 0) {
      $recurring_description .= sprintf($this->language->get('text_length'), $item['recurring_duration']);
    }

    //create new recurring and set to pending status as no payment has been made yet.
    $order_recurring_id = $this->model_checkout_recurring->create($item, $this->session->data['order_id'], $recurring_description);
    $this->model_checkout_recurring->addReference($order_recurring_id, $vendor_tx_code);

    $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

    $sagepay_order_info = $this->getOrder($this->session->data['order_id']);

    $response_data = $this->setPaymentData($order_info, $sagepay_order_info, $price, $order_recurring_id, $item['recurring_name']);

    $next_payment = new DateTime('now');
    $trial_end = new DateTime('now');
    $subscription_end = new DateTime('now');

    if ($item['recurring_trial'] == 1 && $item['recurring_trial_duration'] != 0) {
      $next_payment = $this->calculateSchedule($item['recurring_trial_frequency'], $next_payment, $item['recurring_trial_cycle']);
      $trial_end = $this->calculateSchedule($item['recurring_trial_frequency'], $trial_end, $item['recurring_trial_cycle'] * $item['recurring_trial_duration']);
    } elseif ($item['recurring_trial'] == 1) {
      $next_payment = $this->calculateSchedule($item['recurring_trial_frequency'], $next_payment, $item['recurring_trial_cycle']);
      $trial_end = new DateTime('0000-00-00');
    }

    if ($trial_end > $subscription_end && $item['recurring_duration'] != 0) {
      $subscription_end = new DateTime(date_format($trial_end, 'Y-m-d H:i:s'));
      $subscription_end = $this->calculateSchedule($item['recurring_frequency'], $subscription_end, $item['recurring_cycle'] * $item['recurring_duration']);
    } elseif ($trial_end == $subscription_end && $item['recurring_duration'] != 0) {
      $next_payment = $this->calculateSchedule($item['recurring_frequency'], $next_payment, $item['recurring_cycle']);
      $subscription_end = $this->calculateSchedule($item['recurring_frequency'], $subscription_end, $item['recurring_cycle'] * $item['recurring_duration']);
    } elseif ($trial_end > $subscription_end && $item['recurring_duration'] == 0) {
      $subscription_end = new DateTime('0000-00-00');
    } elseif ($trial_end == $subscription_end && $item['recurring_duration'] == 0) {
      $next_payment = $this->calculateSchedule($item['recurring_frequency'], $next_payment, $item['recurring_cycle']);
      $subscription_end = new DateTime('0000-00-00');
    }

    $this->addRecurringOrder($this->session->data['order_id'], $response_data, $order_recurring_id, date_format($trial_end, 'Y-m-d H:i:s'), date_format($subscription_end, 'Y-m-d H:i:s'));

    if ($response_data['Status'] == 'OK') {
      $this->updateRecurringOrder($order_recurring_id, date_format($next_payment, 'Y-m-d H:i:s'));

      $this->addRecurringTransaction($order_recurring_id, $response_data, 1);
    } else {
      $this->addRecurringTransaction($order_recurring_id, $response_data, 4);
    }
  }

  public function recurringPayments() {
    /*
     * Used by the checkout to state the module
     * supports recurring recurrings.
     */
    return true;
  }
}
?>