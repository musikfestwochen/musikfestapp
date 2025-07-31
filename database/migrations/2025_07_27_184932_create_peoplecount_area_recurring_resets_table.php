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
        Schema::create('peoplecount_area_recurring_resets', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Area::class)->constrained('peoplecount_areas')->cascadeOnDelete();
            $table->integer('reset_value');
            $table->time('reset_time');
            $table->string('timezone');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peoplecount_area_recurring_resets');
    }
};
