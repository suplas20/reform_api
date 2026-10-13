<?php

declare(strict_types=1);

namespace App\Integration\AdministrativeRegions;

use Closure;
use RuntimeException;
use Throwable;

final class OdcloudAdministrativeRegionClient implements AdministrativeRegionSource
{
    private readonly Closure $transport;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $path,
        private readonly string $serviceKey,
        private readonly int $pageSize = 1000,
        private readonly int $timeoutSeconds = 30,
        ?Closure $transport = null,
    ) {
        if ($baseUrl !== 'https://api.odcloud.kr/api'
            || preg_match('/\A\/15136373\/v1\/uddi:[a-f0-9-]{36}\z/', $path) !== 1
            || $pageSize < 1 || $pageSize > 1000 || $timeoutSeconds < 1 || $timeoutSeconds > 30) {
            throw new RuntimeException('Administrative region API configuration is invalid.');
        }
        $this->transport = $transport ?? $this->request(...);
    }

    public function fetchPage(int $page): array
    {
        if ($page < 1 || $page > 10000) {
            throw new RuntimeException('Administrative region page is outside the supported range.');
        }
        if ($this->serviceKey === '' || strlen($this->serviceKey) > 4096 || preg_match('/[\s\x00-\x1f\x7f]/', $this->serviceKey) === 1) {
            throw new RuntimeException('Administrative region API service key is missing or invalid.');
        }
        $url = $this->baseUrl . $this->path . '?' . http_build_query([
            'page' => $page, 'perPage' => $this->pageSize, 'returnType' => 'JSON',
        ], '', '&', PHP_QUERY_RFC3986);
        $response = null;
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            try {
                $response = ($this->transport)($url, [
                    'Authorization: Infuser ' . $this->serviceKey,
                    'Accept: application/json',
                ]);
            } catch (Throwable) {
                // Never retain transport errors that may include headers or credentials.
                $response = ['status' => 0, 'body' => ''];
            }
            $status = $response['status'];
            if ($status !== 0 && $status !== 429 && $status < 500) {
                break;
            }
            if ($attempt < 2) {
                usleep(250000 * ($attempt + 1));
            }
        }
        if ($response['status'] !== 200) {
            throw new RuntimeException(sprintf('Administrative region API returned HTTP %d; collection can be resumed.', $response['status']));
        }
        try {
            $document = json_decode($response['body'], true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (Throwable) {
            throw new RuntimeException('Administrative region API returned invalid JSON.');
        }
        if (!is_array($document) || !is_int($document['totalCount'] ?? null)
            || $document['totalCount'] < 0 || $document['totalCount'] > 10000000
            || ($document['matchCount'] ?? null) !== $document['totalCount']
            || ($document['page'] ?? null) !== $page || ($document['perPage'] ?? null) !== $this->pageSize
            || !is_array($document['data'] ?? null) || !array_is_list($document['data'])
            || ($document['currentCount'] ?? null) !== count($document['data'])) {
            throw new RuntimeException('Administrative region API pagination metadata is invalid.');
        }
        $expectedCount = min($this->pageSize, max(0, $document['totalCount'] - ($page - 1) * $this->pageSize));
        if (count($document['data']) !== $expectedCount) {
            throw new RuntimeException('Administrative region API returned an incomplete page.');
        }
        foreach ($document['data'] as $item) {
            if (!is_array($item) || array_is_list($item)) {
                throw new RuntimeException('Administrative region API row is invalid.');
            }
        }
        return ['items' => $document['data'], 'totalCount' => $document['totalCount']];
    }

    /** @param list<string> $headers @return array{status: int, body: string} */
    private function request(string $url, array $headers): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET', 'header' => implode("\r\n", $headers),
                'timeout' => $this->timeoutSeconds, 'ignore_errors' => true, 'follow_location' => 0,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $stream = @fopen($url, 'rb', false, $context);
        if ($stream === false) {
            throw new RuntimeException('Unable to connect to the administrative region API.');
        }
        try {
            $body = stream_get_contents($stream, 16777217);
            $metadata = stream_get_meta_data($stream);
            $statusLine = $metadata['wrapper_data'][0] ?? '';
            if (!is_string($body) || strlen($body) > 16777216 || ($metadata['timed_out'] ?? false)
                || preg_match('/\AHTTP\/\S+\s+(\d{3})/', $statusLine, $matches) !== 1) {
                throw new RuntimeException('Administrative region response exceeded the transport limits.');
            }
            return ['status' => (int) $matches[1], 'body' => $body];
        } finally {
            fclose($stream);
        }
    }
}
