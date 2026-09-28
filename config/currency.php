<?php

/*
|--------------------------------------------------------------------------
| Currency
|--------------------------------------------------------------------------
| KDP MART stores every product/order amount in ONE base currency (the
| "currency" row in the settings table, default INR). Nothing in the database
| is ever overwritten with a converted display value — conversion only happens
| at the moment money is rendered to a shopper, or is snapshotted onto an order.
|
| Rates are STATIC / CONFIGURABLE on purpose. This college project is NOT wired
| to an external exchange-rate provider, so nothing here is a live rate. To go
| live later, replace CurrencyService::rateTable() with a cached API call — no
| view or controller needs to change.
*/

return [

    /*
    | The currency every rate below is expressed against. Rates mean:
    | "1 unit of this anchor buys X units of <code>". Effective rates are
    | derived relative to the store's base currency, so changing the store's
    | base currency in Admin > Settings never requires re-typing the table.
    */
    'anchor' => 'INR',

    /*
    | Fallback base currency when no "currency" setting has been saved yet.
    */
    'base' => 'INR',

    /*
    | Currencies the storefront may display. Removing an entry here (or
    | de-activating it in Admin > Settings) removes it from the selector.
    */
    'currencies' => [
        'INR' => [
            'name'      => 'Indian Rupee',
            'symbol'    => '₹',
            'precision' => 2,
            'rate'      => 1,
        ],
        'USD' => [
            'name'      => 'US Dollar',
            'symbol'    => '$',
            'precision' => 2,
            'rate'      => 0.0120,
        ],
        'EUR' => [
            'name'      => 'Euro',
            'symbol'    => '€',
            'precision' => 2,
            'rate'      => 0.0110,
        ],
        'GBP' => [
            'name'      => 'Pound Sterling',
            'symbol'    => '£',
            'precision' => 2,
            'rate'      => 0.0095,
        ],
    ],

    /*
    | Rates may be overridden from .env so a demo can retune them without a
    | code edit. Admin > Settings takes precedence over these values.
    */
    'env_rates' => [
        'USD' => env('CURRENCY_RATE_USD'),
        'EUR' => env('CURRENCY_RATE_EUR'),
        'GBP' => env('CURRENCY_RATE_GBP'),
    ],

    /*
    | Session key holding a guest's chosen currency.
    */
    'session_key' => 'kdp.currency',
];
