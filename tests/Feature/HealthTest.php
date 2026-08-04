<?php

test('the health endpoint responds successfully', function () {
    $response = $this->get('/api/health');

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
        ]);
});
