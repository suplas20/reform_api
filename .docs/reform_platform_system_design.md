# 리폼 업체 검색 플랫폼 시스템 설계서

> 본 문서는 현재까지 확정된 제품 방향, 인프라 제약, Backend/API/DB/Web/Admin/Mobile 구조를 기준으로 작성한 v1 시스템 설계 문서다.
> 실제 구현 시 이 문서를 기준으로 하며, 확정되지 않은 항목은 임의로 결정하지 않는다.

- 문서 버전: v0.2
- 기준일: 2026-09-14
- 단계: v1 / Cold Start
- Hosting: Cafe24 뉴아우토반 비즈니스
- Backend: PHP 8.4
- Database: MariaDB
- Mobile: React Native + TypeScript
- Public Web: PHP SSR + HTML/CSS/Vanilla JS
- Router: Symfony Routing 7.4 LTS
- HTTP: Symfony HttpFoundation 7.4 LTS
- Logging: Monolog 3.x

---

# 1. 서비스 정의

본 서비스는 가방, 신발, 의류, 가구/소파 등 비주거 리폼/수선/복원/커스텀 업체를 검색하고 비교할 수 있도록 제공하는 플랫폼이다.

초기 v1은 거래 중개 플랫폼이 아니라 **업체 검색 및 정보 제공 서비스**로 시작한다.

## 1.1 핵심 카테고리

초기 주요 카테고리는 다음과 같다.

- 명품 / 가방 / 가죽
- 신발
- 의류
- 가구 / 소파

향후 확장 가능 카테고리:

- 시계
- 주얼리
- 생활용품
- 기타 리폼/수선 분야

주거 인테리어/리모델링은 서비스 범위에서 제외한다.

---

# 2. 채널별 역할

본 프로젝트는 Web과 App의 역할을 명확히 분리한다.

## 2.1 Mobile App

Mobile App이 실제 사용자 서비스의 중심이다.

주요 역할:

- 업체 검색
- 카테고리/서비스/지역/브랜드 필터
- 업체 상세
- 작업사례(Before/After)
- 즐겨찾기
- 사용자 계정
- SNS 로그인
- 위치 기반 검색
- 향후 알림
- 향후 문의/견적/결제

React Native + TypeScript로 개발한다.

## 2.2 Public Web

Public Web은 실제 서비스 기능을 Web에서 완전히 복제하지 않는다.

주요 역할:

- SEO
- 검색엔진 유입
- 서비스 홍보
- 업체 정보 공개
- 작업사례 공개
- 지역/카테고리/서비스 기반 Landing Page
- 콘텐츠/가이드
- App 설치 전환

Public Web은 원칙적으로 **로그인 없는 Read Only 채널**이다.

다음 기능은 Public Web에 구현하지 않는다.

- 일반 사용자 회원가입
- 일반 사용자 로그인
- 즐겨찾기
- 마이페이지
- 개인화
- App 전용 사용자 기능

## 2.3 Admin

Admin은 운영자 전용이다.

주요 역할:

- 업체 관리
- 카테고리 관리
- 서비스 관리
- 지역 관리
- 브랜드 관리
- 업체 이미지 관리
- 작업사례 관리
- 업체 소유권 신청 관리
- 업체 인증 관리
- 회원 조회
- 운영 데이터 관리

Admin은 Public Web과 동일한 PHP Application 내부에서 제공하지만 인증 체계는 분리한다.

---

# 3. 전체 시스템 구조

```text
                         Internet
                            │
             ┌──────────────┴──────────────┐
             │                             │
        Public Web                    Mobile App
        SEO / Marketing               Real Service
             │                             │
             │                         REST API
             │                             │
             ▼                             ▼
      Web Controller                API Controller
             │                             │
             └──────────────┬──────────────┘
                            │
                         Service
                            │
                        Repository
                            │
                           PDO
                            │
                         MariaDB


                          Admin
                            │
                     Admin Controller
                            │
                         Service
                            │
                        Repository
                            │
                         MariaDB
```

핵심 구조:

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

---

# 4. Hosting 제약

Hosting은 Cafe24 뉴아우토반 비즈니스 공유호스팅을 사용한다.

따라서 다음을 전제로 구현하지 않는다.

- root 권한
- Docker
- Redis Server
- Supervisor
- Queue Worker
- WebSocket Server
- 별도 Daemon
- OS Package 설치
- Server-side Node.js build
- 자체 DB Server 설치
- Runtime에서 shell command 의존
- Runtime에서 Composer 설치

Composer Package는 로컬에서 설치한 뒤 `vendor/`를 포함해 배포한다.

---

# 5. 기술 스택

## 5.1 Backend

```text
PHP 8.4
```

Full Stack Framework는 사용하지 않는다.

사용하지 않는 기본 Framework:

- Laravel
- CodeIgniter
- Symfony Full Framework

대신 검증된 독립 Component만 사용한다.

## 5.2 Runtime Libraries

```json
{
  "require": {
    "php": "^8.4",
    "symfony/routing": "^7.4",
    "symfony/http-foundation": "^7.4",
    "monolog/monolog": "^3.0",
    "swagger-api/swagger-ui": "^5.32.15"
  },
  "config": {
    "platform": {
      "php": "8.4.0"
    }
  }
}
```

기본 역할:

| Library | Role |
|---|---|
| Symfony Routing | Routing |
| Symfony HttpFoundation | Request / Response / Cookie / Session |
| Monolog | Logging |
| Swagger UI | OpenAPI 기반 API 문서 화면 |
| PDO | Database Access |

새 라이브러리는 Cafe24 호환성 검토 후 추가한다.

---

# 6. 프로젝트 구조

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
│   ├── layouts/
│   ├── home/
│   ├── business/
│   ├── category/
│   ├── region/
│   ├── case/
│   ├── guide/
│   ├── admin/
│   └── errors/
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

## 6.1 로컬 관리 CLI

`bin/`은 API나 Public Web 실행 경로가 아닌 로컬 관리 명령의 진입점이다.

```text
bin/check-database.php
= DB 연결 및 MariaDB 환경 확인

bin/migrate.php
= Version 관리 SQL Migration 상태 확인 및 적용
```

실행 명령:

```bash
composer db:check
composer db:status
composer db:migrate
```

이 명령은 HTTP 요청에서 호출하지 않으며, 애플리케이션의 정상 실행이나 Cafe24 운영 Shell에 의존하지 않는다.

---

# 7. Routing

Router는 직접 구현하지 않는다.

```text
Symfony Routing 7.4 LTS
```

사용 영역:

```text
routes/api.php
routes/web.php
routes/admin.php
```

모든 Application Request는 `public/index.php`를 통한다.

---

# 8. Layer 설계

## 8.1 Controller

역할:

- Request 파싱
- 입력 Validation 호출
- Service 호출
- Response 생성

Controller에 금지:

- SQL
- 직접 PDO 접근
- 복잡한 Business Logic

## 8.2 Service

역할:

- Business Rule
- Use Case
- 여러 Repository 조합
- Transaction 경계 관리

Service에 금지:

- HTML Rendering
- SQL
- 직접 PDO Query

## 8.3 Repository

역할:

- SQL
- Persistence
- Row 조회/저장
- DB Vendor-specific Query 격리

모든 SQL은 Repository 내부에 둔다.

## 8.4 View

역할:

- HTML Rendering

View에 금지:

- DB Query
- Repository 호출
- Service 호출

---

# 9. Database 기본 원칙

현재 DB는 MariaDB다.

향후 PostgreSQL 이전 가능성을 고려한다.

## 9.1 권장 타입

```text
BIGINT
INT
VARCHAR
TEXT
DECIMAL
DATE
DATETIME
```

## 9.2 최소화

- ENUM
- UNSIGNED 의존
- MariaDB-specific Function
- Stored Procedure Business Logic
- Searchable Core Data의 JSON 저장
- Vendor-specific UPSERT 의존

## 9.3 Charset

```text
utf8mb4
```

## 9.4 PDO

기본 Option:

```php
[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false,
]
```

---

# 10. 핵심 Domain

v1 핵심 Domain:

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

---

# 11. 핵심 ERD 개요

```text
users
 ├── user_social_accounts
 ├── auth_sessions
 ├── user_favorites ───────────────┐
 └── business_claims               │
                                   │
                                   ▼
                              businesses
                                   │
             ┌─────────────────────┼──────────────────────┐
             │                     │                      │
             ▼                     ▼                      ▼
    business_locations     business_categories     business_offerings
             │                     │                      │
             ▼                     ▼                      ▼
          regions               categories              services

                              businesses
                                   │
                   ┌───────────────┼───────────────┐
                   ▼               ▼               ▼
            business_brands  business_images   business_hours
                   │
                   ▼
                 brands

                              businesses
                                   │
                                   ▼
                               work_cases
                                   │
                  ┌────────────────┴─────────────┐
                  ▼                              ▼
         work_case_images                work_case_services
                                                 │
                                                 ▼
                                              services

                              businesses
                              ├── business_sources
                              ├── business_verifications
                              └── business_claims

                              businesses
                              ├── business_external_refs
                              │       └── business_external_ratings
                              ├── reviews
                              │       └── review_images
                              └── business_reputation_stats

search_logs
event_logs

admins
```

---

# 12. Table 설계 방향

## 12.1 businesses

핵심 업체 정보.

예상 Column:

```text
id
name
slug
short_description
description
phone
website_url
status
claimed_user_id
created_at
updated_at
deleted_at
```

`status`는 VARCHAR를 사용한다.

예상 값:

```text
DRAFT
ACTIVE
INACTIVE
SUSPENDED
```

## 12.2 business_locations

```text
id
business_id
region_id
postal_code
address
address_detail
latitude
longitude
is_primary
created_at
updated_at
```

한 업체가 여러 지점을 가질 수 있게 1:N 구조를 허용한다.

## 12.3 regions

계층형 지역 구조.

```text
id
parent_id
code
name
slug
level
sort_order
status
created_at
updated_at
```

## 12.4 categories

물품 종류.

예:

```text
가방
신발
의류
가구
```

Column:

```text
id
parent_id
name
slug
description
sort_order
status
created_at
updated_at
```

## 12.5 services

작업 종류.

예:

```text
복원
염색
지퍼 교체
손잡이 교체
사이즈 수선
밑창 교체
```

Column:

```text
id
name
slug
description
sort_order
status
created_at
updated_at
```

## 12.5.1 category_services

카테고리에 적용할 수 있는 서비스 조합을 정의한다.

```text
category_id
service_id
sort_order
```

Constraint:

```text
PRIMARY KEY(category_id, service_id)
```

## 12.6 business_categories

```text
business_id
category_id
```

Constraint:

```text
UNIQUE(business_id, category_id)
```

## 12.7 business_offerings

```text
id
business_id
category_id
service_id
title
description
price_min
price_max
price_note
duration_min_days
duration_max_days
is_primary
status
created_at
updated_at
```

Constraint:

```text
UNIQUE(business_id, category_id, service_id)
```

업체가 실제로 제공하는 Category + Service 조합을 저장한다.
별도의 `business_services` 테이블은 사용하지 않는다.

## 12.8 brands

```text
id
name
slug
status
created_at
updated_at
```

## 12.9 business_brands

```text
business_id
brand_id
```

Constraint:

```text
UNIQUE(business_id, brand_id)
```

## 12.10 business_images

이미지는 BLOB으로 DB에 저장하지 않는다.

```text
id
business_id
storage_type
file_path
original_filename
mime_type
width
height
image_type
alt_text
sort_order
is_primary
created_at
```

Storage 초기값:

```text
LOCAL
```

향후:

```text
R2
S3
```

등으로 확장 가능하게 설계한다.

## 12.11 work_cases

```text
id
business_id
title
slug
description
status
created_at
updated_at
deleted_at
```

## 12.12 work_case_images

```text
id
work_case_id
file_path
image_role
sort_order
created_at
```

`image_role` 예:

```text
BEFORE
AFTER
OTHER
```

## 12.13 work_case_services

```text
work_case_id
service_id
```

## 12.14 business_hours

시간 구간 단위 구조.

```text
id
business_id
day_of_week
sequence
open_time
close_time
created_at
```

## 12.15 business_external_refs

외부 플랫폼의 업체 식별자와 페이지 URL을 저장한다.

```text
id
business_id
provider
external_id
external_url
status
created_at
updated_at
```

`business_links`와 합치지 않는다.

## 12.16 business_external_ratings

공식적으로 사용 가능한 외부 평점 통계만 저장한다.

```text
id
business_id
provider
rating
review_count
fetched_at
expires_at
created_at
updated_at
```

외부 리뷰 원문·작성자·사진은 저장하지 않는다.

## 12.17 reviews

Reform Platform 자체 사용자 리뷰다.

```text
id
business_id
user_id
rating
content
status
created_at
updated_at
deleted_at
```

`rating`은 1부터 5까지 허용하며, 작성·수정·삭제·신고 정책은 별도 설계에서 확정한다.

## 12.18 review_images

```text
id
review_id
storage_type
file_path
sort_order
created_at
```

## 12.19 business_reputation_stats

검색 결과용 자체 평판 집계값을 저장한다.

```text
business_id
review_count
rating_avg
favorite_count
work_case_count
updated_at
```

Review와 Favorite 변경 시 같은 Transaction에서 동기식으로 갱신한다.
`work_case_count`의 집계 대상 Status와 갱신 시점은 WorkCase 상세 설계에서 확정한다.
자체 평판과 외부 평판은 합산하지 않는다.

---

# 13. User / Authentication 설계

일반 사용자 인증은 **Mobile App 전용**이다.

Public Web에는 일반 사용자 로그인/회원가입을 제공하지 않는다.

## 13.1 users

SNS 로그인 중심이므로 Password Column을 기본 전제로 하지 않는다.

```text
id
name
email
profile_image_url
status
created_at
updated_at
deleted_at
```

## 13.2 user_social_accounts

SNS Provider 계정 연결.

```text
id
user_id
provider
provider_user_id
email
created_at
updated_at
```

예상 Provider:

```text
KAKAO
NAVER
GOOGLE
APPLE
```

SNS별 ID Column을 `users`에 직접 추가하지 않는다.

## 13.3 Social Login Flow

```text
React Native
     │
     ▼
SNS Login SDK
     │
     ▼
Provider Token
     │
     ▼
POST /api/v1/auth/social
     │
     ▼
PHP API
     │
     ├── Provider Token 검증
     ├── Social Account 조회
     ├── 기존 User 조회 또는 생성
     └── Service Token 발급
```

Provider Token은 SNS 인증용이다.

우리 API 호출은 별도로 발급한 Service Token을 사용한다.

## 13.4 auth_sessions

Mobile 인증은 Access + Refresh Token 방식으로 설계한다.

예상 Column:

```text
id
user_id
access_token_hash
refresh_token_hash
access_expires_at
refresh_expires_at
last_used_at
revoked_at
created_at
```

원본 Token은 DB에 저장하지 않는다.

## 13.5 user_favorites

```text
user_id
business_id
created_at
```

Constraint:

```text
UNIQUE(user_id, business_id)
```

---

# 14. Admin Authentication

일반 User와 Admin 인증은 분리한다.

## admins

```text
id
email
password_hash
name
role
status
created_at
updated_at
```

Admin 인증:

```text
/admin/login
PHP Session
```

일반 User SNS 인증과 Admin Password 인증을 섞지 않는다.

---

# 15. 업체 출처 / 검증 / 소유권

## 15.1 business_sources

```text
id
business_id
source_type
source_url
external_id
collected_at
last_verified_at
created_at
```

예상 `source_type`:

```text
OFFICIAL
NAVER
GOOGLE
INSTAGRAM
BLOG
OWNER
MANUAL
```

## 15.2 business_verifications

```text
id
business_id
verification_type
status
verified_at
note
created_at
updated_at
```

예:

```text
BUSINESS_REGISTRATION
PHONE
OWNER
MANUAL
```

## 15.3 business_claims

기존 업체 정보를 실제 소유자가 Claim할 수 있게 한다.

```text
id
business_id
user_id
status
requested_at
reviewed_at
reviewed_by
created_at
updated_at
```

예상 상태:

```text
PENDING
APPROVED
REJECTED
```

---

# 16. API Versioning

모든 Mobile API는 다음 Prefix를 사용한다.

```text
/api/v1/*
```

호환성을 깨는 변경 시 `/api/v2`를 별도로 만든다.

---

# 17. API Response Contract

## Success

```json
{
  "success": true,
  "data": {}
}
```

## List

```json
{
  "success": true,
  "data": [],
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 324,
    "totalPages": 17
  }
}
```

## Error

```json
{
  "success": false,
  "error": {
    "code": "BUSINESS_NOT_FOUND",
    "message": "업체를 찾을 수 없습니다."
  }
}
```

## 17.1 OpenAPI / Swagger UI

API 계약 원본:

```text
openapi/openapi.json
```

문서 Endpoint:

```text
GET /api/docs
GET /api/docs/openapi.json
```

Swagger UI는 고정된 공식 Composer Package의 Asset을 사용한다.
`vendor/`를 직접 공개하지 않고 허용 목록에 포함된 Asset만 Controller를 통해 전달한다.

API Endpoint, Field, Status Code 또는 Error Code를 변경할 때 같은 변경에서 OpenAPI 명세를 갱신한다.
OpenAPI 파일에는 Credential, Token, Provider Secret, 내부 파일 경로를 포함하지 않는다.

---

# 18. HTTP Status

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

---

# 19. 초기 API 범위

Mobile v1의 최종 Endpoint별 Request/Response Contract와 구현 우선순위는 `.docs/reform_mobile_api_definition_v1.md`를 기준으로 한다. 아래 Business 계약은 해당 문서가 추가되기 전에 구현한 중간 Vertical Slice의 상태를 기록한다.

## Reference Data

```text
GET /api/v1/categories
GET /api/v1/services
GET /api/v1/regions
GET /api/v1/brands
```

## Business

```text
GET /api/v1/businesses
GET /api/v1/businesses/{id}
```

### 현재 Business List 중간 구현

```text
GET /api/v1/businesses?page=1&perPage=20
```

Pagination:

```text
page: 기본 1, 최소 1
perPage: 기본 20, 최소 1, 최대 100
totalPages: 조회 결과가 없으면 0
```

공개 조건은 Business Detail과 동일하다.

정렬:

```text
published_at DESC
id DESC
```

현재 목록 Item은 상세 응답에서 `description`과 `phone`을 제외한 기본 정보와 자체·외부 평판을 포함한다.
다음 Business 작업에서 Mobile API 정의서 14장의 검색어·카테고리·서비스·지역·브랜드·좌표 Filter, 정렬 및 전체 Summary DTO로 교체한다.

### 현재 Business Detail 중간 구현

첫 Vertical Slice는 다음 Endpoint만 구현한다.

```text
GET /api/v1/businesses/{id}
```

공개 조건:

```text
status = ACTIVE
deleted_at IS NULL
published_at IS NOT NULL
published_at <= 현재 UTC 시각
```

현재 구현된 응답 Field:

```json
{
  "success": true,
  "data": {
    "id": 123,
    "name": "ABC 가죽공방",
    "slug": "abc-leather",
    "shortDescription": "가죽 제품을 복원합니다.",
    "description": null,
    "phone": "02-1234-5678",
    "supports": {
      "shipping": true,
      "visit": false,
      "pickup": true
    },
    "publishedAt": "2026-09-15T00:00:00Z",
    "reputation": {
      "internal": {
        "rating": 4.8,
        "reviewCount": 24,
        "favoriteCount": 52,
        "workCaseCount": 18
      },
      "external": [
        {
          "provider": "GOOGLE",
          "rating": 4.6,
          "reviewCount": 128,
          "url": "https://example.com/google-place"
        },
        {
          "provider": "NAVER",
          "rating": null,
          "reviewCount": null,
          "url": "https://example.com/naver-place"
        }
      ]
    }
  }
}
```

업체가 공개 조건을 만족하지 않거나 존재하지 않으면 `BUSINESS_NOT_FOUND` 오류와 HTTP 404를 반환한다.

## Work Case

```text
GET /api/v1/work-cases
GET /api/v1/work-cases/{id}
```

## Search

```text
GET /api/v1/search
GET /api/v1/search/suggestions
```

## Authentication

```text
POST /api/v1/auth/social
POST /api/v1/auth/refresh
POST /api/v1/auth/logout
GET  /api/v1/me
PATCH /api/v1/me
DELETE /api/v1/me
```

## Favorite

```text
GET    /api/v1/favorites
POST   /api/v1/favorites/{businessId}
DELETE /api/v1/favorites/{businessId}
```

## Event

```text
POST /api/v1/events
```

요청 Field, 허용 Event Name, 민감정보 금지 규칙은 `.docs/reform_mobile_api_definition_v1.md` 22장을 따른다. 성공 응답은 Body 없는 HTTP 204이다.

---

# 20. 검색 API 설계 방향

예상 Query:

```text
GET /api/v1/businesses
    ?keyword=
    &categoryId=
    &serviceId=
    &regionId=
    &brandId=
    &lat=
    &lng=
    &radiusKm=
    &verifiedOnly=
    &sort=
    &page=
    &perPage=
```

정확한 Query Parameter 및 Validation Rule은 API 상세 Contract 단계에서 확정한다.

검색 Logic은 반드시 다음 구조에 둔다.

```text
SearchController
      ↓
SearchService
      ↓
BusinessRepository
```

향후 별도 Search Engine으로 교체 가능해야 한다.

---

# 21. 위치 검색

원본 좌표를 항상 유지한다.

```text
latitude
longitude
```

v1에서는 MariaDB에서 범용적인 거리 계산 방식으로 구현할 수 있다.

향후 PostgreSQL/PostGIS 이전 가능성을 고려한다.

---

# 22. Search / Event Logging

## search_logs

예상 구조:

```text
id
user_id NULL
session_key NULL
keyword
category_id NULL
service_id NULL
region_id NULL
result_count
created_at
```

## event_logs

```text
id
user_id NULL
session_key NULL
event_name
business_id NULL
work_case_id NULL
metadata TEXT NULL
created_at
```

예상 Event:

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

`metadata`는 분석용 부가정보에 한해 JSON 문자열 사용 가능하다.

---

# 23. Public Web SEO 구조

Public Web은 검색엔진 유입과 App 전환이 목표다.

예상 URL:

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

예:

```text
/regions/seoul/gangnam/bag
/categories/bag/restoration
/businesses/123/abc-leather
/guides/leather-bag-restoration-price
```

---

# 24. SEO 원칙

기본 적용:

```text
SSR
semantic HTML
title
meta description
canonical
OpenGraph
robots.txt
sitemap.xml
structured data
검색 친화 URL
```

웹 페이지의 핵심 CTA는 App 설치 및 App 서비스 이용으로 연결한다.

예:

```text
앱에서 업체 찾기
앱에서 주변 업체 검색하기
앱에서 더 많은 작업사례 보기
```

---

# 25. SEO Landing Page 생성 규칙

다음 조합을 모두 무조건 생성하지 않는다.

```text
지역 × 카테고리 × 서비스 × 브랜드
```

업체가 없거나 정보가 빈약한 조합은 indexable page로 만들지 않는다.

Thin Page 대량 생성을 피한다.

SEO 가치가 충분한 조합만 Public Page로 제공한다.

---

# 26. Public Web에서 하지 않는 것

Public Web에 다음 기능은 구현하지 않는다.

```text
일반 사용자 회원가입
일반 사용자 로그인
즐겨찾기
마이페이지
App의 전체 검색 UX 복제
Push
개인화
```

Public Web은 App과 경쟁하는 두 번째 Product가 아니다.

---

# 27. Storage

초기 업체/작업사례 이미지:

```text
Cafe24 Local Storage
```

DB에는 전체 URL이 아니라 상대 Path/Storage 정보를 저장한다.

향후:

```text
Cloudflare R2
AWS S3
S3-compatible Object Storage
```

로 이전 가능하게 Storage Layer를 분리한다.

---

# 28. Security

## SQL Injection

- PDO Prepared Statement 필수
- 문자열 결합 SQL 금지

## XSS

- DB/User 입력값 HTML Escape

## CSRF

- Admin Session 기반 변경 요청에는 CSRF 적용
- Bearer Token API에는 Browser Session용 CSRF를 그대로 적용하지 않음

## Token

- 원본 Access/Refresh Token DB 저장 금지
- Token 로그 기록 금지

## Password

- Admin Password는 `password_hash()` / `password_verify()` 사용

## File Upload

검증 항목:

```text
파일 크기
확장자
MIME Type
Random Filename
저장 위치
실행 파일 차단
```

---

# 29. Exception Handling

중앙 Exception Handler를 둔다.

예:

```text
NotFoundException      → 404
ValidationException    → 422
UnauthorizedException  → 401
ForbiddenException     → 403
ConflictException      → 409
RateLimitException     → 429
Throwable              → 500
```

Production에서는 Stack Trace, SQL Error, File Path를 사용자에게 노출하지 않는다.

---

# 30. Logging

Monolog 사용.

초기 Handler:

```text
StreamHandler
→ storage/logs/app.log
```

로그 금지:

- Password
- Access Token
- Refresh Token
- Session ID
- API Secret
- DB Password
- 과도한 개인정보

---

# 31. Cache

v1에서는 Redis를 사용하지 않는다.

필요 시 File Cache만 사용한다.

Cache 장애/삭제 시에도 서비스 기능은 정상 동작해야 한다.

---

# 32. Queue / Worker / Scheduler

v1 핵심 기능은 아래를 전제로 하지 않는다.

```text
Queue Worker
Redis Queue
Background Worker
Supervisor
WebSocket
Daemon
```

Cafe24 환경에서 불가능하거나 운영 리스크가 큰 구조는 사용하지 않는다.

---

# 33. Coding Convention

기본:

```text
declare(strict_types=1)
PSR-4
PSR-12 준수
Type Declaration 사용
Return Type 명시
final Class 적극 사용
Global State 최소화
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

# 34. 개발 순서

현재 제품 방향에서는 App/API 우선이다.

## Phase 1 — Foundation

```text
Bootstrap
Composer
Router
HttpFoundation
PDO
Exception Handler
Logging
API Response
```

## Phase 2 — DB / Core Domain

```text
Business
Category
Service
Region
Brand
WorkCase
```

## Phase 3 — Search API

```text
Business List
Filter
Sort
Pagination
Nearby Search
```

## Phase 4 — User / SNS Auth

```text
User
Social Account
Access Token
Refresh Token
Favorite
```

## Phase 5 — Minimum Admin

```text
Business CRUD
Category
Service
Region
Brand
WorkCase
```

## Phase 6 — React Native

실제 사용자 Product 개발.

## Phase 7 — Public SEO Web

검색엔진 유입 및 App 설치 전환.

---

# 35. 첫 Vertical Slice

첫 번째 구현 대상은 Business Domain으로 한다.

```text
DB
  ↓
BusinessRepository
  ↓
BusinessService
  ↓
API BusinessController
  ↓
Admin BusinessController
  ↓
Public Business Page
```

이 흐름이 정상 동작하는 것을 확인한 후 Domain을 확장한다.

---

# 36. 아직 상세 설계가 필요한 항목

다음은 아직 최종 확정하지 않는다.

```text
최종 ERD Column Type
Index 전체 목록
Foreign Key Delete Rule
Soft Delete 적용 범위
SNS Provider v1 실제 채택 목록
Provider Token 검증 상세 구현
Access Token 수명
Refresh Token 수명
Refresh Rotation 정책
Rate Limit 방식
Image Resize 방식
Image Format
Search Ranking
Korean Search 방식
Pagination 상세 방식
API DTO 상세 Field
Device / Push 구조
Map Provider
App Deep Link
App Store / Play Store 전환 방식
SEO Sitemap 생성 방식
Admin Role 상세
Backup 정책
Monitoring 정책
```

후속 설계에서 확정한다.

---

# 37. 확정된 핵심 원칙

```text
App = 실제 서비스
API = 핵심 Backend Interface
Public Web = SEO / Marketing / App 전환
Admin = 운영도구
Public Web 일반 사용자 로그인 없음
일반 사용자 인증 = Mobile SNS Login
Admin 인증 = 별도 Session Login
Backend = PHP 8.4
DB = MariaDB
향후 PostgreSQL 이전 가능성 고려
Router = Symfony Routing 7.4
HTTP = Symfony HttpFoundation 7.4
DB Access = PDO
SQL = Repository 내부
Business Logic = Service
Cafe24 공유호스팅 제약을 최우선 적용
```
