<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class CredentialCipher
{
    public function __construct(private string $storage)
    {
    }

    public function encrypt(string $plain): string
    {
        $key = $this->key();
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return 's1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
        }
        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('Sodium or OpenSSL is required to encrypt mail credentials.');
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) throw new RuntimeException('Could not encrypt mail credentials.');
        return 'o1:' . base64_encode($iv . $tag . $cipher);
    }

    public function decrypt(string $encrypted): string
    {
        [$version, $payload] = array_pad(explode(':', $encrypted, 2), 2, '');
        $data = base64_decode($payload, true);
        if ($data === false) throw new RuntimeException('Stored mail credentials are invalid.');
        $key = $this->key(false);
        if ($version === 's1' && function_exists('sodium_crypto_secretbox_open')) {
            $size = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            $plain = sodium_crypto_secretbox_open(substr($data, $size), substr($data, 0, $size), $key);
            if ($plain !== false) return $plain;
        }
        if ($version === 'o1' && function_exists('openssl_decrypt')) {
            $plain = openssl_decrypt(substr($data, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($data, 0, 12), substr($data, 12, 16));
            if ($plain !== false) return $plain;
        }
        throw new RuntimeException('Could not decrypt the stored mail password.');
    }

    private function key(bool $create = true): string
    {
        $path = $this->storage . DIRECTORY_SEPARATOR . '.credential-key';
        if (!is_file($path)) {
            if (!$create) throw new RuntimeException('The credential encryption key is missing.');
            $encoded = base64_encode(random_bytes(32));
            $handle = @fopen($path, 'x');
            if (is_resource($handle)) {
                $written = fwrite($handle, $encoded);
                fclose($handle);
                if ($written !== strlen($encoded)) {
                    @unlink($path);
                    throw new RuntimeException('Could not create the credential encryption key.');
                }
                @chmod($path, 0600);
            } elseif (!is_file($path)) {
                throw new RuntimeException('Could not create the credential encryption key.');
            }
        }
        $key = base64_decode(trim((string) file_get_contents($path)), true);
        if ($key === false || strlen($key) !== 32) throw new RuntimeException('The credential encryption key is invalid.');
        return $key;
    }
}
