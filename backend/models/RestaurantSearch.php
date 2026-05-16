<?php

namespace backend\models;

use agent\models\Subscription;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Restaurant;
use yii\db\Expression;

/**
 * RestaurantSearch represents the model behind the search form of `common\models\Restaurant`.
 */
class RestaurantSearch extends Restaurant
{
    private const NUMERIC_FILTER_PATTERN = '/^\s*(<=|>=|<|>|=)?\s*([0-9]+(?:\.[0-9]+)?)\s*$/';
    public $date_start;
    public $date_end;

    public $country_name;
    public $currency_title;

    public $has_not_deployed;
    public $noOrder;
    public $noItem;
    public $active;
    public $notActive;
    public $notActive90Days;
    public $customDomain;
    public $plugnDomain;

    public $active15Days;
    public $notActive15Days;
    public $notActive30Days;

    public $activeSubscription;
    public $noActiveSubscription;

    public $payment_method_id;

    public $storesWithPaymentGateway;

    public  $notDeleted;

    public $haveArmada;

    /**
     * {@inheritdoc}
     */
     public function rules()
     {
         return [
             [["haveArmada", "notDeleted", "active15Days", "notActive15Days", 'country_id', 'currency_id', 'license_number', 'vendor_sector', 'store_layout', 'enable_gift_message',
                 'retention_email_sent', 'referral_code', 'is_public', 'accept_order_247', 'iban', 'business_id',
                 'business_entity_id', 'wallet_id', 'merchant_id', 'operator_id',
                'restaurant_uuid', 'is_tap_enable', "tap_merchant_status", 'name', 'name_ar' ,'app_id', 'has_not_deployed',
                 'last_active_at', 'last_order_at', 'restaurant_email', 'restaurant_created_at', 'restaurant_updated_at',
                 'restaurant_domain', 'country_name', 'currency_title', 'is_myfatoorah_enable', 'has_deployed',
                 'is_sandbox', 'is_under_maintenance', 'enable_debugger', 'is_deleted', 'noOrder', 'total_orders',
                 'customDomain', 'noItem', 'notActive', 'ip_address', 'notActive90Days', "activeSubscription",
                 "active",
                 "noActiveSubscription", "plugnDomain", "date_start", "date_end", "payment_method_id", "storesWithPaymentGateway"], 'safe'],
             [['restaurant_status'], 'integer'],
             [['platform_fee','version'], 'number'],//'total_orders'
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
    public function search($params, $source_utm_uuid = null)
    {
        $query = Restaurant::find()->joinWith(['country', 'currency']);

        if($source_utm_uuid) {
            $query->joinWith(['restaurantByCampaign'])
                ->andWhere(['utm_uuid' => $source_utm_uuid]);
        }

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort'=> ['defaultOrder' => ['restaurant_created_at' => SORT_DESC]],
        ]);

      //  $dataProvider->defa

        $dataProvider->sort->attributes['country_name'] = [
            'asc' => ['country.country_name' => SORT_ASC],
            'desc' => ['country.country_name' => SORT_DESC],
        ];

        $dataProvider->sort->attributes['currency_title'] = [
            'asc' => ['currency.title' => SORT_ASC],
            'desc' => ['currency.title' => SORT_DESC],
        ];

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        if ($this->date_start && $this->date_end) {
            $query->filterByDateRange($this->date_start, $this->date_end);
        }

        // grid filtering conditions

        if($this->restaurant_created_at)    
            $query->andFilterWhere([
                'DATE(restaurant_created_at)' => date('Y-m-d', strtotime($this->restaurant_created_at))
            ]);
            
        if($this->restaurant_updated_at)    
            $query->andFilterWhere([
                'DATE(restaurant_updated_at)' => date('Y-m-d', strtotime($this->restaurant_updated_at)),
            ]);

        $query->andFilterWhere(['restaurant_status' => $this->restaurant_status]);

        if (!is_null($this->platform_fee)) {
            $query->andFilterWhere(['platform_fee' => $this->platform_fee]);
        }

        if($this->is_tap_enable)
            $query->andFilterWhere(['is_tap_enable' => $this->is_tap_enable]);

        if ($this->tap_merchant_status) {
            if ($this->tap_merchant_status == "Unknown") {
                $query->andWhere(new Expression( 'tap_merchant_status IS NULL OR tap_merchant_status=""'));
            } else {
                $query->andFilterWhere(['tap_merchant_status' => $this->tap_merchant_status]);
            }
        }

        if($this->is_myfatoorah_enable)
            $query->andFilterWhere(['is_myfatoorah_enable' => $this->is_myfatoorah_enable]);

        if($this->has_not_deployed) {
            //$query->andFilterWhere(['has_deployed' => false]);
            $query->filterNotPublished();
        }

        if(isset($this->has_deployed) && strlen($this->has_deployed) > 0) { //in_array($this->has_deployed, [1, 0])

            if($this->has_deployed) {
                $query->andFilterWhere(['has_deployed' => $this->has_deployed]);
            } else {
                $query->filterNotPublished();
            }
        }

        if($this->is_sandbox)
            $query->andFilterWhere(['is_sandbox'  => $this->is_sandbox]);

        if($this->is_under_maintenance)
            $query->andFilterWhere(['is_under_maintenance' => $this->is_under_maintenance]);

        if($this->enable_debugger)
            $query->andFilterWhere(['enable_debugger' => $this->enable_debugger]);

        if($this->is_deleted) {
            $query->andFilterWhere(['restaurant.is_deleted' => $this->is_deleted]);
        } else {
        //    $query->andFilterWhere(['restaurant.is_deleted' => 0]);
        }

        if($this->ip_address) {
            $query->andFilterWhere(['restaurant.ip_address' => $this->ip_address]);
        }

        if($this->notActive30Days) {
            $query->andWhere("last_active_at IS NULL OR DATE(last_active_at) < DATE('".
                date('Y-m-d', strtotime("-30 days"))."')");
        }

        if($this->active15Days) {
            $query->filterByOrderInDays(15);
        }

        if($this->notActive15Days) {
            $query->filterByNoOrderInDays(15);
        }

        if($this->notActive90Days) {
            $query->andWhere("last_active_at IS NULL OR DATE(last_active_at) < DATE('".
                date('Y-m-d', strtotime("-90 days"))."')");
        }

        if($this->noOrder) {
            $query->andWhere( "last_order_at IS NULL OR DATE(last_order_at) < DATE('".
                date('Y-m-d', strtotime("-30 days"))."')");
        }

        if($this->noItem) {
            $query
                ->joinWith(['items'])
                ->andWhere( new Expression("item_uuid IS NULL"));
        }

        if($this->last_active_at) {
            $query->andWhere("DATE(last_active_at) = DATE('".
                date('Y-m-d', strtotime($this->last_active_at))."')");
        }

        if($this->last_order_at) {
            $query->andWhere( "DATE(last_order_at) = DATE('".
               date('Y-m-d', strtotime($this->last_order_at))."')");
        }

        if($this->is_public) {
            $query->andFilterWhere(['is_public' => $this->is_public]);
        }

        if($this->accept_order_247) {
            $query->andFilterWhere(['accept_order_247' => $this->accept_order_247]);
        }

        if($this->customDomain) {
            $query->andFilterWhere(['not like', 'restaurant_domain', ".plugn."]);
        }

        if($this->plugnDomain) {
            $query->andFilterWhere(['like', 'restaurant_domain', ".plugn."]);
        }

        if($this->enable_gift_message) {
            $query->andFilterWhere(['enable_gift_message' => $this->enable_gift_message]);
        }

        if($this->app_id) {
            $query->andFilterWhere(['app_id' => $this->app_id]);
        }

        if($this->restaurant_email) {
            $query->andFilterWhere(['restaurant_email' => $this->restaurant_email]);
        }

        if ($this->noActiveSubscription) {
            $query->filterFree();

           // $query->andWhere(new Expression('platform_fee=0.05'));
            //$query->joinWith(['activeSubscription'])
             //   ->andWhere(['plan_id' => 1]);
                //->andWhere(new Expression("plan_id=1 OR subscription_status=".Subscription::STATUS_INACTIVE." OR
                //    DATE(NOW()) < DATE(subscription_end_at)"));
        }

        if ($this->activeSubscription) {
            $query->filterPremium();

            /*->joinWith(['subscriptions'])
                ->andWhere(['plan_id' => 2, "subscription_status" => Subscription::STATUS_ACTIVE])
                ->andWhere(new Expression("DATE(NOW()) <= DATE(subscription_end_at)"));*/
        }

        if ($this->payment_method_id) {
            $query->joinWith(['restaurantPaymentMethods'])
                ->andWhere(['restaurant_payment_method.payment_method_id' => $this->payment_method_id]);//, "restaurant_payment_method.status" => 1
        }

        $query->andFilterWhere(['like', 'restaurant.country_id', $this->country_id])
            ->andFilterWhere(['like', 'restaurant.currency_id', $this->currency_id])
            ->andFilterWhere(['like', 'restaurant.license_number', $this->license_number])
            ->andFilterWhere(['like', 'restaurant.vendor_sector', $this->vendor_sector])
            ->andFilterWhere(['like', 'restaurant.store_layout', $this->store_layout])
            ->andFilterWhere(['like', 'restaurant.retention_email_sent', $this->retention_email_sent])
            ->andFilterWhere(['like', 'restaurant.referral_code', $this->referral_code])
            ->andFilterWhere(['like', 'restaurant.iban', $this->iban])
            ->andFilterWhere(['business_entity_id', 'restaurant.business_entity_id', $this->business_entity_id])
            ->andFilterWhere(['like', 'restaurant.wallet_id', $this->wallet_id])
            ->andFilterWhere(['like', 'restaurant.merchant_id', $this->merchant_id])
            ->andFilterWhere(['like', 'restaurant.operator_id', $this->operator_id])
            ->andFilterWhere(['like', 'restaurant.business_id', $this->business_id]);

        $query->andFilterWhere(['like', 'restaurant.restaurant_uuid', $this->restaurant_uuid])
            ->andFilterWhere(['like', 'restaurant.restaurant_domain', $this->restaurant_domain])
            ->andFilterWhere(['like', 'restaurant.name', $this->name])
            ->andFilterWhere(['like', 'restaurant.version', $this->version])
            ->andFilterWhere(['like', 'currency.title', $this->currency_title])
            //->andFilterWhere(['like', 'total_orders', $this->total_orders])
            ->andFilterWhere(['like', 'restaurant.name_ar', $this->name_ar]);

        if ($this->total_orders !== null && $this->total_orders !== '') {
            $this->applyNumericFilter($query, 'total_orders', $this->total_orders);
        }

        if ($this->storesWithPaymentGateway) {
            $query->filterStoresWithPaymentGateway();
        }

        if ($this->notDeleted) {
            $query->andFilterWhere(['restaurant.is_deleted' => 0]);
        }

        if ($this->notActive) {
            $query->inActive();
        }

        if ($this->active) {
            $query->active();
        }

        if ($this->haveArmada) {
            $query->andWhere(new Expression('armada_api_key IS NOT NULL'));
        }

        if($this->country_name)
            $query->andFilterWhere(['like', 'country.country_name',
                new Expression("'%". $this->country_name . "%'")]);

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
