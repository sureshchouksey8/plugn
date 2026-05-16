<?php

namespace backend\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Payment;
use yii\db\Expression;

/**
 * PaymentSearch represents the model behind the search form of `common\models\Payment`.
 */
class PaymentSearch extends Payment
{
    public $store_name;
    public $customer_name;
    public $date_from;
    public $date_to;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['date_from', 'date_to', 'payment_uuid', 'store_name', 'customer_name','restaurant_uuid', 'order_uuid', 'payment_gateway_order_id', 'payment_gateway_transaction_id', 'payment_gateway_payment_id', 'payment_gateway_invoice_id', 'payment_mode', 'payment_current_status', 'payment_udf1', 'payment_udf2', 'payment_udf3', 'payment_udf4', 'payment_udf5', 'payment_created_at', 'payment_updated_at', 'response_message', 'payment_token', 'payment_gateway_name'], 'safe'],
            [['customer_id', 'received_callback'], 'integer'],
            [['payment_amount_charged', 'payment_net_amount', 'payment_gateway_fee', 'plugn_fee'], 'number'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params, $pagination = true)
    {
        $query = Payment::find()
              ->joinWith(['restaurant', 'customer'])
              ->orderBy(['payment_created_at' => SORT_DESC]);

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider($pagination? [
            'query' => $query,
        ]: [
            'query' => $query,
            'pagination' => false
        ]);

        $dataProvider->sort->attributes['store_name'] = [
            'asc' => ['restaurant.name' => SORT_ASC],
            'desc' => ['restaurant.name' => SORT_DESC],
        ];

        $dataProvider->sort->attributes['customer_name'] = [
            'asc' => ['customer.customer_name' => SORT_ASC],
            'desc' => ['customer.customer_name' => SORT_DESC],
        ];

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'payment_amount_charged' => $this->payment_amount_charged,
            'payment_net_amount' => $this->payment_net_amount,
            'payment_gateway_fee' => $this->payment_gateway_fee,
            'plugn_fee' => $this->plugn_fee,
            'payment_created_at' => $this->payment_created_at,
            'payment_updated_at' => $this->payment_updated_at,
            'received_callback' => $this->received_callback,
        ]);

        if($this->date_from) {
            $query->andWhere(new Expression('DATE(payment_created_at) >= DATE(:date_from)', [
                ':date_from' => $this->date_from,
            ]));
        }

        if($this->date_to) {
            $query->andWhere(new Expression('DATE(payment_created_at) <= DATE(:date_to)', [
                ':date_to' => $this->date_to,
            ]));
        }

        $query->andFilterWhere(['like', 'payment_uuid', $this->payment_uuid])
            ->andFilterWhere(['like', 'restaurant.name', $this->store_name])
            ->andFilterWhere(['like', 'customer.customer_name', $this->customer_name])
            ->andFilterWhere(['like', 'order_uuid', $this->order_uuid])
            ->andFilterWhere(['like', 'payment_gateway_order_id', $this->payment_gateway_order_id])
            ->andFilterWhere(['like', 'payment_gateway_transaction_id', $this->payment_gateway_transaction_id])
            ->andFilterWhere(['like', 'payment_gateway_payment_id', $this->payment_gateway_payment_id])
            ->andFilterWhere(['like', 'payment_gateway_invoice_id', $this->payment_gateway_invoice_id])
            ->andFilterWhere(['like', 'payment_mode', $this->payment_mode])
            ->andFilterWhere(['like', 'payment_current_status', $this->payment_current_status])
            ->andFilterWhere(['like', 'payment_udf1', $this->payment_udf1])
            ->andFilterWhere(['like', 'payment_udf2', $this->payment_udf2])
            ->andFilterWhere(['like', 'payment_udf3', $this->payment_udf3])
            ->andFilterWhere(['like', 'payment_udf4', $this->payment_udf4])
            ->andFilterWhere(['like', 'payment_udf5', $this->payment_udf5])
            ->andFilterWhere(['like', 'response_message', $this->response_message])
            ->andFilterWhere(['like', 'payment_token', $this->payment_token])
            ->andFilterWhere(['like', 'payment_gateway_name', $this->payment_gateway_name]);

        return $dataProvider;
    }
}
