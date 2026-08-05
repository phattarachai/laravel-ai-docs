<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The suite's only table: something `actingAs` can sign in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_users');
    }
};
