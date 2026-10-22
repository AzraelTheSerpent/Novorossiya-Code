<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('verticals', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('slug')->unique();
            $t->string('description', 500)->nullable(); $t->string('color', 7)->default('#547864');
            $t->json('fields')->nullable(); $t->timestamps();
        });
        Schema::table('places', function (Blueprint $t) {
            $t->foreignId('vertical_id')->nullable()->constrained()->nullOnDelete();
            $t->json('tags')->nullable(); $t->json('details')->nullable();
            $t->string('visit_format')->default('individual');
            $t->timestamp('verified_at')->nullable();
        });
        Schema::create('journeys', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name'); $t->string('slug')->unique(); $t->text('description')->nullable();
            $t->unsignedInteger('duration')->default(120); $t->boolean('published')->default(false);
            $t->timestamps();
        });
        Schema::create('journey_place', function (Blueprint $t) {
            $t->foreignId('journey_id')->constrained()->cascadeOnDelete();
            $t->foreignId('place_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('position'); $t->unsignedInteger('minutes')->default(30);
            $t->primary(['journey_id', 'place_id']);
        });
        Schema::create('investment_sites', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->text('description'); $t->string('address');
            $t->unsignedInteger('area'); $t->string('format'); $t->string('contact')->nullable();
            $t->timestamps();
        });
        Schema::create('investment_requests', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('investment_site_id')->constrained()->cascadeOnDelete();
            $t->string('project'); $t->text('description'); $t->string('contact');
            $t->string('status')->default('new'); $t->text('response')->nullable(); $t->timestamps();
        });
        Schema::create('place_reports', function (Blueprint $t) {
            $t->id(); $t->foreignId('place_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type')->default('inaccuracy'); $t->text('message');
            $t->boolean('resolved')->default(false); $t->timestamps();
        });
        Schema::table('reviews', function (Blueprint $t) {
            $t->text('owner_reply')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('reviews', fn(Blueprint $t) => $t->dropColumn('owner_reply'));
        foreach (['place_reports', 'investment_requests', 'investment_sites', 'journey_place', 'journeys'] as $table) Schema::dropIfExists($table);
        Schema::table('places', function(Blueprint $t) {
            $t->dropConstrainedForeignId('vertical_id');
            $t->dropColumn(['tags', 'details', 'visit_format', 'verified_at']);
        });
        Schema::dropIfExists('verticals');
    }
};
