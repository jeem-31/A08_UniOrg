<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            // Change image column to JSON to support multiple images
            $table->json('images')->nullable()->after('type');
        });
        
        // Migrate existing single image data to images array
        DB::statement("UPDATE announcements SET images = JSON_ARRAY(image) WHERE image IS NOT NULL");
        
        // Drop old image column
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('image')->nullable()->after('type');
        });
        
        // Migrate first image from array back to single image
        DB::statement("UPDATE announcements SET image = JSON_UNQUOTE(JSON_EXTRACT(images, '$[0]')) WHERE images IS NOT NULL");
        
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};
