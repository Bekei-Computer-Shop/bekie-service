<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\UploadProfileImageRequest;
use App\Http\Resources\Api\Client\V1\UserProfileResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $file->store('avatars', 'public');

        $user->update(['avatar' => $path]);

        return $this->success(
            new UserProfileResource($user),
            'Profile image updated successfully.'
        );
    }
}
