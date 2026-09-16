# 개발 진행 상태

- 기준일: 2026-09-16
- 현재 단계: Phase API-1 — Reference 완료, Business·Work Case 확장 대기
- 구현 기준: `AGENTS.MD`, `.docs/reform_mobile_api_definition_v1.md`, `.docs/reform_platform_system_design.md`

## 완료된 항목

- PHP 8.4 및 Composer 로컬 환경
- PSR-4 Autoload (`App\` → `src/`)
- Symfony Routing 7.4
- Symfony HttpFoundation 7.4
- Monolog 3
- Swagger UI 5.32.15 및 OpenAPI 3.1
- `public/index.php` Front Controller
- API/Web/Admin Route 파일 분리
- PDO MariaDB Connection Factory
- 중앙 Exception Handler
- API 성공·목록·오류 응답 규격
- Foundation Smoke Test
- Phase 2 기본 디렉터리 구조
  - `database/migrations/`
  - `database/seeds/`
  - `src/Controller/{Api/V1,Web,Admin}/`
  - `src/{Service,Repository,Auth,Storage}/`
  - `src/Http/Middleware/`
  - `views/`, `public/assets/`
- 로컬 설정·의존성·런타임 파일 Git 제외 규칙
- MariaDB 10.6 연결 및 `utf8mb4_unicode_ci` 확인
- `001_initial_schema.sql`
  - 설계 문서의 Domain Table 27개
  - Migration 이력 Table 1개
  - Index, Unique Constraint, Foreign Key Delete Rule
- `002_add_reputation_schema.sql`
  - 외부 플랫폼 Reference 및 허용된 평점 통계
  - 자체 Review 및 Review Image
  - 업체별 자체 Reputation 집계
  - 변경 후 Domain Table 32개
- DB Migration Runner 및 상태 확인 명령
- 실제 DB Schema 검증 Test
- `GET /api/v1/businesses/{id}`
  - BusinessRepository → BusinessService → API BusinessController
  - ACTIVE, 미삭제, 게시 시각 기준 공개
  - 자체 평판과 외부 평판 분리 응답
  - BUSINESS_NOT_FOUND 및 ID Validation
- Business API Contract Test
- `GET /api/v1/businesses`
  - page/perPage Pagination
  - 게시일 최신순 정렬
  - 자체·외부 평판 포함
  - 외부 평판 Batch Query로 N+1 방지
  - VALIDATION_ERROR 처리
- Reference API
  - `GET /api/v1/categories`
  - `GET /api/v1/services`
  - `GET /api/v1/regions`
  - `GET /api/v1/brands`
  - ACTIVE 데이터 및 계층·카테고리·검색·페이지 조건
  - CategoryRepository/ServiceRepository/RegionRepository/BrandRepository
  - ReferenceService → ReferenceController
- `POST /api/v1/events`
  - EventController → EventService → EventLogRepository
  - 허용 Event Name Whitelist
  - 민감한 Metadata Key 차단
  - JSON 형식·필드·길이 Validation
  - 성공 시 HTTP 204
- API 문서
  - `GET /api/docs`
  - `GET /api/docs/openapi.json`
  - `openapi/openapi.json`
  - Swagger Asset 허용 목록 및 보안 Header

## 검증 상태

```text
composer test
→ Foundation smoke tests passed.
→ Business API tests passed.
→ Reference API tests passed.
→ Event API tests passed.
→ API documentation tests passed.

composer validate --strict
→ valid

composer audit
→ No security vulnerability advisories found.

composer test:database
→ Database schema tests passed.

composer db:status
→ [applied] 001_initial_schema
→ [applied] 002_add_reputation_schema
```

로컬 PHP는 8.4.25, Composer는 2.10.3에서 검증했다.

## 아직 구현하지 않은 항목

- 나머지 Core Domain
- 나머지 Repository/Service/Controller 구현
- Reference Seed Data
- 모바일 API 정의서 기준 Business 검색 Filter·정렬 및 전체 Summary/Detail DTO
- Reputation 및 Review API
- Search, SNS Auth, Favorite, Admin, Mobile, Public SEO Web

## 다음 작업

Reference API 4종의 코드와 OpenAPI 계약이 완료됐다. 실제 초기 데이터는 서비스 분류값이 확정된 뒤 Seed로 추가한다.

현재 Business API는 모바일 API 정의서가 추가되기 전의 간략 Read 계약이다. 다음 작업에서는 기존 Endpoint를 삭제하지 않고 정의서의 검색 조건과 전체 DTO로 확장한다.

```text
GET /api/v1/businesses
GET /api/v1/businesses/{businessId}
```

## 로컬 DB 설정

`config/database.local.php`가 없다면 다음 파일을 복사한 뒤 접속 정보를 입력한다.

```text
config/database.local.php.example
→ config/database.local.php
```

실제 접속 정보와 비밀번호는 Git에 포함하지 않는다.

```bash
composer db:check
composer db:status
composer db:migrate
composer test:database
```

## 폴더 이동

프로젝트 코드에는 기존 절대 경로가 저장되어 있지 않다. 새 위치에서 다음을 실행한다.

```bash
composer install --prefer-dist
composer test
```

외장 저장장치에서 설치한 기존 `vendor/`에는 macOS AppleDouble 메타데이터가 남을 수 있으므로, APFS 위치로 이동한 뒤 `vendor/`를 새로 설치하는 것을 권장한다.

현재 APFS 경로에서 `composer test`, `composer validate --strict`, `composer audit` 통과를 확인했다.

2026-09-16 현재 로컬 MariaDB가 중지되어 Business 및 Reference Repository의 실제 DB Query 재검증은 보류됐으며, DB를 사용하지 않는 API Contract Test는 통과했다.
