<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Laravel Blade MVC Views
|--------------------------------------------------------------------------
| Handles primary MVC views for Customer, Vendor, Rider & Admin
*/

Route::get('/', function () {
    return view('welcome', [
        'project' => 'Meramot — Digital Electronic Repair Marketplace',
        'course' => 'CSE470: Software Engineering',
        'faculty' => 'Nazmul Islam [NZU]',
    ]);
});

// Group 6 Role Portals
Route::get('/vendor/onboarding', function () {
    return view('vendor.onboarding');
});

Route::get('/vendor/bidding-board', function () {
    return view('vendor.bidding-board');
});

Route::get('/customer/request/create', function () {
    return view('customer.create-request');
});

Route::get('/admin/verify-agent', function () {
    return view('admin.verify-agent');
});
