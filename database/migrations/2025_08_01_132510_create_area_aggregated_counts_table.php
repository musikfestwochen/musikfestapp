<?php

use App\Models\Peoplecount\Area;
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
        Schema::create('peoplecount_area_aggregated_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Area::class)->constrained()->cascadeOnDelete();
            $table->integer('count')->default(0);
            $table->timestamp('period_start')->comment('Aggregation period start in UTC, inclusive');
            $table->timestamp('period_end')->comment('Aggregation period end in UTC, exclusive');
            $table->binary('checksum', 32, true)->comment('SHA256 checksum of the area configuration at the time of aggregation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peoplecount_area_aggregated_counts');
    }
};
