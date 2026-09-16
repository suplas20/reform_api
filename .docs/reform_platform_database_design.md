# reform_platform Database Design

> 본 문서는 `reform_platform` v1의 MariaDB 논리 설계 기준서다.
> 실제 DDL 작성 시 본 문서를 기준으로 하되, Cafe24의 실제 MariaDB 버전과 지원 기능을 확인한 뒤 최종 SQL을 확정한다.

- Database Name: `reform_platform`
- Runtime: PHP 8.4
- DBMS: MariaDB
- Hosting: Cafe24 뉴아우토반 비즈니스
- Charset: `utf8mb4`
- Architecture: Controller → Service → Repository → PDO → MariaDB
- ORM: 사용하지 않음
- Future Migration Target: PostgreSQL 고려

---

# 1. 설계 목표

본 데이터베이스는 다음 요구사항을 지원한다.

1. 업체 검색
2. 카테고리별 검색
3. 서비스/작업 유형별 검색
4. 지역별 검색
5. 브랜드별 검색
6. 위치 기반 검색
7. 업체 상세정보
8. Before / After 작업사례
9. SNS 로그인
10. 즐겨찾기
11. 업체 소유권 Claim
12. 업체 인증
13. 사용자 검색/행동 로그
14. SEO용 공개 콘텐츠
15. Admin 운영
16. 향후 PostgreSQL 이전

---

# 2. Database

```text
Database: reform_platform
Charset: utf8mb4
```

Collation은 Cafe24의 실제 MariaDB 버전을 확인한 후 최종 확정한다.

초기 후보:

```text
utf8mb4_unicode_ci
```

Production 코드에서 DB 이름은 하드코딩하지 않고 설정값으로 관리한다.

---

# 3. PK 정책

기본 PK는 다음을 사용한다.

```text
BIGINT
```

MariaDB:

```sql
BIGINT NOT NULL AUTO_INCREMENT
```

향후 PostgreSQL 이전 시:

```text
BIGINT GENERATED ... AS IDENTITY
```

계열로 변환할 수 있도록 설계한다.

다음은 사용하지 않는다.

```text
UNSIGNED 의존
```

---

# 4. 시간 컬럼 정책

주요 Entity에는 기본적으로 다음을 둔다.

```text
created_at
updated_at
```

복구 가능성이 필요한 주요 Entity에는:

```text
deleted_at
```

을 추가한다.

`updated_at` 갱신은 MariaDB 전용 자동 갱신 기능보다 PHP Application에서 처리하는 것을 우선한다.

---

# 5. 전체 ERD 개요

```text
                            users
                              │
                  ┌───────────┼─────────────┐
                  │           │             │
                  ▼           ▼             ▼
       user_social_accounts auth_sessions user_favorites
                                            │
                                            ▼
                                       businesses
                                            │
      ┌─────────────────────────────────────┼───────────────────────────────────┐
      │             │             │         │            │                     │
      ▼             ▼             ▼         ▼            ▼                     ▼
 locations        hours         images     links       sources            verifications
      │
      ▼
   regions


                              businesses
                                  │
              ┌───────────────────┼─────────────────────┐
              │                   │                     │
              ▼                   ▼                     ▼
    business_categories   business_offerings    business_brands
              │                   │                     │
              ▼              ┌────┴────┐                ▼
         categories          ▼         ▼              brands
                        categories   services


                              businesses
                                  │
                                  ▼
                              work_cases
                                  │
                   ┌──────────────┴──────────────┐
                   │                             │
                   ▼                             ▼
          work_case_images              work_case_services
                                                 │
                                                 ▼
                                              services


users ───── business_claims ───── businesses

                              businesses
                                  │
             ┌────────────────────┼─────────────────────┐
             │                    │                     │
             ▼                    ▼                     ▼
 business_external_refs        reviews     business_reputation_stats
             │                    │
             ▼                    ▼
 business_external_ratings  review_images

admins

search_logs
event_logs

guides
```

---

# 6. users

앱 사용자 테이블.

Password 기반 회원가입은 v1 기본 기능이 아니다.

```text
users
────────────────────────────
id              BIGINT PK
name            VARCHAR(100) NULL
email           VARCHAR(255) NULL
profile_image   VARCHAR(500) NULL
status          VARCHAR(20)
created_at      DATETIME
updated_at      DATETIME
deleted_at      DATETIME NULL
```

예상 `status`:

```text
ACTIVE
SUSPENDED
WITHDRAWN
```

Index:

```text
INDEX(status)
INDEX(email)
```

`email`은 SNS Provider에 따라 없거나 변경될 수 있으므로 고유 회원 식별자로 사용하지 않는다.

---

# 7. user_social_accounts

SNS 계정 연결 테이블.

```text
user_social_accounts
────────────────────────────
id                  BIGINT PK
user_id             BIGINT FK
provider            VARCHAR(20)
provider_user_id    VARCHAR(255)
provider_email      VARCHAR(255) NULL
created_at          DATETIME
updated_at          DATETIME
```

예상 Provider:

```text
KAKAO
NAVER
GOOGLE
APPLE
```

Constraint:

```text
UNIQUE(provider, provider_user_id)
UNIQUE(user_id, provider)
```

SNS Provider별 컬럼을 `users`에 직접 추가하지 않는다.

잘못된 예:

```text
kakao_id
naver_id
google_id
apple_id
```

---

# 8. auth_sessions

Mobile 인증 세션.

```text
auth_sessions
────────────────────────────
id                      BIGINT PK
user_id                 BIGINT FK
access_token_hash       VARCHAR(255)
refresh_token_hash      VARCHAR(255)
access_expires_at       DATETIME
refresh_expires_at      DATETIME
last_used_at            DATETIME NULL
revoked_at              DATETIME NULL
created_at              DATETIME
```

Index:

```text
UNIQUE(access_token_hash)
UNIQUE(refresh_token_hash)
INDEX(user_id)
INDEX(refresh_expires_at)
```

원본 Access Token / Refresh Token은 DB에 저장하지 않는다.

---

# 9. admins

Admin 사용자.

일반 사용자와 완전히 분리한다.

```text
admins
────────────────────────────
id              BIGINT PK
email           VARCHAR(255)
password_hash   VARCHAR(255)
name            VARCHAR(100)
role            VARCHAR(30)
status          VARCHAR(20)
created_at      DATETIME
updated_at      DATETIME
```

Constraint:

```text
UNIQUE(email)
```

초기 Role:

```text
SUPER_ADMIN
ADMIN
```

필요 시 추후 세분화한다.

---

# 10. businesses

핵심 업체 Entity.

```text
businesses
────────────────────────────────
id                  BIGINT PK
name                VARCHAR(200)
slug                VARCHAR(220)
short_description   VARCHAR(500) NULL
description         TEXT NULL
phone               VARCHAR(30) NULL
supports_shipping   SMALLINT DEFAULT 0
supports_visit      SMALLINT DEFAULT 0
supports_pickup     SMALLINT DEFAULT 0
status              VARCHAR(20)
claimed_user_id     BIGINT NULL FK
published_at        DATETIME NULL
created_at          DATETIME
updated_at          DATETIME
deleted_at          DATETIME NULL
```

예상 `status`:

```text
DRAFT
ACTIVE
INACTIVE
SUSPENDED
```

Index:

```text
UNIQUE(slug)
INDEX(status)
INDEX(name)
INDEX(claimed_user_id)
INDEX(published_at)
```

---

# 11. business_locations

업체 주소 및 좌표.

```text
business_locations
────────────────────────────────
id                  BIGINT PK
business_id         BIGINT FK
region_id           BIGINT FK
postal_code         VARCHAR(20) NULL
road_address        VARCHAR(300)
jibun_address       VARCHAR(300) NULL
address_detail      VARCHAR(300) NULL
latitude            DECIMAL(10,7) NULL
longitude           DECIMAL(10,7) NULL
is_primary          SMALLINT DEFAULT 0
created_at          DATETIME
updated_at          DATETIME
```

Index:

```text
INDEX(business_id)
INDEX(region_id)
INDEX(latitude, longitude)
```

위치 검색을 위해 원본 `latitude`, `longitude`를 유지한다.

향후 PostgreSQL/PostGIS 이전 시에도 좌표 원본을 보존한다.

---

# 12. regions

계층형 지역 테이블.

```text
regions
────────────────────────────
id              BIGINT PK
parent_id       BIGINT NULL FK
code            VARCHAR(30) NULL
name            VARCHAR(100)
slug            VARCHAR(100)
level           SMALLINT
sort_order      INT DEFAULT 0
status          VARCHAR(20)
created_at      DATETIME
updated_at      DATETIME
```

예:

```text
서울특별시
 id = 1
 parent_id = NULL
 level = 1

강남구
 id = 10
 parent_id = 1
 level = 2
```

Index:

```text
INDEX(parent_id)
INDEX(code)
INDEX(slug)
INDEX(status)
```

---

# 13. categories

물품 종류.

예:

```text
가방
신발
의류
가구
```

Schema:

```text
categories
────────────────────────────
id              BIGINT PK
parent_id       BIGINT NULL FK
name            VARCHAR(100)
slug            VARCHAR(100)
description     TEXT NULL
sort_order      INT DEFAULT 0
status          VARCHAR(20)
created_at      DATETIME
updated_at      DATETIME
```

예시 계층:

```text
가방
 ├─ 명품가방
 ├─ 가죽가방
 └─ 여행가방

신발
의류
가구
```

Index:

```text
INDEX(parent_id)
UNIQUE(slug)
INDEX(status)
```

---

# 14. services

작업/서비스 종류.

예:

```text
복원
염색
세탁
지퍼 교체
손잡이 교체
밑창 교체
사이즈 수선
```

Schema:

```text
services
────────────────────────────
id              BIGINT PK
name            VARCHAR(100)
slug            VARCHAR(100)
description     TEXT NULL
sort_order      INT DEFAULT 0
status          VARCHAR(20)
created_at      DATETIME
updated_at      DATETIME
```

Constraint:

```text
UNIQUE(slug)
```

---

# 15. category_services

어떤 카테고리에 어떤 서비스가 적용될 수 있는지 정의한다.

```text
category_services
────────────────────────────
category_id     BIGINT FK
service_id      BIGINT FK
sort_order      INT DEFAULT 0
```

PK:

```text
PRIMARY KEY(category_id, service_id)
```

예:

```text
가방 + 염색
가방 + 지퍼 교체
신발 + 밑창 교체
의류 + 사이즈 수선
```

Mobile Filter UI에서도 활용할 수 있다.

---

# 16. business_categories

업체가 취급하는 물품 카테고리.

```text
business_categories
────────────────────────────
business_id     BIGINT FK
category_id     BIGINT FK
is_primary      SMALLINT DEFAULT 0
created_at      DATETIME
```

PK:

```text
PRIMARY KEY(business_id, category_id)
```

---

# 17. business_offerings

검색 핵심 테이블.

기존 단순 `business_services` 구조 대신 다음 구조를 사용한다.

```text
business_offerings
────────────────────────────────
id                  BIGINT PK
business_id         BIGINT FK
category_id         BIGINT FK
service_id          BIGINT FK
title               VARCHAR(200) NULL
description         TEXT NULL
price_min           DECIMAL(12,2) NULL
price_max           DECIMAL(12,2) NULL
price_note          VARCHAR(300) NULL
duration_min_days   INT NULL
duration_max_days   INT NULL
is_primary          SMALLINT DEFAULT 0
status              VARCHAR(20)
created_at          DATETIME
updated_at          DATETIME
```

예:

```text
ABC 가죽공방
+
가방
+
염색
```

다른 Offering:

```text
ABC 가죽공방
+
가방
+
손잡이 교체
```

Constraint:

```text
UNIQUE(business_id, category_id, service_id)
```

Index:

```text
INDEX(category_id, service_id)
INDEX(service_id)
INDEX(business_id)
INDEX(status)
```

이 테이블을 사용하는 이유:

기존 방식:

```text
business_categories
business_services
```

만 사용하면:

```text
업체 카테고리:
- 가방
- 신발

업체 서비스:
- 염색
- 밑창교체
```

일 때 DB상으로 다음 조합이 실제 제공 서비스인지 구분하기 어렵다.

```text
가방 + 밑창교체
```

`business_offerings`에서는 정확한 조합을 표현할 수 있다.

```text
가방 + 염색
신발 + 밑창교체
```

---

# 18. brands

브랜드 Reference.

```text
brands
────────────────────────────
id              BIGINT PK
name            VARCHAR(150)
slug            VARCHAR(150)
status          VARCHAR(20)
created_at      DATETIME
updated_at      DATETIME
```

예:

```text
Louis Vuitton
Chanel
Hermès
Gucci
Prada
Nike
Adidas
```

Constraint:

```text
UNIQUE(slug)
```

---

# 19. business_brands

업체가 취급하는 브랜드.

```text
business_brands
────────────────────────────
business_id     BIGINT FK
brand_id        BIGINT FK
created_at      DATETIME
```

PK:

```text
PRIMARY KEY(business_id, brand_id)
```

---

# 20. business_hours

업체 영업시간.

하루에 여러 시간 구간을 지원한다.

```text
business_hours
────────────────────────────
id              BIGINT PK
business_id     BIGINT FK
day_of_week     SMALLINT
sequence        SMALLINT
open_time       TIME NULL
close_time      TIME NULL
is_closed       SMALLINT DEFAULT 0
created_at      DATETIME
updated_at      DATETIME
```

요일:

```text
1 = Monday
2 = Tuesday
3 = Wednesday
4 = Thursday
5 = Friday
6 = Saturday
7 = Sunday
```

예:

```text
Monday
09:00 - 12:00

Monday
13:00 - 18:00
```

두 Row로 저장할 수 있다.

---

# 21. business_links

업체 외부 링크/SNS.

```text
business_links
────────────────────────────
id              BIGINT PK
business_id     BIGINT FK
link_type       VARCHAR(30)
url             VARCHAR(1000)
label           VARCHAR(100) NULL
is_primary      SMALLINT DEFAULT 0
sort_order      INT DEFAULT 0
created_at      DATETIME
```

예상 `link_type`:

```text
WEBSITE
INSTAGRAM
NAVER_BLOG
KAKAO_CHANNEL
YOUTUBE
OTHER
```

SNS별 컬럼을 `businesses`에 직접 추가하지 않는다.

---

# 22. business_images

업체 이미지.

이미지 Binary는 DB에 저장하지 않는다.

```text
business_images
────────────────────────────
id                  BIGINT PK
business_id         BIGINT FK
storage_type        VARCHAR(20)
file_path           VARCHAR(1000)
original_filename   VARCHAR(255) NULL
mime_type           VARCHAR(100) NULL
width               INT NULL
height              INT NULL
image_type          VARCHAR(30)
alt_text            VARCHAR(300) NULL
sort_order          INT DEFAULT 0
is_primary          SMALLINT DEFAULT 0
created_at          DATETIME
```

Storage Type:

```text
LOCAL
R2
S3
```

Image Type:

```text
PROFILE
STORE
WORK
OTHER
```

초기에는 `LOCAL`을 사용한다.

---

# 23. work_cases

Before / After 작업사례.

```text
work_cases
────────────────────────────
id              BIGINT PK
business_id     BIGINT FK
category_id     BIGINT NULL FK
brand_id        BIGINT NULL FK
title           VARCHAR(200)
slug            VARCHAR(220)
description     TEXT NULL
status          VARCHAR(20)
published_at    DATETIME NULL
created_at      DATETIME
updated_at      DATETIME
deleted_at      DATETIME NULL
```

Index:

```text
INDEX(business_id)
INDEX(category_id)
INDEX(brand_id)
INDEX(status)
INDEX(published_at)
```

---

# 24. work_case_images

```text
work_case_images
────────────────────────────
id              BIGINT PK
work_case_id    BIGINT FK
storage_type    VARCHAR(20)
file_path       VARCHAR(1000)
image_role      VARCHAR(20)
sort_order      INT DEFAULT 0
created_at      DATETIME
```

Image Role:

```text
BEFORE
AFTER
OTHER
```

---

# 25. work_case_services

작업사례에 적용된 서비스.

```text
work_case_services
────────────────────────────
work_case_id    BIGINT FK
service_id      BIGINT FK
```

PK:

```text
PRIMARY KEY(work_case_id, service_id)
```

한 작업사례에 여러 서비스가 적용될 수 있다.

---

# 26. user_favorites

사용자 업체 즐겨찾기.

```text
user_favorites
────────────────────────────
user_id         BIGINT FK
business_id     BIGINT FK
created_at      DATETIME
```

PK:

```text
PRIMARY KEY(user_id, business_id)
```

DB 차원에서 동일 업체 중복 즐겨찾기를 방지한다.

---

# 27. business_sources

업체 정보 출처.

초기 업체 DB 구축 및 데이터 검증에 사용한다.

```text
business_sources
────────────────────────────
id                  BIGINT PK
business_id         BIGINT FK
source_type         VARCHAR(30)
source_url          VARCHAR(1000) NULL
external_id         VARCHAR(255) NULL
collected_at        DATETIME NULL
last_verified_at    DATETIME NULL
created_at          DATETIME
updated_at          DATETIME
```

예상 Source Type:

```text
OFFICIAL
NAVER
GOOGLE
INSTAGRAM
BLOG
OWNER
MANUAL
```

---

# 28. business_verifications

업체 검증 정보.

```text
business_verifications
────────────────────────────
id                  BIGINT PK
business_id         BIGINT FK
verification_type   VARCHAR(30)
status              VARCHAR(20)
verified_by         BIGINT NULL
verified_at         DATETIME NULL
note                TEXT NULL
created_at          DATETIME
updated_at          DATETIME
```

예상 Verification Type:

```text
BUSINESS_REGISTRATION
PHONE
OWNER
MANUAL
```

---

# 29. business_claims

업체 소유권 Claim.

```text
business_claims
────────────────────────────
id                  BIGINT PK
business_id         BIGINT FK
user_id             BIGINT FK
status              VARCHAR(20)
requested_at        DATETIME
reviewed_at         DATETIME NULL
reviewed_by         BIGINT NULL
note                TEXT NULL
created_at          DATETIME
updated_at          DATETIME
```

예상 상태:

```text
PENDING
APPROVED
REJECTED
```

승인 시 `businesses.claimed_user_id`를 연결한다.

---

# 30. search_logs

검색 분석 로그.

```text
search_logs
────────────────────────────
id              BIGINT PK
user_id         BIGINT NULL
session_key     VARCHAR(100) NULL
keyword         VARCHAR(300) NULL
category_id     BIGINT NULL
service_id      BIGINT NULL
region_id       BIGINT NULL
brand_id        BIGINT NULL
result_count    INT
created_at      DATETIME
```

Index:

```text
INDEX(created_at)
INDEX(keyword)
INDEX(category_id)
INDEX(service_id)
INDEX(region_id)
```

향후 분석 예:

```text
가장 많이 검색된 작업
결과가 0건인 검색어
지역별 수요
카테고리별 검색량
```

---

# 31. event_logs

사용자 행동 로그.

```text
event_logs
────────────────────────────
id              BIGINT PK
user_id         BIGINT NULL
session_key     VARCHAR(100) NULL
event_name      VARCHAR(50)
entity_type     VARCHAR(30) NULL
entity_id       BIGINT NULL
metadata        TEXT NULL
created_at      DATETIME
```

예:

```text
event_name = BUSINESS_VIEW
entity_type = BUSINESS
entity_id = 123
```

또는:

```text
event_name = CASE_VIEW
entity_type = WORK_CASE
entity_id = 555
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

`metadata`에는 핵심 도메인 정보가 아닌 분석용 부가정보만 JSON 문자열로 저장할 수 있다.

예:

```json
{
  "source": "search_result",
  "position": 3
}
```

---

# 32. guides

Public Web SEO 콘텐츠.

```text
guides
────────────────────────────────
id                  BIGINT PK
title               VARCHAR(250)
slug                VARCHAR(250)
summary             VARCHAR(500) NULL
content              LONGTEXT
seo_title           VARCHAR(250) NULL
meta_description    VARCHAR(500) NULL
status              VARCHAR(20)
published_at        DATETIME NULL
created_at          DATETIME
updated_at          DATETIME
deleted_at          DATETIME NULL
```

예상 Status:

```text
DRAFT
PUBLISHED
HIDDEN
```

Constraint:

```text
UNIQUE(slug)
```

예상 URL:

```text
/guides/leather-bag-restoration-price
```

---

# 33. Soft Delete 정책

다음 주요 Entity는 즉시 Hard Delete하지 않는다.

```text
users
businesses
work_cases
guides
reviews
```

다음 컬럼을 사용한다.

```text
deleted_at
```

---

# 34. Reference Data 삭제 정책

다음 Reference Data는 삭제보다 상태 변경을 우선한다.

```text
categories
services
regions
brands
```

예:

```text
status = INACTIVE
```

이유:

연결된 Business/WorkCase 데이터가 존재하는 상태에서 Reference Data가 사라지는 것을 방지한다.

---

# 35. Child / Join Data 삭제 정책

예:

```text
business_categories
business_offerings
business_brands
business_images
work_case_images
work_case_services
```

부모 Entity가 실제 Hard Delete되는 경우 `ON DELETE CASCADE`를 사용할 수 있다.

단, 주요 Entity는 기본적으로 Soft Delete를 사용하므로 실제 Hard Delete는 관리 작업으로 제한한다.

---

# 36. FK 기본 정책

Child Entity:

```text
businesses
    ↓
business_images
```

예:

```text
ON DELETE CASCADE
```

Reference Data:

```text
categories
    ↓
business_categories
```

예:

```text
ON DELETE RESTRICT
```

Reference Data의 실수 삭제로 대량 관계 데이터가 제거되지 않도록 한다.

---

# 37. 예상 테이블 목록

## Authentication

```text
users
user_social_accounts
auth_sessions
admins
```

## Business

```text
businesses
business_locations
business_hours
business_links
business_images
business_sources
business_verifications
business_claims
```

## Classification

```text
regions
categories
services
category_services
brands
```

## Business Relations

```text
business_categories
business_offerings
business_brands
```

## Work Case

```text
work_cases
work_case_images
work_case_services
```

## User Feature

```text
user_favorites
```

## Reputation

```text
business_external_refs
business_external_ratings
reviews
review_images
business_reputation_stats
```

## Analytics

```text
search_logs
event_logs
```

## SEO

```text
guides
```

예상 총 테이블 수:

```text
32
```

---

# 38. 핵심 테이블 우선순위

실제 서비스 핵심은 다음이다.

```text
businesses
business_locations
business_offerings
categories
services
regions
work_cases
users
reviews
business_reputation_stats
```

그 외 테이블은 인증, 운영, 관계, 분석, SEO 기능을 지원한다.

---

# 39. business_offerings 설계 결정

검색 시스템의 핵심 설계 결정이다.

사용하지 않을 단순 구조:

```text
business_categories
business_services
```

문제 예:

```text
ABC업체

카테고리:
- 가방
- 신발

서비스:
- 염색
- 밑창교체
```

이 경우 DB만 보면 다음 잘못된 조합도 가능한 것처럼 보일 수 있다.

```text
가방 + 밑창교체
신발 + 염색
```

따라서:

```text
business_offerings
```

를 사용한다.

예:

```text
ABC업체 + 가방 + 염색
ABC업체 + 신발 + 밑창교체
```

이 구조를 통해 사용자가 실제로 원하는 검색:

```text
가방
+
염색
+
강남
```

을 정확하게 처리한다.

---

# 40. PostgreSQL Migration 고려

현재 MariaDB를 사용하지만 다음 원칙을 유지한다.

## 권장

```text
BIGINT
INT
VARCHAR
TEXT
DECIMAL
DATE
DATETIME
FK
JOIN TABLE
Prepared Statement
Transaction
```

## 최소화

```text
ENUM
UNSIGNED
MariaDB 전용 함수
Stored Procedure Business Logic
핵심 검색 데이터를 JSON으로 저장
Vendor-specific SQL
```

DB 전용 SQL이 필요하면 Repository에 격리한다.

---

# 41. Query Security

ORM은 사용하지 않는다.

모든 SQL은 Repository에서 PDO Prepared Statement로 작성한다.

필수 규칙:

```text
사용자 입력값 SQL 직접 삽입 금지
Prepared Statement 필수
Parameter Binding 필수
ORDER BY 등 Identifier는 Whitelist
IN Query는 Placeholder 동적 생성
LIKE Query도 Binding
LIMIT/OFFSET은 정수 검증
```

SQL 흐름:

```text
Request
   ↓
Validation
   ↓
Controller
   ↓
Service
   ↓
Repository
   ↓
PDO Prepared Statement
   ↓
MariaDB
```

---

# 42. Index 설계 원칙

모든 컬럼에 Index를 생성하지 않는다.

실제 Query Pattern을 기준으로 설계한다.

현재 예상 주요 Index:

```text
businesses.status
businesses.published_at

business_locations.business_id
business_locations.region_id
business_locations(latitude, longitude)

business_categories.category_id

business_offerings.business_id
business_offerings(category_id, service_id)
business_offerings.service_id
business_offerings.status

business_brands.brand_id

work_cases.business_id
work_cases.category_id
work_cases.brand_id
work_cases.status
work_cases.published_at

business_external_refs(business_id, provider)
business_external_refs(provider, external_id)

business_external_ratings(business_id, provider)
business_external_ratings.provider
business_external_ratings.rating
business_external_ratings.review_count
business_external_ratings.fetched_at
business_external_ratings.expires_at

reviews.business_id
reviews.user_id
reviews.status
reviews.created_at
reviews(business_id, status)

auth_sessions.user_id
auth_sessions.refresh_expires_at

search_logs.created_at
search_logs.keyword

event_logs.created_at
```

실제 DDL 단계에서 검색 Query를 기준으로 다시 검증한다.

---

# 43. 설계 및 구현 순서

논리 ERD 이후 다음 순서로 진행한다.

```text
1. MariaDB Version 확인 — 완료

2. 실제 Column Type 확정 — 완료

3. FK / ON DELETE 정책 최종 확정 — 완료

4. Index 최종 설계 — 완료

5. 001_initial_schema.sql 작성 및 적용 — 완료

6. Seed Data 작성 — 다음 작업

7. 대표 검색 Query 작성 — 예정

8. EXPLAIN 기반 Index 검증 — 예정

9. Repository 구현 — 예정
```

첫 Migration 파일 예상명:

```text
database/migrations/001_initial_schema.sql
```

---

# 44. 현재 확정 사항

```text
Database Name
= reform_platform

DBMS
= MariaDB

ORM
= 사용 안 함

DB Access
= PDO

SQL Location
= Repository only

Core Search Model
= Business + Category + Service + Region + BusinessOffering

User Auth
= SNS Login

Public Web
= SEO / Marketing

Mobile
= 실제 사용자 서비스

Future DB
= PostgreSQL Migration 고려
```

---

# 45. 초기 DDL 적용 결정

`database/migrations/001_initial_schema.sql` 작성 및 적용 시 다음을 확정했다.

```text
검증된 MariaDB Version
= 10.6.17

Charset / Collation
= utf8mb4 / utf8mb4_unicode_ci

문서에서 NULL로 표시하지 않은 Column
= NOT NULL

created_at / updated_at
= DB 자동 Default 없이 PHP Application에서 입력·갱신

Child / Join FK
= 부모 Hard Delete 시 CASCADE

Reference FK
= RESTRICT

nullable 소유자·검토자 FK
= SET NULL
```

추가 결정:

- `business_verifications.verified_by`와 `business_claims.reviewed_by`는 `admins.id`를 참조한다.
- `search_logs`와 `event_logs`의 식별자 Column에는 분석 기록 보존을 위해 FK를 적용하지 않는다.
- `work_cases.slug`는 ID가 포함된 URL을 사용하므로 전역 Unique Constraint를 적용하지 않는다.
- 문서에 정의되지 않은 CHECK Constraint와 영업시간 Unique Constraint는 초기 DDL에 추가하지 않는다.
- `business_offerings`의 Unique Index 선두 Column이 `business_id` 조회를 지원하므로 중복 단일 Index는 만들지 않는다.
- `schema_migrations`는 27개 Domain Table과 별도로 Migration 버전과 SHA-256 Checksum을 기록한다.
- `business_offerings`가 Category + Service 조합의 기준이며 별도 `business_services` 테이블은 사용하지 않는다.

---

# 46. Migration 실행 구조

초기 Schema와 이후 변경사항은 다음 구조로 관리한다.

```text
database/migrations/*.sql
= Version 관리되는 Schema 변경 원본

src/Database/MigrationRunner.php
= Migration 탐색, SHA-256 Checksum 검증, 적용 이력 기록

bin/migrate.php
= 로컬 CLI 실행 진입점

schema_migrations
= 적용된 Version, Checksum, 적용 시각 기록
```

보조 연결 확인 명령:

```text
bin/check-database.php
```

Composer 명령:

```bash
composer db:check
composer db:status
composer db:migrate
composer test:database
```

운영 원칙:

- `bin/`은 로컬 관리 CLI 전용이며 HTTP Route에 연결하지 않는다.
- API와 Public Web의 정상 요청 처리에서 Migration을 자동 실행하지 않는다.
- Cafe24의 운영 Shell 사용 가능 여부를 전제로 애플리케이션을 설계하지 않는다.
- 운영 Schema 변경은 검토된 SQL Migration을 별도의 승인된 배포 절차로 적용한다.
- 적용된 Migration 파일의 내용이 변경되면 Checksum 오류로 중단한다.

---

# 47. 평판 및 리뷰 설계 반영

세부 정책은 다음 변경 설계서를 우선한다.

```text
.docs/reform_platform_reputation_design_change.md
```

적용 Migration:

```text
database/migrations/002_add_reputation_schema.sql
```

추가된 Domain Table:

```text
business_external_refs
business_external_ratings
reviews
review_images
business_reputation_stats
```

DDL 적용 결정:

- MariaDB가 Unique Index에서 여러 NULL을 허용하므로 `(provider, external_id)` Unique Index를 적용한다.
- 외부 평점은 `(business_id, provider)` Composite FK로 `business_external_refs`를 참조한다.
- 자체 리뷰의 `rating`은 CHECK Constraint로 1부터 5까지만 허용한다.
- `reviews`는 `deleted_at`을 사용하는 Soft Delete 대상이다.
- 새 Child Table은 부모가 실제 Hard Delete될 때 `ON DELETE CASCADE`를 적용한다.
- 기존 업체의 평판 통계 Row는 Migration 시 생성하며 기존 즐겨찾기·미삭제 작업사례 수를 반영한다.
- 외부 리뷰 원문·작성자·사진은 저장하지 않는다.
- Naver 평점과 리뷰 수는 공식 사용이 허용되기 전까지 저장하지 않는다.
- 자체 평판과 외부 평판은 합산하지 않는다.

후속 설계에서 확정할 항목:

- `business_sources`와 `business_external_refs` 사이 Provider 식별자의 Source of Truth 및 기존 데이터 이관 여부
- `reviews.status = DELETED`와 `deleted_at`의 역할 구분
- `work_case_count`에 포함할 WorkCase Status와 생성·게시·숨김·Soft Delete 시 갱신 규칙
- 신규 Business 생성 시 `business_reputation_stats` Row 생성 Transaction
