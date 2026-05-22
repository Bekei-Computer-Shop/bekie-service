<?php

namespace App\Http\Requests\Api\Admin\V1;

use Illuminate\Foundation\Http\FormRequest;

abstract class AdminBaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
