<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'subscribed_at', 
        'expires_at',
        'is_active',
        'is_approved',
        'approved_at',
        'admin_notes'
    ];

    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
