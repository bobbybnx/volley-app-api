<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fundamental extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function exercises()
    {
        return $this->belongsToMany(Exercise::class, 'exercise_fundamental');
    }
}
