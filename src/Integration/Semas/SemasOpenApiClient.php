<?php

declare(strict_types=1);

namespace App\Integration\Semas;

use Closure;
use JsonException;
use RuntimeException;

final class SemasOpenApiClient
{
    private const SUCCESS_CODES = ['', '0', '00', '0000'];

    /** @var null|Closure(string, int): string */
    private readonly ?Closure $transport;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $serviceKey,
        private readonly int $pageSize = 1000,
        private readonly int $timeoutSeconds = 30,
        ?callable $transport = null,
    ) {
        if ($this->endpoint === '' || !str_starts_with($this->endpoint, 'https://')) {
            throw new RuntimeException('SEMAS API endpoint must be an HTTPS URL.');
        }

        if ($this->pageSize < 1 || $this->pageSize > 1000) {
            throw new RuntimeException('SEMAS API page size must be between 1 and 1000.');
        }

        if ($this->timeoutSeconds < 1 || $this->timeoutSeconds > 120) {
            throw new RuntimeException('SEMAS API timeout must be between 1 and 120 seconds.');
        }

        $this->transport = $transport === null ? null : Closure::fromCallable($transport);
    }

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     totalCount: int,
     *     dataReferenceMonth: ?string
     * }
     */
    public function fetchIndustryPage(string $smallIndustryCode, int $page): array
    {
        if (preg_match('/^S206(?:01|02|03|99)$/', $smallIndustryCode) !== 1) {
            throw new RuntimeException('Unsupported SEMAS small-industry code.');
        }

        if ($page < 1) {
            throw new RuntimeException('SEMAS API page must be at least 1.');
        }

        if ($this->serviceKey === '') {
            throw new RuntimeException(
                'SEMAS API service key is missing. Configure DATA_GO_KR_SERVICE_KEY '
                . 'or config/public_data.local.php.',
            );
        }

        $query = http_build_query(
            [
                'serviceKey' => $this->serviceKey,
                'pageNo' => $page,
                'numOfRows' => $this->pageSize,
                'divId' => 'indsSclsCd',
                'key' => $smallIndustryCode,
                'type' => 'json',
            ],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );
        $url = $this->endpoint . '?' . $query;
        $payload = $this->transport !== null
            ? ($this->transport)($url, $this->timeoutSeconds)
            : $this->request($url);

        return $this->decode($payload);
    }

    private function request(string $url): string
    {
        if (function_exists('curl_init')) {
            return $this->requestWithCurl($url);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\nUser-Agent: reform-platform-semas-importer/1.0\r\n",
                'ignore_errors' => true,
                'timeout' => $this->timeoutSeconds,
            ],
        ]);
        $payload = @file_get_contents($url, false, $context);

        if (!is_string($payload)) {
            throw new RuntimeException('Unable to connect to the SEMAS API.');
        }

        $status = $this->streamResponseStatus($http_response_header ?? []);

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('SEMAS API returned HTTP %d.', $status));
        }

        return $payload;
    }

    private function requestWithCurl(string $url): string
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException('Unable to initialize the SEMAS API HTTP client.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeoutSeconds),
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: reform-platform-semas-importer/1.0',
            ],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $payload = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if (!is_string($payload)) {
            throw new RuntimeException(sprintf('Unable to connect to the SEMAS API: %s', $error));
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('SEMAS API returned HTTP %d.', $status));
        }

        return $payload;
    }

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     totalCount: int,
     *     dataReferenceMonth: ?string
     * }
     */
    private function decode(string $payload): array
    {
        try {
            $document = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('SEMAS API returned invalid JSON.', previous: $exception);
        }

        if (!is_array($document)) {
            throw new RuntimeException('SEMAS API response root is invalid.');
        }

        $root = isset($document['response']) && is_array($document['response'])
            ? $document['response']
            : $document;
        $header = isset($root['header']) && is_array($root['header']) ? $root['header'] : [];
        $body = isset($root['body']) && is_array($root['body']) ? $root['body'] : [];
        $resultCode = trim((string) ($header['resultCode'] ?? ''));

        if (!in_array($resultCode, self::SUCCESS_CODES, true)) {
            $message = trim((string) ($header['resultMsg'] ?? $header['resultMessage'] ?? 'Unknown error'));
            throw new RuntimeException(sprintf('SEMAS API error %s: %s', $resultCode, $message));
        }

        $items = $body['items'] ?? [];

        if (is_array($items) && array_key_exists('item', $items)) {
            $items = $items['item'];
        }

        if ($items === null || $items === '') {
            $items = [];
        }

        if (!is_array($items)) {
            throw new RuntimeException('SEMAS API items payload is invalid.');
        }

        if ($items !== [] && !array_is_list($items)) {
            $columnItems = $this->transposeColumnItems($items);
            $items = $columnItems ?? [$items];
        }

        $columns = $this->responseColumns($header['columns'] ?? []);
        $normalizedItems = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (array_is_list($item)) {
                if ($columns === [] || count($item) !== count($columns)) {
                    throw new RuntimeException('SEMAS API row does not match its column definition.');
                }

                $item = array_combine($columns, $item);
            }

            if (is_array($item)) {
                $normalizedItems[] = $item;
            }
        }

        $totalCount = filter_var($body['totalCount'] ?? count($normalizedItems), FILTER_VALIDATE_INT);

        if ($totalCount === false || $totalCount < 0) {
            throw new RuntimeException('SEMAS API totalCount is invalid.');
        }

        $referenceMonth = trim((string) ($header['stdrYm'] ?? $body['stdrYm'] ?? ''));

        return [
            'items' => $normalizedItems,
            'totalCount' => $totalCount,
            'dataReferenceMonth' => preg_match('/^\d{6}$/', $referenceMonth) === 1
                ? $referenceMonth
                : null,
        ];
    }

    /** @return list<string> */
    private function responseColumns(mixed $columns): array
    {
        if (is_string($columns)) {
            $columns = preg_split('/\s*,\s*/u', trim($columns));
        }

        if (!is_array($columns)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $column): string => trim((string) $column), $columns),
            static fn (string $column): bool => $column !== '',
        ));
    }

    /**
     * Some versions of this API return an object of column arrays instead of an array of rows.
     *
     * @param array<mixed> $items
     * @return null|list<array<string, mixed>>
     */
    private function transposeColumnItems(array $items): ?array
    {
        if ($items === []) {
            return [];
        }

        $rowCount = null;

        foreach ($items as $values) {
            if (!is_array($values) || !array_is_list($values)) {
                return null;
            }

            $rowCount ??= count($values);

            if (count($values) !== $rowCount) {
                throw new RuntimeException('SEMAS API column arrays have inconsistent lengths.');
            }
        }

        $rows = [];

        for ($index = 0; $index < (int) $rowCount; ++$index) {
            $row = [];

            foreach ($items as $column => $values) {
                $row[(string) $column] = $values[$index];
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /** @param list<string> $headers */
    private function streamResponseStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }
}
