<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner report 2026-10-29: Plage du Port Vieux (3247) has a 360° panorama but no krpano tour in storage, so the
 * "360° view" popup was black. `has_vtour`: null = not checked yet (treated as present, as before), true/false =
 * checked by `media:check-vtours` or by the beach page (App\Support\SphereTour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->boolean('has_vtour')->nullable()->after('mobile_upload')
                ->comment('360 tour (vtour/tour.html) exists in storage; null = not checked');
        });
    }

    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropColumn('has_vtour');
        });
    }
};
