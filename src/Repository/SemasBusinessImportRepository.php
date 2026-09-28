<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use RuntimeException;

final class SemasBusinessImportRepository
{
    private const SOURCE_TYPE = 'SEMAS_PUBLIC_DATA';
    private const SOURCE_URL = 'https://www.data.go.kr/data/15012005/openapi.do';

    /** @var array<string, int> */
    private array $categoryIds = [];

    /** @var array<string, int> */
    private array $serviceIds = [];

    public function __construct(private readonly PDO $connection)
    {
    }

    /** @param array<string, mixed> $record */
    public function upsertCollected(array $record, ?string $referenceMonth, string $now): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO semas_business_imports (
                    external_id, business_name, branch_name,
                    industry_large_code, industry_large_name,
                    industry_medium_code, industry_medium_name,
                    industry_small_code, industry_small_name,
                    standard_industry_code, standard_industry_name,
                    province_code, province_name, district_code, district_name,
                    administrative_dong_code, administrative_dong_name,
                    legal_dong_code, legal_dong_name,
                    lot_number_code, lot_number_address,
                    road_name_code, road_name,
                    building_management_number, building_name, road_address, postal_code,
                    dong_number, floor_number, unit_number,
                    longitude, latitude, data_reference_month,
                    classification_status, classified_at,
                    first_collected_at, last_collected_at, created_at, updated_at
                ) VALUES (
                    :external_id, :business_name, :branch_name,
                    :industry_large_code, :industry_large_name,
                    :industry_medium_code, :industry_medium_name,
                    :industry_small_code, :industry_small_name,
                    :standard_industry_code, :standard_industry_name,
                    :province_code, :province_name, :district_code, :district_name,
                    :administrative_dong_code, :administrative_dong_name,
                    :legal_dong_code, :legal_dong_name,
                    :lot_number_code, :lot_number_address,
                    :road_name_code, :road_name,
                    :building_management_number, :building_name, :road_address, :postal_code,
                    :dong_number, :floor_number, :unit_number,
                    :longitude, :latitude, :data_reference_month,
                    'PENDING', NULL,
                    :first_collected_at, :last_collected_at, :created_at, :updated_at
                )
                ON DUPLICATE KEY UPDATE
                    business_name = VALUES(business_name),
                    branch_name = VALUES(branch_name),
                    industry_large_code = VALUES(industry_large_code),
                    industry_large_name = VALUES(industry_large_name),
                    industry_medium_code = VALUES(industry_medium_code),
                    industry_medium_name = VALUES(industry_medium_name),
                    industry_small_code = VALUES(industry_small_code),
                    industry_small_name = VALUES(industry_small_name),
                    standard_industry_code = VALUES(standard_industry_code),
                    standard_industry_name = VALUES(standard_industry_name),
                    province_code = VALUES(province_code),
                    province_name = VALUES(province_name),
                    district_code = VALUES(district_code),
                    district_name = VALUES(district_name),
                    administrative_dong_code = VALUES(administrative_dong_code),
                    administrative_dong_name = VALUES(administrative_dong_name),
                    legal_dong_code = VALUES(legal_dong_code),
                    legal_dong_name = VALUES(legal_dong_name),
                    lot_number_code = VALUES(lot_number_code),
                    lot_number_address = VALUES(lot_number_address),
                    road_name_code = VALUES(road_name_code),
                    road_name = VALUES(road_name),
                    building_management_number = VALUES(building_management_number),
                    building_name = VALUES(building_name),
                    road_address = VALUES(road_address),
                    postal_code = VALUES(postal_code),
                    dong_number = VALUES(dong_number),
                    floor_number = VALUES(floor_number),
                    unit_number = VALUES(unit_number),
                    longitude = VALUES(longitude),
                    latitude = VALUES(latitude),
                    data_reference_month = VALUES(data_reference_month),
                    classification_status = 'PENDING',
                    classified_at = NULL,
                    last_collected_at = VALUES(last_collected_at),
                    updated_at = VALUES(updated_at)
                SQL,
        );
        $statement->execute([
            ...$record,
            'data_reference_month' => $referenceMonth,
            'first_collected_at' => $now,
            'last_collected_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function findClassificationBatch(int $afterId, int $limit): array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT id, business_name, branch_name, industry_small_code
                FROM semas_business_imports
                WHERE id > :after_id
                ORDER BY id ASC
                LIMIT :limit
                SQL,
        );
        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /** @param list<string> $keywords */
    public function replaceClassification(int $importId, array $keywords, string $now): void
    {
        $delete = $this->connection->prepare(
            'DELETE FROM semas_business_import_keywords WHERE import_id = :import_id',
        );
        $delete->execute(['import_id' => $importId]);

        if ($keywords !== []) {
            $insert = $this->connection->prepare(
                <<<'SQL'
                    INSERT INTO semas_business_import_keywords (import_id, keyword, created_at)
                    VALUES (:import_id, :keyword, :created_at)
                    SQL,
            );

            foreach ($keywords as $keyword) {
                $insert->execute([
                    'import_id' => $importId,
                    'keyword' => $keyword,
                    'created_at' => $now,
                ]);
            }
        }

        $update = $this->connection->prepare(
            <<<'SQL'
                UPDATE semas_business_imports
                SET classification_status = :classification_status,
                    classified_at = :classified_at,
                    updated_at = :updated_at
                WHERE id = :id
                SQL,
        );
        $update->execute([
            'classification_status' => $keywords === [] ? 'UNMATCHED' : 'MATCHED',
            'classified_at' => $now,
            'updated_at' => $now,
            'id' => $importId,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function findMatchedBatch(int $afterId, int $limit): array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT
                    id,
                    business_id,
                    external_id,
                    business_name,
                    branch_name,
                    industry_small_code,
                    industry_small_name,
                    province_code,
                    province_name,
                    district_code,
                    district_name,
                    lot_number_address,
                    building_name,
                    road_address,
                    postal_code,
                    dong_number,
                    floor_number,
                    unit_number,
                    longitude,
                    latitude
                FROM semas_business_imports
                WHERE id > :after_id
                  AND classification_status = :classification_status
                ORDER BY id ASC
                LIMIT :limit
                SQL,
        );
        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->bindValue(':classification_status', 'MATCHED');
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $placeholders = [];
        $parameters = [];

        foreach ($ids as $index => $id) {
            $name = 'import_id_' . $index;
            $placeholders[] = ':' . $name;
            $parameters[$name] = $id;
        }

        $keywordStatement = $this->connection->prepare(sprintf(
            <<<'SQL'
                SELECT import_id, keyword
                FROM semas_business_import_keywords
                WHERE import_id IN (%s)
                ORDER BY import_id ASC, keyword ASC
                SQL,
            implode(', ', $placeholders),
        ));
        $keywordStatement->execute($parameters);
        $keywordsByImportId = [];

        while ($keyword = $keywordStatement->fetch()) {
            $keywordsByImportId[(int) $keyword['import_id']][] = (string) $keyword['keyword'];
        }

        foreach ($rows as &$row) {
            $row['matching_keywords'] = $keywordsByImportId[(int) $row['id']] ?? [];
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, mixed> $candidate
     * @param list<string> $categorySlugs
     * @param list<string> $serviceSlugs
     */
    public function syncBusiness(
        array $candidate,
        array $categorySlugs,
        array $serviceSlugs,
        string $now,
    ): int {
        $provinceId = $this->upsertRegion(
            null,
            $this->regionCode((string) $candidate['province_code']),
            (string) $candidate['province_name'],
            1,
            $now,
        );
        $districtId = $this->upsertRegion(
            $provinceId,
            $this->regionCode((string) $candidate['district_code']),
            (string) $candidate['district_name'],
            2,
            $now,
        );
        $categoryIds = $this->idsForSlugs('categories', $categorySlugs);
        $serviceIds = $this->idsForSlugs('services', $serviceSlugs);
        $businessId = $this->existingBusinessId($candidate['business_id'] ?? null);
        $editable = false;

        if ($businessId === null) {
            $businessId = $this->insertBusiness($candidate, $now);
            $editable = true;
        } else {
            $editable = $this->updateImportedBusiness($businessId, $candidate, $now);
        }

        $this->upsertLocation($businessId, $districtId, $candidate, $editable, $now);
        $this->insertTaxonomy($businessId, $categoryIds, $serviceIds, $now);
        $this->upsertSource($businessId, (string) $candidate['external_id'], $now);

        $statement = $this->connection->prepare(
            <<<'SQL'
                UPDATE semas_business_imports
                SET business_id = :business_id, updated_at = :updated_at
                WHERE id = :id
                SQL,
        );
        $statement->execute([
            'business_id' => $businessId,
            'updated_at' => $now,
            'id' => (int) $candidate['id'],
        ]);

        return $businessId;
    }

    private function upsertRegion(
        ?int $parentId,
        string $code,
        string $name,
        int $level,
        string $now,
    ): int {
        $find = $this->connection->prepare(
            'SELECT id FROM regions WHERE code = :code ORDER BY id ASC LIMIT 1',
        );
        $find->execute(['code' => $code]);
        $id = $find->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        $insert = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO regions (
                    parent_id, code, name, slug, level, sort_order, status, created_at, updated_at
                ) VALUES (
                    :parent_id, :code, :name, :slug, :level, 0, :status, :created_at, :updated_at
                )
                SQL,
        );
        $insert->execute([
            'parent_id' => $parentId,
            'code' => $code,
            'name' => $name,
            'slug' => strtolower(str_replace('KR-', 'kr-', $code)),
            'level' => $level,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /** @param list<string> $slugs @return list<int> */
    private function idsForSlugs(string $table, array $slugs): array
    {
        if (!in_array($table, ['categories', 'services'], true)) {
            throw new RuntimeException('Unsupported taxonomy table.');
        }

        $cache = $table === 'categories' ? $this->categoryIds : $this->serviceIds;
        $missing = array_values(array_filter(
            $slugs,
            static fn (string $slug): bool => !isset($cache[$slug]),
        ));

        if ($missing !== []) {
            $placeholders = [];
            $parameters = ['status' => 'ACTIVE'];

            foreach ($missing as $index => $slug) {
                $name = 'slug_' . $index;
                $placeholders[] = ':' . $name;
                $parameters[$name] = $slug;
            }

            $statement = $this->connection->prepare(sprintf(
                'SELECT id, slug FROM %s WHERE slug IN (%s) AND status = :status',
                $table,
                implode(', ', $placeholders),
            ));
            $statement->execute($parameters);

            while ($row = $statement->fetch()) {
                $cache[(string) $row['slug']] = (int) $row['id'];
            }

            if ($table === 'categories') {
                $this->categoryIds = $cache;
            } else {
                $this->serviceIds = $cache;
            }
        }

        $ids = [];

        foreach ($slugs as $slug) {
            if (!isset($cache[$slug])) {
                throw new RuntimeException(sprintf(
                    'Required reference seed is missing: %s.%s',
                    $table,
                    $slug,
                ));
            }

            $ids[] = $cache[$slug];
        }

        return array_values(array_unique($ids));
    }

    private function existingBusinessId(mixed $businessId): ?int
    {
        if (!is_numeric($businessId) || (int) $businessId < 1) {
            return null;
        }

        $statement = $this->connection->prepare(
            'SELECT id FROM businesses WHERE id = :id LIMIT 1',
        );
        $statement->execute(['id' => (int) $businessId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** @param array<string, mixed> $candidate */
    private function insertBusiness(array $candidate, string $now): int
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO businesses (
                    name, slug, short_description, description, phone,
                    supports_shipping, supports_visit, supports_pickup,
                    status, claimed_user_id, published_at, created_at, updated_at, deleted_at
                ) VALUES (
                    :name, :slug, :short_description, NULL, NULL,
                    0, 1, 0,
                    :status, NULL, :published_at, :created_at, :updated_at, NULL
                )
                SQL,
        );
        $statement->execute([
            'name' => (string) $candidate['business_name'],
            'slug' => $this->businessSlug((string) $candidate['external_id']),
            'short_description' => (string) $candidate['industry_small_name'],
            'status' => 'ACTIVE',
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, mixed> $candidate */
    private function updateImportedBusiness(int $businessId, array $candidate, string $now): bool
    {
        $editableStatement = $this->connection->prepare(
            <<<'SQL'
                SELECT 1
                FROM businesses
                WHERE id = :id
                  AND status IN (:draft_status, :active_status)
                  AND claimed_user_id IS NULL
                  AND deleted_at IS NULL
                LIMIT 1
                SQL,
        );
        $editableStatement->execute([
            'id' => $businessId,
            'draft_status' => 'DRAFT',
            'active_status' => 'ACTIVE',
        ]);

        if ($editableStatement->fetchColumn() === false) {
            return false;
        }

        $statement = $this->connection->prepare(
            <<<'SQL'
                UPDATE businesses
                SET name = :name,
                    short_description = :short_description,
                    status = :active_status,
                    published_at = COALESCE(published_at, :published_at),
                    updated_at = :updated_at
                WHERE id = :id
                  AND status IN (:draft_status, :current_active_status)
                  AND claimed_user_id IS NULL
                  AND deleted_at IS NULL
                SQL,
        );
        $statement->execute([
            'name' => (string) $candidate['business_name'],
            'short_description' => (string) $candidate['industry_small_name'],
            'active_status' => 'ACTIVE',
            'published_at' => $now,
            'updated_at' => $now,
            'id' => $businessId,
            'draft_status' => 'DRAFT',
            'current_active_status' => 'ACTIVE',
        ]);

        return true;
    }

    /** @param array<string, mixed> $candidate */
    private function upsertLocation(
        int $businessId,
        int $regionId,
        array $candidate,
        bool $editable,
        string $now,
    ): void {
        $roadAddress = trim((string) ($candidate['road_address'] ?? ''));
        $lotAddress = trim((string) ($candidate['lot_number_address'] ?? ''));

        if ($roadAddress === '' && $lotAddress === '') {
            return;
        }

        $roadAddress = $roadAddress !== '' ? $roadAddress : $lotAddress;
        $detailParts = array_filter([
            trim((string) ($candidate['building_name'] ?? '')),
            trim((string) ($candidate['dong_number'] ?? '')),
            trim((string) ($candidate['floor_number'] ?? '')),
            trim((string) ($candidate['unit_number'] ?? '')),
        ], static fn (string $value): bool => $value !== '');
        $addressDetail = $detailParts === [] ? null : implode(' ', $detailParts);
        $find = $this->connection->prepare(
            <<<'SQL'
                SELECT id
                FROM business_locations
                WHERE business_id = :business_id
                ORDER BY is_primary DESC, id ASC
                LIMIT 1
                SQL,
        );
        $find->execute(['business_id' => $businessId]);
        $locationId = $find->fetchColumn();

        $parameters = [
            'region_id' => $regionId,
            'postal_code' => $candidate['postal_code'],
            'road_address' => $roadAddress,
            'jibun_address' => $lotAddress === '' ? null : $lotAddress,
            'address_detail' => $addressDetail,
            'latitude' => $candidate['latitude'],
            'longitude' => $candidate['longitude'],
            'updated_at' => $now,
        ];

        if ($locationId !== false) {
            if (!$editable) {
                return;
            }

            $update = $this->connection->prepare(
                <<<'SQL'
                    UPDATE business_locations
                    SET region_id = :region_id,
                        postal_code = :postal_code,
                        road_address = :road_address,
                        jibun_address = :jibun_address,
                        address_detail = :address_detail,
                        latitude = :latitude,
                        longitude = :longitude,
                        updated_at = :updated_at
                    WHERE id = :id
                    SQL,
            );
            $update->execute([...$parameters, 'id' => (int) $locationId]);

            return;
        }

        $insert = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO business_locations (
                    business_id, region_id, postal_code, road_address, jibun_address,
                    address_detail, latitude, longitude, is_primary, created_at, updated_at
                ) VALUES (
                    :business_id, :region_id, :postal_code, :road_address, :jibun_address,
                    :address_detail, :latitude, :longitude, 1, :created_at, :updated_at
                )
                SQL,
        );
        $insert->execute([
            ...$parameters,
            'business_id' => $businessId,
            'created_at' => $now,
        ]);
    }

    /** @param list<int> $categoryIds @param list<int> $serviceIds */
    private function insertTaxonomy(
        int $businessId,
        array $categoryIds,
        array $serviceIds,
        string $now,
    ): void {
        $categoryInsert = $this->connection->prepare(
            <<<'SQL'
                INSERT IGNORE INTO business_categories (
                    business_id, category_id, is_primary, created_at
                ) VALUES (
                    :business_id, :category_id, :is_primary, :created_at
                )
                SQL,
        );
        $offeringInsert = $this->connection->prepare(
            <<<'SQL'
                INSERT IGNORE INTO business_offerings (
                    business_id, category_id, service_id, title, description,
                    price_min, price_max, price_note,
                    duration_min_days, duration_max_days,
                    is_primary, status, created_at, updated_at
                )
                SELECT
                    :business_id, category_services.category_id, category_services.service_id,
                    NULL, NULL, NULL, NULL, NULL, NULL, NULL,
                    :is_primary, :status, :created_at, :updated_at
                FROM category_services
                WHERE category_services.category_id = :category_id
                  AND category_services.service_id = :service_id
                SQL,
        );

        foreach ($categoryIds as $categoryIndex => $categoryId) {
            $categoryInsert->execute([
                'business_id' => $businessId,
                'category_id' => $categoryId,
                'is_primary' => $categoryIndex === 0 ? 1 : 0,
                'created_at' => $now,
            ]);

            foreach ($serviceIds as $serviceIndex => $serviceId) {
                $offeringInsert->execute([
                    'business_id' => $businessId,
                    'category_id' => $categoryId,
                    'service_id' => $serviceId,
                    'is_primary' => $categoryIndex === 0 && $serviceIndex === 0 ? 1 : 0,
                    'status' => 'ACTIVE',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function upsertSource(int $businessId, string $externalId, string $now): void
    {
        $find = $this->connection->prepare(
            <<<'SQL'
                SELECT id
                FROM business_sources
                WHERE business_id = :business_id
                  AND source_type = :source_type
                  AND external_id = :external_id
                ORDER BY id ASC
                LIMIT 1
                SQL,
        );
        $find->execute([
            'business_id' => $businessId,
            'source_type' => self::SOURCE_TYPE,
            'external_id' => $externalId,
        ]);
        $sourceId = $find->fetchColumn();

        if ($sourceId !== false) {
            $update = $this->connection->prepare(
                <<<'SQL'
                    UPDATE business_sources
                    SET source_url = :source_url,
                        collected_at = :collected_at,
                        last_verified_at = :last_verified_at,
                        updated_at = :updated_at
                    WHERE id = :id
                    SQL,
            );
            $update->execute([
                'source_url' => self::SOURCE_URL,
                'collected_at' => $now,
                'last_verified_at' => $now,
                'updated_at' => $now,
                'id' => (int) $sourceId,
            ]);

            return;
        }

        $insert = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO business_sources (
                    business_id, source_type, source_url, external_id,
                    collected_at, last_verified_at, created_at, updated_at
                ) VALUES (
                    :business_id, :source_type, :source_url, :external_id,
                    :collected_at, :last_verified_at, :created_at, :updated_at
                )
                SQL,
        );
        $insert->execute([
            'business_id' => $businessId,
            'source_type' => self::SOURCE_TYPE,
            'source_url' => self::SOURCE_URL,
            'external_id' => $externalId,
            'collected_at' => $now,
            'last_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function regionCode(string $providerCode): string
    {
        return 'KR-' . preg_replace('/[^0-9A-Za-z]/', '', $providerCode);
    }

    private function businessSlug(string $externalId): string
    {
        $identifier = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $externalId));

        return 'semas-' . trim($identifier, '-');
    }
}
