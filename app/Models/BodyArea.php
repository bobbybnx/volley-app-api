<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BodyArea extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function exercises()
    {
        return $this->belongsToMany(Exercise::class, 'body_area_exercise');
    }
}
