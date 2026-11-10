<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths (Root Frontend / Backend Separation)
    |--------------------------------------------------------------------------
    | All Laravel Blade UI views live in the root `frontend/views` directory
    | to keep Frontend presentation and Backend business logic cleanly separated
    | in two distinct root folders while running on standard Laravel 10 MVC.
    */

    'paths' => [
        base_path('../frontend/views'),
        resource_path('views'),
    ],

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        realpath(storage_path('framework/views'))
    ),

];
