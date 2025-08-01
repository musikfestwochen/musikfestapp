<?php

use App\Models\Peoplecount\Area;
use App\Models\User;
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
        Schema::create('peoplecount_area_single_resets', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Area::class)->constrained('peoplecount_areas')->cascadeOnDelete();
            $table->integer('reset_value');
            $table->datetime('effective_at');
            $table->foreignIdFor(User::class, 'created_by')->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peoplecount_area_single_resets');
    }
};
