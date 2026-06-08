<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proctor_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_record_id')->constrained('exam_records')->cascadeOnDelete();
            $table->string('event_type');
            $table->timestamp('event_time');
            $table->text('detail')->nullable();
            $table->timestamps();

            $table->index(['exam_record_id', 'event_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proctor_events');
    }
};
