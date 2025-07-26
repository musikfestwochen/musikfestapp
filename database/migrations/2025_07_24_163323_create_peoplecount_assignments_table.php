<?php

use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Event;
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
        Schema::create('peoplecount_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Event::class)->constrained('peoplecount_events')->cascadeOnDelete();
            $table->foreignIdFor(Area::class)->constrained('peoplecount_areas')->cascadeOnDelete();
            $table->foreignIdFor(Sensor::class)->constrained('peoplecount_sensors')->cascadeOnDelete();
            $table->boolean('direction_flipped')->default(false);
            $table->dateTime('active_from');
            $table->dateTime('active_to');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peoplecount_assignments');
    }
};
