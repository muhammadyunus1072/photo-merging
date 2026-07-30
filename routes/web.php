<?php

use App\Http\Controllers\PhotoScannerController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__ . '/settings.php';


Route::group(["controller" => PhotoScannerController::class, "prefix" => "scan", "as" => "scan."], function () {
    Route::get('{id}', 'scanFolder')->name('scanFolder');
});
