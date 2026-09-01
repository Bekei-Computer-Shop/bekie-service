<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Client\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SlideResource extends JsonResource
{
    public function toArray($request): array
    {
        $meta = $this->meta ?? [];

        // The admin "slide" is stored as a cover image (`image_desktop`) plus a
        // sequence of extra frames in `meta.frames`. The storefront carousel
        // wants one ordered list, so we compose them here rather than forcing
        // the client to know about the storage split.
        $images = [];

        if ($this->image_desktop !== null) {
            $images[] = $this->resolveImage($this->image_desktop);
        }

        foreach (($meta['frames'] ?? []) as $frame) {
            if (is_string($frame) && $frame !== '') {
                $images[] = $this->resolveImage($frame);
            }
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'button_text' => $this->button_text,
            'button_url' => $this->button_url,
            'image_desktop' => $this->resolveImage($this->image_desktop),
            'image_mobile' => $this->resolveImage($this->image_mobile),
            'images' => $images,
            'duration_ms' => (int) ($meta['durationMs'] ?? 3000),
            'transition' => $meta['transition'] ?? 'fade',
            'gradient' => $meta['gradient'] ?? null,
            'meta' => $meta,
            'position' => $this->position,
            'is_active' => (bool) $this->is_active,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
        ];
    }

    /**
     * Uploads normally hold an absolute URL (Cloudinary) that must pass through
     * untouched; only a relative path is resolved against the public disk.
     */
    private function resolveImage(?string $image): ?string
    {
        if ($image === null || $image === '' || preg_match('#^(https?://|data:|//)#i', $image) === 1) {
            return $image;
        }

        return Storage::disk('public')->url($image);
    }
}