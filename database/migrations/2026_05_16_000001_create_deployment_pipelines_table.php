<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('steps'); // ordered array of step keys
            $table->string('branch')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('command_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('command'); // whitelisted binary: composer|php|git|npm
            $table->json('args');      // array of arguments
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_presets');
        Schema::dropIfExists('deployment_pipelines');
    }
};
