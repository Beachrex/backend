<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            // Image folder name (e.g., hr-557-k-odalmacija)
            $table->string('folder_name')->nullable()->after('name')->comment('Folder name for beach images');
            
            // Mobile upload flag
            $table->boolean('mobile_upload')->default(true)->after('folder_name')->comment('Flag for mobile uploads');
            
            // Video and embed URLs
            $table->text('video_url')->nullable()->after('mobile_upload')->comment('YouTube video URL');
            $table->text('webcam')->nullable()->after('video_url')->comment('Webcam embed code');
            $table->text('path_to_beach_map_url')->nullable()->after('webcam')->comment('Google Maps embed URL');
            $table->text('beach_weather')->nullable()->after('path_to_beach_map_url')->comment('Weather widget embed code');
            $table->text('water_temperature')->nullable()->after('beach_weather')->comment('Water temperature widget embed code');
            
            // Boat-only access flag (1:boat only, 0:bridge)
            $table->boolean('boat_only')->default(false)->after('water_temperature')->comment('1:boat only, 0:bridge');
            
            // View counter
            $table->unsignedBigInteger('beach_views')->default(0)->after('boat_only')->comment('Beach view counter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropColumn([
                'folder_name',
                'mobile_upload',
                'video_url',
                'webcam',
                'path_to_beach_map_url',
                'beach_weather',
                'water_temperature',
                'boat_only',
                'beach_views',
            ]);
        });
    }
};

