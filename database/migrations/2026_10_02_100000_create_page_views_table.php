<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only analytics events for public detail-page visits (see
        // App\Services\Analytics\PageViewRecorder). No updated_at, and no
        // foreign key on subject_id on purpose: history must not depend on
        // the lifetime of the match/edition/player it refers to.
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 20);
            $table->unsignedBigInteger('subject_id');
            // HMAC of the rppl_vid cookie value — never the raw cookie.
            $table->char('visitor_hash', 64);
            // UTC, like every other timestamp in the application.
            $table->timestamp('viewed_at')->useCurrent();
            // The same instant in the configured display timezone at the
            // time it was recorded, so reports can group portably (no
            // database-specific date functions) and history stays stable.
            $table->date('local_date');
            $table->unsignedTinyInteger('local_hour');

            $table->index(['event_type', 'subject_id', 'viewed_at'], 'page_views_subject_time_index');
            $table->index(['event_type', 'local_date'], 'page_views_type_date_index');
            $table->index(['visitor_hash', 'event_type', 'subject_id', 'viewed_at'], 'page_views_visitor_dedupe_index');
            $table->index('viewed_at', 'page_views_viewed_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
