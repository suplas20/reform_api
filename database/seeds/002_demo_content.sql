SET @seed_now = UTC_TIMESTAMP();

INSERT INTO businesses
    (name, slug, short_description, description, phone, supports_shipping, supports_visit,
     supports_pickup, status, published_at, created_at, updated_at)
VALUES
    ('[데모] 브라운 리페어', 'demo-brown-repair', '가죽 가방 수선·복원·염색 전문 데모 업체',
     'API와 앱 화면 확인을 위한 개발용 데모 업체입니다. 실제 영업 업체가 아닙니다.', NULL,
     1, 1, 1, 'ACTIVE', @seed_now, @seed_now, @seed_now),
    ('[데모] 솔 리페어 랩', 'demo-sole-repair-lab', '구두와 운동화 밑창·굽 수선 전문 데모 업체',
     'API와 앱 화면 확인을 위한 개발용 데모 업체입니다. 실제 영업 업체가 아닙니다.', NULL,
     1, 1, 0, 'ACTIVE', @seed_now, @seed_now, @seed_now),
    ('[데모] 리폼 아틀리에', 'demo-reform-atelier', '의류 사이즈·기장 수선과 커스텀 리폼 데모 업체',
     'API와 앱 화면 확인을 위한 개발용 데모 업체입니다. 실제 영업 업체가 아닙니다.', NULL,
     0, 1, 1, 'ACTIVE', @seed_now, @seed_now, @seed_now)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), short_description = VALUES(short_description), description = VALUES(description),
    supports_shipping = VALUES(supports_shipping), supports_visit = VALUES(supports_visit),
    supports_pickup = VALUES(supports_pickup), status = VALUES(status),
    published_at = VALUES(published_at), updated_at = VALUES(updated_at);

INSERT INTO business_locations
    (business_id, region_id, postal_code, road_address, jibun_address, address_detail,
     latitude, longitude, is_primary, created_at, updated_at)
SELECT business.id, region.id, seed.postal_code, seed.road_address, NULL, seed.address_detail,
    seed.latitude, seed.longitude, 1, @seed_now, @seed_now
FROM (
    SELECT 'demo-brown-repair' business_slug, 'KR-11680' region_code, '06000' postal_code,
        '서울 강남구 데모로 10' road_address, '1층 (개발용 주소)' address_detail,
        37.5172000 latitude, 127.0473000 longitude
    UNION ALL SELECT 'demo-sole-repair-lab', 'KR-11440', '04000',
        '서울 마포구 샘플로 20', '2층 (개발용 주소)', 37.5663000, 126.9019000
    UNION ALL SELECT 'demo-reform-atelier', 'KR-11200', '04700',
        '서울 성동구 테스트로 30', '3층 (개발용 주소)', 37.5633000, 127.0369000
) seed
INNER JOIN businesses business ON business.slug = seed.business_slug
INNER JOIN regions region ON region.code = seed.region_code
LEFT JOIN business_locations existing
    ON existing.business_id = business.id AND existing.road_address = seed.road_address
WHERE existing.id IS NULL;

INSERT IGNORE INTO business_categories (business_id, category_id, is_primary, created_at)
SELECT business.id, category.id, 1, @seed_now
FROM (
    SELECT 'demo-brown-repair' business_slug, 'bag' category_slug
    UNION ALL SELECT 'demo-sole-repair-lab', 'shoes'
    UNION ALL SELECT 'demo-reform-atelier', 'clothing'
) mapping
INNER JOIN businesses business ON business.slug = mapping.business_slug
INNER JOIN categories category ON category.slug = mapping.category_slug;

INSERT INTO business_offerings
    (business_id, category_id, service_id, title, description, price_min, price_max,
     price_note, duration_min_days, duration_max_days, is_primary, status, created_at, updated_at)
SELECT business.id, category.id, service.id, mapping.title, mapping.description,
    mapping.price_min, mapping.price_max, mapping.price_note,
    mapping.duration_min_days, mapping.duration_max_days, mapping.is_primary,
    'ACTIVE', @seed_now, @seed_now
FROM (
    SELECT 'demo-brown-repair' business_slug, 'bag' category_slug, 'restoration' service_slug,
        '가죽 가방 복원' title, '마모와 오염 상태를 확인한 뒤 가죽 표면을 복원합니다.' description,
        80000 price_min, 250000 price_max, '손상 범위에 따라 달라질 수 있습니다.' price_note,
        5 duration_min_days, 14 duration_max_days, 1 is_primary
    UNION ALL SELECT 'demo-brown-repair', 'bag', 'dyeing', '가방 전체 염색',
        '가죽 상태에 맞춰 기존 색을 복원하거나 색상을 변경합니다.', 120000, 350000,
        '소재와 색상에 따라 상담이 필요합니다.', 7, 21, 0
    UNION ALL SELECT 'demo-brown-repair', 'bag', 'handle-replacement', '가방 손잡이 교체',
        '기존 형태와 유사한 가죽 손잡이를 제작해 교체합니다.', 60000, 180000,
        '부자재 비용이 추가될 수 있습니다.', 3, 10, 0
    UNION ALL SELECT 'demo-sole-repair-lab', 'shoes', 'sole-replacement', '구두·운동화 밑창 교체',
        '마모된 밑창을 용도에 맞는 새 밑창으로 교체합니다.', 40000, 120000,
        '밑창 종류에 따라 비용이 달라집니다.', 2, 7, 1
    UNION ALL SELECT 'demo-sole-repair-lab', 'shoes', 'heel-repair', '구두 굽 수선',
        '닳거나 파손된 굽과 굽창을 수선합니다.', 20000, 70000,
        '굽 전체 교체는 별도 상담이 필요합니다.', 1, 5, 0
    UNION ALL SELECT 'demo-sole-repair-lab', 'shoes', 'cleaning', '신발 전문 클리닝',
        '소재별 세척과 오염 제거를 진행합니다.', 25000, 80000,
        '이염 위험이 있는 소재는 작업 전 안내합니다.', 3, 7, 0
    UNION ALL SELECT 'demo-reform-atelier', 'clothing', 'size-alteration', '의류 사이즈 수선',
        '체형과 디자인을 고려해 품과 실루엣을 조정합니다.', 30000, 150000,
        '안감과 디테일에 따라 비용이 달라집니다.', 2, 10, 1
    UNION ALL SELECT 'demo-reform-atelier', 'clothing', 'hemming', '기장 수선',
        '바지, 치마, 소매의 기장을 자연스럽게 조정합니다.', 10000, 60000,
        '원단과 마감 방식에 따라 비용이 달라집니다.', 1, 5, 0
    UNION ALL SELECT 'demo-reform-atelier', 'clothing', 'custom-remodeling', '의류 커스텀 리폼',
        '기존 의류의 형태와 용도를 새 디자인으로 변경합니다.', 80000, 400000,
        '디자인 상담 후 견적을 확정합니다.', 7, 30, 0
) mapping
INNER JOIN businesses business ON business.slug = mapping.business_slug
INNER JOIN categories category ON category.slug = mapping.category_slug
INNER JOIN services service ON service.slug = mapping.service_slug
ON DUPLICATE KEY UPDATE
    title = VALUES(title), description = VALUES(description), price_min = VALUES(price_min),
    price_max = VALUES(price_max), price_note = VALUES(price_note),
    duration_min_days = VALUES(duration_min_days), duration_max_days = VALUES(duration_max_days),
    is_primary = VALUES(is_primary), status = VALUES(status), updated_at = VALUES(updated_at);

INSERT IGNORE INTO business_brands (business_id, brand_id, created_at)
SELECT business.id, brand.id, @seed_now
FROM (
    SELECT 'demo-brown-repair' business_slug, 'chanel' brand_slug
    UNION ALL SELECT 'demo-brown-repair', 'louis-vuitton'
    UNION ALL SELECT 'demo-brown-repair', 'gucci'
    UNION ALL SELECT 'demo-brown-repair', 'hermes'
    UNION ALL SELECT 'demo-sole-repair-lab', 'nike'
    UNION ALL SELECT 'demo-sole-repair-lab', 'adidas'
    UNION ALL SELECT 'demo-sole-repair-lab', 'new-balance'
    UNION ALL SELECT 'demo-sole-repair-lab', 'dr-martens'
    UNION ALL SELECT 'demo-reform-atelier', 'burberry'
    UNION ALL SELECT 'demo-reform-atelier', 'dior'
) mapping
INNER JOIN businesses business ON business.slug = mapping.business_slug
INNER JOIN brands brand ON brand.slug = mapping.brand_slug;

INSERT INTO business_hours
    (business_id, day_of_week, sequence, open_time, close_time, is_closed, created_at, updated_at)
SELECT business.id, schedule.day_of_week, 1, schedule.open_time, schedule.close_time,
    schedule.is_closed, @seed_now, @seed_now
FROM businesses business
CROSS JOIN (
    SELECT 1 day_of_week, '10:00:00' open_time, '19:00:00' close_time, 0 is_closed
    UNION ALL SELECT 2, '10:00:00', '19:00:00', 0
    UNION ALL SELECT 3, '10:00:00', '19:00:00', 0
    UNION ALL SELECT 4, '10:00:00', '19:00:00', 0
    UNION ALL SELECT 5, '10:00:00', '19:00:00', 0
    UNION ALL SELECT 6, '10:00:00', '17:00:00', 0
    UNION ALL SELECT 7, NULL, NULL, 1
) schedule
LEFT JOIN business_hours existing
    ON existing.business_id = business.id
   AND existing.day_of_week = schedule.day_of_week
   AND existing.sequence = 1
WHERE business.slug IN ('demo-brown-repair', 'demo-sole-repair-lab', 'demo-reform-atelier')
  AND existing.id IS NULL;

INSERT INTO business_verifications
    (business_id, verification_type, status, verified_by, verified_at, note, created_at, updated_at)
SELECT business.id, 'DEMO', 'APPROVED', NULL, @seed_now,
    '개발 환경 화면 확인용 데모 데이터', @seed_now, @seed_now
FROM businesses business
LEFT JOIN business_verifications existing
    ON existing.business_id = business.id AND existing.verification_type = 'DEMO'
WHERE business.slug IN ('demo-brown-repair', 'demo-sole-repair-lab', 'demo-reform-atelier')
  AND existing.id IS NULL;

INSERT INTO business_reputation_stats
    (business_id, review_count, rating_avg, favorite_count, work_case_count, updated_at)
SELECT business.id, 0, NULL, 0, 0, @seed_now
FROM businesses business
WHERE business.slug IN ('demo-brown-repair', 'demo-sole-repair-lab', 'demo-reform-atelier')
ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at);

INSERT INTO work_cases
    (business_id, category_id, brand_id, title, slug, description, status,
     published_at, created_at, updated_at)
SELECT business.id, category.id, brand.id, seed.title, seed.slug, seed.description,
    'PUBLISHED', @seed_now, @seed_now, @seed_now
FROM (
    SELECT 'demo-brown-repair' business_slug, 'bag' category_slug, 'louis-vuitton' brand_slug,
        '가죽 가방 손잡이 교체' title, 'demo-bag-handle-replacement' slug,
        '마모된 가죽 손잡이를 새 가죽으로 제작해 교체한 데모 작업 사례입니다.' description
    UNION ALL SELECT 'demo-brown-repair', 'bag', 'chanel', '가죽 가방 색상 복원',
        'demo-leather-bag-color-restoration', '색 빠짐과 표면 마모가 있는 가방을 염색·복원한 데모 작업 사례입니다.'
    UNION ALL SELECT 'demo-sole-repair-lab', 'shoes', 'dr-martens', '가죽 부츠 밑창 교체',
        'demo-leather-boots-sole-replacement', '마모된 부츠 밑창을 교체하고 가장자리를 마감한 데모 작업 사례입니다.'
    UNION ALL SELECT 'demo-reform-atelier', 'clothing', 'burberry', '트렌치코트 품과 기장 수선',
        'demo-trench-coat-alteration', '전체 실루엣을 유지하며 품과 소매 기장을 조정한 데모 작업 사례입니다.'
) seed
INNER JOIN businesses business ON business.slug = seed.business_slug
INNER JOIN categories category ON category.slug = seed.category_slug
LEFT JOIN brands brand ON brand.slug = seed.brand_slug
LEFT JOIN work_cases existing ON existing.slug = seed.slug
WHERE existing.id IS NULL;

INSERT IGNORE INTO work_case_services (work_case_id, service_id)
SELECT work_case.id, service.id
FROM (
    SELECT 'demo-bag-handle-replacement' work_case_slug, 'handle-replacement' service_slug
    UNION ALL SELECT 'demo-leather-bag-color-restoration', 'dyeing'
    UNION ALL SELECT 'demo-leather-bag-color-restoration', 'restoration'
    UNION ALL SELECT 'demo-leather-boots-sole-replacement', 'sole-replacement'
    UNION ALL SELECT 'demo-trench-coat-alteration', 'size-alteration'
    UNION ALL SELECT 'demo-trench-coat-alteration', 'hemming'
) mapping
INNER JOIN work_cases work_case ON work_case.slug = mapping.work_case_slug
INNER JOIN services service ON service.slug = mapping.service_slug;

UPDATE business_reputation_stats stats
INNER JOIN businesses business ON business.id = stats.business_id
SET stats.work_case_count = (
        SELECT COUNT(*) FROM work_cases
        WHERE work_cases.business_id = business.id
          AND work_cases.status = 'PUBLISHED'
          AND work_cases.deleted_at IS NULL
    ),
    stats.updated_at = @seed_now
WHERE business.slug IN ('demo-brown-repair', 'demo-sole-repair-lab', 'demo-reform-atelier');
