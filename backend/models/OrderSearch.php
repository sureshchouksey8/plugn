<?php

namespace backend\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Order;
use yii\db\Expression;

/**
 * OrderSearch represents the model behind the search form of `common\models\Order`.
 */
class OrderSearch extends Order
{
    public $country_id;
    public $date_start;
    public $date_end;
    public $type;
    private const NUMERIC_FILTER_PATTERN = '/^\s*(<=|>=|<|>|=)?\s*([0-9]+(?:\.[0-9]+)?)\s*$/';

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['area_id', 'payment_method_id', 'order_status','customer_id'], 'integer'],
            [["type", "country_id", "date_start", "date_end", "total_price", 'payment_uuid', 'order_mode', 'currency_code', 'payment_method_id ', 'country_name', 'order_uuid', 'area_name', 'area_name_ar', 'unit_type', 'block', 'street', 'avenue', 'house_number', 'special_directions', 'customer_name', 'customer_phone_number', 'customer_email', 'payment_method_name','payment_method_name_ar','restaurant_uuid'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return parent::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = Order::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        //->activeOrders()

        if ($this->country_id) {
            $query->filterByCountry($this->country_id);
        }

        if ($this->date_start && $this->date_end) {
            $query->filterByDateRange($this->date_start, $this->date_end);
        }

        if ($this->type == "checkout-completed") {
            $query->checkoutCompleted();
        }

        if ($this->type == "filter-completed") {
            $query->filterCompleted();
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'order_uuid' => $this->order_uuid,
            'restaurant_uuid' => $this->restaurant_uuid,
            'customer_id' => $this->customer_id,
            'area_id' => $this->area_id,
            'payment_method_id' => $this->payment_method_id,
            'order_status' => $this->order_status,
        ]);

        if ($this->total_price) {
            $this->applyNumericFilter($query, 'total_price', $this->total_price);
        }

        $query->andFilterWhere(['like', 'area_name', $this->area_name])
            ->andFilterWhere(['like', 'area_name_ar', $this->area_name_ar])
            ->andFilterWhere(['like', 'unit_type', $this->unit_type])
            ->andFilterWhere(['like', 'block', $this->block])
            ->andFilterWhere(['like', 'street', $this->street])
            ->andFilterWhere(['like', 'avenue', $this->avenue])
            ->andFilterWhere(['like', 'house_number', $this->house_number])
            ->andFilterWhere(['like', 'special_directions', $this->special_directions])
            ->andFilterWhere(['like', 'customer_name', $this->customer_name])
            ->andFilterWhere(['like', 'customer_phone_number', $this->customer_phone_number])
            ->andFilterWhere(['like', 'customer_email', $this->customer_email])
            ->andFilterWhere(['like', 'payment_method_name', $this->payment_method_name])
            ->andFilterWhere(['like', 'payment_method_name_ar', $this->payment_method_name_ar])
            ->orderBy('order_created_at DESC');

        return $dataProvider;
    }

    private function applyNumericFilter($query, $column, $value)
    {
        if (!preg_match(self::NUMERIC_FILTER_PATTERN, (string)$value, $matches)) {
            $query->andWhere('0=1');
            return;
        }

        $operator = $matches[1] ?: '=';
        $query->andWhere([$operator, $column, $matches[2]]);
    }
}
