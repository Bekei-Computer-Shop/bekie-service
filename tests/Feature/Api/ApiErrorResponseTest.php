<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

/**
 * Every API error, whatever raised it, answers with the same envelope and a
 * message a customer can read.
 */
class ApiErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    private const ENVELOPE = ['status', 'code', 'message', 'data', 'errors'];

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api/v1/__errors')->group(function (): void {
            Route::post('one-field', fn (Request $request) => $request->validate(['email' => 'required|email']));
            Route::post('many-fields', fn (Request $request) => $request->validate(['email' => 'required', 'name' => 'required']));
            Route::get('products/{product}', fn (Product $product) => $product);
            Route::get('abort', fn () => abort(404, 'This item is no longer in your cart.'));
            Route::get('crash', fn () => throw new RuntimeException('SQLSTATE[42S02]: secret table name'));
            Route::get('throttled', fn () => throw new TooManyRequestsHttpException(30));
            Route::get('manual', fn () => throw ValidationException::withMessages(['code' => 'Bad coupon.']));
        });
    }

    public function test_a_single_invalid_field_leads_with_its_own_message(): void
    {
        $this->postJson('/api/v1/__errors/one-field', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonStructure(self::ENVELOPE)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('message', 'The email field must be a valid email address.')
            ->assertJsonPath('data', null)
            ->assertJsonPath('errors.email.0', 'The email field must be a valid email address.');
    }

    public function test_several_invalid_fields_are_counted_in_the_message(): void
    {
        $this->postJson('/api/v1/__errors/many-fields')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Please check the 2 highlighted fields and try again.')
            ->assertJsonStructure(['errors' => ['email', 'name']]);
    }

    public function test_a_missing_record_is_named_without_exposing_the_model_class(): void
    {
        $response = $this->getJson('/api/v1/__errors/products/999999')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found')
            ->assertJsonPath('message', "We couldn't find that product. It may have been removed or the link may be out of date.");

        $this->assertStringNotContainsString('App\\\\Models', $response->getContent());
    }

    public function test_an_abort_message_reaches_the_user(): void
    {
        $this->getJson('/api/v1/__errors/abort')
            ->assertNotFound()
            ->assertJsonPath('message', 'This item is no longer in your cart.');
    }

    public function test_an_unknown_endpoint_gets_a_friendly_message(): void
    {
        $response = $this->getJson('/api/v1/__errors/no-such-endpoint')
            ->assertNotFound()
            ->assertJsonStructure(self::ENVELOPE)
            ->assertJsonPath('message', "We couldn't find what you were looking for.");

        $this->assertStringNotContainsString('The route', $response->getContent());
    }

    public function test_an_unexpected_failure_hides_internals_and_gives_a_support_reference(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/v1/__errors/crash')
            ->assertStatus(500)
            ->assertJsonPath('code', 'server_error');

        $reference = $response->json('errors.reference.0');

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{10}$/', $reference);
        $this->assertStringContainsString($reference, $response->json('message'));
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    public function test_rate_limits_say_how_long_to_wait(): void
    {
        $this->getJson('/api/v1/__errors/throttled')
            ->assertStatus(429)
            ->assertHeader('Retry-After', '30')
            ->assertJsonPath('code', 'too_many_requests')
            ->assertJsonPath('message', "You're going a little too fast. Please wait 30 seconds and try again.");
    }

    public function test_signing_in_is_asked_for_when_no_token_is_sent(): void
    {
        $this->getJson('/api/v1/profile')
            ->assertUnauthorized()
            ->assertJsonStructure(self::ENVELOPE)
            ->assertJsonPath('code', 'unauthenticated')
            ->assertJsonPath('message', 'Please sign in to continue.');
    }

    public function test_an_invalid_token_reads_as_an_expired_session(): void
    {
        $this->withToken('not-a-real-token')
            ->getJson('/api/v1/profile')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'session_expired')
            ->assertJsonPath('message', 'Your session has expired. Please sign in again.');
    }

    public function test_admin_routes_use_the_same_envelope(): void
    {
        $this->getJson('/api/v1/admin/auth/me')
            ->assertUnauthorized()
            ->assertJsonStructure(self::ENVELOPE)
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_errors_is_always_an_object(): void
    {
        $this->assertStringContainsString('"errors":{}', $this->getJson('/api/v1/profile')->getContent());
    }
}
