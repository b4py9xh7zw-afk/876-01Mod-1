<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appeal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_record_id')->constrained('exam_records')->cascadeOnDelete();
            $table->foreignId('proctor_event_id')->nullable()->constrained('proctor_events')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('explanation');
            $table->json('screenshots')->nullable();
            $table->string('status')->default('pending');
            $table->text('review_comment')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['exam_record_id', 'status']);
            $table->index('reviewer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appeal_records');
    }
};
