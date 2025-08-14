<?php

namespace App\Jobs;

use App\Models\Lesson;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ProcessLessonVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $lesson;

    public function __construct(Lesson $lesson)
    {
        $this->lesson = $lesson;
    }

    public function handle()
    {
        $videoPath = storage_path("app/{$this->lesson->video_path}");
        $outputDir = storage_path("app/private_videos/hls/lesson_{$this->lesson->id}");

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        // توليد مفتاح AES
        $keyFile = "{$outputDir}/enc.key";
        $key = random_bytes(16);
        file_put_contents($keyFile, $key);

        // ملف معلومات المفتاح
        $keyInfoFile = "{$outputDir}/enc.keyinfo";
        $keyUri = route('video.key', ['lesson' => $this->lesson->id]); // API لتسليم المفتاح
        $iv = bin2hex(random_bytes(16));

        file_put_contents($keyInfoFile, "{$keyUri}\n{$keyFile}\n{$iv}");

        // أمر FFmpeg
        $process = new Process([
            'ffmpeg',
            '-i', $videoPath,
            '-hls_time', '10',
            '-hls_key_info_file', $keyInfoFile,
            '-hls_playlist_type', 'vod',
            "{$outputDir}/index.m3u8"
        ]);

        $process->run();

        if ($process->isSuccessful()) {
            $this->lesson->update([
                'video_path' => "private_videos/hls/lesson_{$this->lesson->id}/index.m3u8"
            ]);
        }
    }
}
