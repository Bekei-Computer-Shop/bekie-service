<?php

declare(strict_types=1);

use App\Models\ContentItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public content endpoints return only the published page fields', function (): void {
    ContentItem::factory()->page()->create([
        'slug' => 'about-us',
        'title' => 'About BackieDeal',
        'body' => 'Our story.',
        'status' => 'published',
        'published_at' => now()->subMinute(),
    ]);

    $this->getJson('/api/v1/about-us')
        ->assertOk()
        ->assertJsonPath('data.slug', 'about-us')
        ->assertJsonPath('data.title', 'About BackieDeal')
        ->assertJsonPath('data.body', 'Our story.')
        ->assertJsonMissingPath('data.status')
        ->assertJsonMissingPath('data.author');
});

test('public content endpoints hide drafts and future publications', function (): void {
    ContentItem::factory()->page()->create([
        'slug' => 'terms-and-conditions',
        'status' => 'draft',
    ]);
    ContentItem::factory()->page()->create([
        'slug' => 'contact-us',
        'status' => 'published',
        'published_at' => now()->addMinute(),
    ]);

    $this->getJson('/api/v1/terms-and-conditions')->assertNotFound();
    $this->getJson('/api/v1/contact-us')->assertNotFound();
});

test('public content endpoints return a consistent not found response', function (): void {
    $this->getJson('/api/v1/about-us')
        ->assertNotFound()
        ->assertJson([
            'status' => 'error',
            'message' => 'Content page not found.',
        ]);
});
