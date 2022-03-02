<?php

namespace CupNoodles\RelayDelivery\Models;

use Model;

/**
 * @method static instance()
 */
class RelayDeliverySettings extends Model
{
    public $implement = ['System\Actions\SettingsModel'];

    // A unique code
    public $settingsCode = 'cupnoodles_relaydelivery_settings';

    // Reference to field configuration
    public $settingsFieldsConfig = 'relaydeliverysettings';


}
