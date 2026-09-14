# 개발 진행 상태

- 기준일: 2026-09-14
- 현재 단계: Phase 2 — DB / Core Domain 구조 준비
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

## 검증 상태

```text
composer test
→ Foundation smoke tests passed.

composer validate --strict
→ valid

composer audit
→ No security vulnerability advisories found.
```

로컬 PHP는 8.4.25, Composer는 2.10.3에서 검증했다.

## 아직 구현하지 않은 항목

- DB Migration 및 Schema
- Business 등 Core Domain
- Repository/Service/Controller 실제 구현
- Search, SNS Auth, Favorite, Admin, Mobile, Public SEO Web

## 다음 작업

디렉터리 구조만 준비된 상태다. 시스템 설계서 Phase 2의 Core DB Schema를 시작하기 전에 다음 미확정 항목을 사용자와 결정한다.

- 최종 Column Type
- Index
- Foreign Key Delete Rule
- Soft Delete 적용 범위

첫 Vertical Slice는 설계서에 따라 Business Domain으로 진행한다.

## 로컬 DB 설정

`config/database.local.php`가 없다면 다음 파일을 복사한 뒤 접속 정보를 입력한다.

```text
config/database.local.php.example
→ config/database.local.php
```

실제 접속 정보와 비밀번호는 Git에 포함하지 않는다.

## 폴더 이동

프로젝트 코드에는 기존 절대 경로가 저장되어 있지 않다. 새 위치에서 다음을 실행한다.

```bash
composer install --prefer-dist
composer test
```

외장 저장장치에서 설치한 기존 `vendor/`에는 macOS AppleDouble 메타데이터가 남을 수 있으므로, APFS 위치로 이동한 뒤 `vendor/`를 새로 설치하는 것을 권장한다.

현재 APFS 경로에서 `composer test`, `composer validate --strict`, `composer audit` 통과를 확인했다.
