<?php

use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('csv-import');
});

Route::post('/import', [ImportController::class, 'import']);
Route::get('/customers', [ImportController::class, 'index']);