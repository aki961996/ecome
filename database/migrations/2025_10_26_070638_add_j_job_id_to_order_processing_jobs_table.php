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
        Schema::table('order_processing_jobs', function (Blueprint $table) {
            $table->string('j_job_id')->nullable()->after('order_id');   // q job id
            $table->index(['j_job_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_processing_jobs', function (Blueprint $table) {
           $table->dropColumn('j_job_id');

        });
    }
};
