<?php

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\StoreMediaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MediaController extends BaseAdminController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $folder = $request->input('folder', '/');

        try {
            // The Admin API authenticates with basic auth, not an upload signature.
            $response = Http::withBasicAuth(
                (string) config('cloudinary.api_key'),
                (string) config('cloudinary.api_secret'),
            )->timeout(15)->get('https://api.cloudinary.com/v1_1/'.config('cloudinary.cloud_name').'/resources/image', [
                'type' => 'upload',
                'prefix' => $folder === '/' ? null : $folder,
            ]);

            if (! $response->successful()) {
                return $this->error('Unable to list media from Cloudinary.', 502);
            }

            $items = collect($response->json('resources', []))->map(fn (array $resource): array => [
                'url' => $resource['secure_url'] ?? null,
                'path' => $resource['public_id'] ?? null,
                'size' => $resource['bytes'] ?? null,
                'last_modified' => $resource['created_at'] ?? null,
            ]);

            return $this->success($items);
        } catch (\Throwable $e) {
            return $this->error('Unable to list media from Cloudinary.', 502, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $folder = $request->input('folder', config('cloudinary.folder'));

        $cloudName = config('cloudinary.cloud_name');
        $apiKey = config('cloudinary.api_key');
        $apiSecret = config('cloudinary.api_secret');

        if (! $cloudName || ! $apiKey || ! $apiSecret) {
            return $this->error('Cloudinary credentials are not configured.', 500);
        }

        try {
            $timestamp = (string) time();

            // resource_type is carried by the URL path, not the body: Cloudinary
            // excludes it from the signed string, so signing it yields 401.
            $signature = $this->signParams([
                'folder' => $folder,
                'timestamp' => $timestamp,
            ], $apiSecret);

            $response = Http::asMultipart()->timeout(30)->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                [
                    'name' => 'file',
                    'contents' => fopen($file->getRealPath(), 'r'),
                    'filename' => $file->getClientOriginalName(),
                ],
                [
                    'name' => 'folder',
                    'contents' => $folder,
                ],
                [
                    'name' => 'api_key',
                    'contents' => $apiKey,
                ],
                [
                    'name' => 'timestamp',
                    'contents' => $timestamp,
                ],
                [
                    'name' => 'signature',
                    'contents' => $signature,
                ],
            ]);

            if (! $response->successful()) {
                return $this->error('Cloudinary upload failed.', 502, ['cloudinary' => $response->json()]);
            }

            $payload = $response->json();

            return $this->created([
                'url' => $payload['secure_url'] ?? null,
                'path' => $payload['public_id'] ?? null,
                'resource_type' => $payload['resource_type'] ?? null,
            ], 'File uploaded successfully.');
        } catch (\Throwable $e) {
            return $this->error('Cloudinary upload failed.', 502, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Sign an upload/destroy request the way Cloudinary verifies it: a plain
     * SHA-1 of the alphabetically sorted params, concatenated with the API
     * secret. Not an HMAC — Cloudinary hashes `<query string><secret>`.
     *
     * Every param sent in the request body must be signed here, except `file`,
     * `api_key`, `resource_type` and `signature`, which Cloudinary excludes.
     * Adding a body param without adding it here yields 401 Invalid Signature.
     *
     * Values are joined raw and MUST NOT be URL-encoded: Cloudinary signs
     * `public_id=products/photo`, not `public_id=products%2Fphoto`, so
     * http_build_query() here breaks any value containing a slash.
     *
     * @param  array<string, string>  $params
     */
    private function signParams(array $params, string $apiSecret): string
    {
        ksort($params);

        $stringToSign = collect($params)
            ->map(fn (string $value, string $key): string => $key.'='.$value)
            ->implode('&');

        return sha1($stringToSign.$apiSecret);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string']);
        $path = $request->input('path');

        $cloudName = config('cloudinary.cloud_name');
        $apiKey = config('cloudinary.api_key');
        $apiSecret = config('cloudinary.api_secret');

        if (! $cloudName || ! $apiKey || ! $apiSecret) {
            return $this->error('Cloudinary credentials are not configured.', 500);
        }

        try {
            $timestamp = (string) time();
            $signature = $this->signParams([
                'public_id' => $path,
                'timestamp' => $timestamp,
            ], $apiSecret);

            $response = Http::asForm()->timeout(30)->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/destroy", [
                'public_id' => $path,
                'api_key' => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
            ]);

            if (! $response->successful()) {
                return $this->error('Cloudinary deletion failed.', 502);
            }

            return $this->noContent();
        } catch (\Throwable $e) {
            return $this->error('Cloudinary deletion failed.', 502, ['exception' => $e->getMessage()]);
        }
    }
}
