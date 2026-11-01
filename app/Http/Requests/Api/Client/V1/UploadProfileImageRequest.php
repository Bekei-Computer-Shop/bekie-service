<?php

namespace App\Http\Requests\Api\Client\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UploadProfileImageRequest extends FormRequest
{
    public const MAX_SIZE_KB = 5120;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // bail: report only the first problem, so the customer gets one clear message.
            'avatar' => 'bail|required|file|image|mimes:jpeg,png,jpg,gif|max:'.self::MAX_SIZE_KB,
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => 'Please choose a photo to upload.',
            'avatar.file' => 'Please choose a photo to upload.',
            'avatar.image' => 'This file isn\'t a photo. Please upload a JPG, PNG or GIF image.',
            'avatar.mimes' => 'This photo format isn\'t supported. Please upload a JPG, PNG or GIF image.',
            'avatar.max' => 'Your photo is larger than 5 MB. Please choose a smaller photo and try again.',
            'avatar.uploaded' => $this->uploadFailureMessage(),
        ];
    }

    /**
     * Answer with the first error as the headline message and a machine-readable
     * `code`, so the app can show it as-is or react to it (e.g. offer to resize).
     */
    protected function failedValidation(Validator $validator): void
    {
        $failed = array_key_first($validator->failed()['avatar'] ?? []) ?? '';

        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'code' => $this->errorCode(strtolower($failed)),
            'message' => $validator->errors()->first('avatar'),
            'data' => null,
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }

    private function errorCode(string $rule): string
    {
        return match ($rule) {
            'required', 'file' => 'avatar_required',
            'image', 'mimes' => 'avatar_invalid_type',
            'max' => 'avatar_too_large',
            'uploaded' => match ($this->uploadError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'avatar_too_large',
                UPLOAD_ERR_PARTIAL => 'avatar_upload_interrupted',
                default => 'avatar_upload_failed',
            },
            default => 'avatar_invalid',
        };
    }

    /**
     * The `uploaded` rule fails when PHP itself rejected the file before
     * validation ran, so the PHP upload error says what actually went wrong.
     */
    private function uploadFailureMessage(): string
    {
        return match ($this->uploadError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Your photo is too large. Please choose a photo under 5 MB and try again.',
            UPLOAD_ERR_PARTIAL => 'Your photo didn\'t finish uploading. Please check your connection and try again.',
            default => 'We couldn\'t upload your photo right now. Please try again in a few minutes.',
        };
    }

    private function uploadError(): ?int
    {
        $file = $this->file('avatar');

        return $file ? $file->getError() : null;
    }
}
