## Relay Delivery API integration 

**NOTE:** This extension has not been tested yet! Use this code at your own risk!

Relay Delivery ([https://www.relay.delivery/]) is a delivery service curently available in New York City, Philadelphia, and Washington DC. In order to use this plugin you must be in one of these cities, and contact Relay in order to get an API key and a Producer Location Key for each location that you'd like to accept deliveries from. 

This extension allows for the creation of a 'Relay Delivery API' Delivery zone which will be dynamically linked to Relay's can-i-deliver endpoint, negating the need for a zone map on your TastyIgniter location. 

After order creation, the order data will be sent to your Relay dashboard, and should show up alongside other orders with a source ID of 'API' (or something else, if you've set it up with Relay). 

### Installation

Download the extension and add to your extensions folder under "/extensions/cupnoodles/relaydelivery" inside your Tasty Igniter install.
Enable extension in Systems -> Extensions.

### Setup

This extension overwrites the 'LocalBox' and 'checkout' components. In order to use this extension, you'll need to replace any declarations of these components in your theme with 'LocalBoxRelay' and 'checkoutRelay' respectively. 

For example, 

```
'[checkout]':
    showCountryField: 0
```
 Should be changed to 
```
 '[checkoutRelay]':
    showCountryField: 0
````

And then 

```
@component('checkout')
```
replaced with 
```
@component('checkoutRelay')
```




