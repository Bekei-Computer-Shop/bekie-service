<?php

use App\Models\User;

test('a null name does not overwrite the supplied first name', function () {
    $user = new User([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'name' => null,
    ]);

    expect($user->first_name)->toBe('Jane')
        ->and($user->last_name)->toBe('Doe')
        ->and($user->name)->toBe('Jane Doe');
});
