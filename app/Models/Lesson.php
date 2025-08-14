<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
  'course_id','title','description','content',
  'order','duration_minutes','is_free','target_gender',
  'video_path','hls_dir','hls_master','hls_key_path'
];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
