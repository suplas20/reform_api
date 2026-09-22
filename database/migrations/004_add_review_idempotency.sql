ALTER TABLE reviews
    ADD COLUMN idempotency_key VARCHAR(100) NULL AFTER user_id,
    ADD COLUMN request_hash VARCHAR(64) NULL AFTER idempotency_key,
    ADD UNIQUE KEY uq_reviews_user_idempotency (user_id, idempotency_key);
