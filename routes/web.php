<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Documentation is served by Scramble at /docs/client and /docs/admin.
// The legacy /api/docs* paths are preserved as 301 redirects so existing
// bookmarks continue to work after the migration.
Route::redirect('/api/docs', '/docs/client', 301);
Route::redirect('/api/admin/docs', '/docs/admin', 301);
Route::redirect('/api/docs/redoc', '/docs/client', 301);
Route::redirect('/api/admin/docs/redoc', '/docs/admin', 301);

// Legacy web admin panel routes have been removed. The admin surface now
// lives entirely under the JSON API at /api/v1/admin/* — see
// routes/api_admin.php. The removed routes referenced controllers in
// App\Http\Controllers\Admin\* which no longer exist (controllers were
// migrated to App\Http\Controllers\Api\Admin\V1\* during Phase B).
// See DEPLOYMENT.md "Route cleanup" for the rationale.
