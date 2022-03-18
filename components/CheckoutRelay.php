<?php

namespace CupNoodles\RelayDelivery\Components;

use Igniter\Cart\Components\Checkout;

use Igniter\Flame\Exception\ApplicationException;
use Illuminate\Support\Facades\Event;

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