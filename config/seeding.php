<?php

return [
    /*
    | Password given to the admin, manager and staff logins AdminUserSeeder
    | creates. Required outside the local environment; locally it falls back
    | to `password` when unset. Never commit a real value.
    */
    'admin_password' => env('SEED_ADMIN_PASSWORD'),
];
