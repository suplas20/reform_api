SET @seed_now = UTC_TIMESTAMP();

INSERT INTO categories (parent_id, name, slug, description, sort_order, status, created_at, updated_at)
VALUES
    (NULL, '가방', 'bag', '가방 수선·복원·리폼', 10, 'ACTIVE', @seed_now, @seed_now),
    (NULL, '신발', 'shoes', '구두·운동화·부츠 수선과 복원', 20, 'ACTIVE', @seed_now, @seed_now),
    (NULL, '의류', 'clothing', '의류 수선·리폼·복원', 30, 'ACTIVE', @seed_now, @seed_now),
    (NULL, '가구·소파', 'furniture-sofa', '가구와 소파 수선·천갈이·복원', 40, 'ACTIVE', @seed_now, @seed_now),
    (NULL, '시계', 'watch', '시계 수리와 외관 복원', 50, 'ACTIVE', @seed_now, @seed_now),
    (NULL, '주얼리', 'jewelry', '주얼리 수리·세척·도금', 60, 'ACTIVE', @seed_now, @seed_now),
    (NULL, '생활용품', 'household', '생활용품 수선과 복원', 70, 'ACTIVE', @seed_now, @seed_now),
    (NULL, '기타', 'other', '그 밖의 리폼·수선·복원', 80, 'ACTIVE', @seed_now, @seed_now)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), description = VALUES(description), sort_order = VALUES(sort_order),
    status = VALUES(status), updated_at = VALUES(updated_at);

INSERT INTO categories (parent_id, name, slug, description, sort_order, status, created_at, updated_at)
SELECT parent.id, seed.name, seed.slug, seed.description, seed.sort_order, 'ACTIVE', @seed_now, @seed_now
FROM (
    SELECT 'bag' parent_slug, '명품 가방' name, 'luxury-bag' slug, '명품 브랜드 가방' description, 10 sort_order
    UNION ALL SELECT 'bag', '가죽 가방', 'leather-bag', '천연·인조 가죽 가방', 20
    UNION ALL SELECT 'bag', '여행 가방', 'travel-bag', '캐리어와 여행용 가방', 30
    UNION ALL SELECT 'shoes', '구두', 'dress-shoes', '정장화와 가죽 구두', 10
    UNION ALL SELECT 'shoes', '운동화', 'sneakers', '스니커즈와 운동화', 20
    UNION ALL SELECT 'shoes', '부츠', 'boots', '가죽·패브릭 부츠', 30
    UNION ALL SELECT 'clothing', '아우터', 'outerwear', '코트·재킷·패딩', 10
    UNION ALL SELECT 'clothing', '바지', 'pants', '정장·캐주얼 바지', 20
    UNION ALL SELECT 'clothing', '원피스', 'dress', '원피스와 드레스', 30
    UNION ALL SELECT 'clothing', '니트', 'knitwear', '니트와 스웨터', 40
    UNION ALL SELECT 'furniture-sofa', '소파', 'sofa', '가죽·패브릭 소파', 10
    UNION ALL SELECT 'furniture-sofa', '의자', 'chair', '식탁·사무용 의자', 20
    UNION ALL SELECT 'furniture-sofa', '테이블', 'table', '원목·도장 테이블', 30
) seed
INNER JOIN categories parent ON parent.slug = seed.parent_slug
ON DUPLICATE KEY UPDATE
    parent_id = VALUES(parent_id), name = VALUES(name), description = VALUES(description),
    sort_order = VALUES(sort_order), status = VALUES(status), updated_at = VALUES(updated_at);

INSERT INTO services (name, slug, description, sort_order, status, created_at, updated_at)
VALUES
    ('일반 수선', 'repair', '파손 부위의 일반적인 수선', 10, 'ACTIVE', @seed_now, @seed_now),
    ('복원', 'restoration', '마모·손상된 상태를 원형에 가깝게 복원', 20, 'ACTIVE', @seed_now, @seed_now),
    ('염색', 'dyeing', '가죽·원단 색상 복원 또는 변경', 30, 'ACTIVE', @seed_now, @seed_now),
    ('세탁·클리닝', 'cleaning', '소재별 전문 세탁과 오염 제거', 40, 'ACTIVE', @seed_now, @seed_now),
    ('지퍼 교체', 'zipper-replacement', '고장 난 지퍼 수선 또는 교체', 50, 'ACTIVE', @seed_now, @seed_now),
    ('손잡이 교체', 'handle-replacement', '가방 손잡이 제작과 교체', 60, 'ACTIVE', @seed_now, @seed_now),
    ('안감 교체', 'lining-replacement', '내부 안감 수선과 전체 교체', 70, 'ACTIVE', @seed_now, @seed_now),
    ('장식·부속 교체', 'hardware-replacement', '금속 장식과 부속품 교체', 80, 'ACTIVE', @seed_now, @seed_now),
    ('밑창 교체', 'sole-replacement', '구두·운동화 밑창 보강과 교체', 90, 'ACTIVE', @seed_now, @seed_now),
    ('굽 수선', 'heel-repair', '구두 굽 수선과 교체', 100, 'ACTIVE', @seed_now, @seed_now),
    ('사이즈 수선', 'size-alteration', '의류 품과 길이 조정', 110, 'ACTIVE', @seed_now, @seed_now),
    ('기장 수선', 'hemming', '바지·소매·치마 기장 조정', 120, 'ACTIVE', @seed_now, @seed_now),
    ('천갈이', 'reupholstery', '소파·의자의 가죽 또는 패브릭 교체', 130, 'ACTIVE', @seed_now, @seed_now),
    ('가죽 교체', 'leather-replacement', '손상된 가죽 부분 또는 전체 교체', 140, 'ACTIVE', @seed_now, @seed_now),
    ('광택·폴리싱', 'polishing', '표면 흠집 제거와 광택 복원', 150, 'ACTIVE', @seed_now, @seed_now),
    ('커스텀 리폼', 'custom-remodeling', '형태와 용도를 바꾸는 맞춤 리폼', 160, 'ACTIVE', @seed_now, @seed_now),
    ('각인', 'engraving', '금속·가죽 표면 각인', 170, 'ACTIVE', @seed_now, @seed_now),
    ('도금', 'plating', '주얼리와 금속 부속 재도금', 180, 'ACTIVE', @seed_now, @seed_now)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), description = VALUES(description), sort_order = VALUES(sort_order),
    status = VALUES(status), updated_at = VALUES(updated_at);

INSERT IGNORE INTO category_services (category_id, service_id, sort_order)
SELECT categories.id, services.id, mapping.sort_order
FROM (
    SELECT 'bag' category_slug, 'repair' service_slug, 10 sort_order
    UNION ALL SELECT 'bag', 'restoration', 20
    UNION ALL SELECT 'bag', 'dyeing', 30
    UNION ALL SELECT 'bag', 'cleaning', 40
    UNION ALL SELECT 'bag', 'zipper-replacement', 50
    UNION ALL SELECT 'bag', 'handle-replacement', 60
    UNION ALL SELECT 'bag', 'lining-replacement', 70
    UNION ALL SELECT 'bag', 'hardware-replacement', 80
    UNION ALL SELECT 'bag', 'custom-remodeling', 90
    UNION ALL SELECT 'luxury-bag', 'repair', 10
    UNION ALL SELECT 'luxury-bag', 'restoration', 20
    UNION ALL SELECT 'luxury-bag', 'dyeing', 30
    UNION ALL SELECT 'luxury-bag', 'cleaning', 40
    UNION ALL SELECT 'luxury-bag', 'hardware-replacement', 50
    UNION ALL SELECT 'leather-bag', 'repair', 10
    UNION ALL SELECT 'leather-bag', 'restoration', 20
    UNION ALL SELECT 'leather-bag', 'dyeing', 30
    UNION ALL SELECT 'leather-bag', 'cleaning', 40
    UNION ALL SELECT 'leather-bag', 'handle-replacement', 50
    UNION ALL SELECT 'travel-bag', 'repair', 10
    UNION ALL SELECT 'travel-bag', 'cleaning', 20
    UNION ALL SELECT 'travel-bag', 'zipper-replacement', 30
    UNION ALL SELECT 'shoes', 'repair', 10
    UNION ALL SELECT 'shoes', 'restoration', 20
    UNION ALL SELECT 'shoes', 'dyeing', 30
    UNION ALL SELECT 'shoes', 'cleaning', 40
    UNION ALL SELECT 'shoes', 'sole-replacement', 50
    UNION ALL SELECT 'shoes', 'heel-repair', 60
    UNION ALL SELECT 'shoes', 'polishing', 70
    UNION ALL SELECT 'dress-shoes', 'repair', 10
    UNION ALL SELECT 'dress-shoes', 'sole-replacement', 20
    UNION ALL SELECT 'dress-shoes', 'heel-repair', 30
    UNION ALL SELECT 'dress-shoes', 'polishing', 40
    UNION ALL SELECT 'sneakers', 'repair', 10
    UNION ALL SELECT 'sneakers', 'cleaning', 20
    UNION ALL SELECT 'sneakers', 'sole-replacement', 30
    UNION ALL SELECT 'boots', 'repair', 10
    UNION ALL SELECT 'boots', 'restoration', 20
    UNION ALL SELECT 'boots', 'sole-replacement', 30
    UNION ALL SELECT 'clothing', 'repair', 10
    UNION ALL SELECT 'clothing', 'restoration', 20
    UNION ALL SELECT 'clothing', 'dyeing', 30
    UNION ALL SELECT 'clothing', 'cleaning', 40
    UNION ALL SELECT 'clothing', 'size-alteration', 50
    UNION ALL SELECT 'clothing', 'hemming', 60
    UNION ALL SELECT 'clothing', 'custom-remodeling', 70
    UNION ALL SELECT 'outerwear', 'repair', 10
    UNION ALL SELECT 'outerwear', 'cleaning', 20
    UNION ALL SELECT 'outerwear', 'size-alteration', 30
    UNION ALL SELECT 'pants', 'size-alteration', 10
    UNION ALL SELECT 'pants', 'hemming', 20
    UNION ALL SELECT 'dress', 'size-alteration', 10
    UNION ALL SELECT 'dress', 'hemming', 20
    UNION ALL SELECT 'knitwear', 'repair', 10
    UNION ALL SELECT 'knitwear', 'cleaning', 20
    UNION ALL SELECT 'furniture-sofa', 'repair', 10
    UNION ALL SELECT 'furniture-sofa', 'restoration', 20
    UNION ALL SELECT 'furniture-sofa', 'cleaning', 30
    UNION ALL SELECT 'furniture-sofa', 'reupholstery', 40
    UNION ALL SELECT 'furniture-sofa', 'leather-replacement', 50
    UNION ALL SELECT 'sofa', 'repair', 10
    UNION ALL SELECT 'sofa', 'reupholstery', 20
    UNION ALL SELECT 'sofa', 'leather-replacement', 30
    UNION ALL SELECT 'chair', 'repair', 10
    UNION ALL SELECT 'chair', 'reupholstery', 20
    UNION ALL SELECT 'table', 'repair', 10
    UNION ALL SELECT 'table', 'restoration', 20
    UNION ALL SELECT 'watch', 'repair', 10
    UNION ALL SELECT 'watch', 'restoration', 20
    UNION ALL SELECT 'watch', 'cleaning', 30
    UNION ALL SELECT 'watch', 'polishing', 40
    UNION ALL SELECT 'jewelry', 'repair', 10
    UNION ALL SELECT 'jewelry', 'cleaning', 20
    UNION ALL SELECT 'jewelry', 'polishing', 30
    UNION ALL SELECT 'jewelry', 'engraving', 40
    UNION ALL SELECT 'jewelry', 'plating', 50
    UNION ALL SELECT 'household', 'repair', 10
    UNION ALL SELECT 'household', 'restoration', 20
    UNION ALL SELECT 'household', 'cleaning', 30
    UNION ALL SELECT 'other', 'repair', 10
    UNION ALL SELECT 'other', 'restoration', 20
    UNION ALL SELECT 'other', 'custom-remodeling', 30
) mapping
INNER JOIN categories ON categories.slug = mapping.category_slug
INNER JOIN services ON services.slug = mapping.service_slug;

INSERT INTO regions (parent_id, code, name, slug, level, sort_order, status, created_at, updated_at)
SELECT NULL, seed.code, seed.name, seed.slug, 1, seed.sort_order, 'ACTIVE', @seed_now, @seed_now
FROM (
    SELECT 'KR-11' code, '서울특별시' name, 'seoul' slug, 10 sort_order
    UNION ALL SELECT 'KR-26', '부산광역시', 'busan', 20
    UNION ALL SELECT 'KR-27', '대구광역시', 'daegu', 30
    UNION ALL SELECT 'KR-28', '인천광역시', 'incheon', 40
    UNION ALL SELECT 'KR-29', '광주광역시', 'gwangju', 50
    UNION ALL SELECT 'KR-30', '대전광역시', 'daejeon', 60
    UNION ALL SELECT 'KR-31', '울산광역시', 'ulsan', 70
    UNION ALL SELECT 'KR-36', '세종특별자치시', 'sejong', 80
    UNION ALL SELECT 'KR-41', '경기도', 'gyeonggi', 90
    UNION ALL SELECT 'KR-51', '강원특별자치도', 'gangwon', 100
    UNION ALL SELECT 'KR-43', '충청북도', 'chungbuk', 110
    UNION ALL SELECT 'KR-44', '충청남도', 'chungnam', 120
    UNION ALL SELECT 'KR-52', '전북특별자치도', 'jeonbuk', 130
    UNION ALL SELECT 'KR-46', '전라남도', 'jeonnam', 140
    UNION ALL SELECT 'KR-47', '경상북도', 'gyeongbuk', 150
    UNION ALL SELECT 'KR-48', '경상남도', 'gyeongnam', 160
    UNION ALL SELECT 'KR-50', '제주특별자치도', 'jeju', 170
) seed
LEFT JOIN regions existing ON existing.code = seed.code
WHERE existing.id IS NULL;

INSERT INTO regions (parent_id, code, name, slug, level, sort_order, status, created_at, updated_at)
SELECT parent.id, seed.code, seed.name, seed.slug, 2, seed.sort_order, 'ACTIVE', @seed_now, @seed_now
FROM (
    SELECT 'KR-11' parent_code, 'KR-11680' code, '강남구' name, 'gangnam-gu' slug, 10 sort_order
    UNION ALL SELECT 'KR-11', 'KR-11650', '서초구', 'seocho-gu', 20
    UNION ALL SELECT 'KR-11', 'KR-11710', '송파구', 'songpa-gu', 30
    UNION ALL SELECT 'KR-11', 'KR-11170', '용산구', 'yongsan-gu', 40
    UNION ALL SELECT 'KR-11', 'KR-11200', '성동구', 'seongdong-gu', 50
    UNION ALL SELECT 'KR-11', 'KR-11440', '마포구', 'mapo-gu', 60
    UNION ALL SELECT 'KR-11', 'KR-11110', '종로구', 'jongno-gu', 70
    UNION ALL SELECT 'KR-11', 'KR-11140', '중구', 'jung-gu-seoul', 80
    UNION ALL SELECT 'KR-11', 'KR-11500', '강서구', 'gangseo-gu-seoul', 90
    UNION ALL SELECT 'KR-11', 'KR-11590', '동작구', 'dongjak-gu', 100
    UNION ALL SELECT 'KR-11', 'KR-11620', '관악구', 'gwanak-gu', 110
    UNION ALL SELECT 'KR-11', 'KR-11350', '노원구', 'nowon-gu', 120
    UNION ALL SELECT 'KR-11', 'KR-11410', '서대문구', 'seodaemun-gu', 130
    UNION ALL SELECT 'KR-11', 'KR-11215', '광진구', 'gwangjin-gu', 140
    UNION ALL SELECT 'KR-11', 'KR-11230', '동대문구', 'dongdaemun-gu', 150
    UNION ALL SELECT 'KR-11', 'KR-11260', '중랑구', 'jungnang-gu', 160
    UNION ALL SELECT 'KR-11', 'KR-11290', '성북구', 'seongbuk-gu', 170
    UNION ALL SELECT 'KR-11', 'KR-11305', '강북구', 'gangbuk-gu', 180
    UNION ALL SELECT 'KR-11', 'KR-11320', '도봉구', 'dobong-gu', 190
    UNION ALL SELECT 'KR-11', 'KR-11380', '은평구', 'eunpyeong-gu', 200
    UNION ALL SELECT 'KR-11', 'KR-11470', '양천구', 'yangcheon-gu', 210
    UNION ALL SELECT 'KR-11', 'KR-11530', '구로구', 'guro-gu', 220
    UNION ALL SELECT 'KR-11', 'KR-11545', '금천구', 'geumcheon-gu', 230
    UNION ALL SELECT 'KR-11', 'KR-11560', '영등포구', 'yeongdeungpo-gu', 240
    UNION ALL SELECT 'KR-11', 'KR-11740', '강동구', 'gangdong-gu', 250
) seed
INNER JOIN regions parent ON parent.code = seed.parent_code
LEFT JOIN regions existing ON existing.code = seed.code
WHERE existing.id IS NULL;

INSERT INTO brands (name, slug, status, created_at, updated_at)
VALUES
    ('샤넬', 'chanel', 'ACTIVE', @seed_now, @seed_now),
    ('루이비통', 'louis-vuitton', 'ACTIVE', @seed_now, @seed_now),
    ('구찌', 'gucci', 'ACTIVE', @seed_now, @seed_now),
    ('에르메스', 'hermes', 'ACTIVE', @seed_now, @seed_now),
    ('프라다', 'prada', 'ACTIVE', @seed_now, @seed_now),
    ('디올', 'dior', 'ACTIVE', @seed_now, @seed_now),
    ('버버리', 'burberry', 'ACTIVE', @seed_now, @seed_now),
    ('보테가 베네타', 'bottega-veneta', 'ACTIVE', @seed_now, @seed_now),
    ('생로랑', 'saint-laurent', 'ACTIVE', @seed_now, @seed_now),
    ('셀린느', 'celine', 'ACTIVE', @seed_now, @seed_now),
    ('펜디', 'fendi', 'ACTIVE', @seed_now, @seed_now),
    ('코치', 'coach', 'ACTIVE', @seed_now, @seed_now),
    ('MCM', 'mcm', 'ACTIVE', @seed_now, @seed_now),
    ('나이키', 'nike', 'ACTIVE', @seed_now, @seed_now),
    ('아디다스', 'adidas', 'ACTIVE', @seed_now, @seed_now),
    ('뉴발란스', 'new-balance', 'ACTIVE', @seed_now, @seed_now),
    ('닥터마틴', 'dr-martens', 'ACTIVE', @seed_now, @seed_now),
    ('롤렉스', 'rolex', 'ACTIVE', @seed_now, @seed_now),
    ('까르띠에', 'cartier', 'ACTIVE', @seed_now, @seed_now),
    ('티파니앤코', 'tiffany-and-co', 'ACTIVE', @seed_now, @seed_now)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), status = VALUES(status), updated_at = VALUES(updated_at);

INSERT INTO help_categories (name, sort_order, status, created_at, updated_at)
SELECT seed.name, seed.sort_order, 'ACTIVE', @seed_now, @seed_now
FROM (
    SELECT '이용 안내' name, 10 sort_order
    UNION ALL SELECT '계정·로그인', 20
    UNION ALL SELECT '업체·검색', 30
    UNION ALL SELECT '리뷰', 40
    UNION ALL SELECT '문의·상담', 50
    UNION ALL SELECT '개인정보', 60
) seed
LEFT JOIN help_categories existing ON existing.name = seed.name
WHERE existing.id IS NULL;

INSERT INTO help_faqs (category_id, question, answer_html, sort_order, status, created_at, updated_at)
SELECT category.id, seed.question, seed.answer_html, seed.sort_order, 'PUBLISHED', @seed_now, @seed_now
FROM (
    SELECT '이용 안내' category_name, 're:form은 어떤 서비스인가요?' question,
        '<p>가방, 신발, 의류, 가구 등 다양한 물품의 수선·복원·리폼 업체를 조건별로 찾는 서비스입니다.</p>' answer_html, 10 sort_order
    UNION ALL SELECT '이용 안내', '업체 정보는 어떻게 확인하나요?', '<p>업체 상세 화면에서 주소, 영업시간, 지원 방식, 작업 사례와 리뷰를 확인할 수 있습니다.</p>', 20
    UNION ALL SELECT '계정·로그인', '어떤 방법으로 로그인할 수 있나요?', '<p>앱에서 제공하는 카카오, 네이버, 구글 또는 Apple 소셜 로그인을 이용할 수 있습니다.</p>', 10
    UNION ALL SELECT '계정·로그인', '회원 탈퇴는 어디에서 하나요?', '<p>앱 설정의 계정 메뉴에서 회원 탈퇴를 요청할 수 있습니다.</p>', 20
    UNION ALL SELECT '업체·검색', '지역과 서비스로 업체를 찾을 수 있나요?', '<p>지역, 물품 카테고리, 작업 서비스, 취급 브랜드와 현재 위치를 조합해 검색할 수 있습니다.</p>', 10
    UNION ALL SELECT '업체·검색', '표시된 가격은 확정 금액인가요?', '<p>표시 가격은 참고 범위일 수 있습니다. 실제 비용은 물품 상태와 작업 범위에 따라 업체 상담 후 확정됩니다.</p>', 20
    UNION ALL SELECT '리뷰', '리뷰는 누가 작성할 수 있나요?', '<p>로그인한 사용자가 업체 이용 경험을 바탕으로 리뷰를 작성할 수 있습니다.</p>', 10
    UNION ALL SELECT '리뷰', '리뷰에 사진을 첨부할 수 있나요?', '<p>리뷰 작성 시 최대 5장의 이미지를 업로드할 수 있습니다.</p>', 20
    UNION ALL SELECT '문의·상담', '업체에 어떻게 문의하나요?', '<p>업체 상세 화면의 문의하기에서 물품 정보, 희망 일정과 사진을 함께 전달할 수 있습니다.</p>', 10
    UNION ALL SELECT '문의·상담', '문의 답변은 어디에서 확인하나요?', '<p>마이페이지의 문의 내역과 알림에서 업체 답변을 확인할 수 있습니다.</p>', 20
    UNION ALL SELECT '개인정보', '내 정보는 어떻게 보호되나요?', '<p>인증 토큰은 원문으로 저장하지 않으며, 서비스 제공에 필요한 최소 정보만 처리합니다.</p>', 10
) seed
INNER JOIN help_categories category ON category.name = seed.category_name
LEFT JOIN help_faqs existing ON existing.question = seed.question
WHERE existing.id IS NULL;
