<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;

class Presence extends Model
{
    use HasFactory;
    protected $fillable = [
        'uid',
        'firestore_id',
        'nama',
        'tanggal',
        'clock_in',
        'clock_out',
        'foto_clock_in',
        'foto_clock_out',
    ];

    protected static function booted()
    {
        static::deleted(function ($presence) {
            if ($presence->firestore_id) {
                $service = new \App\Services\FirestoreService();
                $service->deleteAbsensi($presence->firestore_id);
            }
        });
    }

}
