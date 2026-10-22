<?php

namespace Tests\Unit;

use App\Models\Place;
use App\Services\AiContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AiContextBuilderTest extends \Tests\TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        Cache::flush();
    }

    public function test_only_approved_places_are_included(): void
    {
        Place::first()->update(['status' => 'on_moderation']);

        $builder = app(AiContextBuilder::class);
        $context = $builder->build();

        $this->assertStringContainsString('<CATALOG>', $context);
        $this->assertStringNotContainsString('id='.Place::first()->id, $context);
        $this->assertLessThanOrEqual(AiContextBuilder::MAX_LENGTH, mb_strlen($context));
    }

    public function test_context_does_not_expose_owner_or_contacts(): void
    {
        $builder = app(AiContextBuilder::class);
        $context = $builder->build();

        $this->assertStringNotContainsString('owner_id', $context);
        $this->assertStringNotContainsString('email', $context);
        $this->assertStringNotContainsString('phone', $context);
        $this->assertStringNotContainsString('@kod.local', $context);
    }

    public function test_context_length_limited(): void
    {
        $builder = app(AiContextBuilder::class);
        $context = $builder->build();

        $this->assertLessThanOrEqual(AiContextBuilder::MAX_LENGTH, mb_strlen($context));
    }

    public function test_context_is_cached(): void
    {
        $builder = app(AiContextBuilder::class);
        $builder->build();

        DB::enableQueryLog();

        $builder->build();

        $this->assertCount(0, DB::getQueryLog());
        DB::flushQueryLog();
    }
}
