<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo Mode
    |--------------------------------------------------------------------------
    |
    | Turn this on for a public demo. Every email the app sends is also saved
    | to the demo inbox at /demo/inbox, a banner on every page points to it,
    | and the sign-in page lists the demo accounts. Leave it off otherwise:
    | the inbox shows every captured email to anyone who opens it.
    |
    */

    'enabled' => (bool) env('DEMO_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Inbox Size
    |--------------------------------------------------------------------------
    |
    | How many of the newest captured emails the demo inbox keeps.
    |
    */

    'inbox_size' => 200,

    /*
    |--------------------------------------------------------------------------
    | Demo Accounts
    |--------------------------------------------------------------------------
    |
    | The accounts DatabaseSeeder creates, listed on the sign-in page. They all
    | use the password below.
    |
    */

    'password' => 'password',

    'accounts' => [
        ['email' => 'admin@foodbank.test', 'description' => 'Administrator of Food Bank North'],
        ['email' => 'volunteer@foodbank.test', 'description' => 'Volunteer in Food Bank North'],
        ['email' => 'both@example.test', 'description' => 'Volunteer in Food Bank North and administrator of River Cleanup'],
        ['email' => 'admin@rivercleanup.test', 'description' => 'Administrator of River Cleanup'],
        ['email' => 'requester@example.test', 'description' => 'Waiting for the Community Kitchen request to be approved'],
        ['email' => 'operator@example.test', 'description' => 'Platform operator'],
    ],

];
