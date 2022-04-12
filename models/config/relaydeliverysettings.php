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
        'tabs' => [
            'fields' => [
                'enable_driver_tipping' => [
                    'tab' => 'lang:cupnoodles.relaydelivery::default.label_tipping',
                    'label' => 'lang:cupnoodles.relaydelivery::default.label_enable_tipping',
                    'type' => 'switch',
                    'default' => FALSE,
                    'on' => 'lang:admin::lang.text_yes',
                    'off' => 'lang:admin::lang.text_no',
                ],
                'driver_tip_value_type' => [
                    'tab' => 'lang:cupnoodles.relaydelivery::default.label_tipping',
                    'label' => 'lang:igniter.cart::default.label_tip_value_type',
                    'type' => 'radiotoggle',
                    'default' => 'F',
                    'options' => [
                        'F' => 'lang:admin::lang.menus.text_fixed_amount',
                        'P' => 'lang:admin::lang.menus.text_percentage',
                    ],
                    'trigger' => [
                        'action' => 'show',
                        'field' => 'enable_driver_tipping',
                        'condition' => 'checked',
                    ],
                ],
                'driver_tip_amounts' => [
                    'tab' => 'lang:cupnoodles.relaydelivery::default.label_tipping',
                    'label' => 'lang:igniter.cart::default.label_tip_amounts',
                    'type' => 'repeater',
                    'span' => 'left',
                    'sortable' => TRUE,
                    'showAddButton' => TRUE,
                    'showRemoveButton' => TRUE,
                    'form' => [
                        'fields' => [
                            'priority' => [
                                'label' => 'lang:igniter.cart::default.column_condition_priority',
                                'type' => 'hidden',
                            ],
                            'value' => [
                                'label' => 'lang:igniter.cart::default.column_tip_amount',
                                'type' => 'currency',
                            ],
                        ],
                    ],
                    'trigger' => [
                        'action' => 'show',
                        'field' => 'enable_driver_tipping',
                        'condition' => 'checked',
                    ],
                ],

            ],
        ],
        
        'rules' => [

        ],
    ],
];

foreach($locations as $location){
    $settings['form']['tabs']['fields']['location_'.$location->location_id.'_relay_api_key'] =  [
        'tab' => 'lang:cupnoodles.relaydelivery::default.label_keys',
        'label' => lang('cupnoodles.relaydelivery::default.relay_api_key_for') . $location->location_name,
        'type' => 'text',
        'span' => 'left',
        'default' => '',
        'attributes' => [
            'maxlength' => '1024'
        ]
    ];

    $settings['form']['tabs']['fields']['location_'.$location->location_id.'_relay_producer_key'] =  [
        'tab' => 'lang:cupnoodles.relaydelivery::default.label_keys',
        'label' => lang('cupnoodles.relaydelivery::default.relay_api_producer_key_for') . $location->location_name,
        'type' => 'text',
        'span' => 'left',
        'default' => '',
        'attributes' => [
            'maxlength' => '1024'
        ]
    ];

    $settings['form']['tabs']['fields']['location_'.$location->location_id.'_relay_producer_location_key'] =  [
        'tab' => 'lang:cupnoodles.relaydelivery::default.label_keys',
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