<?php

namespace App\Services;

use Intervention\Image\ImageManager ;;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Facades\Image;







/**
 * CourseImageGenerator
 *
 * Generates course images by selecting a random template and adding text/logo
 * based on predefined coordinates for each template.
 */
class CourseImageGenerator
{
    private array $templates;

    private string $defaultFont;

    public function __construct()
    {
        $this->templates = [
            [
                'id' => 'tpl1',
                'file' => storage_path('app/templates/template1.jpg'),
                'positions' => [
                    'title' => ['x' => 60, 'y' => 80,  'width' => 620, 'size' => 36, 'color' => '#ffffff', 'align' => 'left'],
                    'price' => ['x' => 60, 'y' => 420, 'width' => 300, 'size' => 22, 'color' => '#ffd700', 'align' => 'left'],
                    'grade' => ['x' => 60, 'y' => 460, 'width' => 300, 'size' => 18, 'color' => '#ffffff', 'align' => 'left'],
                    'description' => ['x' => 60, 'y' => 130, 'width' => 620, 'size' => 18, 'color' => '#ffffff', 'align' => 'left'],
                    'logo' => ['x' => 40, 'y' => 520, 'size' => 40, 'align' => 'left']
                ]
            ],
            [
                'id' => 'tpl2',
                'file' => storage_path('app/templates/template2.jpg'),
                'positions' => [
                    'title' => ['x' => 80, 'y' => 120, 'width' => 560, 'size' => 34, 'color' => '#000000', 'align' => 'left'],
                    'price' => ['x' => 620, 'y' => 120, 'width' => 200, 'size' => 20, 'color' => '#000000', 'align' => 'right'],
                    'grade' => ['x' => 80, 'y' => 420, 'width' => 560, 'size' => 18, 'color' => '#000000', 'align' => 'left'],
                    'description' => ['x' => 80, 'y' => 170, 'width' => 560, 'size' => 16, 'color' => '#333333', 'align' => 'left'],
                    'logo' => ['x' => 60, 'y' => 520, 'size' => 36, 'align' => 'left']
                ]
            ],
            [
                'id' => 'tpl3',
                'file' => storage_path('app/templates/template3.jpg'),
                'positions' => [
                    'title' => ['x' => 100, 'y' => 60,  'width' => 520, 'size' => 38, 'color' => '#ffffff', 'align' => 'left'],
                    'price' => ['x' => 100, 'y' => 420, 'width' => 300, 'size' => 20, 'color' => '#ffffff', 'align' => 'left'],
                    'grade' => ['x' => 100, 'y' => 455, 'width' => 300, 'size' => 18, 'color' => '#ffffff', 'align' => 'left'],
                    'description' => ['x' => 100, 'y' => 110, 'width' => 520, 'size' => 16, 'color' => '#ffffff', 'align' => 'left'],
                    'logo' => ['x' => 660, 'y' => 520, 'size' => 34, 'align' => 'right']
                ]
            ],
            [
                'id' => 'tpl4',
                'file' => storage_path('app/templates/template4.jpg'),
                'positions' => [
                    'title' => ['x' => 40, 'y' => 40,  'width' => 720, 'size' => 40, 'color' => '#ffffff', 'align' => 'center'],
                    'price' => ['x' => 40, 'y' => 500, 'width' => 350, 'size' => 24, 'color' => '#ffffff', 'align' => 'left'],
                    'grade' => ['x' => 420, 'y' => 500, 'width' => 350, 'size' => 20, 'color' => '#ffffff', 'align' => 'right'],
                    'description' => ['x' => 40, 'y' => 120, 'width' => 720, 'size' => 18, 'color' => '#ffffff', 'align' => 'center'],
                    'logo' => ['x' => 680, 'y' => 40, 'size' => 40, 'align' => 'right']
                ]
            ],
            [
                'id' => 'tpl5',
                'file' => storage_path('app/templates/template5.jpg'),
                'positions' => [
                    'title' => ['x' => 60, 'y' => 100, 'width' => 600, 'size' => 36, 'color' => '#ffffff', 'align' => 'left'],
                    'price' => ['x' => 60, 'y' => 420, 'width' => 300, 'size' => 22, 'color' => '#00ff00', 'align' => 'left'],
                    'grade' => ['x' => 60, 'y' => 460, 'width' => 300, 'size' => 18, 'color' => '#ffffff', 'align' => 'left'],
                    'description' => ['x' => 60, 'y' => 150, 'width' => 600, 'size' => 16, 'color' => '#ffffff', 'align' => 'left'],
                    'logo' => ['x' => 40, 'y' => 520, 'size' => 36, 'align' => 'left']
                ]
            ],
        ];

        $this->defaultFont = public_path('fonts/Cairo-Regular.ttf');
    }

    /**
     * Generate course image with provided data
     *
     * @param array $data Required keys: title, Optional: price, grade, description, instructor, logo_path, currency
     * @return string Relative path to the generated image
     * @throws \InvalidArgumentException
     */
    public function generateCourseImage(array $data): string
    {
        if (empty($data['title'])) {
            throw new \InvalidArgumentException('Title is required for course image generation');
        }

        $tpl = $this->templates[array_rand($this->templates)];

        if (!file_exists($tpl['file'])) {
            Log::warning("Template file not found: {$tpl['file']}");
            return $this->createFallbackImage($data);
        }

        try {
            $img = Image::make($tpl['file']);
            $fontPath = file_exists($this->defaultFont) ? $this->defaultFont : null;

            $this->addTextElements($img, $tpl, $data, $fontPath);

            if (!empty($data['logo_path']) && file_exists($data['logo_path']) && !empty($tpl['positions']['logo'])) {
                $this->addLogo($img, $data['logo_path'], $tpl['positions']['logo']);
            }

            return $this->saveImage($img);
        } catch (\Exception $e) {
            Log::error('Course image generation failed: ' . $e->getMessage());
            return $this->createFallbackImage($data);
        }
    }

    private function addTextElements($img, array $tpl, array $data, ?string $fontPath): void
    {
        foreach (['title', 'description', 'price', 'grade'] as $field) {
            if (empty($tpl['positions'][$field])) continue;

            $text = $this->getFieldText($field, $data);
            if (empty($text)) continue;

            $pos = $tpl['positions'][$field];
            $this->drawTextBox(
                $img,
                $text,
                $pos['x'],
                $pos['y'],
                $pos['width'] ?? 400,
                $fontPath,
                $pos['size'] ?? 18,
                $pos['color'] ?? '#000000',
                $pos['align'] ?? 'left'
            );
        }

        if (!empty($data['instructor']) && !empty($tpl['positions']['instructor'])) {
            $pos = $tpl['positions']['instructor'];
            $this->drawTextBox(
                $img,
                $data['instructor'],
                $pos['x'],
                $pos['y'],
                $pos['width'] ?? 200,
                $fontPath,
                $pos['size'] ?? 16,
                $pos['color'] ?? '#fff',
                $pos['align'] ?? 'left'
            );
        }
    }

    private function getFieldText(string $field, array $data): string
    {
        switch ($field) {
            case 'title':
                return $data['title'] ?? '';
            case 'description':
                return $data['description'] ?? '';
            case 'price':
                return isset($data['price']) ? $data['price'] . ' ' . ($data['currency'] ?? 'جنيه') : '';
            case 'grade':
                return $data['grade'] ?? '';
            default:
                return '';
        }
    }

    private function addLogo($img, string $logoPath, array $position): void
    {
        try {
            $logo = Image::make($logoPath);
            $logoSize = $position['size'] ?? 40;
            $logo->resize($logoSize, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            $destX = $this->computeX($img->width(), $position['x'], $position['align'] ?? 'left', $logo->width());
            $destY = $position['y'];
            $img->insert($logo, 'top-left', $destX, $destY);
        } catch (\Exception $e) {
            Log::warning('Failed to insert logo: ' . $e->getMessage());
        }
    }

    private function saveImage($img): string
    {
        $filename = Str::slug(uniqid('course_')) . '.jpg';
        $relativePath = 'uploads/courses/' . $filename;
        $fullPath = public_path($relativePath);

        $directory = dirname($fullPath);
        if (!file_exists($directory) && !mkdir($directory, 0755, true)) {
            throw new \RuntimeException("Failed to create directory: {$directory}");
        }

        $img->save($fullPath, 88);
        return $relativePath;
    }

    private function drawTextBox($img, string $text, int $x, int $y, int $boxWidth, ?string $fontPath, int $fontSize, string $hexColor, string $align = 'left'): void
    {
        $lines = $this->wrapTextToLines($text, $fontPath, $fontSize, $boxWidth);
        $lineHeight = (int)($fontSize * 1.25);
        $startX = $this->computeX($img->width(), $x, $align, null);

        foreach ($lines as $i => $line) {
            $lineY = $y + ($i * $lineHeight);
            $img->text($line, $startX, $lineY, function ($font) use ($fontPath, $fontSize, $hexColor, $align) {
                if ($fontPath) {
                    $font->file($fontPath);
                }
                $font->size($fontSize);
                $font->color($hexColor);
                $font->align($align);
                $font->valign('top');
            });
        }
    }

    private function wrapTextToLines(string $text, ?string $fontFile, int $fontSize, int $maxWidth): array
    {
        if (!$fontFile || !file_exists($fontFile)) {
            $approx = max(10, (int)($maxWidth / 10));
            $words = explode(' ', $text);
            $lines = [];
            $current = '';

            foreach ($words as $w) {
                if (mb_strlen($current . ' ' . $w) <= $approx) {
                    $current = trim($current . ' ' . $w);
                } else {
                    if ($current !== '') $lines[] = $current;
                    $current = $w;
                }
            }
            if ($current !== '') $lines[] = $current;
            return $lines;
        }

        $words = preg_split('/\s+/u', trim($text));
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $try = $current === '' ? $word : $current . ' ' . $word;
            $box = imagettfbbox($fontSize, 0, $fontFile, $try);
            $width = abs($box[2] - $box[0]);

            if ($width <= $maxWidth) {
                $current = $try;
            } else {
                if ($current !== '') $lines[] = $current;

                $wbox = imagettfbbox($fontSize, 0, $fontFile, $word);
                $wwidth = abs($wbox[2] - $wbox[0]);

                if ($wwidth <= $maxWidth) {
                    $current = $word;
                } else {
                    $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
                    $piece = '';

                    foreach ($chars as $ch) {
                        $tryPiece = $piece . $ch;
                        $b = imagettfbbox($fontSize, 0, $fontFile, $tryPiece);
                        $pw = abs($b[2] - $b[0]);

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

    private function computeX(int $imgWidth, int $x, string $align = 'left', ?int $elementWidth = null): int
    {
        $align = strtolower($align);

        if ($align === 'center') {
            if ($elementWidth) {
                return (int)(($imgWidth / 2) - ($elementWidth / 2) + $x);
            }
            return (int)($imgWidth / 2);
        }

        if ($align === 'right') {
            if ($elementWidth) {
                return max(0, $imgWidth - $x - $elementWidth);
            }
            return max(0, $imgWidth - $x);
        }

        return $x;
    }
    private function createFallbackImage(array $data): string
    {
        $img = Image::canvas(800, 600, '#2d3748');
        $fontPath = file_exists($this->defaultFont) ? $this->defaultFont : null;

        $title = $data['title'] ?? 'Course';
        $img->text($title, 400, 200, function ($font) use ($fontPath) {
            if ($fontPath) $font->file($fontPath);
            $font->size(32);
            $font->color('#ffffff');
            $font->align('center');
            $font->valign('top');
        });

        return $this->saveImage($img);
    }
}
