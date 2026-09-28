<?php

/*
|--------------------------------------------------------------------------
| Display preferences (switcher UI + its flash messages)
|--------------------------------------------------------------------------
| Resolves __('preferences.*'). The values in the layout that compare a
| flash error against these strings (unknown_currency / unknown_locale)
| therefore match the message the controller actually produced.
*/

return [
    'language'         => 'Language',
    'currency'         => 'Currency',
    'reset'            => 'Display preferences reset to defaults.',
    'reset_action'     => 'Reset',
    'currency_set'     => 'Prices now shown in :currency.',
    'locale_set'       => 'Language changed to :locale.',
    'unknown_currency' => 'That currency is not available.',
    'unknown_locale'   => 'That language is not available.',
];
