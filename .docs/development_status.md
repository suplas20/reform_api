# 개발 진행 상태

- 기준일: 2026-09-14
- 현재 단계: Phase 2 — 초기 DB Schema 완료
- 구현 기준: `AGENTS.MD`, `.docs/reform_platform_system_design.md`

## 완료된 항목

- PHP 8.4 및 Composer 로컬 환경
- PSR-4 Autoload (`App\` → `src/`)
- Symfony Routing 7.4
- Symfony HttpFoundation 7.4
- Monolog 3
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
- DB Migration Runner 및 상태 확인 명령
- 실제 DB Schema 검증 Test

## 검증 상태

```text
composer test
→ Foundation smoke tests passed.

composer validate --strict
→ valid

composer audit
→ No security vulnerability advisories found.

composer test:database
→ Database schema tests passed.

composer db:status
→ [applied] 001_initial_schema
```

로컬 PHP는 8.4.25, Composer는 2.10.3에서 검증했다.

## 아직 구현하지 않은 항목

- Business 등 Core Domain
- Repository/Service/Controller 실제 구현
- Reference Seed Data
- Search, SNS Auth, Favorite, Admin, Mobile, Public SEO Web

## 다음 작업

초기 Schema와 Migration 적용이 완료됐다. 다음 작업은 Reference Seed Data의 실제 값을 확정한 뒤, 설계서에 따라 Business Domain의 첫 Vertical Slice를 구현한다.

```text
BusinessRepository
→ BusinessService
→ API BusinessController
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
