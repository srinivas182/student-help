<?php

namespace App\Domains\Curriculum\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type', 'sector', 'province', 'city', 'is_verified'];

    protected function casts(): array
    {
        return ['is_verified' => 'boolean'];
    }
}
