<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CourseImageGenerator
{
    private array $templates = [
        'template1.jpg',
        'template2.jpg',
        'template3.jpg',
        'template4.jpg',
        'template5.jpg'
    ];

    private ImageManager $manager;
    private bool $useImagick = false;

    public function __construct()
    {
        // استخدم Imagick إذا كان متوفرًا
        if (class_exists('Imagick')) {
            $this->manager = new ImageManager(new \Intervention\Image\Drivers\Imagick\Driver());
            $this->useImagick = true;
        } else {
            $this->manager = new ImageManager(new Driver());
        }
    }

    private function createDefaultImage(string $title, float $price, string $description, string $grade): string
    {
        // إنشاء صورة افتراضية بسيطة
        $image = $this->manager->create(800, 600)->fill('#f8f9fa');
        $this->addTextToImage($image, $title, $price, $description, $grade);
        $filename = 'courses/' . uniqid() . '.jpg';
        $fullPath = storage_path('app/public/' . $filename);

        if (!file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        $image->save($fullPath);
        return $filename;
    }

    public function generateCourseImage(string $title, float $price, string $description, string $grade): string
    {
        // اختيار قالب عشوائي
        $templateName = $this->templates[array_rand($this->templates)];
        $templatePath = storage_path('app/templates/' . $templateName);

        // التأكد من وجود القالب
        if (!file_exists($templatePath)) {
            return $this->createDefaultImage($title, $price, $description, $grade);
        }

        try {
            // قراءة القالب
            $image = $this->manager->read($templatePath);

            // إضافة النصوص
            $this->addTextToImage($image, $title, $price, $description, $grade);

            // حفظ الصورة
            $filename = 'courses/' . uniqid() . '.jpg';
            $fullPath = storage_path('app/public/' . $filename);

            if (!file_exists(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0755, true);
            }

            $image->save($fullPath);

            return $filename;
        } catch (\Exception $e) {
            Log::error('Error generating course image: ' . $e->getMessage());
            return $this->createDefaultImage($title, $price, $description, $grade);
        }
    }

    private function addTextToImage($image, string $title, float $price, string $description, string $grade): void
    {
        $titleColor = '#2c3e50';
        $priceColor = '#e74c3c';
        $descColor  = '#7f8c8d';
        $gradeColor = '#27ae60';

        $template = $image->filename ?? '';
        switch (basename($template)) {
            case 'template1.jpg':
                $titleXY = [400, 180]; $priceXY = [400, 240]; $gradeXY = [400, 300]; break;
            case 'template2.jpg':
                $titleXY = [200, 100]; $priceXY = [200, 160]; $gradeXY = [200, 220]; break;
            case 'template3.jpg':
                $titleXY = [600, 400]; $priceXY = [600, 460]; $gradeXY = [600, 520]; break;
            case 'template4.jpg':
                $titleXY = [100, 500]; $priceXY = [100, 560]; $gradeXY = [100, 620]; break;
            case 'template5.jpg':
                $titleXY = [700, 100]; $priceXY = [700, 160]; $gradeXY = [700, 220]; break;
            default:
                $titleXY = [400, 200]; $priceXY = [400, 260]; $gradeXY = [400, 320];
        }

        $title     = $this->fixArabicText($title);
        $gradeText = $this->fixArabicText('الصف ' . $grade);
        $priceText = $price . ' جنيه';

        $image->text($title, $titleXY[0], $titleXY[1], function ($font) use ($titleColor) {
            $font->filename(base_path('public/fonts/NotoSansArabic-Bold.ttf'));
            $font->size(32);
            $font->color($titleColor);
            $font->align('center');
            $font->valign('middle');
        });

        $image->text($priceText, $priceXY[0], $priceXY[1], function ($font) use ($priceColor) {
            $font->filename(base_path('public/fonts/NotoSansArabic-Bold.ttf'));
            $font->size(28);
            $font->color($priceColor);
            $font->align('center');
            $font->valign('middle');
        });

        $image->text($gradeText, $gradeXY[0], $gradeXY[1], function ($font) use ($gradeColor) {
            $font->filename(base_path('public/fonts/NotoSansArabic-Bold.ttf'));
            $font->size(24);
            $font->color($gradeColor);
            $font->align('center');
            $font->valign('middle');
        });
    }

    private function fixArabicText($text): string
    {
        if ($this->useImagick) {
            return $text;
        }
        return implode('', array_reverse(preg_split('//u', $text, 0, PREG_SPLIT_NO_EMPTY)));
    }

    private function truncateText(string $text, int $length): string
    {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '...' : $text;
    }

    public function copyTemplatesToStorage(): void
    {
        $templatesDir = storage_path('app/templates');
        if (!file_exists($templatesDir)) {
            mkdir($templatesDir, 0755, true);
        }

        for ($i = 1; $i <= 5; $i++) {
            $source = public_path("templates/template{$i}.jpg");
            $destination = $templatesDir . "/template{$i}.jpg";

            if (file_exists($source) && !file_exists($destination)) {
                copy($source, $destination);
            }
        }
    }
}
