ALTER TABLE users
    ADD COLUMN nickname VARCHAR(100) NULL AFTER name,
    ADD COLUMN tagline VARCHAR(200) NULL AFTER nickname,
    ADD COLUMN phone VARCHAR(30) NULL AFTER email;

ALTER TABLE auth_sessions
    ADD COLUMN device_id VARCHAR(100) NULL AFTER user_id,
    ADD INDEX idx_auth_sessions_device_id (device_id);

CREATE TABLE review_tags (
    review_id BIGINT NOT NULL,
    tag VARCHAR(50) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (review_id, tag),
    CONSTRAINT fk_review_tags_review
        FOREIGN KEY (review_id) REFERENCES reviews (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE review_helpful_users (
    review_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (review_id, user_id),
    INDEX idx_review_helpful_users_user_id (user_id),
    CONSTRAINT fk_review_helpful_users_review
        FOREIGN KEY (review_id) REFERENCES reviews (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_review_helpful_users_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE media_uploads (
    id VARCHAR(40) NOT NULL,
    user_id BIGINT NOT NULL,
    purpose VARCHAR(20) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    content_type VARCHAR(100) NOT NULL,
    expected_size BIGINT NOT NULL,
    storage_path VARCHAR(1000) NOT NULL,
    upload_token_hash VARCHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL,
    expires_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_media_uploads_user_status (user_id, status),
    INDEX idx_media_uploads_expires_at (expires_at),
    CONSTRAINT fk_media_uploads_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE inquiries (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    business_id BIGINT NOT NULL,
    inquiry_type VARCHAR(20) NOT NULL,
    item_category_id BIGINT NULL,
    preferred_date_type VARCHAR(30) NOT NULL,
    preferred_date DATE NULL,
    message VARCHAR(500) NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    closed_at DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_inquiries_user_status (user_id, status),
    INDEX idx_inquiries_business_id (business_id),
    CONSTRAINT fk_inquiries_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_inquiries_business
        FOREIGN KEY (business_id) REFERENCES businesses (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_inquiries_category
        FOREIGN KEY (item_category_id) REFERENCES categories (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE inquiry_media (
    inquiry_id BIGINT NOT NULL,
    media_id VARCHAR(40) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (inquiry_id, media_id),
    UNIQUE KEY uq_inquiry_media_media_id (media_id),
    CONSTRAINT fk_inquiry_media_inquiry
        FOREIGN KEY (inquiry_id) REFERENCES inquiries (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_inquiry_media_upload
        FOREIGN KEY (media_id) REFERENCES media_uploads (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE inquiry_replies (
    id BIGINT NOT NULL AUTO_INCREMENT,
    inquiry_id BIGINT NOT NULL,
    sender_type VARCHAR(20) NOT NULL,
    sender_name VARCHAR(100) NOT NULL,
    sender_avatar_path VARCHAR(1000) NULL,
    message VARCHAR(1000) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_inquiry_replies_inquiry_id (inquiry_id),
    CONSTRAINT fk_inquiry_replies_inquiry
        FOREIGN KEY (inquiry_id) REFERENCES inquiries (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE recent_businesses (
    user_id BIGINT NOT NULL,
    business_id BIGINT NOT NULL,
    viewed_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, business_id),
    INDEX idx_recent_businesses_user_viewed (user_id, viewed_at),
    CONSTRAINT fk_recent_businesses_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_recent_businesses_business
        FOREIGN KEY (business_id) REFERENCES businesses (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    notification_type VARCHAR(20) NOT NULL,
    title VARCHAR(200) NOT NULL,
    body VARCHAR(500) NOT NULL,
    image_path VARCHAR(1000) NULL,
    deep_link VARCHAR(500) NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_notifications_user_created (user_id, created_at),
    INDEX idx_notifications_user_read (user_id, read_at),
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE user_settings (
    user_id BIGINT NOT NULL,
    push_enabled SMALLINT NOT NULL DEFAULT 1,
    inquiry_notifications_enabled SMALLINT NOT NULL DEFAULT 1,
    review_notifications_enabled SMALLINT NOT NULL DEFAULT 1,
    marketing_consent SMALLINT NOT NULL DEFAULT 0,
    nearby_recommendations_enabled SMALLINT NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_user_settings_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE user_devices (
    user_id BIGINT NOT NULL,
    device_id VARCHAR(100) NOT NULL,
    platform VARCHAR(20) NOT NULL,
    push_token VARCHAR(500) NOT NULL,
    app_version VARCHAR(30) NOT NULL,
    locale VARCHAR(20) NULL,
    timezone VARCHAR(50) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, device_id),
    UNIQUE KEY uq_user_devices_push_token (push_token),
    CONSTRAINT fk_user_devices_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE help_categories (
    id BIGINT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_help_categories_status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE help_faqs (
    id BIGINT NOT NULL AUTO_INCREMENT,
    category_id BIGINT NOT NULL,
    question VARCHAR(300) NOT NULL,
    answer_html TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_help_faqs_category_status (category_id, status),
    CONSTRAINT fk_help_faqs_category
        FOREIGN KEY (category_id) REFERENCES help_categories (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE ads (
    id BIGINT NOT NULL AUTO_INCREMENT,
    placement VARCHAR(50) NOT NULL,
    advertiser VARCHAR(150) NOT NULL,
    headline VARCHAR(200) NOT NULL,
    body VARCHAR(500) NULL,
    image_url VARCHAR(1000) NOT NULL,
    call_to_action VARCHAR(50) NOT NULL,
    target_url VARCHAR(1000) NOT NULL,
    status VARCHAR(20) NOT NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_ads_placement_status (placement, status)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE ad_events (
    id BIGINT NOT NULL AUTO_INCREMENT,
    ad_id BIGINT NOT NULL,
    user_id BIGINT NULL,
    event_id VARCHAR(100) NOT NULL,
    event_type VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ad_events_event (ad_id, event_type, event_id),
    INDEX idx_ad_events_user_id (user_id),
    CONSTRAINT fk_ad_events_ad
        FOREIGN KEY (ad_id) REFERENCES ads (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_ad_events_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
