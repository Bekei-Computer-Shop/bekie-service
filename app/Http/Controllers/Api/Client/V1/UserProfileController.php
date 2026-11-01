<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\UploadProfileImageRequest;
use App\Http\Resources\Api\Client\V1\UserProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UserProfileController extends BaseApiController
{
    public function show(Request $request)
    {
        $user = $request->attributes->get('authenticated_user');

        if (! $user instanceof User) {
            return $this->error('Unauthorized.', 401);
        }

        return $this->success(
            new UserProfileResource($user),
            'Profile retrieved successfully.'
        );
    }

    public function updateAvatar(UploadProfileImageRequest $request)
    {
        $user = $request->attributes->get('authenticated_user');

        if (! $user instanceof User) {
            return $this->error('Unauthorized.', 401);
        }

        $file = $request->file('avatar');

        if (! $file) {
            return $this->avatarError('Please choose a photo to upload.', 422, 'avatar_required');
        }

        $cloudName = config('cloudinary.cloud_name');
        $apiKey = config('cloudinary.api_key');
        $apiSecret = config('cloudinary.api_secret');

        if (! $cloudName || ! $apiKey || ! $apiSecret) {
            Log::error('Avatar upload unavailable: Cloudinary credentials are not configured.');

            return $this->avatarUnavailable();
        }

        $folder = trim((string) config('cloudinary.folder', 'uploads'), '/').'/avatars';
        $publicId = 'customer-'.$user->id;
        $timestamp = (string) time();
        $signature = $this->cloudinarySignature([
            'folder' => $folder,
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ], (string) $apiSecret);

        try {
            $stream = fopen($file->getRealPath(), 'r');

            if ($stream === false) {
                return $this->avatarError("We couldn't read this photo. Please choose another photo and try again.", 422, 'avatar_unreadable');
            }

            try {
                $response = Http::asMultipart()->timeout(30)->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                    ['name' => 'file', 'contents' => $stream, 'filename' => $file->getClientOriginalName()],
                    ['name' => 'folder', 'contents' => $folder],
                    ['name' => 'public_id', 'contents' => $publicId],
                    ['name' => 'api_key', 'contents' => (string) $apiKey],
                    ['name' => 'timestamp', 'contents' => $timestamp],
                    ['name' => 'signature', 'contents' => $signature],
                ]);
            } finally {
                fclose($stream);
            }

            if (! $response->successful()) {
                Log::warning('Avatar upload rejected by Cloudinary.', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'error' => $response->json('error.message'),
                ]);

                // A 4xx means Cloudinary could not use this file (corrupt, unsupported
                // content); anything else is on our side or theirs, so the customer can retry.
                return $response->clientError() && ! in_array($response->status(), [401, 403, 429], true)
                    ? $this->avatarError("We couldn't process this photo. Please choose a different photo and try again.", 422, 'avatar_unprocessable')
                    : $this->avatarUnavailable();
            }

            $avatarUrl = $response->json('secure_url');

            if (! is_string($avatarUrl) || ! str_starts_with($avatarUrl, 'https://')) {
                Log::error('Avatar upload returned an invalid image URL.', ['user_id' => $user->id, 'url' => $avatarUrl]);

                return $this->avatarUnavailable();
            }

            $user->update(['avatar' => $avatarUrl]);
        } catch (\Throwable $e) {
            Log::error('Avatar upload failed.', ['user_id' => $user->id, 'exception' => $e]);

            return $this->avatarUnavailable();
        }

        return $this->success(
            new UserProfileResource($user),
            'Profile image updated successfully.'
        );
    }

    private function avatarUnavailable(): JsonResponse
    {
        return $this->avatarError("We couldn't update your photo right now. Please try again in a few minutes.", 503, 'avatar_service_unavailable');
    }

    /**
     * Same shape as the request's validation errors, so the app handles every
     * avatar failure one way: show `message`, branch on `code` if it needs to.
     */
    private function avatarError(string $message, int $status, string $code): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'code' => $code,
            'message' => $message,
            'data' => null,
            'errors' => ['avatar' => [$message]],
        ], $status);
    }

    /**
     * @param  array<string, string>  $params
     */
    private function cloudinarySignature(array $params, string $apiSecret): string
    {
        ksort($params);

        $stringToSign = collect($params)
            ->map(fn (string $value, string $key): string => $key.'='.$value)
            ->implode('&');

        return sha1($stringToSign.$apiSecret);
    }
}
