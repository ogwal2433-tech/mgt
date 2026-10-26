<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SequenceCounter extends Model
{
    protected $fillable = ['name', 'value'];
}
