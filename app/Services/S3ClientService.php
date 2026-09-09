<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class S3ClientService
{
    public function __construct(private array $config)
    {
    }

    public function putObject(string $key, string $filePath): bool
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The PHP cURL extension is required to mirror backups to MinIO.');
        }
        $endpoint = (string) $this->config['endpoint'];
        $host = (string) parse_url($endpoint, PHP_URL_HOST);
        if ($host === '' || $host === null) throw new RuntimeException('Invalid MinIO endpoint.');
        $body = file_get_contents($filePath);
        if ($body === false) throw new RuntimeException('Could not read the file to mirror.');

        $bucket = (string) $this->config['bucket'];
        $path = '/' . $bucket . '/' . ltrim($key, '/');
        $url = $endpoint . $path;
        $now = gmdate('Ymd\THis\Z');
        $date = substr($now, 0, 8);
        $region = (string) $this->config['region'];
        $payloadHash = hash('sha256', $body);

        $headers = [
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $now,
        ];
        ksort($headers);
        $signedHeaders = implode(';', array_keys($headers));
        $canonicalHeaders = '';
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= $name . ':' . $value . "\n";
        }
        // SigV4 canonical request: method, path, query, headers and payload hash bind the signature to this exact request.
        $canonicalRequest = implode("\n", [
            'PUT', $this->uriEncode($path), '', $canonicalHeaders, $signedHeaders, $payloadHash,
        ]);

        $scope = $date . '/' . $region . '/s3/aws4_request';
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256', $now, $scope, hash('sha256', $canonicalRequest),
        ]);

        // Derive the signing key: kDate -> kRegion -> kService -> kSigning, each an HMAC over the previous.
        $kDate = hash_hmac('sha256', $date, 'AWS4' . (string) $this->config['secret_key'], true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = 'AWS4-HMAC-SHA256 Credential=' . $this->config['access_key'] . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'x-amz-content-sha256: ' . $payloadHash,
                'x-amz-date: ' . $now,
                'Authorization: ' . $authorization,
            ],
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return $response !== false && $status >= 200 && $status < 300;
    }

    private function uriEncode(string $path): string
    {
        return implode('/', array_map(
            static fn (string $segment): string => str_replace('%2F', '/', rawurlencode($segment)),
            explode('/', $path)
        ));
    }
}
