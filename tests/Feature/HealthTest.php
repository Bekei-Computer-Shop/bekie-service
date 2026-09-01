<?php

use App\Http\Middleware\ScrambleDocsAccess;
use Illuminate\Http\Request;

test('the health endpoint responds successfully', function () {
    $response = $this->get('/api/health');

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
        ]);
});

test('scramble docs are public when enabled in production', function () {
    putenv('SCRAMBLE_ENABLED=true');
    $_ENV['SCRAMBLE_ENABLED'] = 'true';
    $_SERVER['SCRAMBLE_ENABLED'] = 'true';

    $middleware = app(ScrambleDocsAccess::class);
    $request = Request::create('/docs/client', 'GET');
    $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

    expect($response->getStatusCode())->toBe(200);

    putenv('SCRAMBLE_ENABLED=false');
    $_ENV['SCRAMBLE_ENABLED'] = 'false';
    $_SERVER['SCRAMBLE_ENABLED'] = 'false';
});
