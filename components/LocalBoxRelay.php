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

use CupNoodles\RelayDelivery\Extension;
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
                if(Extension::canRelayDeliverTo($userLocation->getCoordinates(), $location->location_id)){
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


    

}