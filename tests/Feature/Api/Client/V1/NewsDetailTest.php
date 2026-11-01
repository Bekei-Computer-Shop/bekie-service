<?php

declare(strict_types=1);

use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a published article is found by id with its author and related news', function (): void {
    $author = User::factory()->create(['name' => 'Dara Sok']);
    $article = ContentItem::factory()->news()->published()->create([
        'author_id' => $author->id,
        'category' => 'Guides',
        'slug' => 'choosing-a-gaming-laptop',
    ]);
    $sameCategory = ContentItem::factory()->news()->published()->count(2)->create(['category' => 'Guides']);
    ContentItem::factory()->news()->published()->create(['category' => 'Promotions']);
    ContentItem::factory()->news()->create(['category' => 'Guides', 'status' => 'draft']);

    $response = $this->getJson("/api/v1/news/{$article->id}")
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.id', $article->id)
        ->assertJsonPath('data.slug', 'choosing-a-gaming-laptop')
        ->assertJsonPath('data.title', $article->title)
        ->assertJsonPath('data.author', ['id' => $author->id, 'name' => 'Dara Sok'])
        ->assertJsonCount(2, 'data.related');

    expect(collect($response->json('data.related'))->pluck('id')->sort()->values()->all())
        ->toBe($sameCategory->pluck('id')->sort()->values()->all());
});

test('a published article is found by slug', function (): void {
    $article = ContentItem::factory()->news()->published()->create(['slug' => 'ddr5-explained']);

    $this->getJson('/api/v1/news/ddr5-explained')
        ->assertOk()
        ->assertJsonPath('data.id', $article->id);
});

test('drafts, scheduled articles, other content types and unknown ids are not found', function (): void {
    $draft = ContentItem::factory()->news()->create(['status' => 'draft']);
    $scheduled = ContentItem::factory()->news()->create(['status' => 'published', 'published_at' => now()->addDay()]);
    $page = ContentItem::factory()->page()->published()->create();

    foreach ([$draft->id, $scheduled->id, $page->id, 999999, 'no-such-article'] as $key) {
        $this->getJson("/api/v1/news/{$key}")
            ->assertNotFound()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', "We couldn't find this article. It may have been moved or removed.");
    }
});
