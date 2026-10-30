<?php

declare(strict_types=1);

use App\Models\ContentItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public news includes the uploaded cover video URL', function (): void {
    ContentItem::factory()->news()->published()->create([
        'video_url' => 'https://res.cloudinary.com/test-cloud/video/upload/clip.mp4',
    ]);

    $this->getJson('/api/v1/news')
        ->assertOk()
        ->assertJsonPath('data.items.0.video_url', 'https://res.cloudinary.com/test-cloud/video/upload/clip.mp4');
});
