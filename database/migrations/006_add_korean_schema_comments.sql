-- MariaDB 스키마의 테이블 및 컬럼 설명을 한글로 문서화한다.

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `admins`
    COMMENT = '관리자 계정',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '이메일 주소',
    MODIFY COLUMN `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '비밀번호 해시',
    MODIFY COLUMN `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '이름',
    MODIFY COLUMN `role` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '관리자 권한',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `ads`
    COMMENT = '앱 광고 콘텐츠',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `placement` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '광고 노출 위치',
    MODIFY COLUMN `advertiser` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '광고주명',
    MODIFY COLUMN `headline` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '광고 제목',
    MODIFY COLUMN `body` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '본문 내용',
    MODIFY COLUMN `image_url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '이미지 URL',
    MODIFY COLUMN `call_to_action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '광고 행동 유도 문구',
    MODIFY COLUMN `target_url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '이동 대상 URL',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `starts_at` datetime NULL DEFAULT NULL COMMENT '노출 시작 일시',
    MODIFY COLUMN `ends_at` datetime NULL DEFAULT NULL COMMENT '노출 종료 일시',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `ad_events`
    COMMENT = '광고 노출 및 클릭 이벤트',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `ad_id` bigint(20) NOT NULL COMMENT '광고 식별자',
    MODIFY COLUMN `user_id` bigint(20) NULL DEFAULT NULL COMMENT '사용자 식별자',
    MODIFY COLUMN `event_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '이벤트 고유 식별자',
    MODIFY COLUMN `event_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '이벤트 유형',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시';

ALTER TABLE `auth_sessions`
    COMMENT = '사용자 인증 세션',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `user_id` bigint(20) NOT NULL COMMENT '사용자 식별자',
    MODIFY COLUMN `device_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '기기 식별자',
    MODIFY COLUMN `access_token_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '액세스 토큰 해시',
    MODIFY COLUMN `refresh_token_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '리프레시 토큰 해시',
    MODIFY COLUMN `access_expires_at` datetime NOT NULL COMMENT '액세스 토큰 만료 일시',
    MODIFY COLUMN `refresh_expires_at` datetime NOT NULL COMMENT '리프레시 토큰 만료 일시',
    MODIFY COLUMN `last_used_at` datetime NULL DEFAULT NULL COMMENT '최근 사용 일시',
    MODIFY COLUMN `revoked_at` datetime NULL DEFAULT NULL COMMENT '인증 폐기 일시',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시';

ALTER TABLE `brands`
    COMMENT = '수선 대상 브랜드 기준 정보',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '이름',
    MODIFY COLUMN `slug` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL 식별 문자열',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `businesses`
    COMMENT = '수선·리폼 업체 기본 정보',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '이름',
    MODIFY COLUMN `slug` varchar(220) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL 식별 문자열',
    MODIFY COLUMN `short_description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '짧은 설명',
    MODIFY COLUMN `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '상세 설명',
    MODIFY COLUMN `phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '전화번호',
    MODIFY COLUMN `supports_shipping` smallint(6) NOT NULL DEFAULT 0 COMMENT '택배 접수 지원 여부',
    MODIFY COLUMN `supports_visit` smallint(6) NOT NULL DEFAULT 0 COMMENT '매장 방문 지원 여부',
    MODIFY COLUMN `supports_pickup` smallint(6) NOT NULL DEFAULT 0 COMMENT '방문 수령 지원 여부',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `claimed_user_id` bigint(20) NULL DEFAULT NULL COMMENT '업체 소유권을 가진 사용자 식별자',
    MODIFY COLUMN `published_at` datetime NULL DEFAULT NULL COMMENT '공개 일시',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시',
    MODIFY COLUMN `deleted_at` datetime NULL DEFAULT NULL COMMENT '소프트 삭제 일시';

ALTER TABLE `business_brands`
    COMMENT = '업체별 취급 브랜드',
    MODIFY COLUMN `business_id` bigint(20) NOT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `brand_id` bigint(20) NOT NULL COMMENT '브랜드 식별자',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시';

ALTER TABLE `business_categories`
    COMMENT = '업체별 취급 카테고리',
    MODIFY COLUMN `business_id` bigint(20) NOT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `category_id` bigint(20) NOT NULL COMMENT '카테고리 식별자',
    MODIFY COLUMN `is_primary` smallint(6) NOT NULL DEFAULT 0 COMMENT '대표 항목 여부',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시';

ALTER TABLE `business_claims`
    COMMENT = '업체 소유권 신청',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `business_id` bigint(20) NOT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `user_id` bigint(20) NOT NULL COMMENT '사용자 식별자',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `requested_at` datetime NOT NULL COMMENT '신청 일시',
    MODIFY COLUMN `reviewed_at` datetime NULL DEFAULT NULL COMMENT '검토 일시',
    MODIFY COLUMN `reviewed_by` bigint(20) NULL DEFAULT NULL COMMENT '검토 관리자 식별자',
    MODIFY COLUMN `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '관리 메모',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `business_external_ratings`
    COMMENT = '업체 외부 플랫폼 평점',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `business_id` bigint(20) NOT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `provider` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '외부 제공자 코드',
    MODIFY COLUMN `rating` decimal(3,2) NULL DEFAULT NULL COMMENT '평점',
    MODIFY COLUMN `review_count` int(11) NULL DEFAULT NULL COMMENT '리뷰 수',
    MODIFY COLUMN `fetched_at` datetime NOT NULL COMMENT '외부 데이터 조회 일시',
    MODIFY COLUMN `expires_at` datetime NULL DEFAULT NULL COMMENT '만료 일시',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `business_external_refs`
    COMMENT = '업체 외부 플랫폼 참조 정보',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `business_id` bigint(20) NOT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `provider` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '외부 제공자 코드',
    MODIFY COLUMN `external_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '외부 서비스 식별자',
    MODIFY COLUMN `external_url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '외부 서비스 URL',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `business_hours`
    COMMENT = '업체 영업시간',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `business_id` bigint(20) NOT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `day_of_week` smallint(6) NOT NULL COMMENT '요일 코드',
    MODIFY COLUMN `sequence` smallint(6) NOT NULL COMMENT '순번',
    MODIFY COLUMN `open_time` time NULL DEFAULT NULL COMMENT '영업 시작 시각',
    MODIFY COLUMN `close_time` time NULL DEFAULT NULL COMMENT '영업 종료 시각',
    MODIFY COLUMN `is_closed` smallint(6) NOT NULL DEFAULT 0 COMMENT '휴무 여부',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `business_images`
    COMMENT = '업체 이미지',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `business_id` bigint(20) NOT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `storage_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '스토리지 유형',
    MODIFY COLUMN `file_path` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '파일 저장 경로',
    MODIFY COLUMN `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '업로드 원본 파일명',
    MODIFY COLUMN `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '파일 MIME 유형',
    MODIFY COLUMN `width` int(11) NULL DEFAULT NULL COMMENT '이미지 너비(픽셀)',
    MODIFY COLUMN `height` int(11) NULL DEFAULT NULL COMMENT '이미지 높이(픽셀)',
    MODIFY COLUMN `image_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '이미지 유형',
    MODIFY COLUMN `alt_text` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '이미지 대체 텍스트',
    MODIFY COLUMN `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '표시 순서',
    MODIFY COLUMN `is_primary` smallint(6) NOT NULL DEFAULT 0 COMMENT '대표 항목 여부',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시';

ALTER TABLE `business_links`
    COMMENT = '업체 운영 채널 링크',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `business_id` bigint(20) NOT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `link_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '링크 유형',
    MODIFY COLUMN `url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '링크 URL',
    MODIFY COLUMN `label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '표시 라벨',
    MODIFY COLUMN `is_primary` smallint(6) NOT NULL DEFAULT 0 COMMENT '대표 항목 여부',
    MODIFY COLUMN `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '표시 순서',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시';

ALTER TABLE `business_locations`
    COMMENT = '업체 위치 및 주소',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `business_id` bigint(20) NOT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `region_id` bigint(20) NOT NULL COMMENT '지역 식별자',
    MODIFY COLUMN `postal_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '우편번호',
    MODIFY COLUMN `road_address` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '도로명 주소',
    MODIFY COLUMN `jibun_address` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '지번 주소',
    MODIFY COLUMN `address_detail` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '상세 주소',
    MODIFY COLUMN `latitude` decimal(10,7) NULL DEFAULT NULL COMMENT '위도',
    MODIFY COLUMN `longitude` decimal(10,7) NULL DEFAULT NULL COMMENT '경도',
    MODIFY COLUMN `is_primary` smallint(6) NOT NULL DEFAULT 0 COMMENT '대표 항목 여부',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `business_offerings`
    COMMENT = '업체별 카테고리·서비스 제공 정보',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `business_id` bigint(20) NOT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `category_id` bigint(20) NOT NULL COMMENT '카테고리 식별자',
    MODIFY COLUMN `service_id` bigint(20) NOT NULL COMMENT '서비스 식별자',
    MODIFY COLUMN `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '제목',
    MODIFY COLUMN `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '상세 설명',
    MODIFY COLUMN `price_min` decimal(12,2) NULL DEFAULT NULL COMMENT '최소 가격',
    MODIFY COLUMN `price_max` decimal(12,2) NULL DEFAULT NULL COMMENT '최대 가격',
    MODIFY COLUMN `price_note` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '가격 안내 문구',
    MODIFY COLUMN `duration_min_days` int(11) NULL DEFAULT NULL COMMENT '최소 작업 기간(일)',
    MODIFY COLUMN `duration_max_days` int(11) NULL DEFAULT NULL COMMENT '최대 작업 기간(일)',
    MODIFY COLUMN `is_primary` smallint(6) NOT NULL DEFAULT 0 COMMENT '대표 항목 여부',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `business_reputation_stats`
    COMMENT = '업체 평판 집계',
    MODIFY COLUMN `business_id` bigint(20) NOT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `review_count` int(11) NOT NULL DEFAULT 0 COMMENT '리뷰 수',
    MODIFY COLUMN `rating_avg` decimal(3,2) NULL DEFAULT NULL COMMENT '평균 평점',
    MODIFY COLUMN `favorite_count` int(11) NOT NULL DEFAULT 0 COMMENT '찜 수',
    MODIFY COLUMN `work_case_count` int(11) NOT NULL DEFAULT 0 COMMENT '작업 사례 수',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `business_sources`
    COMMENT = '업체 정보 수집 출처',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `business_id` bigint(20) NOT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `source_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '정보 출처 유형',
    MODIFY COLUMN `source_url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '정보 출처 URL',
    MODIFY COLUMN `external_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '외부 서비스 식별자',
    MODIFY COLUMN `collected_at` datetime NULL DEFAULT NULL COMMENT '수집 일시',
    MODIFY COLUMN `last_verified_at` datetime NULL DEFAULT NULL COMMENT '최근 검증 일시',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `business_verifications`
    COMMENT = '업체 검증 이력',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `business_id` bigint(20) NOT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `verification_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '검증 유형',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `verified_by` bigint(20) NULL DEFAULT NULL COMMENT '검증 관리자 식별자',
    MODIFY COLUMN `verified_at` datetime NULL DEFAULT NULL COMMENT '검증 일시',
    MODIFY COLUMN `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '관리 메모',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `categories`
    COMMENT = '수선 대상 카테고리 기준 정보',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `parent_id` bigint(20) NULL DEFAULT NULL COMMENT '상위 항목 식별자',
    MODIFY COLUMN `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '이름',
    MODIFY COLUMN `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL 식별 문자열',
    MODIFY COLUMN `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '상세 설명',
    MODIFY COLUMN `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '표시 순서',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `category_services`
    COMMENT = '카테고리별 제공 가능 서비스',
    MODIFY COLUMN `category_id` bigint(20) NOT NULL COMMENT '카테고리 식별자',
    MODIFY COLUMN `service_id` bigint(20) NOT NULL COMMENT '서비스 식별자',
    MODIFY COLUMN `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '표시 순서';

ALTER TABLE `event_logs`
    COMMENT = '사용자 행동 이벤트 로그',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `user_id` bigint(20) NULL DEFAULT NULL COMMENT '사용자 식별자',
    MODIFY COLUMN `session_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '비로그인 세션 키',
    MODIFY COLUMN `event_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '이벤트명',
    MODIFY COLUMN `entity_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '이벤트 대상 유형',
    MODIFY COLUMN `entity_id` bigint(20) NULL DEFAULT NULL COMMENT '이벤트 대상 식별자',
    MODIFY COLUMN `metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '부가 메타데이터',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시';

ALTER TABLE `guides`
    COMMENT = '가이드 콘텐츠',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `title` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '제목',
    MODIFY COLUMN `slug` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL 식별 문자열',
    MODIFY COLUMN `summary` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '요약 내용',
    MODIFY COLUMN `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '콘텐츠 내용',
    MODIFY COLUMN `seo_title` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '검색엔진 노출 제목',
    MODIFY COLUMN `meta_description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '검색엔진 메타 설명',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `published_at` datetime NULL DEFAULT NULL COMMENT '공개 일시',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시',
    MODIFY COLUMN `deleted_at` datetime NULL DEFAULT NULL COMMENT '소프트 삭제 일시';

ALTER TABLE `help_categories`
    COMMENT = '도움말 카테고리',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '이름',
    MODIFY COLUMN `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '표시 순서',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `help_faqs`
    COMMENT = '자주 묻는 질문',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `category_id` bigint(20) NOT NULL COMMENT '카테고리 식별자',
    MODIFY COLUMN `question` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '질문 내용',
    MODIFY COLUMN `answer_html` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '답변 HTML 내용',
    MODIFY COLUMN `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '표시 순서',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `inquiries`
    COMMENT = '사용자 문의',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `user_id` bigint(20) NOT NULL COMMENT '사용자 식별자',
    MODIFY COLUMN `business_id` bigint(20) NOT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `inquiry_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '문의 유형',
    MODIFY COLUMN `item_category_id` bigint(20) NULL DEFAULT NULL COMMENT '문의 품목 카테고리 식별자',
    MODIFY COLUMN `preferred_date_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '희망 일자 유형',
    MODIFY COLUMN `preferred_date` date NULL DEFAULT NULL COMMENT '희망 일자',
    MODIFY COLUMN `message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '메시지 내용',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시',
    MODIFY COLUMN `closed_at` datetime NULL DEFAULT NULL COMMENT '종료 일시';

ALTER TABLE `inquiry_media`
    COMMENT = '문의 첨부 미디어',
    MODIFY COLUMN `inquiry_id` bigint(20) NOT NULL COMMENT '문의 식별자',
    MODIFY COLUMN `media_id` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '미디어 식별자',
    MODIFY COLUMN `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '표시 순서';

ALTER TABLE `inquiry_replies`
    COMMENT = '문의 답변',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `inquiry_id` bigint(20) NOT NULL COMMENT '문의 식별자',
    MODIFY COLUMN `sender_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '발신자 유형',
    MODIFY COLUMN `sender_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '발신자명',
    MODIFY COLUMN `sender_avatar_path` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '발신자 프로필 이미지 경로',
    MODIFY COLUMN `message` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '메시지 내용',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시';

ALTER TABLE `media_uploads`
    COMMENT = '미디어 업로드 관리',
    MODIFY COLUMN `id` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '레코드 식별자',
    MODIFY COLUMN `user_id` bigint(20) NOT NULL COMMENT '사용자 식별자',
    MODIFY COLUMN `purpose` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '사용 목적',
    MODIFY COLUMN `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '원본 이름',
    MODIFY COLUMN `content_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '콘텐츠 유형',
    MODIFY COLUMN `expected_size` bigint(20) NOT NULL COMMENT '예상 파일 크기(바이트)',
    MODIFY COLUMN `storage_path` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '스토리지 저장 경로',
    MODIFY COLUMN `upload_token_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '업로드 토큰 해시',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `expires_at` datetime NOT NULL COMMENT '만료 일시',
    MODIFY COLUMN `completed_at` datetime NULL DEFAULT NULL COMMENT '처리 완료 일시',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `notifications`
    COMMENT = '사용자 알림',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `user_id` bigint(20) NOT NULL COMMENT '사용자 식별자',
    MODIFY COLUMN `notification_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '알림 유형',
    MODIFY COLUMN `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '제목',
    MODIFY COLUMN `body` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '본문 내용',
    MODIFY COLUMN `image_path` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '이미지 경로',
    MODIFY COLUMN `deep_link` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '앱 딥링크',
    MODIFY COLUMN `read_at` datetime NULL DEFAULT NULL COMMENT '읽은 일시',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시';

ALTER TABLE `recent_businesses`
    COMMENT = '최근 본 업체',
    MODIFY COLUMN `user_id` bigint(20) NOT NULL COMMENT '사용자 식별자',
    MODIFY COLUMN `business_id` bigint(20) NOT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `viewed_at` datetime NOT NULL COMMENT '조회 일시';

ALTER TABLE `regions`
    COMMENT = '행정 지역 기준 정보',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `parent_id` bigint(20) NULL DEFAULT NULL COMMENT '상위 항목 식별자',
    MODIFY COLUMN `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '코드',
    MODIFY COLUMN `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '이름',
    MODIFY COLUMN `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL 식별 문자열',
    MODIFY COLUMN `level` smallint(6) NOT NULL COMMENT '계층 단계',
    MODIFY COLUMN `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '표시 순서',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `reviews`
    COMMENT = '업체 리뷰',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `business_id` bigint(20) NOT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `user_id` bigint(20) NOT NULL COMMENT '사용자 식별자',
    MODIFY COLUMN `idempotency_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '중복 요청 방지 키',
    MODIFY COLUMN `request_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '요청 본문 해시',
    MODIFY COLUMN `rating` smallint(6) NOT NULL COMMENT '평점',
    MODIFY COLUMN `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '콘텐츠 내용',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시',
    MODIFY COLUMN `deleted_at` datetime NULL DEFAULT NULL COMMENT '소프트 삭제 일시';

ALTER TABLE `review_helpful_users`
    COMMENT = '리뷰 도움됨 사용자',
    MODIFY COLUMN `review_id` bigint(20) NOT NULL COMMENT '리뷰 식별자',
    MODIFY COLUMN `user_id` bigint(20) NOT NULL COMMENT '사용자 식별자',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시';

ALTER TABLE `review_images`
    COMMENT = '리뷰 이미지',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `review_id` bigint(20) NOT NULL COMMENT '리뷰 식별자',
    MODIFY COLUMN `storage_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '스토리지 유형',
    MODIFY COLUMN `file_path` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '파일 저장 경로',
    MODIFY COLUMN `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '표시 순서',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시';

ALTER TABLE `review_tags`
    COMMENT = '리뷰 태그',
    MODIFY COLUMN `review_id` bigint(20) NOT NULL COMMENT '리뷰 식별자',
    MODIFY COLUMN `tag` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '리뷰 태그',
    MODIFY COLUMN `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '표시 순서';

ALTER TABLE `schema_migrations`
    COMMENT = '데이터베이스 마이그레이션 이력',
    MODIFY COLUMN `version` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '마이그레이션 버전',
    MODIFY COLUMN `checksum` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '파일 무결성 체크섬',
    MODIFY COLUMN `applied_at` datetime NOT NULL COMMENT '마이그레이션 적용 일시';

ALTER TABLE `search_logs`
    COMMENT = '검색 실행 로그',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `user_id` bigint(20) NULL DEFAULT NULL COMMENT '사용자 식별자',
    MODIFY COLUMN `session_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '비로그인 세션 키',
    MODIFY COLUMN `keyword` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '매칭 키워드',
    MODIFY COLUMN `category_id` bigint(20) NULL DEFAULT NULL COMMENT '카테고리 식별자',
    MODIFY COLUMN `service_id` bigint(20) NULL DEFAULT NULL COMMENT '서비스 식별자',
    MODIFY COLUMN `region_id` bigint(20) NULL DEFAULT NULL COMMENT '지역 식별자',
    MODIFY COLUMN `brand_id` bigint(20) NULL DEFAULT NULL COMMENT '브랜드 식별자',
    MODIFY COLUMN `result_count` int(11) NOT NULL COMMENT '검색 결과 수',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시';

ALTER TABLE `semas_business_imports`
    COMMENT = '소상공인시장진흥공단 상가업소 수집 원본',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `business_id` bigint(20) NULL DEFAULT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `external_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '외부 서비스 식별자',
    MODIFY COLUMN `business_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '업체명',
    MODIFY COLUMN `branch_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '지점명',
    MODIFY COLUMN `industry_large_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '상권업종 대분류 코드',
    MODIFY COLUMN `industry_large_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '상권업종 대분류명',
    MODIFY COLUMN `industry_medium_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '상권업종 중분류 코드',
    MODIFY COLUMN `industry_medium_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '상권업종 중분류명',
    MODIFY COLUMN `industry_small_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '상권업종 소분류 코드',
    MODIFY COLUMN `industry_small_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '상권업종 소분류명',
    MODIFY COLUMN `standard_industry_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '표준산업분류 코드',
    MODIFY COLUMN `standard_industry_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '표준산업분류명',
    MODIFY COLUMN `province_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '시도 코드',
    MODIFY COLUMN `province_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '시도명',
    MODIFY COLUMN `district_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '시군구 코드',
    MODIFY COLUMN `district_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '시군구명',
    MODIFY COLUMN `administrative_dong_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '행정동 코드',
    MODIFY COLUMN `administrative_dong_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '행정동명',
    MODIFY COLUMN `legal_dong_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '법정동 코드',
    MODIFY COLUMN `legal_dong_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '법정동명',
    MODIFY COLUMN `lot_number_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '지번 코드',
    MODIFY COLUMN `lot_number_address` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '지번 주소',
    MODIFY COLUMN `road_name_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '도로명 코드',
    MODIFY COLUMN `road_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '도로명',
    MODIFY COLUMN `building_management_number` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '건물관리번호',
    MODIFY COLUMN `building_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '건물명',
    MODIFY COLUMN `road_address` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '도로명 주소',
    MODIFY COLUMN `postal_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '우편번호',
    MODIFY COLUMN `dong_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '건물 동 정보',
    MODIFY COLUMN `floor_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '건물 층 정보',
    MODIFY COLUMN `unit_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '건물 호 정보',
    MODIFY COLUMN `longitude` decimal(12,9) NULL DEFAULT NULL COMMENT '경도',
    MODIFY COLUMN `latitude` decimal(11,9) NULL DEFAULT NULL COMMENT '위도',
    MODIFY COLUMN `data_reference_month` char(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '원천 데이터 기준 연월',
    MODIFY COLUMN `classification_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING' COMMENT '키워드 분류 상태',
    MODIFY COLUMN `classified_at` datetime NULL DEFAULT NULL COMMENT '키워드 분류 일시',
    MODIFY COLUMN `first_collected_at` datetime NOT NULL COMMENT '최초 수집 일시',
    MODIFY COLUMN `last_collected_at` datetime NOT NULL COMMENT '최근 수집 일시',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `semas_business_import_keywords`
    COMMENT = '상가업소 수집 데이터의 매칭 키워드',
    MODIFY COLUMN `import_id` bigint(20) NOT NULL COMMENT '상가업소 수집 원본 식별자',
    MODIFY COLUMN `keyword` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '매칭 키워드',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시';

ALTER TABLE `services`
    COMMENT = '수선·리폼 서비스 기준 정보',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '이름',
    MODIFY COLUMN `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL 식별 문자열',
    MODIFY COLUMN `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '상세 설명',
    MODIFY COLUMN `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '표시 순서',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `users`
    COMMENT = '모바일 앱 사용자',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '이름',
    MODIFY COLUMN `nickname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '사용자 닉네임',
    MODIFY COLUMN `tagline` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '사용자 한줄 소개',
    MODIFY COLUMN `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '이메일 주소',
    MODIFY COLUMN `phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '전화번호',
    MODIFY COLUMN `profile_image` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '프로필 이미지 경로',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시',
    MODIFY COLUMN `deleted_at` datetime NULL DEFAULT NULL COMMENT '소프트 삭제 일시';

ALTER TABLE `user_devices`
    COMMENT = '사용자 모바일 기기 및 푸시 토큰',
    MODIFY COLUMN `user_id` bigint(20) NOT NULL COMMENT '사용자 식별자',
    MODIFY COLUMN `device_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '기기 식별자',
    MODIFY COLUMN `platform` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '모바일 운영체제',
    MODIFY COLUMN `push_token` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '푸시 알림 토큰',
    MODIFY COLUMN `app_version` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '앱 버전',
    MODIFY COLUMN `locale` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '언어 및 지역 설정',
    MODIFY COLUMN `timezone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '시간대',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `user_favorites`
    COMMENT = '사용자 찜 업체',
    MODIFY COLUMN `user_id` bigint(20) NOT NULL COMMENT '사용자 식별자',
    MODIFY COLUMN `business_id` bigint(20) NOT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시';

ALTER TABLE `user_settings`
    COMMENT = '사용자 알림 및 마케팅 설정',
    MODIFY COLUMN `user_id` bigint(20) NOT NULL COMMENT '사용자 식별자',
    MODIFY COLUMN `push_enabled` smallint(6) NOT NULL DEFAULT 1 COMMENT '푸시 알림 수신 여부',
    MODIFY COLUMN `inquiry_notifications_enabled` smallint(6) NOT NULL DEFAULT 1 COMMENT '문의 알림 수신 여부',
    MODIFY COLUMN `review_notifications_enabled` smallint(6) NOT NULL DEFAULT 1 COMMENT '리뷰 알림 수신 여부',
    MODIFY COLUMN `marketing_consent` smallint(6) NOT NULL DEFAULT 0 COMMENT '마케팅 정보 수신 동의 여부',
    MODIFY COLUMN `nearby_recommendations_enabled` smallint(6) NOT NULL DEFAULT 1 COMMENT '주변 업체 추천 수신 여부',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `user_social_accounts`
    COMMENT = '사용자 소셜 로그인 계정',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `user_id` bigint(20) NOT NULL COMMENT '사용자 식별자',
    MODIFY COLUMN `provider` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '외부 제공자 코드',
    MODIFY COLUMN `provider_user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '외부 제공자 사용자 식별자',
    MODIFY COLUMN `provider_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '외부 제공자 이메일',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시';

ALTER TABLE `work_cases`
    COMMENT = '업체 작업 사례',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `business_id` bigint(20) NOT NULL COMMENT '업체 식별자',
    MODIFY COLUMN `category_id` bigint(20) NULL DEFAULT NULL COMMENT '카테고리 식별자',
    MODIFY COLUMN `brand_id` bigint(20) NULL DEFAULT NULL COMMENT '브랜드 식별자',
    MODIFY COLUMN `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '제목',
    MODIFY COLUMN `slug` varchar(220) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL 식별 문자열',
    MODIFY COLUMN `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '상세 설명',
    MODIFY COLUMN `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태',
    MODIFY COLUMN `published_at` datetime NULL DEFAULT NULL COMMENT '공개 일시',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시',
    MODIFY COLUMN `updated_at` datetime NOT NULL COMMENT '수정 일시',
    MODIFY COLUMN `deleted_at` datetime NULL DEFAULT NULL COMMENT '소프트 삭제 일시';

ALTER TABLE `work_case_images`
    COMMENT = '작업 사례 이미지',
    MODIFY COLUMN `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '레코드 식별자',
    MODIFY COLUMN `work_case_id` bigint(20) NOT NULL COMMENT '작업 사례 식별자',
    MODIFY COLUMN `storage_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '스토리지 유형',
    MODIFY COLUMN `file_path` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '파일 저장 경로',
    MODIFY COLUMN `image_role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '이미지 용도',
    MODIFY COLUMN `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '표시 순서',
    MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '생성 일시';

ALTER TABLE `work_case_services`
    COMMENT = '작업 사례별 서비스',
    MODIFY COLUMN `work_case_id` bigint(20) NOT NULL COMMENT '작업 사례 식별자',
    MODIFY COLUMN `service_id` bigint(20) NOT NULL COMMENT '서비스 식별자';

SET FOREIGN_KEY_CHECKS = 1;
