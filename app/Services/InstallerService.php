<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use RuntimeException;

final class InstallerService
{
    public function __construct(private array $config, private Auth $auth)
    {
    }

    public function checks(): array
    {
        $storage = $this->config['storage'];
        $root = $this->config['root'];

        return [
            [
                'label' => 'PHP 8.1 or newer',
                'passed' => version_compare(PHP_VERSION, '8.1.0', '>='),
                'value' => PHP_VERSION,
                'required' => true,
            ],
            [
                'label' => 'PDO extension',
                'passed' => extension_loaded('pdo'),
                'value' => extension_loaded('pdo') ? 'Enabled' : 'Missing',
                'required' => true,
            ],
            [
                'label' => 'PDO SQLite extension',
                'passed' => extension_loaded('pdo_sqlite'),
                'value' => extension_loaded('pdo_sqlite') ? 'Enabled' : 'Missing',
                'required' => true,
            ],
            [
                'label' => 'PDO MySQL extension',
                'passed' => extension_loaded('pdo_mysql'),
                'value' => extension_loaded('pdo_mysql') ? 'Enabled' : 'Missing (required only for MySQL Sync)',
                'required' => false,
            ],
            [
                'label' => 'Fileinfo extension',
                'passed' => extension_loaded('fileinfo'),
                'value' => extension_loaded('fileinfo') ? 'Enabled' : 'Missing',
                'required' => true,
            ],
            [
                'label' => 'Storage is writable',
                'passed' => is_dir($storage) && is_writable($storage),
                'value' => $storage,
                'required' => true,
            ],
            [
                'label' => 'Environment file can be created',
                'passed' => is_file($root . '/.env')
                    ? is_writable($root . '/.env')
                    : is_writable($root),
                'value' => $root . DIRECTORY_SEPARATOR . '.env',
                'required' => true,
            ],
            [
                'label' => 'Apache rewrite rules',
                'passed' => is_file($root . '/.htaccess'),
                'value' => is_file($root . '/.htaccess') ? 'Found' : 'Not found',
                'required' => false,
            ],
        ];
    }

    public function ready(): bool
    {
        foreach ($this->checks() as $check) {
            if ($check['required'] && !$check['passed']) {
                return false;
            }
        }
        return true;
    }

    public function installed(): bool
    {
        return $this->auth->installed() || is_file($this->lockPath());
    }

    public function install(array $input): void
    {
        if ($this->installed()) {
            throw new RuntimeException('TMPBase is already installed.');
        }
        if (!$this->ready()) {
            throw new RuntimeException('Fix the required server checks before installing.');
        }

        $appName = trim((string) ($input['app_name'] ?? 'TMPBase'));
        $appUrl = rtrim(trim((string) ($input['app_url'] ?? '')), '/');
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $confirmation = (string) ($input['password_confirmation'] ?? '');

        if ($appName === '' || strlen($appName) > 80 || preg_match('/[\r\n=]/', $appName)) {
            throw new \InvalidArgumentException('Enter a valid application name.');
        }
        if (!filter_var($appUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($appUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Enter a valid HTTP or HTTPS application URL.');
        }
        $urlParts = parse_url($appUrl);
        if (
            !empty($urlParts['user'])
            || !empty($urlParts['pass'])
            || !empty($urlParts['query'])
            || !empty($urlParts['fragment'])
            || !in_array($urlParts['path'] ?? '', ['', '/'], true)
        ) {
            throw new \InvalidArgumentException('The application URL must be the domain root without credentials, query parameters or a subdirectory.');
        }
        if ($name === '' || strlen($name) > 100) {
            throw new \InvalidArgumentException('Enter the administrator name.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Enter a valid administrator email.');
        }
        if (strlen($password) < 10) {
            throw new \InvalidArgumentException('The administrator password must contain at least 10 characters.');
        }
        if ($password !== $confirmation) {
            throw new \InvalidArgumentException('Password confirmation does not match.');
        }

        $this->writeEnvironment($appName, $appUrl);
        $this->auth->install($name, $email, $password);

        $lock = json_encode([
            'installed_at' => gmdate('c'),
            'app_url' => $appUrl,
            'php_version' => PHP_VERSION,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (file_put_contents($this->lockPath(), $lock, LOCK_EX) === false) {
            throw new RuntimeException('The administrator was created, but the installation lock could not be written.');
        }
    }

    private function writeEnvironment(string $appName, string $appUrl): void
    {
        $secure = parse_url($appUrl, PHP_URL_SCHEME) === 'https' ? 'true' : 'false';
        $contents = implode("\n", [
            'APP_NAME=' . $appName,
            'APP_URL=' . $appUrl,
            'APP_ENV=production',
            'APP_DEBUG=false',
            'SESSION_SECURE=' . $secure,
            'MAX_UPLOAD_MB=10',
            'RATE_LIMIT_PER_MINUTE=120',
            '',
        ]);

        $path = $this->config['root'] . DIRECTORY_SEPARATOR . '.env';
        $temporary = $path . '.tmp.' . bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Could not create the .env file. Check root directory permissions.');
        }
    }

    private function lockPath(): string
    {
        return $this->config['storage'] . DIRECTORY_SEPARATOR . 'installed.lock';
    }
}
