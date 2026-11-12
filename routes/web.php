<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Vue.js SPA Routes
|--------------------------------------------------------------------------
| All frontend routes are handled by Vue Router.
| Laravel returns the same Blade view for frontend URLs.
*/

Route::get('/{any?}', function () {
    return view('app');
})
    ->where('any', '.*')
    ->name('spa');
