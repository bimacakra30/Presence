<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class EmployeePosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'position',
        'start_date',
        'end_date',
    ];

    protected static function booted()
    {
        static::saved(function ($position) {
            $position->syncEmployeeCurrentPosition();
        });

        static::deleted(function ($position) {
            $position->syncEmployeeCurrentPosition();
        });
    }

    public function syncEmployeeCurrentPosition()
    {
        $latest = self::where('employee_id', $this->employee_id)
            ->whereNull('end_date') // Hanya posisi aktif
            ->latest('start_date')
            ->first();

        $this->employee->update([
            'position' => $latest?->position,
        ]);
    }


    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
