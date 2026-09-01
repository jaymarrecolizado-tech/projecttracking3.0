<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarangayReference extends Model
{
    protected $fillable = ['province', 'municipality', 'name', 'name_normalized', 'psgc'];
}
