<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SISPO AI Layer Configuration
    |--------------------------------------------------------------------------
    |
    | This config controls if Gemini or external LLMs are enabled for automated
    | candidate audits. Set to false to run the system 100% deterministically
    | with zero API token costs, utilizing the new Human Review workflow.
    |
    */

    'use_gemini' => env('SISPO_USE_GEMINI', false),
];
