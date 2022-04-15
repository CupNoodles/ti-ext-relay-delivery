<?php

namespace CupNoodles\RelayDelivery\CartConditions;

use CupNoodles\RelayDelivery\Models\RelayDeliverySettings;
use Igniter\Flame\Cart\CartCondition;
use Igniter\Local\Facades\Location;
use System\Models\Currencies_model;
use Igniter\Cart\Classes\CartManager;

class DriverTip extends CartCondition
{
    protected $tippingEnabled = FALSE;

    protected $tipValueType;

    public $priority = 100;

    public function onLoad()
    {
        $this->tippingEnabled = (bool)RelayDeliverySettings::get('enable_driver_tipping');
        $this->tipValueType = RelayDeliverySettings::get('driver_tip_value_type', 'F');
    }

    public function getLabel()
    {
        return lang($this->label);
    }

    public function beforeApply()
    {
        if (!$this->tippingEnabled)
            return FALSE;

        // if amount is not set, empty or 0
        if (!$tipAmount = $this->getMetaData('amount'))
            return FALSE;

        $value = $this->getMetaData('amount');
        if (preg_match('/^\d+([\.\d]{2})?([%])?$/', $value) === FALSE || $value < 0) {
            $this->removeMetaData('amount');
            flash()->warning(lang('igniter.cart::default.alert_tip_not_applied'))->now();
        }
    }

    public function getActions()
    {
        $amountType = $this->getMetaData('amountType');
        $amount = $this->getMetaData('amount');
        if ($amountType == 'amount' && $this->tipValueType != 'F')
            $amount .= '%';

        $precision = optional(Currencies_model::getDefault())->decimal_position ?? 2;

        return [
            ['value' => "+{$amount}", 'valuePrecision' => $precision],
        ];
    }


    public function calculate($total)
    {
        if(Location::orderTypeIsDelivery()){
            $cartManager = CartManager::instance();
            $staffTipAmount = $cartManager->getCart()->getCondition('tip')->calculatedValue;
            $total -= $staffTipAmount;
            
            $result = parent::calculate($total);
            $result += $staffTipAmount;

            return $result;
        }
        else{
            return $total;
        }
    }
}
