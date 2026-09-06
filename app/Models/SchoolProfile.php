<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name', 'npsn', 'address', 'city', 'phone', 'email', 'principal_name', 'letterhead_path',
])]
class SchoolProfile extends Model
{
}
