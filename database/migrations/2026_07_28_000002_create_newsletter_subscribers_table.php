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
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->unique()->comment('Subscriber email address (normalized lowercase)');
            $table->unsignedTinyInteger('status_id')->default(1)->comment('1=active/subscribed, 2=unsubscribed/disabled');
            $table->foreign('status_id')->references('id')->on('status');
            $table->index('status_id');
            $table->boolean('is_synced')->default(false)->comment('Whether the subscriber has been synced to Klaviyo/ESP');
            $table->boolean('consent_accepted')->default(false)->comment('GDPR consent flag — user accepted the privacy policy');
            $table->timestamp('subscribed_at')->nullable()->comment('When the user first subscribed');
            $table->timestamp('synced_at')->nullable()->comment('When the subscriber was last synced to the ESP');
            $table->string('source', 50)->nullable()->comment('Subscription source (footer, popup, etc.)');
            $table->ipAddress('ip_address')->nullable()->comment('Subscriber IP for audit/GDPR');
            $table->text('user_agent')->nullable()->comment('Subscriber user agent for audit');
            $table->string('locale', 5)->nullable()->comment('Locale at subscription time (for localized ESP campaigns)');
            $table->index('is_synced');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
