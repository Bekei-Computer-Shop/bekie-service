<?php

//
// Vercel serverless entrypoint.
//
// All requests to the application are handled by this file.
//

// Let Vercel handle static files.
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|ico|svg|woff|woff2|ttf|eot)$/', $_SERVER['REQUEST_URI'])) {
    return false;
}

// Load the real Laravel entrypoint.
require_once __DIR__ . '/../public/index.php';
