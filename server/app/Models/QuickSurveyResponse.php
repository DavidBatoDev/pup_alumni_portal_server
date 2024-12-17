<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuickSurveyResponse extends Model
{
    use HasFactory;

    protected $fillable = ['alumni_id', 'selected_options', 'other_response'];

    public function alumni()
    {
        return $this->belongsTo(Alumni::class, 'alumni_id');
    }
}
