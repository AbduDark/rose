<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * CourseImageGenerator
 *
 * Selects a random template image without any text overlay.
 */
class CourseImageGenerator
{
    private array $templates;

    public function __construct()
    {
        $this->templates = [
            public_path('templates/template1.jpg'),
            public_path('templates/template2.jpg'),
            public_path('templates/template3.jpg'),
        ];
    }

    /**
     * Generate course image by selecting a random template
     *
     * @param array $data Course data (not used but kept for compatibility)
     * @return string Relative path to the selected/copied image
     * @throws \RuntimeException
     */
    public function generateCourseImage(array $data): string
    {
        try {
            // Select random template
            $randomTemplate = $this->templates[array_rand($this->templates)];

            Log::info("Selected template: {$randomTemplate}");

            if (!file_exists($randomTemplate)) {
                Log::warning("Template file not found: {$randomTemplate}");
                throw new \RuntimeException("Template file not found: {$randomTemplate}");
            }

            // Generate unique filename
            $filename = Str::slug(uniqid('course_')) . '.jpg';
            $relativePath = 'uploads/courses/' . $filename;
            $fullPath = public_path($relativePath);

            // Create directory if it doesn't exist
            $directory = dirname($fullPath);
            if (!file_exists($directory) && !mkdir($directory, 0755, true)) {
                throw new \RuntimeException("Failed to create directory: {$directory}");
            }

            // Copy the template to the new location
            if (!copy($randomTemplate, $fullPath)) {
                throw new \RuntimeException("Failed to copy template to: {$fullPath}");
            }

            Log::info("Course image generated successfully: {$relativePath}");
            return $relativePath;

        } catch (\Exception $e) {
            Log::warning("Failed to draw text '{$text}': " . $e->getMessage());
            // رسم احتياطي بدون تأثيرات
            $img->text($text, $x, $y, function ($font) use ($fontSize, $hexColor, $align) {
                $font->size($fontSize);
                $font->color($hexColor);
                $font->align($align);
                $font->valign('top');
            });
        }
    }

    private function wrapTextToLines(string $text, ?string $fontFile, int $fontSize, int $maxWidth): array
    {
        $text = $this->processArabicText($text);

        if (!$fontFile || !file_exists($fontFile)) {
            // تقدير تقريبي للعرض بدون خط
            $charWidth = $fontSize * 0.7; // تعديل للنص العربي
            $maxChars = max(3, (int)($maxWidth / $charWidth));
            $words = explode(' ', trim($text));
            $lines = [];
            $current = '';

            foreach ($words as $word) {
                $testLine = $current === '' ? $word : $current . ' ' . $word;
                if (mb_strlen($testLine, 'UTF-8') <= $maxChars) {
                    $current = $testLine;
                } else {
                    if ($current !== '') $lines[] = $current;
                    $current = $word;
                }
            }
            if ($current !== '') $lines[] = $current;
            return $lines;
        }

        $words = explode(' ', trim($text));
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $try = $current === '' ? $word : $current . ' ' . $word;
            $width = $this->getTextWidth($try, $fontFile, $fontSize);

            if ($width <= $maxWidth) {
                $current = $try;
            } else {
                if ($current !== '') $lines[] = $current;

                $wwidth = $this->getTextWidth($word, $fontFile, $fontSize);

                if ($wwidth <= $maxWidth) {
                    $current = $word;
                } else {
                    // تقسيم الكلمة الطويلة
                    $chars = mb_str_split($word, 1, 'UTF-8');
                    $piece = '';

                    foreach ($chars as $ch) {
                        $tryPiece = $piece . $ch;
                        $pw = $this->getTextWidth($tryPiece, $fontFile, $fontSize);

                        if ($pw <= $maxWidth) {
                            $piece = $tryPiece;
                        } else {
                            if ($piece !== '') $lines[] = $piece;
                            $piece = $ch;
                        }
                    }
                    $current = $piece !== '' ? $piece : '';
                }
            }
        }

        if ($current !== '') $lines[] = $current;
        return $lines;
    }

    private function getTextWidth(string $text, ?string $fontFile, int $fontSize): int
    {
        if (!$fontFile || !file_exists($fontFile) || !function_exists('imagettfbbox')) {
            // تقدير تقريبي
            return mb_strlen($text, 'UTF-8') * ($fontSize * 0.7);
        }

        $box = imagettfbbox($fontSize, 0, $fontFile, $text);
        return abs($box[2] - $box[0]);
    }

    private function processArabicText(string $text): string
    {
        // تنظيف النص وإزالة المسافات الزائدة
        $text = trim($text);
        $text = preg_replace('/\s+/', ' ', $text);

        // إعادة النص كما هو - الخط العربي سيتولى التشكيل الصحيح
        return $text;
    }

    private function computeX(int $imgWidth, int $x, string $align = 'right', ?int $textWidth = null): int
    {
        $align = strtolower($align);

        if ($align === 'center') {
            if ($textWidth) {
                return max(0, $x - ($textWidth / 2));
            }
            return $x;
        }

        if ($align === 'left') {
            return $x;
        }

        // للمحاذاة اليمينية (افتراضي للعربية)
        if ($textWidth) {
            return max(0, $x - $textWidth);
        }
        return $x;
    }

    private function createFallbackImage(array $data): string
    {
        $img = Image::canvas(1200, 800, '#2d3748');
        $fontPath = file_exists($this->defaultFont) ? $this->defaultFont : null;

        $title = $data['title'] ?? 'كورس';
        $img->text($title, 600, 300, function ($font) use ($fontPath) {
            if ($fontPath) $font->file($fontPath);
            $font->size(48);
            $font->color('#ffffff');
            $font->align('center');
            $font->valign('center');
        });

        if (!empty($data['grade'])) {
            $img->text($data['grade'], 600, 400, function ($font) use ($fontPath) {
                if ($fontPath) $font->file($fontPath);
                $font->size(36);
                $font->color('#ffffff');
                $font->align('center');
                $font->valign('center');
            });
            Log::error('Course image generation failed: ' . $e->getMessage());
            throw new \RuntimeException('Course image generation failed: ' . $e->getMessage());
        }
    }
}
