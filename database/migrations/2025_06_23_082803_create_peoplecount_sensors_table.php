<?php

use App\Models\Organization;
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
        Schema::create('peoplecount_sensors', function (Blueprint $table) {
            $table->id();
            $table->string('vendor');
            $table->string('model');
            $table->string('serial');
            $table->string('api_token')->nullable();
            $table->foreignIdFor(Organization::class)->constrained()->cascadeOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peoplecount_sensors');
    }
};
