-- Additive, nullable representative coordinates; no user locations are stored here.
-- The small regions reference table is altered; existing codes, IDs and FKs are preserved.
ALTER TABLE regions
    ADD COLUMN center_latitude DECIMAL(10,7) NULL COMMENT '지역 대표지점 위도 WGS84',
    ADD COLUMN center_longitude DECIMAL(10,7) NULL COMMENT '지역 대표지점 경도 WGS84',
    ADD COLUMN center_source VARCHAR(40) NULL COMMENT '대표좌표 출처 및 기준일',
    ADD COLUMN center_source_code VARCHAR(30) NULL COMMENT '좌표 제공기관의 지역 코드',
    ADD COLUMN center_updated_at DATETIME NULL COMMENT '대표좌표 반영 시각 UTC';
