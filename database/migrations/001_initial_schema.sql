CREATE TABLE users (
    id BIGINT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NULL,
    email VARCHAR(255) NULL,
    profile_image VARCHAR(500) NULL,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_users_status (status),
    INDEX idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE admins (
    id BIGINT NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE regions (
    id BIGINT NOT NULL AUTO_INCREMENT,
    parent_id BIGINT NULL,
    code VARCHAR(30) NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    level SMALLINT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_regions_parent_id (parent_id),
    INDEX idx_regions_code (code),
    INDEX idx_regions_slug (slug),
    INDEX idx_regions_status (status),
    CONSTRAINT fk_regions_parent
        FOREIGN KEY (parent_id) REFERENCES regions (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE categories (
    id BIGINT NOT NULL AUTO_INCREMENT,
    parent_id BIGINT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug),
    INDEX idx_categories_parent_id (parent_id),
    INDEX idx_categories_status (status),
    CONSTRAINT fk_categories_parent
        FOREIGN KEY (parent_id) REFERENCES categories (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE services (
    id BIGINT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_services_slug (slug)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE brands (
    id BIGINT NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_brands_slug (slug)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE businesses (
    id BIGINT NOT NULL AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    short_description VARCHAR(500) NULL,
    description TEXT NULL,
    phone VARCHAR(30) NULL,
    supports_shipping SMALLINT NOT NULL DEFAULT 0,
    supports_visit SMALLINT NOT NULL DEFAULT 0,
    supports_pickup SMALLINT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL,
    claimed_user_id BIGINT NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_businesses_slug (slug),
    INDEX idx_businesses_status (status),
    INDEX idx_businesses_name (name),
    INDEX idx_businesses_claimed_user_id (claimed_user_id),
    INDEX idx_businesses_published_at (published_at),
    CONSTRAINT fk_businesses_claimed_user
        FOREIGN KEY (claimed_user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE user_social_accounts (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    provider VARCHAR(20) NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    provider_email VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_social_provider_account (provider, provider_user_id),
    UNIQUE KEY uq_social_user_provider (user_id, provider),
    CONSTRAINT fk_social_accounts_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE auth_sessions (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    access_token_hash VARCHAR(255) NOT NULL,
    refresh_token_hash VARCHAR(255) NOT NULL,
    access_expires_at DATETIME NOT NULL,
    refresh_expires_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_sessions_access_token_hash (access_token_hash),
    UNIQUE KEY uq_auth_sessions_refresh_token_hash (refresh_token_hash),
    INDEX idx_auth_sessions_user_id (user_id),
    INDEX idx_auth_sessions_refresh_expires_at (refresh_expires_at),
    CONSTRAINT fk_auth_sessions_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE category_services (
    category_id BIGINT NOT NULL,
    service_id BIGINT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (category_id, service_id),
    INDEX idx_category_services_service_id (service_id),
    CONSTRAINT fk_category_services_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_category_services_service
        FOREIGN KEY (service_id) REFERENCES services (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE business_locations (
    id BIGINT NOT NULL AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    region_id BIGINT NOT NULL,
    postal_code VARCHAR(20) NULL,
    road_address VARCHAR(300) NOT NULL,
    jibun_address VARCHAR(300) NULL,
    address_detail VARCHAR(300) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    is_primary SMALLINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_business_locations_business_id (business_id),
    INDEX idx_business_locations_region_id (region_id),
    INDEX idx_business_locations_coordinates (latitude, longitude),
    CONSTRAINT fk_business_locations_business
        FOREIGN KEY (business_id) REFERENCES businesses (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_business_locations_region
        FOREIGN KEY (region_id) REFERENCES regions (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE business_categories (
    business_id BIGINT NOT NULL,
    category_id BIGINT NOT NULL,
    is_primary SMALLINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (business_id, category_id),
    INDEX idx_business_categories_category_id (category_id),
    CONSTRAINT fk_business_categories_business
        FOREIGN KEY (business_id) REFERENCES businesses (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_business_categories_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE business_offerings (
    id BIGINT NOT NULL AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    category_id BIGINT NOT NULL,
    service_id BIGINT NOT NULL,
    title VARCHAR(200) NULL,
    description TEXT NULL,
    price_min DECIMAL(12,2) NULL,
    price_max DECIMAL(12,2) NULL,
    price_note VARCHAR(300) NULL,
    duration_min_days INT NULL,
    duration_max_days INT NULL,
    is_primary SMALLINT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_business_offerings_combination (business_id, category_id, service_id),
    INDEX idx_business_offerings_category_service (category_id, service_id),
    INDEX idx_business_offerings_service_id (service_id),
    INDEX idx_business_offerings_status (status),
    CONSTRAINT fk_business_offerings_business
        FOREIGN KEY (business_id) REFERENCES businesses (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_business_offerings_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_business_offerings_service
        FOREIGN KEY (service_id) REFERENCES services (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE business_brands (
    business_id BIGINT NOT NULL,
    brand_id BIGINT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (business_id, brand_id),
    INDEX idx_business_brands_brand_id (brand_id),
    CONSTRAINT fk_business_brands_business
        FOREIGN KEY (business_id) REFERENCES businesses (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_business_brands_brand
        FOREIGN KEY (brand_id) REFERENCES brands (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE business_hours (
    id BIGINT NOT NULL AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    day_of_week SMALLINT NOT NULL,
    sequence SMALLINT NOT NULL,
    open_time TIME NULL,
    close_time TIME NULL,
    is_closed SMALLINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_business_hours_business_id (business_id),
    CONSTRAINT fk_business_hours_business
        FOREIGN KEY (business_id) REFERENCES businesses (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE business_links (
    id BIGINT NOT NULL AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    link_type VARCHAR(30) NOT NULL,
    url VARCHAR(1000) NOT NULL,
    label VARCHAR(100) NULL,
    is_primary SMALLINT NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_business_links_business_id (business_id),
    CONSTRAINT fk_business_links_business
        FOREIGN KEY (business_id) REFERENCES businesses (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE business_images (
    id BIGINT NOT NULL AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    storage_type VARCHAR(20) NOT NULL,
    file_path VARCHAR(1000) NOT NULL,
    original_filename VARCHAR(255) NULL,
    mime_type VARCHAR(100) NULL,
    width INT NULL,
    height INT NULL,
    image_type VARCHAR(30) NOT NULL,
    alt_text VARCHAR(300) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_primary SMALLINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_business_images_business_id (business_id),
    CONSTRAINT fk_business_images_business
        FOREIGN KEY (business_id) REFERENCES businesses (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE work_cases (
    id BIGINT NOT NULL AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    category_id BIGINT NULL,
    brand_id BIGINT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_work_cases_business_id (business_id),
    INDEX idx_work_cases_category_id (category_id),
    INDEX idx_work_cases_brand_id (brand_id),
    INDEX idx_work_cases_status (status),
    INDEX idx_work_cases_published_at (published_at),
    CONSTRAINT fk_work_cases_business
        FOREIGN KEY (business_id) REFERENCES businesses (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_work_cases_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_work_cases_brand
        FOREIGN KEY (brand_id) REFERENCES brands (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE work_case_images (
    id BIGINT NOT NULL AUTO_INCREMENT,
    work_case_id BIGINT NOT NULL,
    storage_type VARCHAR(20) NOT NULL,
    file_path VARCHAR(1000) NOT NULL,
    image_role VARCHAR(20) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_work_case_images_work_case_id (work_case_id),
    CONSTRAINT fk_work_case_images_work_case
        FOREIGN KEY (work_case_id) REFERENCES work_cases (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE work_case_services (
    work_case_id BIGINT NOT NULL,
    service_id BIGINT NOT NULL,
    PRIMARY KEY (work_case_id, service_id),
    INDEX idx_work_case_services_service_id (service_id),
    CONSTRAINT fk_work_case_services_work_case
        FOREIGN KEY (work_case_id) REFERENCES work_cases (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_work_case_services_service
        FOREIGN KEY (service_id) REFERENCES services (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE user_favorites (
    user_id BIGINT NOT NULL,
    business_id BIGINT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, business_id),
    INDEX idx_user_favorites_business_id (business_id),
    CONSTRAINT fk_user_favorites_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_user_favorites_business
        FOREIGN KEY (business_id) REFERENCES businesses (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE business_sources (
    id BIGINT NOT NULL AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    source_type VARCHAR(30) NOT NULL,
    source_url VARCHAR(1000) NULL,
    external_id VARCHAR(255) NULL,
    collected_at DATETIME NULL,
    last_verified_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_business_sources_business_id (business_id),
    CONSTRAINT fk_business_sources_business
        FOREIGN KEY (business_id) REFERENCES businesses (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE business_verifications (
    id BIGINT NOT NULL AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    verification_type VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL,
    verified_by BIGINT NULL,
    verified_at DATETIME NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_business_verifications_business_id (business_id),
    INDEX idx_business_verifications_verified_by (verified_by),
    CONSTRAINT fk_business_verifications_business
        FOREIGN KEY (business_id) REFERENCES businesses (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_business_verifications_admin
        FOREIGN KEY (verified_by) REFERENCES admins (id)
        ON UPDATE RESTRICT ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE business_claims (
    id BIGINT NOT NULL AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    status VARCHAR(20) NOT NULL,
    requested_at DATETIME NOT NULL,
    reviewed_at DATETIME NULL,
    reviewed_by BIGINT NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_business_claims_business_id (business_id),
    INDEX idx_business_claims_user_id (user_id),
    INDEX idx_business_claims_reviewed_by (reviewed_by),
    CONSTRAINT fk_business_claims_business
        FOREIGN KEY (business_id) REFERENCES businesses (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_business_claims_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_business_claims_admin
        FOREIGN KEY (reviewed_by) REFERENCES admins (id)
        ON UPDATE RESTRICT ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE search_logs (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id BIGINT NULL,
    session_key VARCHAR(100) NULL,
    keyword VARCHAR(300) NULL,
    category_id BIGINT NULL,
    service_id BIGINT NULL,
    region_id BIGINT NULL,
    brand_id BIGINT NULL,
    result_count INT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_search_logs_created_at (created_at),
    INDEX idx_search_logs_keyword (keyword),
    INDEX idx_search_logs_category_id (category_id),
    INDEX idx_search_logs_service_id (service_id),
    INDEX idx_search_logs_region_id (region_id)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE event_logs (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id BIGINT NULL,
    session_key VARCHAR(100) NULL,
    event_name VARCHAR(50) NOT NULL,
    entity_type VARCHAR(30) NULL,
    entity_id BIGINT NULL,
    metadata TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_event_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE guides (
    id BIGINT NOT NULL AUTO_INCREMENT,
    title VARCHAR(250) NOT NULL,
    slug VARCHAR(250) NOT NULL,
    summary VARCHAR(500) NULL,
    content LONGTEXT NOT NULL,
    seo_title VARCHAR(250) NULL,
    meta_description VARCHAR(500) NULL,
    status VARCHAR(20) NOT NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_guides_slug (slug)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
