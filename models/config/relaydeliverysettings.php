<?php

/**
 * Model configuration options for settings model.
 */
$locations = Admin\Models\Locations_model::all();

$settings = [
    'form' => [
        'toolbar' => [
            'buttons' => [
                'save' => ['label' => 'lang:admin::lang.button_save', 'class' => 'btn btn-primary', 'data-request' => 'onSave'],
                'saveClose' => [
                    'label' => 'lang:admin::lang.button_save_close',
                    'class' => 'btn btn-default',
                    'data-request' => 'onSave',
                    'data-request-data' => 'close:1',
                ],
            ],
        ],
        'fields' => [
            'relay_delivery_dev_prod' => [
                'label' => lang('cupnoodles.relaydelivery::default.relay_dev_prod'),
                'type' => 'switch',
                'span' => 'left',
                'default' => '',
                'on' => 'cupnoodles.relaydelivery::default.production',
                'off' => 'cupnoodles.relaydelivery::default.development'
            ],
        ],
        
        'rules' => [

        ],
    ],
];

foreach($locations as $location){
    $settings['form']['fields']['location_'.$location->location_id.'_relay_api_key'] =  [
        'label' => lang('cupnoodles.relaydelivery::default.relay_api_key_for') . $location->location_name,
        'type' => 'text',
        'span' => 'left',
        'default' => '',
        'attributes' => [
            'maxlength' => '1024'
        ]
    ];

    $settings['form']['fields']['location_'.$location->location_id.'_relay_producer_location_key'] =  [
        'label' => lang('cupnoodles.relaydelivery::default.relay_api_producer_location_key_for') . $location->location_name,
        'type' => 'text',
        'span' => 'left',
        'default' => '',
        'attributes' => [
            'maxlength' => '1024'
        ]
    ];
}


return $settings;