<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use InvalidArgumentException;

final class AdministrativeRegionRecordNormalizer
{
    private const FIELDS = [
        '행정동번호' => 'source_record_number',
        '개정일자' => 'revision_text',
        '연결번호' => 'link_number',
        '행정동코드' => 'administrative_code',
        '행정동명' => 'name',
        '배경여부' => 'background_flag',
        '최상위행정동코드' => 'top_level_code',
        '부모행정동코드' => 'parent_code',
        '순번' => 'source_sequence',
    ];

    /** @param array<string, mixed> $item @return array<string, string|null> */
    public function normalize(array $item): array
    {
        if (count($item) !== count(self::FIELDS) || array_diff(array_keys($item), array_keys(self::FIELDS)) !== []) {
            throw new InvalidArgumentException('Administrative region source fields changed.');
        }
        $record = [];
        foreach (self::FIELDS as $source => $column) {
            $value = $item[$source];
            if ($value !== null && !is_int($value) && !is_string($value)) {
                throw new InvalidArgumentException('Administrative region field type is invalid.');
            }
            $text = $value === null ? null : (string) $value;
            $maximum = $column === 'name' ? 100 : 30;
            if ($text !== null && (preg_match('//u', $text) !== 1
                || preg_match('/\A.{0,' . $maximum . '}\z/us', $text) !== 1
                // Historical background rows contain multiline explanatory names.
                || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $text) === 1)) {
                throw new InvalidArgumentException('Administrative region field text is invalid.');
            }
            if (in_array($column, ['source_record_number', 'link_number', 'administrative_code', 'top_level_code', 'parent_code', 'source_sequence'], true)
                && $text !== null && $text !== '' && preg_match('/\A[0-9]{1,30}\z/', $text) !== 1) {
                throw new InvalidArgumentException('Administrative region identifier is invalid.');
            }
            $record[$column] = $text;
        }
        $record['record_hash'] = hash('sha256', json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $rawDate = $record['revision_text'];
        $date = is_string($rawDate) ? DateTimeImmutable::createFromFormat('!Y-m-d', $rawDate) : false;
        // Keep non-standard or empty dates in revision_text without inventing a date.
        $record['revision_date'] = $date !== false && $date->format('Y-m-d') === $rawDate ? $rawDate : null;
        return $record;
    }

    /** @param list<array<string, string|null>> $records */
    public function pageHash(array $records): string
    {
        return hash('sha256', implode('', array_column($records, 'record_hash')));
    }
}
