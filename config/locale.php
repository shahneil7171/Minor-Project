<?php

/*
|--------------------------------------------------------------------------
| Localization
|--------------------------------------------------------------------------
| UI/system text is translated through Laravel's lang/ files (lang/en, lang/hi,
| lang/gu). Product names and descriptions are NOT machine translated — seller
| content is shown exactly as written until the schema grows real translated
| product content.
|
| Only the locales listed below may ever be activated. Middleware validates the
| incoming value against this list, so a hand-crafted "locale" parameter can
| never make the framework load an arbitrary translation file.
*/

return [

    'default' => 'en',

    /*
    | All three supported languages read left-to-right. `direction` exists so a
    | future RTL language (e.g. Arabic/Urdu) only needs a new entry here plus the
    | <html dir> attribute the layout already emits from it.
    */
    'locales' => [
        'en' => [
            'name'      => 'English',
            'native'    => 'English',
            'direction' => 'ltr',
        ],
        'hi' => [
            'name'      => 'Hindi',
            'native'    => 'हिन्दी',
            'direction' => 'ltr',
        ],
        'gu' => [
            'name'      => 'Gujarati',
            'native'    => 'ગુજરાતી',
            'direction' => 'ltr',
        ],
    ],

    /*
    | Session key holding a guest's chosen language.
    */
    'session_key' => 'kdp.locale',
];
