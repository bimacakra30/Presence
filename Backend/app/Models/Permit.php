<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permit extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_karyawan',
        'jenis_perizinan',
        'tanggal_mulai',
        'tanggal_selesai',
        'deskripsi',
        'bukti_izin',
        'status',
        'uid',
        'firestore_id',
        'public_id_bukti_izin'
    ];
}
