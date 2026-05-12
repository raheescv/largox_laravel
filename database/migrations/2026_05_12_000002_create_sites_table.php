<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('path');         // /home/forge/example.com
            $table->string('repository')->nullable();
            $table->string('branch')->default('main');
            $table->boolean('composer')->default(true);
            $table->boolean('npm_build')->default(false);
            $table->json('artisan_cmds')->nullable();
            $table->string('php_service')->default('php8.3-fpm');
            $table->string('queue_program')->nullable(); // supervisor program name
            $table->timestamps();

            $table->unique(['server_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
