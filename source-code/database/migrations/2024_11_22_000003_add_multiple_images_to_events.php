<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Add images column as JSON
            $table->json('images')->nullable()->after('venue');
        });
        
        // Migrate existing single image data to images array
        DB::statement("UPDATE events SET images = JSON_ARRAY(image) WHERE image IS NOT NULL");
        
        // Drop old image column
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('image')->nullable()->after('venue');
        });
        
        // Migrate first image from array back to single image
        DB::statement("UPDATE events SET image = JSON_UNQUOTE(JSON_EXTRACT(images, '$[0]')) WHERE images IS NOT NULL");
        
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};
