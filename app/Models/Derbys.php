<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Derbys extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'date', 'date_created', 'money', 'no_roosters', 'tolerance', 'min_weight', 'max_weight'
    ];
}
