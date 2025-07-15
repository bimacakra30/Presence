<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;

class FirestoreSyncController extends Controller
{
    public function sync()
    {
        // Panggil command
        Artisan::call('sync:firestore-employees');

        // Optional: Ambil output jika mau ditampilkan
        $output = Artisan::output();

        return redirect()->back()->with('success', 'Sync Firestore berhasil! ' . $output);
    }
}
