<?php

use App\Models\Peoplecount\Sensor;
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
        Schema::create('peoplecount_interval_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Sensor::class)->constrained();
            $table->dateTime('ts_from');
            $table->dateTime('ts_to');
            $table->unsignedSmallInteger('count_in');
            $table->unsignedSmallInteger('count_out');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peoplecount_interval_counts');
    }
};
