<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\PublicController;
use App\Controllers\SupportController;
use App\Core\Router;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (str_starts_with($path, '/media/')) {
    $file = app_path('storage' . $path);
    $realFile = realpath($file);
    $mediaRoot = realpath(app_path('storage/media'));
    if (!$realFile || !$mediaRoot || !str_starts_with($realFile, $mediaRoot . DIRECTORY_SEPARATOR)) {
        \App\Core\Response::abort(404, 'Media file not found');
    }

    header('Content-Type: ' . (mime_content_type($realFile) ?: 'application/octet-stream'));
    header('Cache-Control: public, max-age=31536000, immutable');
    header('X-Content-Type-Options: nosniff');
    readfile($realFile);
    exit;
}

$installed = is_file(app_path('.env')) && \App\Core\Env::get('APP_INSTALLED', '0') === '1';
if (!$installed && !str_contains($_SERVER['REQUEST_URI'] ?? '/', 'installer.php')) {
    \App\Core\Response::redirect('/installer.php');
}

$router = new Router();

$router->get('/robots.txt', [PublicController::class, 'robots']);
$router->get('/sitemap.xml', [PublicController::class, 'sitemap']);
$router->get('/feed', [PublicController::class, 'feed']);
$router->get('/indexnow-key.txt', [PublicController::class, 'indexNowKey']);
$router->get('/', [PublicController::class, 'home']);
$router->get('/post/{slug}', [PublicController::class, 'post']);
$router->get('/category/{slug}', [PublicController::class, 'category']);
$router->get('/support/contact', [SupportController::class, 'showContact']);
$router->post('/support/contact', [SupportController::class, 'submitContact']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login/password', [AuthController::class, 'loginPassword']);
$router->get('/login/mfa', [AuthController::class, 'showMfa']);
$router->post('/login/mfa', [AuthController::class, 'verifyMfa']);
$router->post('/login/passkey/options', [AuthController::class, 'passkeyOptions']);
$router->post('/login/passkey/verify', [AuthController::class, 'passkeyVerify']);
$router->get('/login/recovery', [AuthController::class, 'showRecovery']);
$router->post('/login/recovery', [AuthController::class, 'recoveryVerify']);
$router->get('/login/session/revoke/{token}', [AuthController::class, 'revokeSession']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/admin', [AdminController::class, 'dashboard']);
$router->get('/admin/analytics', [AdminController::class, 'analytics']);
$router->get('/admin/posts', [AdminController::class, 'posts']);
$router->get('/admin/posts/create', [AdminController::class, 'createPost']);
$router->post('/admin/posts/create', [AdminController::class, 'storePost']);
$router->get('/admin/posts/{id}/edit', [AdminController::class, 'editPost']);
$router->post('/admin/posts/{id}/edit', [AdminController::class, 'updatePost']);
$router->post('/admin/posts/{id}/delete', [AdminController::class, 'deletePost']);
$router->get('/admin/categories', [AdminController::class, 'categories']);
$router->post('/admin/categories', [AdminController::class, 'storeCategory']);
$router->post('/admin/categories/{id}/edit', [AdminController::class, 'updateCategory']);
$router->post('/admin/categories/{id}/delete', [AdminController::class, 'deleteCategory']);
$router->get('/admin/media', [AdminController::class, 'media']);
$router->post('/admin/media', [AdminController::class, 'uploadMedia']);
$router->post('/admin/media/{id}/delete', [AdminController::class, 'deleteMedia']);
$router->get('/admin/media/picker', [AdminController::class, 'mediaPicker']);
$router->post('/admin/media/picker/upload', [AdminController::class, 'uploadMediaFromPicker']);
$router->get('/admin/imports', [AdminController::class, 'imports']);
$router->post('/admin/imports', [AdminController::class, 'runImport']);
$router->get('/admin/users', [AdminController::class, 'users']);
$router->post('/admin/users', [AdminController::class, 'storeUser']);
$router->post('/admin/users/{id}/edit', [AdminController::class, 'updateUser']);
$router->post('/admin/users/{id}/unlock', [AdminController::class, 'unlockUser']);
$router->get('/admin/preferences', [AdminController::class, 'preferences']);
$router->post('/admin/preferences', [AdminController::class, 'updatePreferences']);
$router->get('/admin/sharing', [AdminController::class, 'sharing']);
$router->post('/admin/sharing', [AdminController::class, 'updateSharing']);
$router->get('/admin/security', [AdminController::class, 'security']);
$router->get('/admin/security/bootstrap', [AdminController::class, 'securityBootstrap']);
$router->post('/admin/security/passkeys/options', [AdminController::class, 'registrationOptions']);
$router->post('/admin/security/passkeys/register', [AdminController::class, 'registerPasskey']);
$router->post('/admin/security/passkeys/delete', [AdminController::class, 'deletePasskey']);
$router->post('/admin/security/totp/begin', [AdminController::class, 'beginTotp']);
$router->post('/admin/security/totp/verify', [AdminController::class, 'verifyTotp']);
$router->post('/admin/security/totp/disable', [AdminController::class, 'disableTotp']);
$router->post('/admin/security/recovery/regenerate', [AdminController::class, 'regenerateRecoveryCodes']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
