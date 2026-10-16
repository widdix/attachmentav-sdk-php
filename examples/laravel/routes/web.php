<?php

use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ScanController::class, 'show'])->name('scan.show');
Route::post('/', [ScanController::class, 'scan'])->name('scan.scan');
