<?php

use App\Http\Controllers\EquipmentsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EquipmentsController::class, 'index'])->name('equipments');
