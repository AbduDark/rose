<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'description' => $this->description,
            'video_url' => $this->when(
                $this->is_free || $this->isUserSubscribed(),
                $this->video_url
            ),
            'duration_minutes' => $this->duration_minutes,
            'order' => $this->order,
            'is_free' => $this->is_free,
            'can_access' => $this->is_free || $this->isUserSubscribed(),
            'created_at' => $this->created_at,
        ];
    }

    private function isUserSubscribed(): bool
    {
        if (!auth()->check()) {
            return false;
        }

        return $this->course->subscriptions()
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->exists();
    }
}
