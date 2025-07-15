<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FirestoreSyncController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sync-firestore', [FirestoreSyncController::class, 'sync'])->name('sync.firestore');

