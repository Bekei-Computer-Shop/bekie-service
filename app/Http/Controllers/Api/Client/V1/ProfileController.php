<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\Profile\ChangePasswordRequest;
use App\Http\Requests\Api\Client\V1\Profile\UpdateProfileImageRequest;
use App\Http\Requests\Api\Client\V1\Profile\UpdateProfileRequest;
use App\Http\Resources\Api\Client\V1\UserProfileResource;
use Illuminate\Support\Facades\Hash;

class ProfileController extends BaseApiController
{
    public function show()
    {
        $user = auth()->user();

        return $this->success(new UserProfileResource($user));
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = auth()->user();
        $data = $request->validated();

        $user->update([
            'first_name' => $data['first_name'] ?? $user->first_name,
            'last_name' => $data['last_name'] ?? $user->last_name,
            'email' => $data['email'] ?? $user->email,
            'phone' => $data['phone'] ?? $user->phone,
        ]);

        return $this->success(new UserProfileResource($user), 'Profile updated successfully.');
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = auth()->user();
        $data = $request->validated();

        if (! Hash::check($data['current_password'], $user->password)) {
            return $this->error('Current password is incorrect.', 422, [
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => $data['new_password'],
        ]);

        return $this->success(new UserProfileResource($user), 'Password changed successfully.');
    }

    public function updateProfileImage(UpdateProfileImageRequest $request)
    {
        $user = auth()->user();

        if (! $request->hasFile('image')) {
            return $this->error('No image provided.', 422);
        }

        $file = $request->file('image');
        $filename = 'avatar_'.$user->id.'_'.time().'.'.$file->getClientOriginalExtension();

        $path = $file->storeAs('avatars', $filename, 'public');

        $user->update([
            'avatar' => '/storage/'.$path,
        ]);

        return $this->success(new UserProfileResource($user), 'Profile image updated successfully.');
    }
}
