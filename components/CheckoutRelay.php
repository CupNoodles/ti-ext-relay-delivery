<?php

namespace CupNoodles\RelayDelivery\Components;

use CupNoodles\RelayDelivery\Models\RelayDeliverySettings;

use Igniter\Cart\Components\Checkout;

use Igniter\Flame\Exception\ApplicationException;
use Illuminate\Support\Facades\Event;
use Igniter\Cart\Classes\CartManager;
use Redirect;
use Location;
use App;

use CupNoodles\RelayDelivery\Extension;

class CheckoutRelay extends Checkout{


    
    public function onRender()
    {
        foreach ($this->getPaymentGateways() as $paymentGateway) {
            $paymentGateway->beforeRenderPaymentForm($paymentGateway, $this->controller);
        }

        $this->addJs('$/igniter/cart/assets/js/checkout.js', 'checkout-js');
    }

    protected function createRules()
    {

        $namedRules = [
            ['first_name', 'lang:igniter.cart::default.checkout.label_first_name', 'required|between:1,48'],
            ['last_name', 'lang:igniter.cart::default.checkout.label_last_name', 'required|between:1,48'],
            ['email', 'lang:igniter.cart::default.checkout.label_email', 'sometimes|required|email:filter|max:96|unique:customers'],
            ['telephone', 'lang:igniter.cart::default.checkout.label_telephone', 'required|between:10,20'],
            ['comment', 'lang:igniter.cart::default.checkout.label_comment', 'max:500'],
            ['payment', 'lang:igniter.cart::default.checkout.label_payment_method', 'sometimes|required|alpha_dash'],
            ['terms_condition', 'lang:button_agree_terms', 'sometimes|integer'],
        ];

        if (Location::orderTypeIsDelivery()) {
            $namedRules[] = ['address.address_1', 'lang:igniter.cart::default.checkout.label_address_1', 'required|min:3|max:128'];
            $namedRules[] = ['address.address_2', 'lang:igniter.cart::default.checkout.label_address_2', 'required|min:1|max:128'];
            $namedRules[] = ['address.city', 'lang:igniter.cart::default.checkout.label_city', 'sometimes|min:2|max:128'];
            $namedRules[] = ['address.state', 'lang:igniter.cart::default.checkout.label_state', 'sometimes|max:128'];
            $namedRules[] = ['address.postcode', 'lang:igniter.cart::default.checkout.label_postcode', 'string'];
            $namedRules[] = ['address.country_id', 'lang:igniter.cart::default.checkout.label_country', 'sometimes|required|integer'];
        }

        return $namedRules;
    }


    // CheckoutRelay can accept a tip amount and will return partials. This requires a modification to your theme and will do nothing witout it. 
    protected function prepareVars(){
        parent::prepareVars();
        $this->page['onApplyTip'] = $this->getEventHandler('onApplyTip');
    }

    public function onApplyTip()
    {
        try {
            $tipType = post('tip_type');
            if (!in_array($tipType, ['staff', 'driver']))
                throw new ApplicationException(lang('igniter.cart::default.alert_tip_not_applied'));

            $amountType = post('amount_type');
            if (!in_array($amountType, ['none', 'amount', 'custom']))
                throw new ApplicationException(lang('igniter.cart::default.alert_tip_not_applied'));

            $amount = post('amount');
            if (preg_match('/^\d+([\.\d]{2})?([%])?$/', $amount) === FALSE)
                throw new ApplicationException(lang('igniter.cart::default.alert_tip_not_applied'));

            $cartManager = CartManager::instance();


            $cartManager->applyCondition($tipType == 'driver' ? 'driver_tip' : 'tip', [
                'amountType' => $amountType,
                'amount' => $amount,
            ]);

            $this->controller->pageCycle();

            return $this->fetchTipPartials();
        }
        catch (Exception $ex) {
            if (Request::ajax()) throw $ex;
            else flash()->alert($ex->getMessage());
        }
    }

    public function fetchTipPartials()
    {
        $this->prepareVars();

        return [
            '#checkout-subtotals' => $this->renderPartial('@subtotals'),
            '#checkout-staff-tip' => $this->renderPartial('@staff_tip_box'),
            '#checkout-driver-tip' => $this->renderPartial('@driver_tip_box'),
            '#checkout-total' => $this->renderPartial('@totals'),
        ];
    }


    public static function driverTippingAmounts()
    {
        $result = [];

        $tipValueType = RelayDeliverySettings::get('driver_tip_value_type', 'F');
        $amounts = (array)RelayDeliverySettings::get('driver_tip_amounts', []);

        $amounts = sort_array($amounts, 'priority');

        foreach ($amounts as $index => $amount) {
            $amount['valueType'] = $tipValueType;
            $result[$index] = (object)$amount;
        }

        return $result;
    }

    // end tipping section




    protected function validateCheckout($data, $order)
    {
        $this->validate($data, $this->createRules(), [
            'email.unique' => lang('igniter.cart::default.checkout.error_email_exists'),
        ]);

        $location = App::make('location');
        if ($this->checkoutStep === 'details' && $order->isDeliveryType()) {

            foreach($order->location->delivery_areas as $delivery_area){
                if($location->isCurrentAreaId($delivery_area->area_id)){
                    if($delivery_area->type == 'relaydelivery'){
                        if(!Extension::canRelayDeliverTo(array_get($data, 'address', []), $order->location->location_id)){
                            throw new ApplicationException(lang('igniter.cart::default.checkout.error_covered_area'));
                        }
                    }
                    else{
                        $this->orderManager->validateDeliveryAddress(array_get($data, 'address', []));
                    }
                }

                
            }
        }

        if ($this->canConfirmCheckout() && $order->order_total > 0 && !$order->payment)
            throw new ApplicationException(lang('igniter.cart::default.checkout.error_invalid_payment'));

        Event::fire('igniter.checkout.afterValidate', [$data, $order]);
    }

}