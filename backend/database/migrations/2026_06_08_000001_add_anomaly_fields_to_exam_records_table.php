<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_records', function (Blueprint $table) {
            $table->boolean('has_anomaly')->default(false)->after('status');
            $table->string('anomaly_status')->default('none')->after('has_anomaly');
        });
    }

    public function down(): void
    {
        Schema::table('exam_records', function (Blueprint $table) {
            $table->dropColumn(['has_anomaly', 'anomaly_status']);
        });
    }
};
