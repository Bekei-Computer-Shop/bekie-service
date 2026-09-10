<?php

declare(strict_types=1);

test('simple cart test without auth', function (): void {
    $response = $this->getJson('/api/v1/cart');

    $response->assertStatus(401);
});
