<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['statement', 'text_format', 'image_path'])]
class QuestionContext extends Model {}
