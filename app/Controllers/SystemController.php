<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Http;
use App\Core\PortalAuth;
use App\Core\Support;
use App\Services\ProjectService;
use App\Services\SystemRuntimeService;
use App\Services\ProjectSqlApiService;
use App\Services\ApiKeyService;
use App\Services\SystemAppService;
use App\Services\StorageService;
use Flight;

final class SystemController
{
    public function __construct(
        private PortalAuth $auth,
        private ProjectService $projects,
        private SystemRuntimeService $runtime,
        private ApiKeyService $keys,
        private SystemAppService $systemApps,
        private ProjectSqlApiService $projectSql,
        private StorageService $storage,
    ) {}

    public function register(): void
    {
        Flight::route('GET /sistema/login', function (): void {
            if (PortalAuth::check()) { Flight::redirect('/sistema'); return; }
            $this->render('system-login', ['title' => 'TMPOS en linea'], false);
        });
        Flight::route('POST /sistema/login', function (): void {
            try {
                Csrf::verify($_POST['_csrf'] ?? null);
                if (!$this->auth->attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
                    throw new \RuntimeException('Correo o contrasena incorrectos.');
                }
                Flight::redirect('/sistema');
            } catch (\Throwable $e) {
                $this->render('system-login', ['title' => 'TMPOS en linea', 'error' => $e->getMessage()], false);
            }
        });
        Flight::route('POST /sistema/logout', function (): void {
            Csrf::verify($_POST['_csrf'] ?? null);
            PortalAuth::logout();
            Flight::redirect('/sistema/login');
        });
        Flight::route('GET /sistema', function (): void {
            $this->protected(function (): void {
                $items = $this->auth->projects((string) PortalAuth::user()['uid']);
                if (count($items) === 1) {
                    Flight::redirect('/sistema/' . rawurlencode((string) $items[0]['slug']));
                    return;
                }
                $this->render('system-dashboard', ['title' => 'Mis empresas', 'projects' => $items]);
            });
        });
        Flight::route('GET /sistema/abrir/@project', function ($project): void {
            $this->protected(function () use ($project): void {
                $uid = (string) $project;
                $this->auth->membership((string) PortalAuth::user()['uid'], $uid);
                $project = $this->projects->findActive($uid);
                Flight::redirect('/sistema/' . rawurlencode((string) $project['slug']));
            });
        });
        Flight::route('GET /sistema/@slug/login', function ($slug): void {
            try {
                $project = $this->projectBySlug((string) $slug);
                if ($this->systemUser($project)) { Flight::redirect('/sistema/' . rawurlencode((string) $project['slug'])); return; }
                $this->render('system-project-login', ['title' => $project['name'], 'project' => $project], false);
            } catch (\Throwable $e) {
                http_response_code($e->getCode() === 404 ? 404 : 400);
                $this->render('system-project-login', ['title' => 'Sistema no disponible', 'error' => $e->getMessage()], false);
            }
        });
        Flight::route('POST /sistema/@slug/login', function ($slug): void {
            $project = null;
            try {
                Csrf::verify($_POST['_csrf'] ?? null);
                $project = $this->projectBySlug((string) $slug);
                $pin = trim((string) ($_POST['pin'] ?? ''));
                if (preg_match('/^\d{4}$/D', $pin) !== 1) throw new \InvalidArgumentException('El PIN debe tener 4 digitos.');
                $this->keys->rateLimitPublic('system-login:' . hash('sha256', $project['uid'] . '|' . $pin), 10);
                $result = $this->runtime->authenticate($project, ['mode' => 'pin', 'pin' => $pin]);
                if (empty($result['success']) || empty($result['data'])) throw new \RuntimeException($result['error'] ?? 'PIN incorrecto.');
                session_regenerate_id(true);
                $_SESSION['system_project_users'][$project['uid']] = $this->safeUser((array) $result['data']);
                Flight::redirect('/sistema/' . rawurlencode((string) $project['slug']));
            } catch (\Throwable $e) {
                http_response_code(in_array($e->getCode(), [404, 423, 429], true) ? $e->getCode() : 401);
                $this->render('system-project-login', ['title' => $project['name'] ?? 'TMPOS', 'project' => $project, 'error' => $e->getMessage()], false);
            }
        });
        Flight::route('POST /sistema/@slug/logout', function ($slug): void {
            Csrf::verify($_POST['_csrf'] ?? null);
            $project = $this->projectBySlug((string) $slug);
            unset($_SESSION['system_project_users'][$project['uid']]);
            session_regenerate_id(true);
            Flight::redirect('/sistema/' . rawurlencode((string) $project['slug']) . '/login');
        });
        $serveApp = function ($slug): void {
            try {
                $project = $this->projectBySlug((string) $slug);
                if (!$this->systemUser($project)) { Flight::redirect('/sistema/' . rawurlencode((string) $project['slug']) . '/login'); return; }
                $app = $this->systemApps->find((string) ($project['system_app'] ?? 'default'));
                $file = (string) $app['path'] . DIRECTORY_SEPARATOR . 'index.html';
                if (!is_file($file)) throw new \RuntimeException('La interfaz TMPOS no esta publicada.', 404);
                $html = (string) file_get_contents($file);
                $base = (string) $app['url'];
                if (empty($app['is_default'])) {
                    $assetBase = rtrim($base, '/') . '/';
                    $html = (string) preg_replace_callback(
                        '/\b(src|href)\s*=\s*(["\'])([^"\']+)\2/i',
                        static function (array $match) use ($assetBase): string {
                            $value = trim($match[3]);
                            if (preg_match('#^(?:https?:|data:|blob:|//|\#)#i', $value)) return $match[0];
                            if (str_starts_with($value, '/assets/')) $value = ltrim($value, '/');
                            $relative = ltrim(preg_replace('#^\./#', '', $value) ?? $value, '/');
                            if (!str_starts_with($relative, 'assets/') && preg_match('/\.(?:js|mjs|css|png|jpe?g|gif|webp|svg|ico|avif|woff2?|ttf|otf|webmanifest)(?:\?.*)?$/i', $relative) !== 1) return $match[0];
                            return $match[1] . '=' . $match[2] . $assetBase . $relative . $match[2];
                        },
                        $html
                    );
                    $base = '/sistema/' . rawurlencode((string) $project['slug']) . '/';
                }
                $systemBootstrap = '<base href="' . htmlspecialchars($base, ENT_QUOTES) . '">';
                if (empty($app['is_default'])) {
                    $context = json_encode([
                        'slug' => (string) $project['slug'],
                        'project_uid' => (string) $project['uid'],
                        'project_name' => (string) $project['name'],
                        'user' => $this->systemUser($project),
                        'csrf' => Csrf::token(),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                    $bridgePath = dirname(__DIR__, 2) . '/public/assets/js/system-legacy-bridge.js';
                    $bridgeVersion = is_file($bridgePath) ? (string) filemtime($bridgePath) : '1';
                    $systemBootstrap .= '<script>window.__TMPBASE_SYSTEM_CONTEXT__=' . $context . ';</script>'
                        . '<script src="/assets/js/system-legacy-bridge.js?v=' . rawurlencode($bridgeVersion) . '"></script>';
                }
                if (preg_match('/<base\b[^>]*>/i', $html)) {
                    $html = (string) preg_replace_callback('/<base\b[^>]*>/i', static fn (): string => $systemBootstrap, $html, 1);
                } else {
                    $html = (string) preg_replace_callback('/<head\b([^>]*)>/i', static fn (array $match): string => $match[0] . $systemBootstrap, $html, 1);
                }
                header('Content-Type: text/html; charset=UTF-8');
                header('Cache-Control: no-store, private');
                echo $html;
            } catch (\Throwable $e) {
                http_response_code($e->getCode() === 404 ? 404 : 400);
                echo 'Sistema no encontrado.';
            }
        };
        Flight::route('GET /sistema/@slug', $serveApp);
        Flight::route('GET /sistema/@slug/', $serveApp);
        Flight::route('GET /sistema/@slug/*', $serveApp);
        Flight::route('GET /api/system/@slug/session', function ($slug): void {
            $this->api((string) $slug, function (array $project, array $systemUser): void {
                Flight::json(['success' => true, 'data' => [
                    'user' => $systemUser,
                    'project' => ['uid' => $project['uid'], 'slug' => $project['slug'], 'name' => $project['name']],
                    'role' => $systemUser['rol'] ?? $systemUser['nivel_seguridad'] ?? 'usuario',
                    'csrf' => Csrf::token(),
                ]]);
            });
        });
        Flight::route('POST /api/system/@slug/runtime', function ($slug): void {
            $this->api((string) $slug, function (array $project, array $systemUser): void {
                $input = Http::input();
                Csrf::verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
                $action = trim((string) ($input['action'] ?? ''));
                $payload = is_array($input['data'] ?? null) ? $input['data'] : [];
                if ($action === 'invoke' && ($payload['channel'] ?? '') === 'auth:login') {
                    $credentials = (array) (($payload['args'] ?? [])[0] ?? []);
                    $result = $this->runtime->authenticate($project, $credentials);
                    if (!empty($result['success']) && !empty($result['data'])) {
                        $safe = $this->safeUser((array) $result['data']);
                        $_SESSION['system_project_users'][$project['uid']] = $safe;
                        $result['data'] = $safe;
                    }
                    Flight::json($result);
                    return;
                }
                Flight::json($this->runtime->handle($project, $action, $payload, $systemUser));
            });
        });
        Flight::route('POST /api/system/@slug/storage/upload', function ($slug): void {
            $this->api((string) $slug, function (array $project): void {
                Csrf::verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
                $directory = (string) ($_POST['directory'] ?? $_GET['directory'] ?? '/');
                Flight::json([
                    'data' => $this->storage->apiData(
                        $this->storage->upload($project, $_FILES['file'] ?? [], $directory)
                    ),
                ], 201);
            });
        });
        Flight::route('DELETE /api/system/@slug/storage/@uid', function ($slug, $uid): void {
            $this->api((string) $slug, function (array $project) use ($uid): void {
                Csrf::verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
                $this->storage->delete($project, (string) $uid);
                Flight::json(['message' => 'File deleted.']);
            });
        });
        Flight::route('POST /api/system/@slug/sql', function ($slug): void {
            $this->api((string) $slug, function (array $project): void {
                Csrf::verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
                $result = $this->projectSql->execute($project, Http::input());
                Flight::json(['success' => true, 'data' => $result]);
            });
        });
    }

    private function api(string $slug, callable $callback): void
    {
        header('Cache-Control: no-store, private');
        try {
            $project = $this->projectBySlug($slug);
            $systemUser = $this->systemUser($project);
            if (!$systemUser) throw new \RuntimeException('La sesion expiro.', 401);
            $callback($project, $systemUser);
        } catch (\Throwable $e) {
            $status = in_array($e->getCode(), [401, 403, 404, 409, 422, 423], true) ? $e->getCode() : 400;
            Http::error($e, $status);
        }
    }

    private function projectBySlug(string $slug): array
    {
        return $this->projects->findActiveBySlug(Support::slug($slug));
    }

    private function systemUser(array $project): ?array
    {
        $user = $_SESSION['system_project_users'][$project['uid']] ?? null;
        return is_array($user) && !empty($user['id']) ? $user : null;
    }

    private function safeUser(array $user): array
    {
        unset($user['password'], $user['pin']);
        return $user;
    }

    private function protected(callable $callback): void
    {
        if (!PortalAuth::check()) { Flight::redirect('/sistema/login'); return; }
        try { $callback(); }
        catch (\Throwable $e) { http_response_code(in_array($e->getCode(), [403,404,423], true) ? $e->getCode() : 400); $this->render('system-error', ['title' => 'No disponible', 'error' => $e->getMessage()]); }
    }

    private function render(string $template, array $data, bool $withLayout = true): void
    {
        $views = dirname(__DIR__) . '/Views';
        extract($data, EXTR_SKIP);
        ob_start(); require $views . '/' . $template . '.php'; $content = (string) ob_get_clean();
        if (!$withLayout) { echo $content; return; }
        require $views . '/system-layout.php';
    }
}
