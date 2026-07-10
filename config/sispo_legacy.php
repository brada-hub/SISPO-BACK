<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Include Virtual Meritos Array
    |--------------------------------------------------------------------------
    |
    | When true, the ExpedienteNormalizedResource will continue to serialize
    | the flat "meritos" array for backward compatibility with older frontend
    | components. When false, only the fully normalized 7 relationships are
    | returned, forcing the frontend to read from native structures.
    |
    */
    'include_virtual_meritos' => env('SISPO_INCLUDE_VIRTUAL_MERITOS', false),
];
