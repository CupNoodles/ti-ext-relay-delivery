<?php 

namespace CupNoodles\RelayDelivery;


use Admin\Widgets\Form;
use Admin\Widgets\Toolbar;
use Admin\Models\Location_areas_model;

use System\Classes\BaseExtension;
use DB;
use Event;
use App;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Cart\Models\Orders_Model;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

use CupNoodles\RelayDelivery\Models\RelayDeliverySettings;

/**
 * Relay Delivery Extension Information File
 */
class Extension extends BaseExtension
{
    /**
     * Returns information about this extension.
     *
     * @return array
     */
    public function extensionMeta()
    {
        return [
            'name'        => 'RelayDelivery',
            'author'      => 'CupNoodles',
            'description' => 'Send order information to Relay Delivery service',
            'icon'        => 'fa-truck-fast',
            'version'     => '1.0.0'
        ];
    }

    /**
     * Register method, called when the extension is first registered.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Boot method, called right before the request route.
     *
     * @return void
     */
    public function boot()
    {

        // Put a 'relay' button for type on delivery areas
        Event::listen('admin.form.extendFieldsBefore', function (Form $form) {
            if ($form->model instanceof Location_areas_model) {
                $form->fields['type']['options']['relaydelivery'] = lang('cupnoodles.relaydelivery::default.relay_delivery_api');
                // the map shows up when you select relay unless you edit the maps trigger conditions
                $form->fields['_mapview']['trigger']['condition'] = 'value[address], value[relaydelivery]';
            }
        });

        // the geocoder throws away the subpremise for some reason - orders in big cities should require apt #
        Event::listen('location.position.updated', function($position, $oldPosition){

            if($position->userPosition()->getValue('subpremise')){
                $position->putSession('subpremise', $position->userPosition()->getValue('subpremise'));
            }
            else{
                $position->putSession('subpremise', '');
            }
        });

        // save the delivery instructions field to the order
        Event::listen('igniter.checkout.beforeSaveOrder', function(Orders_Model $order, $data){
            $order->delivery_instructions = $data['delivery_instructions'];
        });
        
        // send the order to Relay
        Event::listen('igniter.checkout.afterSaveOrder', function(Orders_Model $order){

            if($order->order_type == 'delivery'){
                if( $order->location->delivery_areas[0]->type == 'relaydelivery'){
                    $this->sendOrderToRelay($order);                    
                }
            }
        });

    }


    public function sendOrderToRelay($order){
        
        $location_id = $order->location_id;
        
        // GuzzleHTTP\Client
        $client = new Client();

        $relay_api_key = RelayDeliverySettings::get('location_'.$location_id.'_relay_api_key');
        $relay_api_producer_location_key = RelayDeliverySettings::get('location_'.$location_id.'_relay_producer_location_key');
    
        if(RelayDeliverySettings::get('relay_delivery_dev_prod')){
            $url = 'https://api.deliveryrelay.com/v1/orders';
        }
        else{
            $url = 'https://dev-api.deliveryrelay.com/v1/orders';
        }
        
        $order_totals = $order->getOrderTotals();
        $totals = [];
        foreach($order_totals as $total){
            if($total->code == 'subtotal'){
                $totals['subTotal'] = $total->value;
            }
            if($total->code == 'delivery'){
                $totals['delivery'] = $total->value;
            }
            if($total->code == 'tax'){
                $totals['tax'] = $total->value;
            }
            if($total->code == 'tip'){
                $totals['tip'] = $total->value;
            }
        }

        $request_body = [
            "order" => [
                "producer" => [
                    "producerLocationKey" => $relay_api_producer_location_key
                ],
                "consumer" => [
                    "name"=> $order->first_name . ' ' . $order->last_name,
                    "phone" => $order->telephone,
                    "location" => [
                        "address1" => $order->address->address_1,
                        "apartment"=> $order->address->address_2,
                        "zip" => $order->address->postcode
                    ]
                ],
                
                "price" => $totals,
                "specialInstructions" => $order->delivery_instructions
            ]
        ];

        if(!$order->order_time_is_asap){
            $request_body['order']['time'] = [
                'isFutureOrder' => true,
                'lateDelivery' => date(DATE_RFC3339_EXTENDED, strtotime($order->order_date_time))
            ];
        }
        

        try {
            $res = $client->post($url, [
                    'headers' => [
                        'content-type' => 'application/json',
                        'x-relay-auth' => $relay_api_key
                    ],
                    'json' => $request_body
                ]);

        } catch (RequestException $e) {
            throw new ApplicationException(lang("cupnoodles.relaydelivery::default.relay_api_error"));
        }
        
        $result = json_decode($res->getBody());

        if($result->success == 'true'){
            return true;
        }
        else{
            return false;
        }
        
        
    }


    public function registerSchedule($schedule)
    {


    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label' => 'Relay Delivery Settings',
                'description' => 'Manage Relay Delivery settings',
                'icon' => 'fa fa-truck-fast',
                'model' => 'CupNoodles\RelayDelivery\Models\RelayDeliverySettings',
                'permissions' => ['Module.RelayDelivery'],
            ],
        ];
    }

    public function registerComponents()
    {


        return [
            'CupNoodles\RelayDelivery\Components\LocalBoxRelay' => [
                'code' => 'LocalBoxRelay',
                'name' => 'lang:igniter.local::default.menu.component_title',
                'description' => 'lang:igniter.local::default.menu.component_desc',
            ],
            'CupNoodles\RelayDelivery\Components\CheckoutRelay' => [
                'code' => 'checkoutRelay',
                'name' => 'lang:igniter.local::default.menu.component_title',
                'description' => 'lang:igniter.local::default.menu.component_desc',
            ]
        ];
    }
    /**
     * Registers any admin permissions used by this extension.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return [
            'Admin.UnitsOfMeasure' => [
                'label' => 'cupnoodles.relaydelivery::default.permissions',
                'group' => 'admin::lang.permissions.name',
            ],
        ];
    }



}
