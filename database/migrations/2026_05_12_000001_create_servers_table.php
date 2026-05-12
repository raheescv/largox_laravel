<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(8088);
            $table->string('scheme')->default('https');
            $table->text('agent_secret');     // encrypted via cast
            $table->text('ssh_private_key')->nullable(); // encrypted via cast, for fallback
            $table->string('ssh_user')->default('root');
            $table->unsignedSmallInteger('ssh_port')->default(22);
            $table->string('status')->default('pending'); // pending|online|offline
            $table->timestamp('last_seen_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
