<?php
use App\Services\CourseImageGenerator;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';

// تجربة توليد صورة لكورس تجريبي
$imageService = $app->make(CourseImageGenerator::class);

$title = 'كورس تجريبي';
$price = 150;
$description = '';
$grade = 'الثالث';

$filename = $imageService->generateCourseImage($title, $price, $description, $grade);

echo "تم إنشاء الصورة: " . $filename . "\n";
