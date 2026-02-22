<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('equipments');
})->name('equipments');
