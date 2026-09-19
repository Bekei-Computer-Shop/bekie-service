<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\ContactRequest;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;

class ContactController extends BaseApiController
{
    public function __construct(private readonly ContactService $contacts) {}

    public function store(ContactRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $this->contacts->send($data['name'], $data['email'], $data['subject'], $data['message']);
        } catch (\Throwable) {
            return $this->error('Unable to send your message right now.', 503);
        }

        return $this->success(message: 'Your message has been sent.');
    }
}
