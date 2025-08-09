
<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
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

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function generateCourseImage(string $title, float $price, string $description, string $grade): string
    {
        // اختيار قالب عشوائي
        $templateName = $this->templates[array_rand($this->templates)];
        $templatePath = storage_path('app/templates/' . $templateName);
        
        // التأكد من وجود القالب
        if (!file_exists($templatePath)) {
            // إنشاء صورة افتراضية إذا لم يوجد القالب
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
            
            // التأكد من وجود المجلد
            if (!file_exists(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0755, true);
            }
            
            $image->save($fullPath);
            
            return $filename;
            
        } catch (\Exception $e) {
            \Log::error('Error generating course image: ' . $e->getMessage());
            return $this->createDefaultImage($title, $price, $description, $grade);
        }
    }

    private function addTextToImage($image, string $title, float $price, string $description, string $grade): void
    {
        // إعدادات النص
        $titleColor = '#2c3e50';
        $priceColor = '#e74c3c';
        $descColor = '#7f8c8d';
        $gradeColor = '#27ae60';

        // إضافة عنوان الكورس
        $image->text($this->truncateText($title, 30), 400, 200, function ($font) use ($titleColor) {
            $font->filename(public_path('fonts/NotoSansArabic-Bold.ttf'));
            $font->size(32);
            $font->color($titleColor);
            $font->align('center');
            $font->valign('middle');
        });

        // إضافة السعر
        $image->text($price . ' جنيه', 400, 260, function ($font) use ($priceColor) {
            $font->filename(public_path('fonts/NotoSansArabic-Bold.ttf'));
            $font->size(28);
            $font->color($priceColor);
            $font->align('center');
            $font->valign('middle');
        });

        // إضافة الوصف
        $image->text($this->truncateText($description, 60), 400, 320, function ($font) use ($descColor) {
            $font->filename(public_path('fonts/NotoSansArabic-Regular.ttf'));
            $font->size(18);
            $font->color($descColor);
            $font->align('center');
            $font->valign('middle');
        });

        // إضافة الصف
        $image->text('الصف ' . $grade, 400, 380, function ($font) use ($gradeColor) {
            $font->filename(public_path('fonts/NotoSansArabic-Bold.ttf'));
            $font->size(24);
            $font->color($gradeColor);
            $font->align('center');
            $font->valign('middle');
        });
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

        // نسخ القوالب من المجلد العام إلى storage
        for ($i = 1; $i <= 5; $i++) {
            $source = public_path("templates/template{$i}.jpg");
            $destination = $templatesDir . "/template{$i}.jpg";
            
            if (file_exists($source) && !file_exists($destination)) {
                copy($source, $destination);
            }
        }
    }
}
