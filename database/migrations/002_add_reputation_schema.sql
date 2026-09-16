CREATE TABLE business_external_refs (
    id BIGINT NOT NULL AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    provider VARCHAR(30) NOT NULL,
    external_id VARCHAR(255) NULL,
    external_url VARCHAR(1000) NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_business_external_refs_business_provider (business_id, provider),
    UNIQUE KEY uq_business_external_refs_provider_external_id (provider, external_id),
    CONSTRAINT fk_business_external_refs_business
        FOREIGN KEY (business_id) REFERENCES businesses (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE business_external_ratings (
    id BIGINT NOT NULL AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    provider VARCHAR(30) NOT NULL,
    rating DECIMAL(3,2) NULL,
    review_count INT NULL,
    fetched_at DATETIME NOT NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_business_external_ratings_business_provider (business_id, provider),
    INDEX idx_business_external_ratings_provider (provider),
    INDEX idx_business_external_ratings_rating (rating),
    INDEX idx_business_external_ratings_review_count (review_count),
    INDEX idx_business_external_ratings_fetched_at (fetched_at),
    INDEX idx_business_external_ratings_expires_at (expires_at),
    CONSTRAINT fk_business_external_ratings_ref
        FOREIGN KEY (business_id, provider)
        REFERENCES business_external_refs (business_id, provider)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE reviews (
    id BIGINT NOT NULL AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    rating SMALLINT NOT NULL,
    content TEXT NULL,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_reviews_business_id (business_id),
    INDEX idx_reviews_user_id (user_id),
    INDEX idx_reviews_status (status),
    INDEX idx_reviews_created_at (created_at),
    INDEX idx_reviews_business_status (business_id, status),
    CONSTRAINT chk_reviews_rating
        CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT fk_reviews_business
        FOREIGN KEY (business_id) REFERENCES businesses (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE review_images (
    id BIGINT NOT NULL AUTO_INCREMENT,
    review_id BIGINT NOT NULL,
    storage_type VARCHAR(20) NOT NULL,
    file_path VARCHAR(1000) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_review_images_review_id (review_id),
    CONSTRAINT fk_review_images_review
        FOREIGN KEY (review_id) REFERENCES reviews (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE business_reputation_stats (
    business_id BIGINT NOT NULL,
    review_count INT NOT NULL DEFAULT 0,
    rating_avg DECIMAL(3,2) NULL,
    favorite_count INT NOT NULL DEFAULT 0,
    work_case_count INT NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (business_id),
    CONSTRAINT fk_business_reputation_stats_business
        FOREIGN KEY (business_id) REFERENCES businesses (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO business_reputation_stats (
    business_id,
    review_count,
    rating_avg,
    favorite_count,
    work_case_count,
    updated_at
)
SELECT
    businesses.id,
    0,
    NULL,
    (
        SELECT COUNT(*)
        FROM user_favorites
        WHERE user_favorites.business_id = businesses.id
    ),
    (
        SELECT COUNT(*)
        FROM work_cases
        WHERE work_cases.business_id = businesses.id
          AND work_cases.deleted_at IS NULL
    ),
    UTC_TIMESTAMP()
FROM businesses;
