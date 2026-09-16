# AGENTS.MD

This file defines the mandatory rules for AI coding agents working in this repository.

The project is a Korean reform / repair / restoration business discovery platform.

This document is authoritative for implementation decisions unless the user explicitly approves a change.

---

# 1. Product Model

The system has four distinct surfaces.

```text
Mobile App
= Primary product

REST API
= Primary backend interface

Public Web
= SEO / marketing / app acquisition

Admin
= Internal operations tool
```

Do not treat the Public Web as a full duplicate of the mobile product.

The primary user experience lives in the mobile app.

---

# 2. Current Infrastructure

Production hosting:

```text
Cafe24 New Autobahn Business shared web hosting
```

Backend runtime:

```text
PHP 8.4
```

Database:

```text
MariaDB
```

Public Web:

```text
PHP SSR
HTML5
CSS3
Vanilla JavaScript
```

Mobile:

```text
React Native
TypeScript
```

---

# 3. Hosting Constraints

The production environment is shared hosting.

Never assume access to:

```text
root
Docker
Redis Server
Supervisor
long-running workers
WebSocket server
custom daemon
OS package installation
custom DB server installation
server-side Node.js build
runtime Composer install
runtime shell execution
persistent background process
```

Do not introduce an implementation that depends on these capabilities without explicit user approval.

---

# 4. Allowed Core Runtime Libraries

The default runtime dependencies are:

```text
symfony/routing:^7.4
symfony/http-foundation:^7.4
monolog/monolog:^3
swagger-api/swagger-ui:^5.32.15
PDO
```

Symfony Full Framework is NOT used.

Laravel is NOT used.

CodeIgniter is NOT used.

No ORM is currently used.

---

# 5. ORM Policy

The current project intentionally does NOT use an ORM.

Do not add:

```text
Eloquent
Doctrine ORM
Cycle ORM
Propel
custom ORM
Active Record layer
```

without explicit user approval.

The persistence architecture is:

```text
Controller
   ↓
Service
   ↓
Repository
   ↓
PDO Prepared Statements
   ↓
MariaDB
```

The reason for this decision is:

```text
simple shared-hosting deployment
lower dependency footprint
clear SQL behavior
search-heavy application requirements
future PostgreSQL migration control
explicit query optimization
security through prepared statements and strict query rules
```

ORM is not required for SQL injection protection.

Security must be implemented through:

```text
PDO Prepared Statements
Parameter Binding
Strict Input Validation
Identifier Whitelisting
Repository Isolation
Least Privilege
```

---

# 6. Composer Policy

Composer runs locally.

Production deployment includes the complete `vendor/` directory.

Use:

```json
{
  "config": {
    "platform": {
      "php": "8.4.0"
    }
  }
}
```

Production install target:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

Do not design a deployment process that requires `composer install` to run on Cafe24.

---

# 7. New Dependency Policy

Do not add a package unless it satisfies all of the following:

1. PHP 8.4 compatible
2. Runs on Cafe24 shared hosting
3. Does not require a daemon
4. Does not require unavailable OS packages
5. Does not require unavailable PHP extensions
6. Does not require runtime shell commands
7. Does not require server-side Node.js
8. Does not require long-running background execution
9. Actively maintained
10. No known severe unresolved security issue

When uncertain, do not add the dependency.

Prefer PHP standard library and existing approved packages.

---

# 8. Mandatory Architecture

All application logic must follow:

```text
Controller
   ↓
Service
   ↓
Repository
   ↓
PDO
   ↓
MariaDB
```

Do not bypass this structure.

Allowed exceptions must be explicit and justified.

---

# 9. Project Directory Structure

Expected backend structure:

```text
reform-platform/
│
├── public/
│   ├── index.php
│   ├── .htaccess
│   └── assets/
│       ├── css/
│       ├── js/
│       └── images/
│
├── bin/
│   ├── check-database.php
│   └── migrate.php
│
├── src/
│   ├── Controller/
│   │   ├── Api/
│   │   │   ├── V1/
│   │   │   └── ApiDocumentationController.php
│   │   ├── Web/
│   │   └── Admin/
│   │
│   ├── Service/
│   ├── Repository/
│   ├── Database/
│   ├── Auth/
│   ├── Http/
│   │   └── Middleware/
│   ├── Exception/
│   ├── Storage/
│   └── Support/
│
├── routes/
│   ├── api.php
│   ├── web.php
│   └── admin.php
│
├── views/
│   ├── web/
│   │   ├── layouts/
│   │   ├── home/
│   │   ├── business/
│   │   ├── category/
│   │   ├── region/
│   │   ├── case/
│   │   ├── guide/
│   │   └── errors/
│   │
│   └── admin/
│       ├── layouts/
│       ├── dashboard/
│       ├── business/
│       ├── category/
│       ├── service/
│       ├── region/
│       ├── brand/
│       └── case/
│
├── config/
│   ├── app.php
│   ├── database.php
│   └── services.php
│
├── database/
│   ├── migrations/
│   └── seeds/
│
├── openapi/
│   └── openapi.json
│
├── storage/
│   ├── logs/
│   ├── cache/
│   └── uploads/
│
├── tests/
├── vendor/
├── composer.json
└── composer.lock
```

---

# 10. Front Controller

All application requests must enter through:

```text
public/index.php
```

`index.php` must remain minimal.

It may:

```text
load Composer autoload
create Request
bootstrap application
dispatch router
send Response
```

It must not contain business logic.

---

# 11. Routing

Use:

```text
Symfony Routing 7.4 LTS
```

Do not build a custom router.

Routes are separated by surface:

```text
routes/api.php
routes/web.php
routes/admin.php
```

---

# 12. Controller Rules

Controllers are responsible for:

```text
Request parsing
Validation orchestration
Service calls
Response creation
```

Controllers must NOT contain:

```text
SQL
PDO queries
large business rules
storage implementation details
database transaction implementation details
```

Separate controllers by surface:

```text
src/Controller/Api/V1/
src/Controller/Web/
src/Controller/Admin/
```

Do not share controllers between Web and API.

---

# 13. Service Rules

Services own business logic and use cases.

Examples:

```text
BusinessService
SearchService
AuthService
FavoriteService
EventService
```

Services may:

```text
coordinate multiple repositories
apply domain rules
control transaction boundaries through a dedicated transaction helper
```

Services must NOT:

```text
render HTML
contain SQL
directly call PDO
depend on database-specific SQL
perform raw HTTP response formatting
```

---

# 14. Repository Rules

All SQL belongs in Repository classes.

Examples:

```text
BusinessRepository
CategoryRepository
ServiceRepository
RegionRepository
BrandRepository
UserRepository
FavoriteRepository
AuthSessionRepository
WorkCaseRepository
```

Do not write SQL in:

```text
Controller
Service
View
JavaScript
Template
Middleware
```

Repositories are the only layer allowed to know SQL details.

---

# 15. PDO Configuration

Use PDO for MariaDB.

Recommended configuration:

```php
[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false,
]
```

Required rules:

```text
ERRMODE_EXCEPTION
native prepared statements where possible
no persistent connection
utf8mb4 charset
```

---

# 16. SQL Security — Absolute Rules

All externally supplied values must be treated as untrusted.

Never concatenate untrusted values directly into SQL.

Forbidden:

```php
$sql = "SELECT * FROM businesses WHERE id = " . $_GET['id'];
```

Forbidden:

```php
$sql = "SELECT * FROM businesses WHERE name = '$keyword'";
```

Forbidden:

```php
$sql = "ORDER BY " . $_GET['sort'];
```

Use prepared statements and parameter binding.

Correct:

```php
$stmt = $pdo->prepare(
    'SELECT *
     FROM businesses
     WHERE id = :id'
);

$stmt->execute([
    'id' => $id,
]);
```

---

# 17. Parameter Binding Rules

All SQL values must use placeholders.

Preferred named placeholder style:

```php
$sql = '
    SELECT *
    FROM businesses
    WHERE id = :id
      AND status = :status
';

$stmt = $pdo->prepare($sql);

$stmt->execute([
    'id' => $id,
    'status' => 'ACTIVE',
]);
```

Positional placeholders may be used when they are materially simpler.

Never interpolate request input into SQL text.

---

# 18. SQL Identifier Rules

Prepared statements cannot safely parameterize SQL identifiers such as:

```text
table name
column name
ORDER BY column
ASC / DESC
SQL function
JOIN target
```

Therefore dynamic identifiers must come from strict server-defined whitelists.

Example:

```php
$sortMap = [
    'latest' => 'b.created_at DESC',
    'name' => 'b.name ASC',
    'distance' => 'distance ASC',
];

$orderBy = $sortMap[$sort] ?? $sortMap['latest'];
```

Only pre-defined SQL fragments may be inserted.

Never pass raw user input into:

```text
ORDER BY
GROUP BY
SELECT column
table identifier
sort direction
```

---

# 19. ORDER BY Security

Forbidden:

```php
$sql .= ' ORDER BY ' . $request->query->get('sort');
```

Required:

```php
$sortMap = [
    'latest' => 'b.created_at DESC',
    'oldest' => 'b.created_at ASC',
    'name' => 'b.name ASC',
];

$orderBy = $sortMap[$requestedSort] ?? $sortMap['latest'];

$sql .= " ORDER BY {$orderBy}";
```

The map values must be hard-coded server-controlled values.

---

# 20. IN Query Security

Never build `IN (...)` directly from request strings.

Forbidden:

```php
$sql = 'WHERE service_id IN (' . implode(',', $_GET['service_ids']) . ')';
```

Required:

```php
$ids = [1, 3, 7];

$placeholders = implode(
    ',',
    array_fill(0, count($ids), '?')
);

$sql = "
    SELECT *
    FROM business_offerings
    WHERE service_id IN ($placeholders)
";

$stmt = $pdo->prepare($sql);
$stmt->execute($ids);
```

Before query execution:

```text
validate type
validate count
validate allowed range
remove duplicates if appropriate
```

Empty `IN` lists must be handled explicitly.

Do not generate invalid SQL such as:

```sql
IN ()
```

---

# 21. LIKE Query Security

Use bound parameters.

Correct:

```php
$sql = '
    SELECT *
    FROM businesses
    WHERE name LIKE :keyword
';

$stmt = $pdo->prepare($sql);

$stmt->execute([
    'keyword' => '%' . $keyword . '%',
]);
```

Do not concatenate the keyword directly into SQL.

If `%` or `_` must be treated as literal characters rather than wildcard operators, escape them deliberately and define an `ESCAPE` rule.

---

# 22. LIMIT / OFFSET Rules

Pagination inputs must be validated before SQL construction.

Example rules:

```text
page >= 1
perPage >= 1
perPage <= configured maximum
offset >= 0
```

Recommended maximum:

```text
perPage <= 100
```

Where driver behavior makes bound LIMIT/OFFSET awkward, integers may be inserted only after strict integer casting and range validation.

Example:

```php
$page = max(1, (int) $page);
$perPage = min(max(1, (int) $perPage), 100);
$offset = ($page - 1) * $perPage;
```

Only validated integer values may be interpolated.

---

# 23. Input Validation Rules

Prepared statements protect query structure.

They do NOT replace input validation.

Validate input before repository execution.

Examples:

```text
ID
→ positive integer

page
→ integer >= 1

perPage
→ integer 1..100

latitude
→ -90..90

longitude
→ -180..180

radius
→ configured min/max range

status
→ whitelist

provider
→ whitelist

sort
→ whitelist
```

Never rely on implicit PHP type coercion for security-sensitive input.

---

# 24. SQL Injection Defense Model

The required security model is:

```text
HTTP Request
    ↓
Input Validation
    ↓
Controller
    ↓
Service
    ↓
Repository
    ↓
Prepared Statement
    ↓
Parameter Binding
    ↓
MariaDB
```

For dynamic SQL structure:

```text
User Input
    ↓
Whitelist Mapping
    ↓
Server-controlled SQL Fragment
```

---

# 25. Database Least Privilege

Production DB credentials should use the minimum privileges supported by Cafe24.

Application runtime should not have unnecessary administrative privileges.

Avoid granting capabilities equivalent to:

```text
CREATE USER
GRANT
SUPER
FILE
```

unless the hosting environment requires them and the user explicitly approves the configuration.

Migration and runtime privileges should be separated if Cafe24 allows it.

---

# 26. Database Portability

Current DB:

```text
MariaDB
```

Potential future DB:

```text
PostgreSQL
```

Therefore prefer:

```text
BIGINT
INT
VARCHAR
TEXT
DECIMAL
DATE
DATETIME
FK
JOIN TABLES
PREPARED STATEMENTS
TRANSACTIONS
```

Avoid or minimize:

```text
ENUM
UNSIGNED dependency
MariaDB-only functions
stored-procedure business logic
searchable core data stored as JSON
vendor-specific SQL
```

Vendor-specific SQL, when unavoidable, must remain isolated inside Repositories.

---

# 27. Core Domain Model

Primary domains:

```text
Business
Category
Service
Region
Brand
WorkCase
User
SocialAccount
AuthSession
Favorite
BusinessSource
BusinessVerification
BusinessClaim
BusinessExternalRef
BusinessExternalRating
BusinessReputationStats
Review
SearchLog
EventLog
Admin
```

Do not introduce unrelated domains without need.

---

# 28. Database Relationship Rules

Use normalized relational tables.

Do NOT store values like:

```text
categories = "bag,shoes,clothing"
services = "repair,dye,restore"
brands = "Chanel,Gucci"
```

Use join tables:

```text
category_services
business_categories
business_offerings
business_brands
```

Use unique constraints to prevent duplicate relationships.

`business_offerings` represents the exact Business + Category + Service combination.
Do not reintroduce a separate `business_services` table.

---

# 29. Expected Business Tables

Expected tables include:

```text
businesses
business_locations
business_hours
business_links
business_images
business_sources
business_verifications
business_claims

categories
services
regions
brands
category_services

business_categories
business_offerings
business_brands

work_cases
work_case_images
work_case_services

business_external_refs
business_external_ratings
reviews
review_images
business_reputation_stats

guides
```

The current initial schema source of truth is:

```text
.docs/reform_platform_database_design.md
database/migrations/001_initial_schema.sql
database/migrations/002_add_reputation_schema.sql
```

Exact final columns are still subject to ERD refinement.

Do not silently treat the current draft as immutable schema.

## 29.1 Reputation and Review Rules

The authoritative reputation design is:

```text
.docs/reform_platform_reputation_design_change.md
```

Keep internal and external reputation data separate.

Never store external review:

```text
body
author
photo
```

Provider policy:

```text
GOOGLE = official Places API reference and permitted rating statistics
NAVER = Place ID and external URL only until officially permitted
```

`business_links` stores channels operated by the Business.
`business_external_refs` stores identifiers and URLs owned by external platforms.
Do not merge these tables.

`business_sources` stores provenance and verification history.
Its migration and source-of-truth relationship with `business_external_refs` remains unresolved.
Do not copy or move provider identifiers between these tables without an explicit decision.

Update `business_reputation_stats` synchronously in the same transaction as the related Review or Favorite write.
Do not introduce a queue or background worker for this aggregation.

The qualifying WorkCase statuses and update points for `work_case_count` remain unresolved.

Review creation, uniqueness, editing, deletion (`status = DELETED` versus `deleted_at`), verification, moderation, reporting, reply, and image-limit policies remain unresolved.
Do not invent these rules during implementation.

---

# 30. Expected User Tables

Expected:

```text
users
user_social_accounts
auth_sessions
user_favorites
```

Password-based public user registration is not part of the current v1 plan.

---

# 31. Public Web Rules

Public Web exists for:

```text
SEO
search-engine acquisition
marketing
public business information
public work cases
guides/content
app-install conversion
```

Public Web is primarily read-only.

Do NOT implement normal user features on the Public Web unless explicitly requested.

Do not add:

```text
user registration
user login
favorites
user dashboard
personalization
mobile-only product features
```

---

# 32. Public Web Routes

Public routes may include:

```text
/
/businesses/{id}/{slug}
/categories/{category}
/categories/{category}/{service}
/regions/{sido}
/regions/{sido}/{sigungu}
/regions/{sido}/{sigungu}/{category}
/cases
/cases/{id}/{slug}
/guides
/guides/{slug}
/app
/about
```

The Public Web is not a second full product.

---

# 33. Mobile App Rules

Mobile App is the primary user-facing product.

Technology:

```text
React Native
TypeScript
```

The mobile app accesses data only through:

```text
/api/v1/*
```

The mobile app never connects directly to MariaDB.

---

# 34. API Versioning

API prefix:

```text
/api/v1
```

Do not make breaking changes to existing v1 contracts without explicit approval.

If a breaking contract is necessary, introduce a new version.

## 34.1 API Documentation

The version-controlled OpenAPI source is:

```text
openapi/openapi.json
```

Swagger UI is available at:

```text
GET /api/docs
GET /api/docs/openapi.json
```

Rules:

- Update the OpenAPI document in the same change as every API contract change.
- Never include credentials, tokens, provider secrets, internal paths, or private Admin details.
- Serve only explicitly allowlisted Swagger UI assets; never expose the `vendor/` directory.
- Keep `swagger-api/swagger-ui` pinned to a reviewed version.
- The OpenAPI document defines the external API contract; database rows and internal DTOs are not the contract.

---

# 35. API Response Rules

Success:

```json
{
  "success": true,
  "data": {}
}
```

List:

```json
{
  "success": true,
  "data": [],
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 0,
    "totalPages": 0
  }
}
```

Error:

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "사용자에게 보여줄 메시지"
  }
}
```

---

# 36. HTTP Status Rules

Use appropriate statuses.

```text
200 OK
201 Created
204 No Content

400 Bad Request
401 Unauthorized
403 Forbidden
404 Not Found
409 Conflict
422 Unprocessable Entity
429 Too Many Requests

500 Internal Server Error
```

Do not return HTTP 200 for every failure.

---

# 37. Initial API Areas

Reference data:

```text
GET /api/v1/categories
GET /api/v1/services
GET /api/v1/regions
GET /api/v1/brands
```

Business:

```text
GET /api/v1/businesses
GET /api/v1/businesses/{id}
```

The implemented Business detail v1 contract is defined in `.docs/reform_platform_system_design.md`.

Public Business detail queries must require:

```text
status = ACTIVE
deleted_at IS NULL
published_at is not null and not in the future
```

Business list v1 pagination:

```text
page default = 1
perPage default = 20
perPage maximum = 100
sort = published_at DESC, id DESC
```

Business list external reputation must be loaded with a batch query.
Do not introduce an N+1 query per Business.

Work cases:

```text
GET /api/v1/work-cases
GET /api/v1/work-cases/{id}
```

Search:

```text
GET /api/v1/search
GET /api/v1/search/suggestions
```

Authentication:

```text
POST /api/v1/auth/social
POST /api/v1/auth/refresh
POST /api/v1/auth/logout
GET  /api/v1/me
PATCH /api/v1/me
DELETE /api/v1/me
```

Favorites:

```text
GET    /api/v1/favorites
POST   /api/v1/favorites/{businessId}
DELETE /api/v1/favorites/{businessId}
```

Events:

```text
POST /api/v1/events
```

---

# 38. Authentication Model

Public Web:

```text
No normal user authentication
```

Mobile users:

```text
SNS Login
→ backend provider verification
→ application access token
→ application refresh token
```

Admin:

```text
email/password
→ PHP Session
```

Do not mix these authentication models.

---

# 39. Social Login Rules

Expected providers may include:

```text
KAKAO
NAVER
GOOGLE
APPLE
```

Do not add provider-specific columns to `users`.

Use:

```text
users
user_social_accounts
```

The backend must verify provider credentials/tokens.

Do not trust a provider user ID sent directly by the client.

Provider tokens are not application API tokens.

---

# 40. Token Rules

Application authentication should use:

```text
Access Token
Refresh Token
```

Store hashes, not raw tokens.

Never log:

```text
access token
refresh token
provider token
session ID
password
```

Token lifetime and rotation policy are not yet final.

Do not invent final values unless requested.

---

# 41. Admin Authentication

Admin authentication is separate from user authentication.

Expected model:

```text
admins
PHP Session
```

Admin password handling must use:

```php
password_hash()
password_verify()
```

Apply CSRF protection to session-based Admin state-changing requests.

---

# 42. Search Architecture

Search flow:

```text
SearchController
      ↓
SearchService
      ↓
BusinessRepository
```

Search logic must not be embedded in controllers or views.

Future search-engine replacement should remain possible.

Potential future engines:

```text
Meilisearch
Typesense
OpenSearch
```

Do not add them in v1 unless explicitly approved.

---

# 43. Search Query Construction Rules

Search filters must be assembled from validated conditions.

Recommended pattern:

```text
validated keyword
validated category IDs
validated service IDs
validated region IDs
validated brand IDs
validated coordinates
validated radius
validated sort key
validated pagination
```

Only the Repository constructs the SQL.

The Service may build a search criteria object/array, but must not build SQL strings.

---

# 44. Location Rules

Always preserve raw:

```text
latitude
longitude
```

Do not design location data in a way that blocks future PostgreSQL/PostGIS migration.

Validate coordinate range before repository calls.

---

# 45. Logging Tables

Expected:

```text
search_logs
event_logs
```

Event examples:

```text
SEARCH
FILTER
BUSINESS_IMPRESSION
BUSINESS_VIEW
BUSINESS_CLICK
FAVORITE
CALL_CLICK
MAP_CLICK
WEBSITE_CLICK
SNS_CLICK
CASE_VIEW
SHARE
EXTERNAL_GOOGLE_CLICK
EXTERNAL_NAVER_CLICK
REVIEW_VIEW
REVIEW_CREATE
REVIEW_UPDATE
REVIEW_DELETE
```

Do not block critical user requests on complex analytics processing.

No queue infrastructure should be assumed.

---

# 46. File / Image Rules

Do not store binary images in MariaDB.

Store file metadata/path.

Initial storage:

```text
Cafe24 local storage
```

Future storage may be:

```text
Cloudflare R2
AWS S3
S3-compatible object storage
```

Keep storage access behind a dedicated storage abstraction/service.

---

# 47. Upload Security

For every upload validate:

```text
maximum file size
allowed extension
actual MIME type
random generated filename
storage path
image dimensions if necessary
```

Do not trust the original filename.

Do not allow executable PHP files inside public upload directories.

Do not determine file type from extension alone.

If `fileinfo` is available, prefer real MIME inspection.

Do not assume `gd` or `imagick` until the Cafe24 runtime is verified.

---

# 48. General Security Rules

Mandatory:

```text
PDO prepared statements
strict input validation
identifier whitelisting
output escaping
central exception handling
secure token generation
token hashing
password_hash/password_verify for admin
CSRF for session-based state changes
upload validation
least-privilege DB access
```

Never expose in production:

```text
stack trace
SQL error
filesystem path
DB credentials
API secrets
tokens
session IDs
internal exception details
```

---

# 49. XSS Rules

Escape untrusted values when rendering HTML.

Do not assume DB content is safe because it came from Admin.

Treat persisted content as untrusted unless explicitly sanitized and controlled.

Avoid raw HTML rendering unless the content has a defined sanitization policy.

---

# 50. CSRF Rules

Apply CSRF protection to:

```text
Admin session-based POST
Admin session-based PUT/PATCH/DELETE
other browser-session state-changing requests
```

Do not apply browser-session CSRF patterns blindly to Bearer-token mobile API requests.

---

# 51. Exception Handling

Use centralized exception handling.

Suggested mapping:

```text
NotFoundException      → 404
ValidationException    → 422
UnauthorizedException  → 401
ForbiddenException     → 403
ConflictException      → 409
RateLimitException     → 429
Throwable              → 500
```

Avoid repetitive controller-level try/catch when the global handler can handle the error.

---

# 52. Logging

Use Monolog.

Default production target:

```text
storage/logs/app.log
```

Never log:

```text
password
access token
refresh token
provider token
session ID
DB password
API secret
sensitive personal data unless strictly necessary and reviewed
```

---

# 53. Cache

Redis is not part of v1.

If caching is needed:

```text
file cache
```

The application must work correctly without cache.

Cache failure must not become a critical application dependency.

---

# 54. Queue / Worker Restrictions

Do not introduce:

```text
queue worker
Redis queue
Supervisor
WebSocket worker
long-running daemon
background process requiring persistent execution
```

unless hosting changes or the user explicitly approves it.

---

# 55. SEO Rules

Public Web pages must be SSR.

Support:

```text
semantic HTML
title
meta description
canonical
OpenGraph
robots.txt
sitemap.xml
structured data
human-readable URLs
```

Avoid massive generation of thin SEO pages.

Do not automatically index every combination of:

```text
region × category × service × brand
```

Only meaningful pages with sufficient real data should be indexable.

---

# 56. View Rules

`views/` exists only for:

```text
Public Web HTML
Admin HTML
Error HTML
```

API never renders View templates.

Preferred separation:

```text
views/web/
views/admin/
```

View files must not:

```text
query DB
call Repository
call Service
contain business rules
```

---

# 57. Coding Style

Use:

```php
declare(strict_types=1);
```

Prefer:

```text
PSR-4
PSR-12
typed properties
parameter types
return types
final classes where appropriate
small focused classes
```

Naming:

```text
Class        PascalCase
Method       camelCase
Variable     camelCase
DB           snake_case
API URL      kebab-case
```

---

# 58. Avoid Overengineering

Do not build:

```text
custom framework
custom ORM
complex DI framework
generic event bus
microservices
CQRS
event sourcing
GraphQL
generic repository framework
generic base service hierarchy
```

unless a concrete requirement justifies it and the user approves it.

Build the product first.

---

# 59. Migration Rules

Database schema changes must be represented by version-controlled migration files.

Do not manually change production schema without recording the change.

The approved migration structure is:

```text
database/migrations/*.sql
src/Database/MigrationRunner.php
bin/migrate.php
schema_migrations
```

`bin/` contains local maintenance CLI entry points only.

Migration and database-check commands:

```text
composer db:check
composer db:status
composer db:migrate
```

These commands must never:

```text
run during an HTTP request
be exposed as a Public Web or API route
be required for normal application runtime
assume production shell access
```

Cafe24 production schema changes must use the reviewed SQL migration files through an explicitly approved deployment procedure.

Migration SQL must be reviewed for:

```text
MariaDB compatibility
data loss risk
lock risk
index impact
future PostgreSQL migration impact
```

Avoid destructive migrations unless explicitly approved.

---

# 60. Transaction Rules

Use transactions for multi-step writes that must succeed or fail atomically.

Examples:

```text
create user + social account + auth session
approve business claim + update business ownership
create work case + insert related service mappings
```

Do not start transactions for simple independent reads.

Transaction boundaries belong in Service/Application logic, not in Views or Controllers.

---

# 61. Performance Rules

Do not optimize prematurely.

However, always avoid known dangerous patterns:

```text
N+1 queries
SELECT *
unbounded result sets
unvalidated large perPage
missing indexes for known filter columns
repeated identical DB queries in one request
large image delivery without optimization plan
```

Prefer selecting required columns explicitly for high-volume queries.

---

# 62. Index Rules

Do not create indexes on every column.

Indexes must be based on actual query patterns.

Likely candidates include:

```text
businesses.status
business_locations.region_id
business_categories.category_id
business_offerings.service_id
business_offerings(category_id, service_id)
business_brands.brand_id
work_cases.business_id
user_favorites.user_id
user_favorites.business_id
auth_sessions.user_id
search_logs.created_at
event_logs.created_at
```

Final indexes are determined during detailed ERD/query design.

---

# 63. Development Priority

Current implementation order:

```text
1. Foundation
2. Core DB schema
3. Business / Category / Service / Region / Brand / WorkCase API
4. Search / filter / sorting / pagination
5. SNS authentication
6. Favorites
7. Minimum Admin
8. React Native
9. Public SEO Web
```

Do not prioritize Public Web user features over App/API work.

---

# 64. First Vertical Slice

Implement `Business` first across the stack:

```text
Migration
→ Repository
→ Service
→ API Controller
→ Admin Controller
→ Public SEO detail page
```

Use this slice to validate architecture before expanding domains.

---

# 65. Testing Expectations

At minimum, tests should cover high-risk logic such as:

```text
validation
repository query behavior
search conditions
pagination
authentication
token validation
favorites
authorization
API error contract
```

Testing tools belong in `require-dev`.

Production does not need to execute the test suite.

---

# 66. Do Not Change These Without Approval

Do not change:

```text
Cafe24 hosting
PHP 8.4
MariaDB
Symfony Routing 7.4
Symfony HttpFoundation 7.4
Monolog
PDO-based persistence
No ORM
Controller → Service → Repository
REST /api/v1
React Native + TypeScript
Public Web = SEO / Marketing
Mobile App = Primary Product
SNS Login for normal users
separate Admin authentication
```

without explicit user approval.

---

# 67. Unresolved Design Items

Do not invent final decisions for:

```text
exact final ERD column types
all indexes
FK delete rules
soft-delete coverage
social providers included in v1
provider token verification details
access token TTL
refresh token TTL
refresh rotation
rate-limit implementation
image resize pipeline
image format
search ranking
Korean search strategy
pagination strategy
exact DTO fields
push notification model
map provider
deep-link implementation
sitemap generation strategy
admin role matrix
backup policy
monitoring policy
review creation requirements
one-review-per-user-and-business policy
review image limit
review reporting and moderation
business review reply policy
review usage verification
Google rating cache and refresh policy
```

When implementation reaches one of these unresolved areas, surface the decision explicitly instead of silently choosing a permanent architecture.

---

# 68. Decision Priority

When choosing between solutions, use this priority:

```text
1. Must run on Cafe24 shared hosting
2. Security
3. Maintainability
4. Simplicity
5. Future PostgreSQL portability
6. Development productivity
7. Novelty
```

A newer technology is not automatically a better choice.

---

# 69. Agent Behavior Rules

When making changes:

1. Read this file first.
2. Preserve existing architecture.
3. Prefer the smallest correct implementation.
4. Do not add infrastructure that Cafe24 cannot run.
5. Do not add packages casually.
6. Keep SQL inside Repository classes.
7. Use prepared statements for all values.
8. Use whitelists for dynamic SQL identifiers.
9. Validate input before Repository calls.
10. Do not weaken security for convenience.
11. Do not silently make unresolved architecture decisions.
12. If a user request conflicts with this file, identify the conflict before changing architecture.

---

# 70. Final Rule

If a request conflicts with this file:

1. Identify the conflict.
2. Do not silently rewrite project architecture.
3. Follow explicit user approval before making the architectural change.

This repository must remain:

```text
simple
portable
production-oriented
secure
compatible with Cafe24 shared hosting
easy to migrate toward PostgreSQL later
```
