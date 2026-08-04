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
            $response = Http::timeout(15)->get('https://api.cloudinary.com/v1_1/'.config('cloudinary.cloud_name').'/resources/image', [
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
            $params = [
                'folder' => $folder,
                'resource_type' => 'image',
                'timestamp' => $timestamp,
            ];

            $signature = hash_hmac('sha256', http_build_query($params, '', '&'), $apiSecret);

            $response = Http::asMultipart()->timeout(30)->post("https://api.cloudinary.com/v1_1/{$cloudName}/upload", [
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
                    'name' => 'resource_type',
                    'contents' => 'image',
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
            $signature = hash_hmac('sha256', http_build_query(['public_id' => $path, 'timestamp' => $timestamp], '', '&'), $apiSecret);

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
