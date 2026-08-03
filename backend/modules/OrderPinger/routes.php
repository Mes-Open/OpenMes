<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/modules/order-pinger', fn () => view('order-pinger::status'))
        ->name('order-pinger.status');
});
