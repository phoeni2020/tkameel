<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Veichle extends Model
{
    public function driver(){
        return $this->belongsTo(Driver::class,'driver','id');
    }
}
