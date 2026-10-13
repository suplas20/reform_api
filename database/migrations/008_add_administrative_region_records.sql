-- Additive raw-data storage. Existing region codes, IDs and business links are unchanged.
-- New empty tables only; no backfill, destructive DDL or large-table index rebuild.
CREATE TABLE administrative_region_imports (
    id BIGINT NOT NULL AUTO_INCREMENT COMMENT '수집 스냅샷 식별자',
    snapshot_key VARCHAR(100) NOT NULL COMMENT '중복 실행 방지용 수집 스냅샷 키',
    dataset_version VARCHAR(30) NOT NULL COMMENT '제공 명세의 자료 버전',
    endpoint_path VARCHAR(200) NOT NULL COMMENT '인증키가 제외된 제공 API 경로',
    page_size INT NOT NULL COMMENT '페이지당 수집 건수',
    total_count INT NOT NULL COMMENT 'API가 응답한 전체 원본 행 수',
    last_completed_page INT NOT NULL DEFAULT 0 COMMENT '저장이 완료된 마지막 페이지',
    fetched_count INT NOT NULL DEFAULT 0 COMMENT '저장 완료한 원본 행 수',
    first_page_hash CHAR(64) NOT NULL COMMENT '최초 페이지의 내용 검증 해시',
    last_page_hash CHAR(64) NULL COMMENT '마지막 완료 페이지의 내용 검증 해시',
    status VARCHAR(20) NOT NULL COMMENT '수집 상태 COLLECTING 또는 COMPLETE',
    started_at DATETIME NOT NULL COMMENT '수집 시작 시각 UTC',
    updated_at DATETIME NOT NULL COMMENT '수집 상태 수정 시각 UTC',
    completed_at DATETIME NULL COMMENT '전체 수집 완료 시각 UTC',
    PRIMARY KEY (id),
    UNIQUE KEY uq_administrative_import_snapshot (snapshot_key)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='행정구역 원본 수집 스냅샷 및 재개 위치';

CREATE TABLE administrative_region_records (
    import_id BIGINT NOT NULL COMMENT '원본 수집 스냅샷 식별자',
    source_row_number INT NOT NULL COMMENT '해당 스냅샷 내 API 원본 행 위치',
    source_record_number VARCHAR(30) NULL COMMENT '원본 행정동번호',
    revision_text VARCHAR(30) NULL COMMENT '원본 개정일자 문자열',
    revision_date DATE NULL COMMENT '날짜 형식이 검증된 개정일자',
    link_number VARCHAR(30) NULL COMMENT '원본 연결번호',
    administrative_code VARCHAR(30) NULL COMMENT '통계용 원본 행정동코드',
    name VARCHAR(100) NULL COMMENT '원본 행정동명',
    background_flag VARCHAR(30) NULL COMMENT '원본 배경여부 값',
    top_level_code VARCHAR(30) NULL COMMENT '원본 최상위행정동코드',
    parent_code VARCHAR(30) NULL COMMENT '원본 부모행정동코드',
    source_sequence VARCHAR(30) NULL COMMENT '원본 순번',
    record_hash CHAR(64) NOT NULL COMMENT '원본 아홉 필드의 내용 검증 해시',
    region_id BIGINT NULL COMMENT '검증 후 연결할 서비스 지역 식별자',
    collected_at DATETIME NOT NULL COMMENT '원본 저장 시각 UTC',
    PRIMARY KEY (import_id, source_row_number),
    INDEX idx_administrative_record_code (import_id, administrative_code, revision_date),
    INDEX idx_administrative_record_revision (import_id, revision_date),
    CONSTRAINT fk_administrative_record_import FOREIGN KEY (import_id) REFERENCES administrative_region_imports (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_administrative_record_region FOREIGN KEY (region_id) REFERENCES regions (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='행정동 원본 및 개정 이력 보관';
