# Reform Platform

리폼·수선·복원 업체 검색 플랫폼의 PHP 백엔드, Public Web, Admin 애플리케이션입니다.

현재 구현 범위는 시스템 설계서의 **Phase 1 — Foundation 및 Phase 2 초기 DB·평판 Schema와 Business 상세 API**입니다.

## 요구사항

- PHP 8.4
- Composer 2
- MariaDB
- PHP extensions: JSON, PDO, pdo_mysql

## 로컬 설치

```bash
composer install --prefer-dist
cp config/database.local.php.example config/database.local.php
```

`config/database.local.php`에 로컬 MariaDB 접속 정보를 입력합니다. 이 파일은 Git에서 제외됩니다.

외장 저장장치의 파일시스템에서 macOS `._*` 메타데이터 때문에 dist 압축 해제가 실패하는 경우에만 `composer install --prefer-source`를 사용합니다.

환경 변수로 설정하려면 다음 값을 사용할 수 있습니다.

```text
DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD
APP_ENV
APP_DEBUG
APP_TIMEZONE
LOG_LEVEL
```

## 검증

```bash
composer test
composer test:database
```

`composer test`는 라우팅, HttpFoundation 요청/응답, API 응답 규격, 중앙 예외 처리와 `pdo_mysql` 사용 가능 여부를 확인하며 실제 DB에는 쿼리를 실행하지 않습니다.

`composer test:database`는 설정된 개발 DB에 초기 Schema가 올바르게 적용됐는지 확인합니다.

## API 문서

로컬 서버를 실행한 뒤 다음 주소에서 Swagger UI를 확인할 수 있습니다.

```text
http://127.0.0.1:8080/api/docs
```

OpenAPI 3.1 JSON:

```text
http://127.0.0.1:8080/api/docs/openapi.json
```

API 계약 원본은 `openapi/openapi.json`이며 API 변경과 같은 작업에서 함께 갱신합니다.

## 데이터베이스

```bash
composer db:check
composer db:status
composer db:migrate
```

- `db:check`: MariaDB 연결, 버전, Charset, Collation 확인
- `db:status`: Migration 적용 상태 확인
- `db:migrate`: 적용되지 않은 Migration 실행

Schema 변경은 `database/migrations/`의 버전 관리 SQL 파일로만 기록합니다. 운영 환경에서 애플리케이션 요청 중 Migration을 자동 실행하지 않습니다.

현재 적용 Migration:

```text
001_initial_schema
002_add_reputation_schema
```

## 로컬 서버

```bash
composer serve
```

기본 주소는 `http://127.0.0.1:8080`입니다.

구현된 API:

```text
GET /api/v1/businesses?page=1&perPage=20
GET /api/v1/businesses/{id}
```

공개 상태이고 게시 시각이 도달한 미삭제 업체의 핵심 정보와 자체·외부 평판을 반환합니다. 목록 API의 `perPage`는 최대 100입니다. 등록되지 않은 `/api/v1/*` 요청은 설계된 JSON 오류 규격으로 404를 반환합니다.

## 프로젝트 폴더 이동

애플리케이션 경로는 프로젝트 루트를 기준으로 계산하므로 폴더를 다른 위치로 이동해도 코드나 설정 경로를 수정할 필요가 없습니다.

이동 후 Cursor에서 새 폴더를 열고 다음 명령을 실행합니다.

```bash
composer install --prefer-dist
composer test
```

기존 `vendor/`가 외장 저장장치에서 `--prefer-source`로 설치된 상태라면 새 위치에서 `vendor/`를 제거한 뒤 위 명령으로 다시 설치하는 것을 권장합니다. `composer.lock`이 동일한 버전을 재현합니다.

`config/database.local.php`가 없다면 이동 후 예제 파일을 복사하고 로컬 DB 접속 정보를 입력합니다.

## 운영 의존성 설치

Cafe24 서버에서 Composer를 실행하지 않습니다. 배포 패키지는 로컬에서 다음 명령으로 생성하고 `vendor/`를 포함합니다.

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

## 구현 원칙

- 요청 흐름: Controller → Service → Repository → PDO → MariaDB
- Mobile API prefix: `/api/v1`
- Public Web: 로그인 없는 SEO/마케팅 채널
- Admin: 일반 사용자와 분리된 세션 인증
- SQL: Repository 내부의 PDO Prepared Statement만 사용

상세 기준은 `AGENTS.MD`와 `.docs/reform_platform_system_design.md`를 따릅니다.
