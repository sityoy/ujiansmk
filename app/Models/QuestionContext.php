<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['statement', 'text_format', 'image_path', 'image_caption'])]
class QuestionContext extends Model {}
