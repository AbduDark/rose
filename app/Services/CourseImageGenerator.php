<?php

namespace App\Services;

use Illuminate\Support\Facades\{Log, Storage};
use Intervention\Image\Facades\Image;


class CourseImageGenerator
{
    /**
     * قائمة القوالب المتاحة
     */
    
    private array $templates = [
        'template1.jpg',
        'template2.jpg',
        'template3.jpg',
        'template4.jpg',
        'template5.jpg'
    ];

    private array $colors = [
        ['#e74c3c', '#c0392b'], // أحمر
        ['#3498db', '#2980b9'], // أزرق
        ['#2ecc71', '#27ae60'], // أخضر
        ['#f39c12', '#e67e22'], // برتقالي
        ['#9b59b6', '#8e44ad'], // بنفسجي
    ];

        public function generateCourseImage(string $title, float $price, string $description, string $grade): string
{
    try {
        $coursesPath = public_path('uploads/courses');
        if (!file_exists($coursesPath)) {
            mkdir($coursesPath, 0755, true);
        }

        $image = $this->createSimpleImage($title, $price, $description, $grade);

        if (!$image) {
            Log::warning('Failed to create image with GD, trying fallback method');
            return $this->createFallbackImage($title, $price, $grade);
        }

        $filename = uniqid() . '_course.jpg';
        $relativePath = 'uploads/courses/' . $filename; // بدون 'public/'
        $fullPath = public_path($relativePath);

        if (imagejpeg($image, $fullPath, 85)) {
            imagedestroy($image);
            return $relativePath; // إرجاع المسار النسبي فقط
        }

        imagedestroy($image);
        throw new \Exception('Failed to save image');
    } catch (\Exception $e) {
        return $this->createFallbackImage($title, $price, $grade);
    }
}



    private function createSimpleImage(string $title, float $price, string $description, string $grade)
    {
        // إنشاء صورة بحجم 800x600
        $image = imagecreatetruecolor(800, 600);

        if (!$image) {
            return false;
        }

        // اختيار لون عشوائي
        $colorScheme = $this->colors[array_rand($this->colors)];

        // تحويل الألوان من hex إلى RGB
        $bgColor = $this->hexToRgb($colorScheme[0]);
        $accentColor = $this->hexToRgb($colorScheme[1]);

        // إنشاء الألوان
        $backgroundColor = imagecolorallocate($image, $bgColor[0], $bgColor[1], $bgColor[2]);
        $accentBgColor = imagecolorallocate($image, $accentColor[0], $accentColor[1], $accentColor[2]);
        $white = imagecolorallocate($image, 255, 255, 255);
        $darkGray = imagecolorallocate($image, 51, 51, 51);

        // تعبئة الخلفية
        imagefill($image, 0, 0, $backgroundColor);

        // رسم شكل زخرفي
        imagefilledrectangle($image, 0, 0, 800, 150, $accentBgColor);
        imagefilledrectangle($image, 0, 450, 800, 600, $accentBgColor);

        // إضافة النصوص
        $this->addTextToImage($image, $title, $price, $grade, $white, $darkGray);

        return $image;
    }

    private function addTextToImage($image, string $title, float $price, string $grade, $whiteColor, $darkColor)
    {
        // تحديد مسار الخط - استخدام الخط المدمج إذا لم يوجد خط مخصص
        $fontPath = base_path('public/fonts/NotoSansArabic-Bold.ttf');
        $useCustomFont = file_exists($fontPath);

        // عنوان الكورس
        $titleText = $this->truncateText($title, 40);
        if ($useCustomFont) {
            imagettftext($image, 24, 0, 50, 100, $whiteColor, $fontPath, $titleText);
        } else {
            imagestring($image, 5, 50, 70, $titleText, $whiteColor);
        }

        // السعر
        $priceText = $price . ' جنيه';
        if ($useCustomFont) {
            imagettftext($image, 20, 0, 50, 300, $darkColor, $fontPath, $priceText);
        } else {
            imagestring($image, 4, 50, 280, $priceText, $darkColor);
        }

        // الصف الدراسي
        $gradeText = 'الصف ' . $grade;
        if ($useCustomFont) {
            imagettftext($image, 18, 0, 50, 350, $darkColor, $fontPath, $gradeText);
        } else {
            imagestring($image, 3, 50, 330, $gradeText, $darkColor);
        }

        // شعار الأكاديمية
        $logoText = '🌹 أكاديمية الوردة';
        if ($useCustomFont) {
            imagettftext($image, 16, 0, 550, 550, $whiteColor, $fontPath, $logoText);
        } else {
            imagestring($image, 3, 550, 530, $logoText, $whiteColor);
        }
    }

  private function createFallbackImage(string $title, float $price, string $grade): string
    {
        try {
            // إنشاء مجلد إذا لم يكن موجود
            $coursesPath = public_path('uploads/courses');
            if (!file_exists($coursesPath)) {
                mkdir($coursesPath, 0755, true);
            }

            // إنشاء SVG
            $svgContent = $this->generateSVGImage($title, $price, $grade);

            $filename = uniqid() . '_fallback.svg';
            $relativePath = 'uploads/courses/' . $filename;
            $fullPath = public_path($relativePath);

            if (file_put_contents($fullPath, $svgContent)) {
                Log::info('Fallback SVG image created', ['path' => $relativePath]);
                return $relativePath;
            }

            throw new \Exception('Failed to save SVG');

        } catch (\Exception $e) {
            Log::error('Failed to create fallback image: ' . $e->getMessage());

            // كملاذ أخير، إنشاء صورة افتراضية
            $filename = 'default_' . md5($title . $price) . '.jpg';
            $relativePath = 'uploads/courses/' . $filename;
            $fullPath = public_path($relativePath);

            $defaultContent = base64_decode('data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            file_put_contents($fullPath, $defaultContent);

            return $relativePath;
        }
    }

    private function generateSVGImage(string $title, float $price, string $grade): string
    {
        $colorScheme = $this->colors[array_rand($this->colors)];
        $titleText = htmlspecialchars($this->truncateText($title, 30));
        $priceText = htmlspecialchars($price . ' جنيه');
        $gradeText = htmlspecialchars('الصف ' . $grade);

        return <<<SVG
<svg width="800" height="600" xmlns="http://www.w3.org/2000/svg">
    <rect width="800" height="600" fill="{$colorScheme[0]}"/>
    <rect width="800" height="150" fill="{$colorScheme[1]}"/>
    <rect y="450" width="800" height="150" fill="{$colorScheme[1]}"/>

    <text x="50" y="100" font-family="Arial, sans-serif" font-size="24" fill="white" font-weight="bold">{$titleText}</text>
    <text x="50" y="300" font-family="Arial, sans-serif" font-size="20" fill="#333">{$priceText}</text>
    <text x="50" y="350" font-family="Arial, sans-serif" font-size="18" fill="#333">{$gradeText}</text>
    <text x="550" y="550" font-family="Arial, sans-serif" font-size="16" fill="white">🌹 أكاديمية الوردة</text>
</svg>
SVG;
    }

    private function hexToRgb(string $hex): array
    {
        $hex = str_replace('#', '', $hex);
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        ];
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
            Log::info('Created templates directory');
        }

        // إنشاء قوالب وهمية إذا لم توجد
        for ($i = 1; $i <= 5; $i++) {
            $templatePath = $templatesDir . "/template{$i}.jpg";
            if (!file_exists($templatePath)) {
                $this->createDummyTemplate($templatePath, $i);
            }
        }
    }

    private function createDummyTemplate(string $path, int $templateNumber): void
    {
        try {
            $image = imagecreatetruecolor(800, 600);
            $colorScheme = $this->colors[($templateNumber - 1) % count($this->colors)];

            $bgColor = $this->hexToRgb($colorScheme[0]);
            $accentColor = $this->hexToRgb($colorScheme[1]);

            $backgroundColor = imagecolorallocate($image, $bgColor[0], $bgColor[1], $bgColor[2]);
            $accentBgColor = imagecolorallocate($image, $accentColor[0], $accentColor[1], $accentColor[2]);

            imagefill($image, 0, 0, $backgroundColor);

            // إضافة بعض الأشكال الزخرفية
            switch ($templateNumber) {
                case 1:
                    imagefilledrectangle($image, 0, 0, 800, 100, $accentBgColor);
                    break;
                case 2:
                    imagefilledellipse($image, 400, 300, 600, 400, $accentBgColor);
                    break;
                case 3:
                    imagefilledrectangle($image, 600, 0, 800, 600, $accentBgColor);
                    break;
                case 4:
                    imagefilledrectangle($image, 0, 500, 800, 600, $accentBgColor);
                    break;
                case 5:
                    imagefilledrectangle($image, 0, 0, 200, 600, $accentBgColor);
                    break;
            }

            imagejpeg($image, $path, 85);
            imagedestroy($image);

            Log::info("Created dummy template: template{$templateNumber}.jpg");

        } catch (\Exception $e) {
            Log::error("Failed to create dummy template {$templateNumber}: " . $e->getMessage());
        }
    }
}
