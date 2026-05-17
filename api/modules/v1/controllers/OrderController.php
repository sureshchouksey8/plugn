<?php

namespace api\modules\v1\controllers;

use common\models\Currency;
use Yii;
use yii\db\Expression;
use yii\rest\Controller;
use yii\data\ActiveDataProvider;
use common\models\Voucher;
use common\models\Bank;
use api\models\Order;
use common\models\OrderItem;
use common\models\CustomerBankDiscount;
use common\models\OrderItemExtraOption;
use common\models\AreaDeliveryZone;
use api\models\Restaurant;
use common\models\BankDiscount;
use common\models\RestaurantBranch;
use api\models\BusinessLocation;
use api\models\Payment;
use common\components\TapPayments;
use yii\helpers\Url;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class OrderController extends BaseController {

    public function behaviors() {
        $behaviors = parent::behaviors();

        // remove authentication filter for cors to work
        unset($behaviors['authenticator']);

        // Allow XHR Requests from our different subdomains and dev machines
        $behaviors['corsFilter'] = [
            'class' => \yii\filters\Cors::className(),
            'cors' => [
                'Origin' => Yii::$app->params['allowedOrigins'],
                'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Allow-Credentials' => null,
                'Access-Control-Max-Age' => 86400,
                'Access-Control-Expose-Headers' => [
                    'X-Pagination-Current-Page',
                    'X-Pagination-Page-Count',
                    'X-Pagination-Per-Page',
                    'X-Pagination-Total-Count'
                ],
            ],
        ];

        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function actions() {
        $actions = parent::actions();
        $actions['options'] = [
            'class' => 'yii\rest\OptionsAction',
            // optional:
            'collectionOptions' => ['GET', 'POST', 'HEAD', 'OPTIONS'],
            'resourceOptions' => ['GET', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
        ];
        return $actions;
    }

    /**
     * Place an order
     */
    public function actionPlaceAnOrder($id) {

        $restaurant = Restaurant::findOne($id);

        if ($restaurant) {

            $order = new Order();
            $order->setScenario(Order::SCENARIO_OLD_VERSION);

            $order->restaurant_uuid = $restaurant->restaurant_uuid;

            //Save Customer Info
            $order->utm_uuid = Yii::$app->request->getBodyParam("utm_uuid");
            $order->order_instruction = Yii::$app->request->getBodyParam("order_instruction");
            $order->customer_name = Yii::$app->request->getBodyParam("customer_name");
            $order->customer_phone_country_code = '965';
            $order->customer_phone_number = '+' . $order->customer_phone_country_code . strval(Yii::$app->request->getBodyParam("phone_number"));

            $order->customer_phone_number = str_replace(' ','',$order->customer_phone_number);

            $order->customer_email = Yii::$app->request->getBodyParam("email"); //optional
            //payment method
            $order->payment_method_id = Yii::$app->request->getBodyParam("payment_method_id");

            //save Customer address
            $order->order_mode = Yii::$app->request->getBodyParam("order_mode");

            //Preorder
            // if( Yii::$app->request->getBodyParam("is_order_scheduled") !== null)
            $order->is_order_scheduled = Yii::$app->request->getBodyParam("is_order_scheduled") ? Yii::$app->request->getBodyParam("is_order_scheduled") : 0;

            //Apply promo code
            if (Yii::$app->request->getBodyParam("voucher_id")) {
                $order->voucher_id = Yii::$app->request->getBodyParam("voucher_id");
            }

            //if the order mode = 1 => Delivery
            if ($order->order_mode == Order::ORDER_MODE_DELIVERY)
            {
                $order->area_id = Yii::$app->request->getBodyParam("area_id");

                if($order->area_id && $areaDeliveryZone = AreaDeliveryZone::find()->where(['restaurant_uuid' => $restaurant->restaurant_uuid, 'area_id' =>  $order->area_id])->one())
                   $order->delivery_zone_id = $areaDeliveryZone->delivery_zone_id;

                $order->unit_type = Yii::$app->request->getBodyParam("unit_type");
                $order->block = Yii::$app->request->getBodyParam("block");
                $order->street = Yii::$app->request->getBodyParam("street");
                $order->avenue = Yii::$app->request->getBodyParam("avenue"); //optional
                $order->house_number = Yii::$app->request->getBodyParam("house_number");
                $order->special_directions = Yii::$app->request->getBodyParam("special_directions"); //optional

                if (Yii::$app->request->getBodyParam("deliver_location_latitude"))
                    $order->latitude = Yii::$app->request->getBodyParam("deliver_location_latitude"); //optional
                if (Yii::$app->request->getBodyParam("deliver_location_longitude"))
                    $order->longitude = Yii::$app->request->getBodyParam("deliver_location_longitude"); //optional


                //Preorder
                if ($order->is_order_scheduled != null && $order->is_order_scheduled == true && $restaurant->schedule_order) {
                    $scheduled_time_start_from = Yii::$app->request->getBodyParam("scheduled_time_start_from");

                    $order->scheduled_time_start_from = $scheduled_time_start_from?
                        date("Y-m-d H:i:s", strtotime($scheduled_time_start_from)) : null;

                    $scheduled_time_to = Yii::$app->request->getBodyParam("scheduled_time_to");
                    
                    $order->scheduled_time_to = $scheduled_time_to?
                        date("Y-m-d H:i:s", strtotime($scheduled_time_to)) : null;
                }

            } else if ($order->order_mode == Order::ORDER_MODE_PICK_UP) {
                $order->restaurant_branch_id = Yii::$app->request->getBodyParam("restaurant_branch_id");

                $restaurantBranch = RestaurantBranch::findOne($order->restaurant_branch_id);

                if($restaurantBranch)
                {
                  $pickupLocation = BusinessLocation::find()
                    ->andWhere([
                        'business_location_name' => $restaurantBranch->branch_name_en,
                           'business_location_name_ar' => $restaurantBranch->branch_name_ar
                         ])->one();

                  if($pickupLocation)
                    $order->pickup_location_id = $pickupLocation->business_location_id;

                }
            }

            $response = null;
            $orderAssemblyCommitted = false;
            $transaction = Yii::$app->db->beginTransaction();

            if ($order->save()) {

                $items = Yii::$app->request->getBodyParam("items");


                if ($items) {

                    foreach ($items as $item) {

                        //Save items to the above order
                        $orderItem = new OrderItem;

                        $orderItem->order_uuid = $order->order_uuid;
                        $orderItem->item_uuid = $item["item_uuid"];
                        $orderItem->qty = (int) $item["qty"];


                        //optional field
                        if (array_key_exists("customer_instructions", $item) && $item["customer_instructions"] != null)
                            $orderItem->customer_instruction = $item["customer_instructions"];

                        if ($orderItem->save()) {

//                                There seems to be an issue with your payment, please try again.
                            if (array_key_exists('extraOptions', $item)) {


                                $extraOptionsArray = $item['extraOptions'];


                                if (isset($extraOptionsArray) && count($extraOptionsArray) > 0) {

                                    foreach ($extraOptionsArray as $key => $extraOption) {

                                        $orderItemExtraOption = new OrderItemExtraOption;
                                        $orderItemExtraOption->order_item_id = $orderItem->order_item_id;
                                        $orderItemExtraOption->extra_option_id = $extraOption['extra_option_id'];
                                        $orderItemExtraOption->qty = (int) $item["qty"];

                                        if (!$orderItemExtraOption->save()) {

                                            $response = [
                                                'operation' => 'error',
                                                'message' => $orderItemExtraOption->errors,
                                            ];
                                            break 2;
                                        }
                                    }
                                }
                            }
                        } else {

                            $response = [
                                'operation' => 'error',
                                'message' => $orderItem->getErrors()
                            ];
                            break;
                        }
                    }
                } else {
                    $response = [
                        'operation' => 'error',
                        'message' => 'Item Uuid is invalid.'
                    ];
                }
            } else {
                $response = [
                    'operation' => 'error',
                    'message' => $order->getErrors(),
                ];
            }

            if (!$order->is_order_scheduled && !$restaurant->isOpen()) {
                $response = [
                    'operation' => 'error',
                    'message' => $restaurant->name . ' is currently closed and is not accepting orders at this time',
                ];
            }


            if ($response == null) {

              if (!$order->updateOrderTotalPrice()) {
                  $response = [
                      'operation' => 'error',
                      'message' => $order->getErrors()
                  ];
              }

                if ($response == null && $order->order_mode == Order::ORDER_MODE_DELIVERY && $order->subtotal < $order->restaurantDelivery->min_charge) {
                    $response = [
                        'operation' => 'error',
                        'message' => 'Minimum order amount ' . Yii::$app->formatter->asCurrency(
                            $order->restaurantDelivery->min_charge,
                            $order->currency->code, [
                                \NumberFormatter::MAX_SIGNIFICANT_DIGITS => $order->currency->decimal_place
                            ])
                    ];
                }

                if ($response == null) {
                    $transaction->commit();
                    $orderAssemblyCommitted = true;
                } else {
                    $transaction->rollBack();
                }

                //if payment method not cash redirect customer to payment gateway

                if ($response == null && $order->payment_method_id != 3) {

                    // Create new payment record
                    $payment = new Payment;
                    $payment->restaurant_uuid = $restaurant->restaurant_uuid;
                    $payment->payment_mode = $order->payment_method_id == 1 ? TapPayments::GATEWAY_KNET : TapPayments::GATEWAY_VISA_MASTERCARD;
                    $payment->is_sandbox = $restaurant->is_sandbox;

                    if ($payment->payment_mode == TapPayments::GATEWAY_VISA_MASTERCARD && Yii::$app->request->getBodyParam("payment_token") && Yii::$app->request->getBodyParam("bank_name")) {

                        Yii::$app->tapPayments->setApiKeys(
                            $order->restaurant->live_api_key,
                            $order->restaurant->test_api_key,
                            $payment->is_sandbox
                        );

                        $response = Yii::$app->tapPayments->retrieveToken(Yii::$app->request->getBodyParam("payment_token"));

                        $responseContent = json_decode($response->content);


                        try {

                            // Validate that theres no error from TAP gateway
                            if (isset($responseContent->status) && $responseContent->status == "fail") {
                                $errorMessage = "Error: Invalid Token ID";

                                //\Yii::error($errorMessage, __METHOD__); // Log error faced by user

                                return [
                                    'operation' => 'error',
                                    'message' => 'Invalid Token ID'
                                ];
                            } else if (isset($responseContent->id) && $responseContent->id) {

                                $bank_name = Yii::$app->request->getBodyParam("bank_name");

                                $bank_discount_model = BankDiscount::find()
                                        ->innerJoin('bank', 'bank.bank_id = bank_discount.bank_id')
                                        ->andWhere(['bank.bank_name' => $bank_name])
                                        ->andWhere(['restaurant_uuid' => $order->restaurant_uuid])
                                        ->andWhere(['<=' ,'minimum_order_amount' , $order->total_price])
                                        ->one();


                                if ($bank_discount_model) {
                                    if ($bank_discount_model->isValid($order->customer_phone_number)) {
                                        $customerBankDiscount = new CustomerBankDiscount();
                                        $customerBankDiscount->customer_id = $order->customer_id;
                                        $customerBankDiscount->bank_discount_id = $bank_discount_model->bank_discount_id;
                                        $customerBankDiscount->save();
                                    }

                                    $order->bank_discount_id = $bank_discount_model->bank_discount_id;

                                }

                                $payment->payment_token = Yii::$app->request->getBodyParam("payment_token");

                            }
                        } catch (\Exception $e) {

                            //Yii::error('[TAP Payment Issue > Invalid Token ID]' . json_encode($responseContent), __METHOD__);

                            $response = [
                                'operation' => 'error',
                                'message' => 'Invalid Token id'
                            ];
                        }
                    }

                    $payment->customer_id = $order->customer? $order->customer->customer_id: null; //customer id
                    $payment->order_uuid = $order->order_uuid;
                    $payment->payment_amount_charged = $order->total;
                    $payment->payment_current_status = "Redirected to payment gateway";
                    $payment->is_sandbox = $order->restaurant->is_sandbox;

                    if ($payment->save()) {

                        //Update payment_uuid in order
                        $order->payment_uuid = $payment->payment_uuid;
                        $order->save(false);
                        if (!$order->updateOrderTotalPrice()) {
                            return [
                                'operation' => 'error',
                                'message' => $order->getErrors()
                            ];
                        }

                          Yii::info("[" . $restaurant->name . ": Payment Attempt Started] " . $order->customer_name . ' start attempting making a payment ' .
                              Yii::$app->formatter->asCurrency($order->total, $order->currency->code, [\NumberFormatter::MAX_SIGNIFICANT_DIGITS => $order->currency->decimal_place]), __METHOD__);

                        // Redirect to payment gateway
                        Yii::$app->tapPayments->setApiKeys(
                            $order->restaurant->live_api_key,
                            $order->restaurant->test_api_key,
                            $payment->is_sandbox
                        );

                        if ($order->payment_method_id == 1) {
                            $source_id = TapPayments::GATEWAY_KNET;
                        } else {
                            if ($payment->payment_token)
                                $source_id = $payment->payment_token;
                            else
                                $source_id = TapPayments::GATEWAY_VISA_MASTERCARD;
                        }

                        // $source_id
                        $response = Yii::$app->tapPayments->createCharge(
                                $order->currency->code,
                                "Order placed from: " . $order->customer_name, // Description
                                $order->restaurant->name, //Statement Desc.
                                $payment->payment_uuid, // Reference
                                $order->total,
                                 $order->customer_name,
                                 $order->customer_email,
                                 $order->customer_phone_country_code,
                                 $order->customer_phone_number,
                                 $order->restaurant->platform_fee,
                                 Url::to(['order/callback'], true),
                                Url::to(['order/payment-webhook'], true),
                                $order->paymentMethod->source_id == TapPayments::GATEWAY_VISA_MASTERCARD && $payment->payment_token ? $payment->payment_token : $order->paymentMethod->source_id,
                                $order->restaurant->warehouse_fee,
                                $order->restaurant->warehouse_delivery_charges,
                                $order->area_id ? $order->area->country->country_name : '',
                                $order->restaurant_uuid
                        );

                        $responseContent = json_decode($response->content);

                        try {

                            // Validate that theres no error from TAP gateway
                            if (isset($responseContent->errors)) {
                                $errorMessage = "Error: " . $responseContent->errors[0]->code . " - " . $responseContent->errors[0]->description;

                                //\Yii::error($errorMessage, __METHOD__); // Log error faced by user

                                return [
                                    'operation' => 'error',
                                    'message' => $errorMessage
                                ];
                            }

                            if ($responseContent->id) {

                                $chargeId = $responseContent->id;
                                $redirectUrl = $responseContent->transaction->url;

                                $payment->payment_gateway_transaction_id = $chargeId;

                                if (!$payment->save(false)) {

                                    //\Yii::error($payment->errors, __METHOD__); // Log error faced by user

                                    return [
                                        'operation' => 'error',
                                        'message' => $payment->getErrors()
                                    ];
                                }
                            } else {
                               // \Yii::error('[Payment Issue > Charge id is missing ]' . $responseContent, __METHOD__); // Log error faced by user

                                return [
                                    'operation' => 'error',
                                    'message' => 'Payment Issue > Charge id is missing',
                                ];
                            }


                            return [
                                'operation' => 'redirecting',
                                'redirectUrl' => $redirectUrl,
                            ];
                        } catch (\Exception $e) {

                            /*
                            todo: notify vendor/ admin?
                            if ($payment)
                                Yii::error('[TAP Payment Issue > ]' . json_encode($payment->getErrors()), __METHOD__);

                            Yii::error('[TAP Payment Issue > Charge id is missing]' . json_encode($responseContent), __METHOD__);*/

                            $response = [
                                'operation' => 'error',
                                'message' => $responseContent
                            ];
                        }
                    } else {

                        $response = [
                            'operation' => 'error',
                            'message' => $payment->getErrors()
                        ];
                    }
                } else {


                    //pay by Cash
                    if ($response == null) {

                        //Change order status to pending
                        $order->changeOrderStatusToPending();
                        $order->sendPaymentConfirmationEmail();

                        Yii::info("[" . $order->restaurant->name . ": " . $order->customer_name . " has placed an order for " .
                            Yii::$app->formatter->asCurrency($order->total, $order->currency->code, [\NumberFormatter::MAX_SIGNIFICANT_DIGITS => $order->currency->decimal_place]) . '] ' . 'Paid with ' . $order->payment_method_name, __METHOD__);


//                            //Update product inventory
//                            foreach ($order->getOrderItems()->all() as $orderItem) {
//                                $orderItem->item->decreaseStockQty($orderItem->qty);
//                            }

                        $response = [
                            'operation' => 'success',
                            'order_uuid' => $order->order_uuid,
                            // 'estimated_time_of_arrival' => $order->estimated_time_of_arrival,
                            'message' => 'Order created successfully',
                        ];
                    }
                }
            }

            if (!$orderAssemblyCommitted && $transaction->getIsActive()) {
                $transaction->rollBack();
            }

            if ($orderAssemblyCommitted && is_array($response) && array_key_exists('operation', $response) && $response['operation'] == 'error') {
                $order->delete();
            }
        } else {
            $response = [
                'operation' => 'error',
                'message' => 'Store Uuid is invalid'
            ];
        }

        //for https://pogi.sentry.io/issues/3889482226/?project=5220572&query=is%3Aunresolved&referrer=issue-stream&stream_index=0

        if ($restaurant) {
            $restaurant->updateStats();
        }
        
        return $response;
    }

    /**
     * Process callback from TAP payment gateway
     * @param string $tap_id
     * @return mixed
     */
    public function actionCallback($tap_id) {

        try {
            $paymentRecord = Payment::updatePaymentStatusFromTap($tap_id);
            $paymentRecord->received_callback = true;
            $paymentRecord->save(false);

            // Redirect back to app
            if ($paymentRecord->payment_current_status != 'CAPTURED') {  //Failed Payment
                return $this->redirect($paymentRecord->restaurant->restaurant_domain . '/payment-failed/' . $paymentRecord->order_uuid);
            }

            // Redirect back to app
            // $paymentRecord->order->changeOrderStatusToPending();
            return $this->redirect($paymentRecord->restaurant->restaurant_domain . '/payment-success/' . $paymentRecord->order_uuid . '/' . $paymentRecord->payment_uuid);
        } catch (\Exception $e) {
            throw new NotFoundHttpException($e->getMessage());
        }
    }

    /**
     * Get Order detail
     * WIll delete this fun after we merge with all stores
     * @param type $id
     * @param type $restaurant_uuid
     * @return type
     */
    public function actionOrderDetails($id, $restaurant_uuid) {
      $model = Order::find()->where(['order_uuid' => $id, 'restaurant_uuid' => $restaurant_uuid])->with('orderItems', 'payment')->asArray()->one();


        if (!$model) {
            return [
                'operation' => 'error',
                'message' => 'Invalid order uuid'
            ];
        }

        return [
            'operation' => 'success',
            'body' => $model
        ];
    }

    /**
     * Get Order detail
     * @param type $id
     * @param type $restaurant_uuid
     * @return type
     */
    // public function actionGetOrderDetails($id, $restaurant_uuid) {
    //     $model = Order::find()->where(['order_uuid' => $id, 'restaurant_uuid' => $restaurant_uuid])->one();
    //
    //
    //     if (!$model) {
    //         return [
    //             'operation' => 'error',
    //             'message' => 'Invalid order uuid'
    //         ];
    //     }
    //
    //     return [
    //         'operation' => 'success',
    //         'body' => $model
    //     ];
    // }

    /**
     * Whether is valid promo code or no
     */
    public function actionApplyBankDiscount() {
        $restaurant_uuid = Yii::$app->request->get("restaurant_uuid");
        $phone_number = Yii::$app->request->get("phone_number");
        $bank_name = Yii::$app->request->get("bank_name");


        $bank_discount_model = BankDiscount::find()
                ->innerJoin('bank', 'bank.bank_id = bank_discount.bank_id')
                ->andWhere(['bank.bank_name' => $bank_name])
                ->andWhere(['restaurant_uuid' => $restaurant_uuid])
                ->one();

        if ($bank_discount_model && $bank_discount_model->isValid($phone_number)) {
            return [
                'operation' => 'success',
                'bank_discount' => $bank_discount_model
            ];
        }

        return [
            'operation' => 'error',
            'message' => 'Bank Discount is invalid or expired'
        ];
    }

    /**
     * Whether is valid promo code or no
     */
    public function actionApplyPromoCode() {
        $restaurant_uuid = Yii::$app->request->get("restaurant_uuid");
        $phone_number = Yii::$app->request->get("phone_number");
        $code = Yii::$app->request->get("code");

        $voucher = Voucher::find()
            ->where(['restaurant_uuid' => $restaurant_uuid, 'code' => $code, 'voucher_status' => Voucher::VOUCHER_STATUS_ACTIVE])
            ->one();

        if ($voucher && $voucher->isValid($phone_number)) {
            return [
                'operation' => 'success',
                'voucher' => $voucher
            ];
        }

        return [
            'operation' => 'error',
            'message' => 'Voucher code is invalid or expired'
        ];
    }

    /**
     * update order instruction
     */
    public function actionInstruction($order_uuid = null)
    {
        $order_instruction = Yii::$app->request->getBodyParam('order_instruction');

        Order::updateAll(['order_instruction' => $order_instruction], [
            'order_uuid' => $order_uuid
        ]);

        return [
            'operation' => 'success',
        ];
    }

    /**
     * CheckPendingOrders of type boolean and we want to return
     * True if there are pending  orders , false if these isn't any
     * @param type $restaurantUuid
     * @return boolean
     */
    public function actionCheckPendingOrders($restaurant_uuid) {
        return Order::find()
                        ->andWhere([
                            'restaurant_uuid' => $restaurant_uuid,
                            'order_status' => Order::STATUS_PENDING
                        ])->exists();
    }

    /**
     * Update order status
     */
    public function actionUpdateMashkorOrderStatus() {

        $mashkor_order_number = Yii::$app->request->getBodyParam("order_number");
        $mashkor_secret_token = Yii::$app->request->getBodyParam("webhook_token");

        if ($mashkor_secret_token === '2125bf59e5af2b8c8b5e8b3b19f13e1221') {

          $order_model = Order::find()->where(['mashkor_order_number' => $mashkor_order_number])->one();

          if($order_model) {

              $order_model->setScenario(Order::SCENARIO_UPDATE_MASHKOR_STATUS);

            $order_model->mashkor_driver_name = Yii::$app->request->getBodyParam("driver_name");
            $order_model->mashkor_driver_phone = Yii::$app->request->getBodyParam("driver_phone");
            $order_model->mashkor_tracking_link = Yii::$app->request->getBodyParam("tracking_link");
            $order_model->mashkor_order_status = Yii::$app->request->getBodyParam("order_status");

            if( $order_model->mashkor_order_status == Order::MASHKOR_ORDER_STATUS_IN_DELIVERY ) // In delivery
                $order_model->order_status = Order::STATUS_OUT_FOR_DELIVERY;

            if( $order_model->mashkor_order_status == Order::MASHKOR_ORDER_STATUS_DELIVERED ) // Delivered
                $order_model->order_status = Order::STATUS_COMPLETE;

            if ($order_model->save(false)) {
                return [
                    'operation' => 'success'
                ];
            } else {

             //Yii::error('[Mashkor (Webhook): Error while changing order status ]' . json_encode($order_model->getErrors()), __METHOD__);

              return [
                  'operation' => 'error',
                  'message' => $order_model->getErrors(),
              ];
            }

          } else {
            return [
                'operation' => 'error',
                'message' => 'Invalid Order id',
            ];
          }

        } else {
          return [
              'operation' => 'error',
              'message' => 'Failed to authorize the request.',
          ];
        }
    }

    /**
     * Process callback from TAP payment gateway
     * @param string $tap_id
     * @return mixed
     */
    public function actionPaymentWebhook()
    {
        $headers = Yii::$app->request->headers;
        $headerSignature = $headers->get('hashstring');

        $charge_id = Yii::$app->request->getBodyParam("id");
        $status = Yii::$app->request->getBodyParam("status");
        $amount = Yii::$app->request->getBodyParam("amount");
        $currency = Yii::$app->request->getBodyParam("currency");
        $reference = Yii::$app->request->getBodyParam("reference");
        $destinations = Yii::$app->request->getBodyParam("destinations");
        $response = Yii::$app->request->getBodyParam("response");
        $source = Yii::$app->request->getBodyParam("source");
        $transaction = Yii::$app->request->getBodyParam("transaction");
        $acquirer = Yii::$app->request->getBodyParam("acquirer");

        if ($currency_mode = Currency::find()->where(['code' => $currency])->one())
            $decimal_place = $currency_mode->decimal_place;
        else
            throw new ForbiddenHttpException('Invalid Currency code');

        if (isset($reference)) {
            $gateway_reference = $reference['gateway'];
            $payment_reference = $reference['payment'];
        }

        if (isset($transaction)) {
            $created = $transaction['created'];
        }

        $amountCharged = number_format($amount, $decimal_place, '.', '');

        $toBeHashedString = 'x_id' . $charge_id . 'x_amount' . $amountCharged . 'x_currency' . $currency . 'x_gateway_reference' . $gateway_reference . 'x_payment_reference' . $payment_reference . 'x_status' . $status . 'x_created' . $created . '';

        $isValidSignature = true;


        //Check If Enabled Secret Key and If The header has request
        if ($headerSignature != null) {

            $response_message = null;

            if (isset($acquirer)) {
                if (isset($acquirer['response']))
                    $response_message = $acquirer['response']['message'];

            } else {
                if (isset($response))
                    $response_message = $response['message'];
            }

            $paymentRecord = Payment::updatePaymentStatus($charge_id, $status, $destinations, $source, $reference, $response_message, $response);

            $isValidSignature = false;

            if (!$isValidSignature)
            {
                Yii::$app->tapPayments->setApiKeys(
                    $paymentRecord->restaurant->live_api_key,
                    $paymentRecord->restaurant->test_api_key,
                    $paymentRecord->is_sandbox
                );

                $isValidSignature = Yii::$app->tapPayments->checkTapSignature($toBeHashedString, $headerSignature);

                if (!$isValidSignature) {

                    //todo: notify vendor/admin?

                    //Yii::error('Invalid Signature', __METHOD__);

                    throw new ForbiddenHttpException('Invalid Signature');
                }
            }

            $paymentRecord->received_callback = true;
            $paymentRecord->save(false);

            if ($paymentRecord)
            {
                return [
                    'operation' => 'success',
                    'message' => 'Payment status has been updated successfully'
                ];
            }
        }
    }

}
