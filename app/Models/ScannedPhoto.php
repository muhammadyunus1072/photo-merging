<?php

namespace App\Models;

use App\Jobs\OptimizeStudentPhoto;
use Illuminate\Database\Eloquent\Model;

class ScannedPhoto extends Model
{
    protected $fillable = [
        'folder',
        'filename',
        'status',
    ];

    // protected static function booted(): void
    // {
    //     static::created(function ($student) {
    //         logger('created');
    //         OptimizeStudentPhoto::dispatch($student);
    //     });
    // }
}
