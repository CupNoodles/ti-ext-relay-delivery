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

use Illuminate\Support\Facades\Log;

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
                if( $form->fields['type']['label'] == 'lang:admin::lang.locations.label_area_type'){
                    $form->fields['type']['options']['relaydelivery'] = lang('cupnoodles.relaydelivery::default.relay_delivery_api');
                    // the map shows up when you select relay unless you edit the maps trigger conditions
                    $form->fields['_mapview']['trigger']['condition'] = 'value[address], value[relaydelivery]';
                }
                
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
            if(isset($data['delivery_instructions'])){
                $order->delivery_instructions = $data['delivery_instructions'];
            }
        });
        
        // send the order to Relay
        Event::listen('admin.order.paymentProcessed', function(Orders_Model $order){

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


        $items = [];
        foreach($order->getOrderMenusWithOptions() as $menu){

            $items[] = [
                "name" => $menu->name,
                "quantity" => $menu->quantity,
                "price" => $menu->subtotal
            ];
        }
        
        $order_totals = $order->getOrderTotals();
        $totals = [];
        $totals['subTotal'] = 0;
        foreach($order_totals as $total){
            if($total->code == 'subtotal'){
                $totals['subTotal'] += $total->value;
            }
            // the 'deliveryFee' field in Relay API does not seem to be working - possibly need to update to v2
            if($total->code == 'delivery'){
                $totals['subTotal'] += $total->value;
                //$totals['deliveryFee'] = $total->value;
            }
            if($total->code == 'tax' || $total->code == 'variableTax1'){
                $totals['tax'] = $total->value;
            }
            if($total->code == 'driver_tip'){
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
                "specialInstructions" => $order->delivery_instructions,
                "items" => $items
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

        // add relay ready time into order db if it exists
        if($result->order->time->ready){
            $date = new \DateTime($result->order->time->ready);
            $date->setTimezone(new \DateTimeZone(setting('timezone'))); 
            $order->relay_ready_time = $date->format('Y-m-d H:i:s');
            $order->save();
        }

        

        if($result->success == 'true'){
            return true;
        }
        else{
            return false;
        }
        
        
    }

    public static function canRelayDeliverTo($coordinates, $location_id){   

        // GuzzleHTTP\Client
        $client = new Client();


        $relay_api_key = RelayDeliverySettings::get('location_'.$location_id.'_relay_api_key');
        $relay_api_producer_location_key = RelayDeliverySettings::get('location_'.$location_id.'_relay_producer_location_key');
    
        if(RelayDeliverySettings::get('relay_delivery_dev_prod')){
            $url = 'https://api.deliveryrelay.com/v1/can-deliver';
        }
        else{
            $url = 'https://dev-api.deliveryrelay.com/v1/can-deliver';
        }
        

        if(is_object($coordinates) && get_class($coordinates) == 'Igniter\Flame\Geolite\Model\Coordinates'){
            $request_body = [
                'producerLocationKey' => $relay_api_producer_location_key ,
                "coordinates" => [
                    "latitude" => $coordinates->getLatitude(),
                    "longitude" => $coordinates->getLongitude()
                ]
            ];
        }
        else{
            $request_body = [
                'producerLocationKey' => $relay_api_producer_location_key ,
                "address" => [
                    "street" => $coordinates['address_1'],
                    "city" => $coordinates['city'],
                    "state" => $coordinates['state'],
                    "zip" => $coordinates['postcode'],
                ]
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
        if($result->canDeliver == 'true'){
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

    public function registerCartConditions()
    {
        return [
            \CupNoodles\RelayDelivery\CartConditions\DriverTip::class => [
                'name' => 'driver_tip',
                'label' => 'lang:cupnoodles.relaydelivery::default.text_driver_tip',
                'description' => 'lang:igniter.cart::default.help_tip_condition'
            ],
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
