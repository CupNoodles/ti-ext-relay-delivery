<?php

namespace CupNoodles\RelayDelivery\Components;

use Igniter\Local\Components\LocalBox;

use Exception;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Flame\Geolite\Facades\Geocoder;
use Igniter\Local\Facades\Location;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;

use System\Classes\BaseComponent;

use Admin\Models\Locations_model;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

use CupNoodles\RelayDelivery\Models\RelayDeliverySettings;

class LocalBoxRelay extends LocalBox
{

    public function onRun()
    {
        $this->addJs('$/igniter/local/assets/js/local.js', 'local-js');
        $this->addJs('$/igniter/local/assets/js/local.timeslot.js', 'local-timeslot-js');

        $this->updateCurrentOrderType();

        if ($this->checkCurrentLocation()) {
            flash()->error(lang('igniter.local::default.alert_location_required'));

            return Redirect::to($this->controller->pageUrl($this->property('redirect')));
        }

        $this->prepareVars();
    }

    public function onSearchAddress(){
        
        try {
            if (!$searchQuery = $this->getRequestSearchQuery())
                throw new ApplicationException(lang('igniter.local::default.alert_no_search_query'));

            $userLocation = is_array($searchQuery)
                ? $this->geocodeSearchPoint($searchQuery)
                : $this->geocodeSearchQuery($searchQuery);


            $locations = Locations_model::all();

            $foundLocation = false;
            foreach($locations as $location){
                if($this->canRelayDeliverTo($userLocation->getCoordinates(), $location->location_id)){
                    $foundLocation = true;
                    Location::updateNearbyArea($location->delivery_areas->first());
                }
            }

                
            $nearByLocation = Location::searchByCoordinates(
                $userLocation->getCoordinates()
            )->first(function ($location) use ($userLocation) {
                if ($area = $location->searchDeliveryArea($userLocation->getCoordinates())) {
                    Location::updateNearbyArea($area);

                    return $area;
                }
            });
            
            if (!$foundLocation) {
                throw new ApplicationException(lang('igniter.local::default.alert_no_found_restaurant'));
            }

            if ($redirectPage = post('redirect'))
                return Redirect::to($this->controller->pageUrl($redirectPage));

            return Redirect::to(restaurant_url($this->property('menusPage')));
        }
        catch (Exception $ex) {
            if (Request::ajax()) throw $ex;
            else flash()->danger($ex->getMessage());
        }

    }


    public function canRelayDeliverTo($coordinates, $location_id){   

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
        

        $request_body = [
            'producerLocationKey' => $relay_api_producer_location_key ,
            "coordinates" => [
                "latitude" => $coordinates->getLatitude(),
                "longitude" => $coordinates->getLongitude()
            ]
        ];
        

        try {
            $res = $client->post($url, [
                    'headers' => [
                        'content-type' => 'application/json',
                        'x-relay-auth' => $relay_api_key
                    ],
                    'json' => $request_body
                ]);
                
        } catch (RequestException $e) {
            throw new ApplicationException(lang("cupnoodles.relaydelivery::defualt.relay_api_error"));
        }
        
        $result = json_decode($res->getBody());
        if($result->canDeliver == 'true'){
            return true;
        }
        else{
            return false;
        }





    }

}