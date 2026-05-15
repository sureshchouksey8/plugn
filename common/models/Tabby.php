<?php

namespace common\models;

use Yii;
use yii\base\Model;
use yii\helpers\Url;

class Tabby extends Model
{
    var $tabbyProducts = [
        'tabby_paylater'        => 'payLater',
        'tabby_cc_installments' => 'creditCardInstallments',
        'tabby_installments'    => 'installments'
    ];

    var $apiUrl = 'https://api.tabby.ai/api/v1/payments/';
    var $apiCheckoutUrl = 'https://api.tabby.ai/api/v2/checkout';
    var $errors = [];

    public function getCheckoutData($order, $payment_method = "tabby_installments") {
        $data = [];

        $data['tabbyApiKey'] = Setting::getConfig($order->restaurant_uuid, PaymentMethod::CODE_TABBY,
            'payment_tabby_public_key');

        $data['tabbyDebug']  = Setting::getConfig($order->restaurant_uuid, PaymentMethod::CODE_TABBY,
            'payment_tabby_debug');

        $data['merchantCode']  = $this->getMerchantCode($order);

        $data['merchantUrls'] = array(
            "success"  => Url::to(['payment/tabby/confirm', "redirect" => 1, "order_uuid" => $order->order_uuid],
                true),
            "cancel"   =>  Url::to(['payment/tabby/cancel', "order_uuid" => $order->order_uuid], true),
            "failure"  => Url::to(['payment/tabby/failure', "order_uuid" => $order->order_uuid], true)
        );

        $data['lang'] = Yii::$app->language;

        $data['tabbyProduct']= array_key_exists($payment_method, $this->tabbyProducts)
            ? $this->tabbyProducts[$payment_method] : '';
        //: $this->tabbyProducts['tabby_paylater'];

        $data['tabbyPayment']= $this->getPaymentObject($order);

        return $data;
    }

    public function createSession($order) {
        $data = [];

        $data['payment']= $this->getPaymentObjectCreate($order);
        $data['lang'] = Yii::$app->language;
        $data['merchant_code']  = $this->getMerchantCode($order);

        $result = $this->execute("POST" , "checkout", $data, $order->restaurant_uuid);

        TabbyTransaction::ddlog('info', 'createSession', null, array(
            'data'      => $data,
            'result'    => $result
        ));

        return $result;
    }

    public function addTransaction($data) {

        TabbyTransaction::ddlog('info', 'addTransaction', null, array(
            'order_uuid'  => $data['order_uuid'],
            'payment'   => array(
                'id'    => $data['transaction_id']
            ),
            'body'      => $data['body'],
            'status'    => $data['status'],
            'source'    => $data['source']
        ));

        if ($tid = $this->getTransactionIdByOrder($data['order_uuid'])) {
            return $this->updateTransaction($data);
        } else {

            $tt = new TabbyTransaction();
            $tt->order_uuid =  $data['order_uuid'];
            $tt->transaction_id = $data['transaction_id'];
            $tt->body = $data['body'];
            $tt->status = $data['status'];
            $tt->source = $data['source'];

            if (!$tt->save()) {
                TabbyTransaction::ddlog('error', 'addTransaction', null, $tt->errors);
                Yii::error([
                    'message' => 'Unable to create Tabby transaction.',
                    'order_uuid' => $data['order_uuid'],
                    'transaction_id' => $data['transaction_id'],
                    'errors' => $tt->errors,
                ], __METHOD__);

                $this->addError('transaction', 'Unable to create Tabby transaction.');

                return false;
            }
        }

        return true;
    }

    public function updateTransaction($data) {

        if (!array_key_exists('transaction_id', $data)) {
            $data['transaction_id'] = 'not provided';
            try {
                $txn = json_decode($data['body']);
                $data['transaction_id'] = $txn->id;
            } catch (\Exception $e) {
            }
        }

        TabbyTransaction::ddlog('info', 'updateTransaction', null, array(
            'order_uuid'  => $data['order_uuid'],
            'payment'   => array(
                'id'    => $data['transaction_id']
            ),
            'body'      => $data['body'],
            'status'    => $data['status']
        ));
        
        //if ($data['status'] == 'authorized') {
        $tt = TabbyTransaction::find()
            ->andWhere(['order_uuid' => $data['order_uuid']])
            ->one();

        if (!$tt) {
            $tt = new TabbyTransaction();
            $tt->order_uuid = $data['order_uuid'];
        }

        $tt->body =  $data['body'];
        $tt->status = $data['status'];
        $tt->transaction_id = $data['transaction_id'];

        if (!$tt->save()) {
            TabbyTransaction::ddlog('error', 'addTransaction', null, $tt->errors);
            Yii::error([
                'message' => 'Unable to update Tabby transaction.',
                'order_uuid' => $data['order_uuid'],
                'transaction_id' => $data['transaction_id'],
                'errors' => $tt->errors,
            ], __METHOD__);

            $this->addError('transaction', 'Unable to update Tabby transaction.');

            return false;
        }

        //} else {
        //$sql = "UPDATE `" . DB_PREFIX . "tabby_transaction` SET update_date = now(), body = '" . $this->db->escape($data['body']) . "', status = '" . $this->db->escape($data['status']) . "' WHERE order_uuid = '" . (int)$data['order_uuid'] . "' AND (status = 'captured' OR status = 'closed' OR status = 'refunded')";
        //}

        return true;
    }

    protected function getMerchantCode($order) {
        
        /*$merchant_code = array_key_exists('shipping_address', $this->session->data)
            ? $this->session->data['shipping_address']['iso_code_2']
            : $this->session->data['payment_address' ]['iso_code_2'];

        return $merchant_code;*/

        return "EPPL";// $order->country? $order->country->iso: "KW";
    }

    protected function getPaymentObject($order) {
        $payment = [];

        $payment["order"] = [];
        $payment['order']['reference_id'] = $order->order_uuid. "";
        $payment["order"]["items"] = [];

       /* $order = Order::find()
            ->andWhere(['order_uuid' => $order->order_uuid])
            ->one();*/

        $payment['amount']      = number_format($order->total_price * $order->currency_rate, $order->currency->decimal_place);

            //$this->formatAmount($this->currency->format($order['total'], $order['currency_code'], $order['currency_value'], false));
        $payment['currency']    = $order['currency_code'];

       // $sid = array('sid' => $this->session->getId());
        $payment['description'] = "";//json_encode($sid);

        $images = [];

        /*foreach ($order->getOrderItems() as $product) {
            $images[$product['product_id']] =  $product['image'];
        }*/

        // categories

        $items = $order->getOrderItems()
            ->all();

        foreach ($items as $product) {

            /*$categories = $this->model_catalog_product->getCategories($product['product_id']);

            $category_name = '';
            if ($categories) {
                if ($cinfo = $this->model_catalog_category->getCategory($categories[0]['category_id'])) {
                    $category_name = $cinfo['name'];
                }
            }*/

            //$product['image'] = $images[$product['product_id']] ?: 'placeholder.png';

            $payment["order"]["items"][] = array(
                'title'         => Yii::$app->language == "ar"? $product->item_name_ar: $product->item_name,
                'reference_id'  => $product['order_item_id']. "",
                'unit_price'    => number_format($product['item_unit_price'] * $order->currency_rate, $order->currency->decimal_place),
                'tax_amount'    => 0,
                'quantity'      => (int) $product['qty'],
                'category'      => null,// $category_name,
                'product_url'   => null,//$this->url->link('product/product', 'product_id=' . $product['product_id'], true),
                'image_url'     => null //$this->getBaseUrl() .'image/'. $product['image']
            );

        }

        // get discount
        $totals = [];//$this->getOrderTotals($this->session->data['order_uuid']);
        $discount = 0;
        foreach ($totals as $total) {
            $discount += (in_array($total['code'], ['voucher', 'coupon'])) ? -$total['value'] : 0;
        }

        $payment['order']['discount_amount']  = $order->voucher_discount;
            //$this->formatAmount($this->currency->format($discount, $order['currency_code'], $order['currency_value'], false));

        $sub_total  = number_format($order->subtotal * $order->currency_rate, $order->currency->decimal_place);

        $total      = number_format($order->total_price * $order->currency_rate, $order->currency->decimal_place);
        $tax_total  = number_format($order->tax * $order->currency_rate, $order->currency->decimal_place);

        $shipping   = 0;
        $shipping_cost = 0;

        if ($order->shipping_country_id) {
            $shipping_cost  = number_format($order->delivery_fee * $order->currency_rate, $order->currency->decimal_place);

            //$shipping       = $this->tax->calculate($shipping_cost, $tax_class, $this->config->get('config_tax'));

            $payment["shipping_address"] = [
                'address'   =>$order->address_1 .
                    (!empty($order->address_2) ? ' ' : '') .
                    $order->address_2,
                'city'      => $order->city
            ];
        }

        $payment['order']['tax_amount']  =number_format($order->tax * $order->currency_rate, $order->currency->decimal_place);

        $payment['order']['shipping_amount']  = number_format($order->delivery_fee * $order->currency_rate, $order->currency->decimal_place);

        $payment['buyer']   = $this->getBuyerObject($order);

        /*$payment['order_history'] = $this->getOrderHistoryObject($payment['buyer']);

        if ($this->customer->isLogged()) {
            $res = $this->db->query("SELECT c.date_added, count(o.customer_id) as orders FROM `" . DB_PREFIX . "customer` c LEFT JOIN `" . DB_PREFIX . "order` o on (c.customer_id = o.customer_id AND order_status_id IN (3, 5, 11)) WHERE c.customer_id = " . (int)$this->session->data['customer_id'] . " GROUP BY c.customer_id");
            if ($res->num_rows) {
                $payment['buyer_history'] = [
                    'registered_since'  => (new \DateTime($res->row['date_added']))->format("c"),
                    'loyalty_level'     => (int)$res->row['orders']
                ];
            }
        }*/

        return $payment;
    }

    public function getPaymentObjectCreate($order) {
        $payment = [];

        $payment['amount']      = number_format($order->total_price * $order->currency_rate, $order->currency->decimal_place);

        //$this->formatAmount($this->currency->format($order['total'], $order['currency_code'], $order['currency_value'], false));
        $payment['currency']    = $order['currency_code'];

       // $sid = array('sid' => $this->session->getId());
        $payment['description'] = "";//json_encode($sid);
        $payment['buyer']   = $this->getBuyerObject($order);

        $payment["order"] = [];
        $payment["order"]["items"] = [];

        $images = [];
        /*foreach ($this->cart->getProducts() as $product) {
            $images[$product['product_id']] =  $product['image'];
        }*/

        // categories

        $items = $order->getOrderItems()
            ->all();

        foreach ($items as $product) {
            /*$categories = $this->model_catalog_product->getCategories($product['product_id']);
            $category_name = '';
            if ($categories) {
                $cinfo = $this->model_catalog_category->getCategory($categories[0]['category_id']);
                $category_name = array_key_exists('name', $cinfo) ? $cinfo['name'] : '';
            }*/

            //$product['image'] = $images[$product['product_id']] ?: 'placeholder.png';

            $payment["order"]["items"][] = array(
                'title'         => Yii::$app->language == "ar"? $product->item_name_ar: $product->item_name,
                'reference_id'  => $product['order_item_id']. "",
                'unit_price'    => number_format($product['item_unit_price'] * $order->currency_rate, $order->currency->decimal_place),
                'tax_amount'    => 0,
                'quantity'      => (int) $product['qty'],
                'category'      => null,// $category_name,
                'product_url'   => null,//$this->url->link('product/product', 'product_id=' . $product['product_id'], true),
                'image_url'     => null //$this->getBaseUrl() .'image/'. $product['image']
            );
        }

        $sub_total  = number_format($order->subtotal * $order->currency_rate, $order->currency->decimal_place);

        $total      = number_format($order->total_price * $order->currency_rate, $order->currency->decimal_place);

        $tax_total  = number_format($order->tax * $order->currency_rate, $order->currency->decimal_place);


        $shipping   = $shipping_cost = number_format($order->delivery_fee * $order->currency_rate, $order->currency->decimal_place);


        if ($order->shipping_country_id) {
            $shipping_cost  = number_format($order->delivery_fee * $order->currency_rate, $order->currency->decimal_place);

            //$shipping       = $this->tax->calculate($shipping_cost, $tax_class, $this->config->get('config_tax'));

            $payment["shipping_address"] = [
                'address'   =>$order->address_1 .
                    (!empty($order->address_2) ? ' ' : '') .
                    $order->address_2,
                'city'      => $order->city
            ];
        }

        $payment['order']['tax_amount']  = number_format($order->tax * $order->currency_rate, $order->currency->decimal_place); //$this->formatAmount($this->currency->format($tax_total + $shipping - $shipping_cost, $this->session->data['currency'], '', false));
        $payment['order']['shipping_amount']  = number_format($order->delivery_fee * $order->currency_rate, $order->currency->decimal_place);//$this->formatAmount($this->currency->format($shipping, $this->session->data['currency'], '', false));
        //$payment['amount'] += $payment['order']['shipping_amount'];
        // already contain currency_value
        $payment['amount'] = number_format($order->total_price * $order->currency_rate, $order->currency->decimal_place); //$this->formatAmount($payment['amount']);

        //$payment['order_history'] = $this->getOrderHistoryObject($payment['buyer']);

        /*if ($this->customer->isLogged()) {
            $res = $this->db->query("SELECT c.date_added, count(o.customer_id) as orders FROM `" . DB_PREFIX . "customer` c LEFT JOIN `" . DB_PREFIX . "order` o on (c.customer_id = o.customer_id AND order_status_id IN (3, 5, 11)) WHERE c.customer_id = " . (int)$this->session->data['customer_id'] . " GROUP BY c.customer_id");
            if ($res->num_rows) {
                $payment['buyer_history'] = [
                    'registered_since'  => (new \DateTime($res->row['date_added']))->format("c"),
                    'loyalty_level'     => (int)$res->row['orders']
                ];
            }
        }*/

        return $payment;
    }

    /*
    protected function getOrderHistoryObject($buyer) {
        $order_history = [];

        // get Order details by email and phone

        $where_fields = [
            'email'            => $buyer['email'],
            'telephone'        => $buyer['phone']
        ];

        $query = "SELECT order_uuid FROM `" . DB_PREFIX . "order`";
        $where = [];
        foreach ($where_fields as $name => $value) {
            $where[] = "$name = '".$this->db->escape($value)."'";
        }

        $order_query = $this->db->query($query . " WHERE order_status_id > 0 AND (" . implode(" OR ", $where) . ") order by order_uuid DESC");
        foreach ($order_query->rows as $row) {
            $order = $this->model_checkout_order->getOrder($row['order_uuid']);
            // bypass not finished orders
            if (in_array($this->getOrderHistoryStatus($order), array('new', 'processing'))) continue;

            $order_history[] = [
                "amount"            => $order->total_price,// $this->formatAmount($this->currency->format($order['total'], $order['currency_code'], $order['currency_value'], false)),
                "buyer"             => $this->getOrderHistoryBuyerObject($order),
                "items"             => $this->getOrderHistoryItemsObject($order),
                "payment_method"    => $order->payment_method_name,// $order['payment_code'],
                "purchased_at"      => date(\DateTime::RFC3339, strtotime($order['order_created_at'])),
                "shipping_address"  => $this->getOrderHistoryShippingAddressObject($order),
                "status"            => $this->getOrderHistoryStatus($order)
            ];
            if (count($order_history) >= 10) break;
        }

        return $order_history;
    }*/

    protected function getOrderHistoryStatus($order) {
        $status = 'processing';
        switch ($order['order_status']) {
            case 5:
                $status = 'canceled';
                break;
            case 4:
                $status = 'complete';
                break;
            case 6:
            case 7:
                $status = 'refunded';
                break;
            case 0:
            case 1:
            case 9:
                $status = 'new';
        };
     
        return $status;
    }

    protected function getOrderHistoryShippingAddressObject($order) {
        return [
            'address'   => $order['address_1'] . ' ' . $order['address_2'],
            'city'      => $order['city']
        ];
    }

    protected function getOrderHistoryItemsObject($order) {
        $result = [];

        $products = $order->getOrderItems()->all();

        foreach ($products as $product) {
            $result[] = [
                'quantity'      => (int)$product['qty'],
                'title'         => Yii::$app->language == "ar"? $product['item_name_ar']: $product['item_name'],
                'unit_price'    => number_format($product['item_unit_price'] * $order->currency_rate, $order->currency->decimal_place),
                'reference_id'  => $product['order_item_id']. "",
                'ordered'       => (int)$product['qty']
            ];
        }

        return $result;
    }

    protected function getOrderHistoryBuyerObject($order) {
        return [
            'name'  => $order['customer_name'],
            'phone' => $order['customer_phone_number']//$order['customer_phone_country_code'] . " ".
        ];
    }

    /**
     * @return array
     */
    protected function getBuyerObject($order) {

        $dob = null;
        $email = null;
        $name = null;
        $phone = null;

        /*if ($this->customer->isLogged()) {

            $this->load->model('account/customer');

            $ci = $this->model_account_customer->getCustomer($this->customer->getId());

            $name = $ci['firstname'] . ' ' . $ci['lastname'];
            $email = $ci['email'];
            $phone = $ci['telephone'];
        } elseif(array_key_exists('guest', $this->session->data)) {
            $name   = $this->session->data['guest']['firstname'] . ' ' . $this->session->data['guest']['lastname'];
            $email  = $this->session->data['guest']['email'];
            $phone  = $this->session->data['guest']['telephone'];
            if (array_key_exists('order_data', $_POST)) {
                // some situations, variables exists in post, but not updated on order
                $name   = $_POST['order_data']['firstname'] . ' ' . $_POST['order_data']['lastname'];
                $email  = $_POST['order_data']['email'];
                $phone  = $_POST['order_data']['telephone'];
            }
        } elseif (array_key_exists('order_data', $_POST)) {
            // some situations, variables exists in post, but not updated on order
            $name   = $_POST['order_data']['firstname'] . ' ' . $_POST['order_data']['lastname'];
            $email  = $_POST['order_data']['email'];
            $phone  = $_POST['order_data']['telephone'];
        } elseif (defined('JOURNAL3_ACTIVE') && array_key_exists('order_uuid', $this->session->data)) {
            $this->load->model('journal3/order');
            $data = $this->model_journal3_order->load($this->session->data['order_uuid']);
            $name   = $data['firstname'] . ' ' . $data['lastname'];
            $email  = $data['email'];
            $phone  = $data['telephone'];
        }*/

        return [
            "dob"   => null,// $order->,
            "email" => $order->customer_email,
            "name"  => $order->customer_name,
            "phone" => $order->customer_phone_number
        ];
    }

    protected function formatAmount($amount) {
        return number_format($amount, 2, '.', '');
    }

    public function execute($method, $endpoint, $data = array(), $restaurant_uuid) {
        $publicKey = Setting::getConfig($restaurant_uuid, PaymentMethod::CODE_TABBY,
            'payment_tabby_public_key');
        $secretKey = Setting::getConfig($restaurant_uuid, PaymentMethod::CODE_TABBY,
            'payment_tabby_secret_key');

        $this->errors = [];

        if ($endpoint == "checkout") {
            $url = $this->apiCheckoutUrl;
        } else {
            $url = $this->apiUrl . $endpoint;
        }

        $curl_options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 10
        );

        $curl_options[CURLOPT_HTTPHEADER][] = 'Accept-Charset: utf-8';
        $curl_options[CURLOPT_HTTPHEADER][] = 'Accept: application/json';
        if ($endpoint == "checkout") {
            $curl_options[CURLOPT_HTTPHEADER][] = 'Authorization: Bearer ' . trim($publicKey);
        } else {
            $curl_options[CURLOPT_HTTPHEADER][] = 'Authorization: Bearer ' . trim($secretKey);
        }

        if ($method != "GET") {
            $data_json = json_encode($data);
            $curl_options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
            $curl_options[CURLOPT_HTTPHEADER][] = 'Content-Length: ' . strlen($data_json);
            $curl_options[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
            $curl_options[CURLOPT_POSTFIELDS] = $data_json;
        }

        $ch = curl_init();
        curl_setopt_array($ch, $curl_options);
        $response = curl_exec($ch);

        $result = [];
        $curl_info = curl_getinfo($ch);

        if ($curl_info['http_code'] != 200) {
            $this->errors[] = array('name' => 'http_code', 'message' => $curl_info['http_code'], 'response' => $response);
        } else {

            TabbyTransaction::ddlog('info', 'api call', null, array(
                'url'           => $url,
                'curl_options'  => $curl_options,
                'response'      => $response
            ));
        }

        if (curl_errno($ch)) {
            $curl_code = curl_errno($ch);

            $constant = get_defined_constants(true);
            $curl_constant = preg_grep('/^CURLE_/', array_flip($constant['curl']));

            $this->errors[] = array('name' => $curl_constant[$curl_code], 'message' => curl_strerror($curl_code));
        }

        try {
            $result = json_decode($response);
        } catch (Exception $e) {
            $this->errors[] = ['name' => 'json', 'message' => $e->getMessage()];
        }

        if(empty($this->errors)) {
            return $result;
        } else {

            // log error
            TabbyTransaction::ddlog('error', 'request error', null, $this->errors);
            $res = new \stdClass();
            $res->status = 'error';
            $res->errors = $this->errors;
            return $res;
        }
    }

    public function getTransactionIdByOrder($order_uuid) {
        $model = TabbyTransaction::find()
            ->andWhere(['order_uuid' => $order_uuid])
            ->orderBy("id DESC")
            ->one();
        return isset($model['transaction_id']) ? $model['transaction_id'] : 0;
    }

    public function getTransactionRecord($transaction_id) {
        return TabbyTransaction::find()
            ->andWhere(['transaction_id' => $transaction_id])
            ->orderBy("id DESC")
            ->one();
    }

    public function getTransaction($transaction_id) {
        $row = $this->getTransactionRecord($transaction_id);
        return $row['body'];
    }

    public function getTransactionStatus($transaction_id) {
        $row = $this->getTransactionRecord($transaction_id);
        return $row  ? $row['status'] : null;//&& array_key_exists('status', $row)
    }

    public function getOrderIdByTransaction($transaction_id) {
        $row = $this->getTransactionRecord($transaction_id);
        return $row['order_uuid'];
    }

    public function getTabbyTransaction($transaction_id, $restaurant_uuid) {
        $endpoint = $transaction_id;
        return $this->execute("GET", $endpoint, $restaurant_uuid);
    }

    public function capture($transaction_id, $amount, $restaurant_uuid) {
        $error = true;
        $message = "";

        $transaction = $this->getTabbyTransaction($transaction_id, $restaurant_uuid);
        if ($transaction->status != "AUTHORIZED") {
            $message = 'Payment is not authorized';
        } else if (count($transaction->captures) > 0) {
            $message = 'Payment is captured';
            //$error = false;
        } else {
            $endpoint = $transaction_id . "/captures";
            $params = array(
                'amount' => $amount
            );

            $transaction = $this->execute("POST", $endpoint, $params, $restaurant_uuid);
            if (empty($transaction)) {
                $message = "Transaction not found.";
            } elseif ($transaction->status == 'error') {
                $message = $transaction->error;
            } elseif ($transaction->status !== 'CLOSED' && $transaction->status !== 'AUTHORIZED') {
                $message = "Transaction state is not valid.";
            } else {
                $error = false;
                /*
                $this->model_checkout_order->addOrderHistory(
                    $transaction->order->reference_id, $status,
                    sprintf("Capture transaction #%s. Amount %s %s", $transaction->captures[count($transaction->captures) - 1]->id, $amount, $transaction->currency)
                );*/

                $order = Order::find()
                    ->andWhere(['order_uuid' => $transaction->order->reference_id])
                    ->one();

                $payment_tabby_order_status = Setting::getConfig($order->restaurant_uuid,
                    PaymentMethod::CODE_TABBY,
                    'payment_tabby_order_status');

                $payment_tabby_capture_status = Setting::getConfig($order->restaurant_uuid,
                    PaymentMethod::CODE_TABBY,
                    'payment_tabby_capture_status');

                /*$payment_tabby_order_status_id = Setting::getConfig($order->restaurant_uuid,
                    PaymentMethod::CODE_TABBY,
                    'payment_tabby_order_status_id');*/

                $status = $transaction->status == 'CLOSED'
                    ? ($payment_tabby_capture_status ?: Order::STATUS_COMPLETE)
                    : ($payment_tabby_order_status ?: Order::STATUS_BEING_PREPARED);

                OrderHistory::addOrderHistory($order->order_uuid, $status,
                    sprintf("Capture transaction #%s. Amount %s %s", $transaction->captures[count($transaction->captures) - 1]->id, $amount, $transaction->currency)
                );

                $this->addTransaction([
                    'order_uuid'       => $transaction->order->reference_id,//$transaction->order_uuid,
                    'transaction_id' => $transaction_id,
                    'body'           => json_encode($transaction),
                    'status'         => 'captured',
                    'source'         => 'admin'
                ]);
            }
        }

        TabbyTransaction::ddlog($error ? 'error' : 'info', 'capture', null, array(
            'payment'   => array(
                'id'    => $transaction_id
            ),
            'amount'    => $amount,
            'message'   => $message,
            'error'     => $error
        ));

        return array(
            'error'    => $error,
            'message'  => $message
        );
    }

    public function refund($transaction_id, $amount, $restaurant_uuid) {
        $error = true;
        $message = "";

        $transaction = $this->getTabbyTransaction($transaction_id, $restaurant_uuid);

        if (count($transaction->refunds) > 0) {
            $refunds_total = 0;
            foreach ($transaction->refunds as $refund) {
                $refunds_total += $refund->amount;
            }
        }
        if (count($transaction->captures) == 0) {
            $message = 'Payment is not captured';
        } else if (count($transaction->refunds) > 0 && $refunds_total == $transaction->amount) {
            $message = 'Payment is refunded';
            //$error = false;
        } else {
            $capture_id = $transaction->captures[0]->id;
            $endpoint = $transaction_id . "/refunds";
            $captures_total = 0;
            foreach ($transaction->captures as $capture) {
                $captures_total += $capture->amount;
            }

            $params = array(
                'capture_id' => $capture_id,
                'amount' => $amount
            );

            $transaction = $this->execute("POST", $endpoint, $params, $restaurant_uuid);
            if (empty($transaction)) {
                $message = "Transaction not found.";
            } elseif ($transaction->status == 'error') {
                $message = $transaction->error;
            } elseif ($transaction->status !== 'CLOSED') {
                $message = "Transaction state is not valid.";
            } else {
                $error = false;
                /*$this->model_checkout_order->addOrderHistory(
                    $transaction->order->reference_id, $this->config->get('payment_tabby_refund_status_id') ?: 11,
                    sprintf("Refund transaction #%s. Amount %s %s", $transaction->refunds[count($transaction->refunds) - 1]->id, $amount, $transaction->currency)
                );*/

                $status = Order::STATUS_REFUNDED;

                OrderHistory::addOrderHistory($transaction->order->reference_id, $status,
                    sprintf("Refund transaction #%s. Amount %s %s", $transaction->refunds[count($transaction->refunds) - 1]->id, $amount, $transaction->currency)
                );

                $this->updateTransaction([
                    'order_uuid' => $transaction->order->reference_id,// $transaction->order_uuid,
                    'body' => json_encode($transaction),
                    'status' => 'refunded'
                ]);
            }
        }

        TabbyTransaction::ddlog($error ? 'error' : 'info', 'refund', null, array(
            'payment'   => array(
                'id'    => $transaction_id
            ),
            'amount'    => $amount,
            'message'   => $message,
            'error'     => $error
        ));

        return array(
            'error'    => $error,
            'message'  => $message
        );
    }

    public function close($transaction_id, $restaurant_uuid) {
        $error = true;
        $message = "";

        $transaction = $this->getTabbyTransaction($transaction_id, $restaurant_uuid);

        if ($transaction->status != "CREATED" && $transaction->status != "AUTHORIZED") {
            $message = 'Transaction state is not valid.';
        } else {
            $endpoint = $transaction_id . "/close";
            $transaction = $this->execute("POST", $endpoint, $restaurant_uuid);
            if (empty($transaction)) {
                $message = "Transaction not found.";
            } elseif ($transaction->status == 'error') {
                $message = $transaction->error;
            } elseif ($transaction->status != 'CLOSED') {
                $message = "Transaction state is not valid.";
            } else {
                $error = false;

                /*$this->model_checkout_order->addOrderHistory(
                    $transaction->order->reference_id, $this->config->get('payment_tabby_cancel_status_id') ?: 7,
                    sprintf("Closed transaction #%s. Amount %s %s", $transaction_id, $transaction->amount, $transaction->currency)
                );*/

                OrderHistory::addOrderHistory($transaction->order->reference_id, Order::STATUS_CANCELED,
                    sprintf("Closed transaction #%s. Amount %s %s", $transaction_id, $transaction->amount, $transaction->currency)
                );

                $this->updateTransaction([
                    'order_uuid' => $transaction->order->reference_id,// $transaction->order_uuid,
                    'body' => json_encode($transaction),
                    'status' => 'closed'
                ]);
            }
        }

        TabbyTransaction::ddlog($error ? 'error' : 'info', 'close', null, array(
            'payment'   => array(
                'id'    => $transaction_id
            ),
            'message'   => $message,
            'error'     => $error
        ));

        return array(
            'error'    => $error,
            'message'  => $message
        );
    }

    public function delete($transaction_id, $restaurant_uuid) {
        $error = true;

        $transaction = $this->getTabbyTransaction($transaction_id, $restaurant_uuid);

        if ($transaction->status == 'CREATED') {
            $order_uuid = $this->getOrderIdByTransaction($transaction_id);

            if ($order_uuid) {

                $order = Order::find()
                    ->andWhere(['order_uuid' => $order_uuid])
                    ->one();

                $order->delete();

                $this->updateTransaction([
                    'order_uuid' => $order_uuid,
                    'body' => json_encode($transaction),
                    'status' => 'deleted'
                ]);
                $error = false;
            }
        }

        TabbyTransaction::ddlog($error ? 'error' : 'info', 'delete', null, array(
            'payment'   => array(
                'id'    => $transaction_id
            ),
            'error'     => $error
        ));

        return array(
            'error'  => $error,
        );
    }

    public function getErrors($attribute = null) {
        return $this->errors;
    }
}
