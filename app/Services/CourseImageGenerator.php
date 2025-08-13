<?php

namespace App\Services;

use Intervention\Image\ImageManager;
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
                'file' => public_path('templates/template1.jpg'),
                'positions' => [
                    'title' => [
                        'x' => 177,
                        'y' => 177,
                        'width' => 365,
                        'size' => 60,
                        'color' => '#ffffff',
                        'align' => 'left',
                        'stroke' => [
                            'size' => 2,
                            'color' => '#ffffff'
                        ],
                        'outline' => [
                            'color' => '#000000',
                            'opacity' => 18,
                            'size' => 13,
                            'range' => 50
                        ]
                    ],
                    'price' => ['x' => 60, 'y' => 420, 'width' => 300, 'size' => 22, 'color' => '#ffd700', 'align' => 'left'],
                    'grade' => ['x' => 60, 'y' => 460, 'width' => 300, 'size' => 18, 'color' => '#ffffff', 'align' => 'left'],
                    'description' => ['x' => 60, 'y' => 130, 'width' => 620, 'size' => 18, 'color' => '#ffffff', 'align' => 'left'],
                    'logo' => ['x' => 40, 'y' => 520, 'size' => 40, 'align' => 'left']
                ]
            ],
            // Other templates can be uncommented and updated as needed
        ];

        $this->defaultFont = public_path('fonts/NotoSansArabic-Regular.ttf');
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

        // Select random template
        $tpl = $this->templates[array_rand($this->templates)];

        Log::info("Selected template: {$tpl['id']} for course: {$data['title']}");

        if (!file_exists($tpl['file'])) {
            Log::warning("Template file not found: {$tpl['file']}");
            return $this->createFallbackImage($data);
        }

        try {
            $img = Image::make($tpl['file']);
            $fontPath = file_exists($this->defaultFont) ? $this->defaultFont : null;

            Log::info("Using font: " . ($fontPath ? $fontPath : 'system default'));

            $this->addTextElements($img, $tpl, $data, $fontPath);

            if (!empty($data['logo_path']) && file_exists($data['logo_path']) && !empty($tpl['positions']['logo'])) {
                $this->addLogo($img, $data['logo_path'], $tpl['positions']['logo']);
            }

            $imagePath = $this->saveImage($img);
            Log::info("Course image generated successfully: {$imagePath}");

            return $imagePath;
        } catch (\Exception $e) {
            Log::error('Course image generation failed: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
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
                $pos['align'] ?? 'left',
                $pos // Pass the entire position array for additional styling
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
                $pos['align'] ?? 'left',
                $pos
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

    private function drawTextBox($img, string $text, int $x, int $y, int $boxWidth, ?string $fontPath, int $fontSize, string $hexColor, string $align = 'left', array $position = []): void
    {
        try {
            $lines = $this->wrapTextToLines($text, $fontPath, $fontSize, $boxWidth);
            $lineHeight = (int)($fontSize * 1.25);
            $startX = $this->computeX($img->width(), $x, $align, null);

            // Get additional styling options
            $strokeSize = $position['stroke']['size'] ?? 0;
            $strokeColor = $position['stroke']['color'] ?? '#ffffff';
            $outlineColor = $position['outline']['color'] ?? '#000000';
            $outlineOpacity = $position['outline']['opacity'] ?? 18;
            $outlineSize = $position['outline']['size'] ?? 13;
            $outlineRange = $position['outline']['range'] ?? 50;

            Log::info("Drawing text: {$text} at position ({$startX}, {$y}) with {$fontSize}px font");

            foreach ($lines as $i => $line) {
                $lineY = $y + ($i * $lineHeight);

                // Draw outline/outer glow effect
                if ($outlineSize > 0) {
                    for ($o = 1; $o <= $outlineSize; $o++) {
                        $img->text($line, $startX, $lineY, function ($font) use ($fontPath, $fontSize, $outlineColor, $align, $outlineOpacity) {
                            if ($fontPath && file_exists($fontPath)) {
                                $font->file($fontPath);
                            }
                            $font->size($fontSize);
                            $font->color($outlineColor);
                            $font->align($align);
                            $font->valign('top');
                            $font->opacity($outlineOpacity);
                        });
                    }
                }

                // Draw stroke effect
                if ($strokeSize > 0) {
                    for ($s = 1; $s <= $strokeSize; $s++) {
                        // Draw stroke in all directions
                        $offsets = [
                            [$s, 0], [-$s, 0], [0, $s], [0, -$s],  // horizontal and vertical
                            [$s, $s], [-$s, -$s], [$s, -$s], [-$s, $s]  // diagonal
                        ];

                        foreach ($offsets as $offset) {
                            $img->text($line, $startX + $offset[0], $lineY + $offset[1], function ($font) use ($fontPath, $fontSize, $strokeColor, $align) {
                                if ($fontPath && file_exists($fontPath)) {
                                    $font->file($fontPath);
                                }
                                $font->size($fontSize);
                                $font->color($strokeColor);
                                $font->align($align);
                                $font->valign('top');
                            });
                        }
                    }
                }

                // Draw main text
                $img->text($line, $startX, $lineY, function ($font) use ($fontPath, $fontSize, $hexColor, $align) {
                    if ($fontPath && file_exists($fontPath)) {
                        $font->file($fontPath);
                    }
                    $font->size($fontSize);
                    $font->color($hexColor);
                    $font->align($align);
                    $font->valign('top');
                });
            }
        } catch (\Exception $e) {
            Log::warning("Failed to draw text '{$text}': " . $e->getMessage());
            // Fallback drawing without effects
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
            $charWidth = $fontSize * 0.6;
            $maxChars = max(5, (int)($maxWidth / $charWidth));
            $words = preg_split('/\s+/u', trim($text));
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

    private function processArabicText(string $text): string
    {
        return trim($text);
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
