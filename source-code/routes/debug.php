<?php

use Illuminate\Support\Facades\Route;
use App\Models\Announcement;

Route::get('/debug-announcements', function() {
    $announcements = Announcement::all();
    
    echo "<h1>Announcements Debug</h1>";
    
    foreach ($announcements as $ann) {
        $imageCount = $ann->images ? count($ann->images) : 0;
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px;'>";
        echo "<strong>ID:</strong> {$ann->id}<br>";
        echo "<strong>Title:</strong> " . substr($ann->title, 0, 100) . "<br>";
        echo "<strong>Image Count:</strong> {$imageCount}<br>";
        
        if ($imageCount == 14) {
            echo "<h2 style='color: red;'>FOUND THE 14 IMAGE ANNOUNCEMENT!</h2>";
            echo "<strong>Images Array:</strong><br>";
            echo "<pre>";
            print_r($ann->images);
            echo "</pre>";
            
            echo "<h3>Testing transform_images():</h3>";
            $transformed = transform_images($ann->images);
            echo "<strong>Transformed Count:</strong> " . count($transformed) . "<br>";
            echo "<pre>";
            foreach ($transformed as $idx => $url) {
                echo "Image {$idx}: " . (strlen($url) > 100 ? substr($url, 0, 100) . '...' : $url) . "<br>";
            }
            echo "</pre>";
        }
        
        echo "</div>";
    }
});
