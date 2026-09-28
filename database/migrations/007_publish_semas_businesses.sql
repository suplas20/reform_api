UPDATE businesses
INNER JOIN semas_business_imports
    ON semas_business_imports.business_id = businesses.id
SET businesses.status = 'ACTIVE',
    businesses.published_at = COALESCE(businesses.published_at, UTC_TIMESTAMP()),
    businesses.updated_at = UTC_TIMESTAMP()
WHERE businesses.claimed_user_id IS NULL
  AND businesses.deleted_at IS NULL
  AND (
      businesses.status = 'DRAFT'
      OR (businesses.status = 'ACTIVE' AND businesses.published_at IS NULL)
  );
