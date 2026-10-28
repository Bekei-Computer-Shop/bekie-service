<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\UploadProfileImageRequest;
use App\Http\Resources\Api\Client\V1\UserProfileResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
            return $this->error('Image file is required.', 422);
        }

        $cloudName = config('cloudinary.cloud_name');
        $apiKey = config('cloudinary.api_key');
        $apiSecret = config('cloudinary.api_secret');

        if (! $cloudName || ! $apiKey || ! $apiSecret) {
            return $this->error('Cloudinary credentials are not configured.', 500);
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
                return $this->error('Unable to read the profile image.', 422);
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
                return $this->error('Cloudinary upload failed.', 502);
            }

            $avatarUrl = $response->json('secure_url');

            if (! is_string($avatarUrl) || ! str_starts_with($avatarUrl, 'https://')) {
                return $this->error('Cloudinary returned an invalid image URL.', 502);
            }

            $user->update(['avatar' => $avatarUrl]);
        } catch (\Throwable) {
            return $this->error('Cloudinary upload failed.', 502);
        }

        return $this->success(
            new UserProfileResource($user),
            'Profile image updated successfully.'
        );
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
