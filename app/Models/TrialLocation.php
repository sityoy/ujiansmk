<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'latitude', 'longitude', 'radius_meters', 'max_accuracy_meters'])]
class TrialLocation extends Model {}
