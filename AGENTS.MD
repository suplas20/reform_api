# AGENTS.MD

This file defines mandatory rules for AI coding agents working in this repository.

The project is a Korean reform/repair/restoration business discovery platform.

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

---

# 2. Infrastructure Constraints

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
server-side Node.js build
runtime Composer install
```

Any implementation requiring these must not be introduced without explicit user approval.

---

# 3. Allowed Core Libraries

The default runtime dependencies are:

```text
symfony/routing:^7.4
symfony/http-foundation:^7.4
monolog/monolog:^3
PDO
```

Symfony Full Framework is NOT used.

Do not add a new package unless it satisfies all of the following:

1. PHP 8.4 compatible
2. Runs on shared hosting
3. Does not require a daemon
4. Does not require an unavailable PHP extension
5. Does not require OS package installation
6. Does not require runtime shell commands
7. Actively maintained
8. No known severe security issue

When uncertain, do not add the dependency.

---

# 4. Composer Policy

Composer runs locally.

Production deployment includes `vendor/`.

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

# 5. Architecture

Mandatory request flow:

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

---

# 6. Controller Rules

Controllers are responsible for:

```text
Request parsing
Validation orchestration
Service calls
Response creation
```

Controllers must not contain:

```text
SQL
PDO queries
large business rules
storage implementation details
```

Separate controllers by surface:

```text
src/Controller/Api/V1/
src/Controller/Web/
src/Controller/Admin/
```

Do not share controllers between Web and API.

---

# 7. Service Rules

Services own business logic and use cases.

Examples:

```text
BusinessService
SearchService
AuthService
FavoriteService
EventService
```

Services must not:

```text
render HTML
contain SQL
directly query PDO
depend on HTTP-specific response objects unless strictly necessary
```

---

# 8. Repository Rules

All SQL belongs in Repository classes.

Examples:

```text
BusinessRepository
CategoryRepository
ServiceRepository
RegionRepository
UserRepository
FavoriteRepository
```

Do not write SQL in:

```text
Controller
Service
View
JavaScript
```

Use PDO prepared statements.

Never concatenate untrusted input into SQL.

---

# 9. Public Web Rules

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

Public Web routes may include:

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

---

# 10. Mobile App Rules

Mobile App is the primary user-facing service.

Technology:

```text
React Native
TypeScript
```

The mobile app accesses data only through:

```text
/api/v1/*
```

The mobile app never accesses MariaDB directly.

---

# 11. API Rules

API prefix:

```text
/api/v1
```

Do not make breaking changes to existing v1 contracts without explicit approval.

Success response:

```json
{
  "success": true,
  "data": {}
}
```

List response:

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

Error response:

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

# 12. HTTP Status Rules

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

# 13. Authentication Model

Public Web:

```text
No normal user authentication
```

Mobile users:

```text
SNS Login
→ backend verification
→ application access/refresh tokens
```

Admin:

```text
email/password
→ PHP Session
```

Do not mix these authentication models.

---

# 14. Social Login Rules

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

Do not trust provider user IDs sent directly by the client without verification.

Provider tokens are not the same as application API tokens.

---

# 15. Token Rules

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

# 16. Admin Authentication

Admin authentication is separate from user authentication.

Expected model:

```text
admins
admin session
```

Admin password handling must use:

```php
password_hash()
password_verify()
```

Apply CSRF protection to session-based Admin state-changing requests.

---

# 17. Database Portability

Current DB:

```text
MariaDB
```

Future migration target may be:

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

# 18. Core Domain Model

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
SearchLog
EventLog
Admin
```

Do not introduce unrelated domains without need.

---

# 19. Database Relationship Rules

Use normalized relational tables.

Do NOT store values like this:

```text
categories = "bag,shoes,clothing"
services = "repair,dye,restore"
brands = "Chanel,Gucci"
```

Use join tables:

```text
business_categories
business_services
business_brands
```

Use unique constraints for duplicate relationship prevention.

---

# 20. Business Tables

Expected tables include:

```text
businesses
business_locations
business_hours
business_images
business_sources
business_verifications
business_claims
categories
services
regions
brands
business_categories
business_services
business_brands
work_cases
work_case_images
work_case_services
```

The exact final columns are still subject to ERD refinement.

Do not silently treat the current draft as immutable schema.

---

# 21. User Tables

Expected:

```text
users
user_social_accounts
auth_sessions
user_favorites
```

Password-based public user registration is not part of the current v1 plan.

---

# 22. Logging Tables

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
```

Do not block critical user requests on complex analytics processing.

No queue infrastructure should be assumed.

---

# 23. Search Architecture

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

# 24. Location Rules

Always preserve raw:

```text
latitude
longitude
```

Do not design location data in a way that blocks future PostgreSQL/PostGIS migration.

---

# 25. File / Image Rules

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

# 26. Security Rules

Mandatory:

```text
PDO prepared statements
output escaping
central exception handling
secure token generation
token hashing
password_hash/password_verify for admin
CSRF for session-based state changes
upload MIME/size/extension validation
```

Never expose in production:

```text
stack trace
SQL error
filesystem path
DB credentials
API secrets
tokens
```

---

# 27. Error Handling

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

Avoid repetitive controller-level try/catch when the global handler can handle it.

---

# 28. Logging

Use Monolog.

Default production target:

```text
storage/logs/app.log
```

Never log sensitive authentication material.

---

# 29. Cache

Redis is not part of v1.

If caching is needed:

```text
file cache
```

The application must work correctly without cache.

---

# 30. Queue / Worker Restrictions

Do not introduce:

```text
queue worker
Redis queue
Supervisor
WebSocket worker
long-running daemon
```

unless hosting changes or the user explicitly approves it.

---

# 31. SEO Rules

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

Only valuable pages with meaningful data should be indexable.

---

# 32. Coding Style

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

# 33. Avoid Overengineering

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
```

unless a concrete requirement justifies it and the user approves it.

Build the product first.

---

# 34. Development Priority

Current implementation order:

```text
1. Foundation
2. Core DB schema
3. Business/Category/Service/Region/Brand/WorkCase API
4. Search/filter/pagination
5. SNS auth
6. Favorites
7. Minimum Admin
8. React Native
9. Public SEO Web
```

Do not prioritize Public Web user features over App/API work.

---

# 35. First Vertical Slice

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

# 36. Do Not Change These Without Approval

Do not change:

```text
Cafe24 hosting
PHP 8.4
MariaDB
Symfony Routing 7.4
Symfony HttpFoundation 7.4
Monolog
Controller → Service → Repository
REST /api/v1
React Native + TypeScript
Public Web = SEO/Marketing
Mobile App = Primary Product
SNS Login for normal users
separate Admin auth
```

without explicit user approval.

---

# 37. Unresolved Design Items

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
```

When implementation reaches one of these unresolved areas, surface the decision explicitly rather than silently choosing a permanent architecture.

---

# 38. Decision Priority

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

# 39. Final Rule

If a request conflicts with this file:

1. Identify the conflict.
2. Do not silently rewrite project architecture.
3. Ask for or follow explicit user approval before making the architectural change.

This repository should remain simple, portable, production-oriented, and compatible with Cafe24 shared hosting.
