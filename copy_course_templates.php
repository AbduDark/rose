<?php
// script to move and rename attached_assets images to storage/app/templates as template1.jpg ... template5.jpg

$sourceDir = base_path('attached_assets');
$targetDir = storage_path('app/templates');

if (!file_exists($targetDir)) {
    mkdir($targetDir, 0755, true);
}

for ($i = 1; $i <= 5; $i++) {
    $source = $sourceDir . "/{$i}_175474235258" . ($i == 1 ? '2' : ($i == 2 ? '3' : '4')) . ".jpg";
    if (!file_exists($source)) {
        // fallback: try any file that starts with $i_
        $files = glob($sourceDir . "/{$i}_*.jpg");
        $source = $files ? $files[0] : null;
    }
    $target = $targetDir . "/template{$i}.jpg";
    if ($source && file_exists($source)) {
        copy($source, $target);
    }
}

echo "Templates copied and renamed successfully.";
