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
            Log::error('Course image generation failed: ' . $e->getMessage());
            throw new \RuntimeException('Course image generation failed: ' . $e->getMessage());
        }
    }
}
