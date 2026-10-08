# Reform Platform

리폼·수선·복원 업체 검색 플랫폼의 PHP 백엔드, Public Web, Admin 애플리케이션입니다.

현재 구현 범위는 **Foundation, 앱 API 요구사항 45개, 초기 DB·평판·앱 기능 Schema**입니다.

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
DATA_GO_KR_SERVICE_KEY
APP_ENV
APP_DEBUG
APP_TIMEZONE
CORS_ALLOWED_ORIGINS
LOG_LEVEL
APP_BASE_URL
UPLOAD_MAX_IMAGE_BYTES
AUTH_ACCESS_TTL_SECONDS
AUTH_REFRESH_TTL_SECONDS
AD_TRACKING_SECRET
APP_MINIMUM_VERSION
APP_LATEST_VERSION
APP_MAINTENANCE_ENABLED
APP_MAINTENANCE_MESSAGE
CUSTOMER_CENTER_PHONE
CUSTOMER_CENTER_HOURS
TERMS_OF_SERVICE_URL
PRIVACY_POLICY_URL
OPEN_SOURCE_LICENSE_URL
```

`CORS_ALLOWED_ORIGINS`는 쉼표로 구분한 허용 Origin 목록입니다. 로컬 환경의 기본값은
`http://localhost:8081,http://127.0.0.1:8081,http://[::1]:8081`이며, 운영 환경에서는 실제 앱 웹 Origin을 명시해야 합니다.

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

### 목록 페이지네이션

작업 사례, 리뷰, 문의, 찜, 최근 본 업체, 알림, FAQ 목록은 모두 페이지 번호 기반
페이지네이션을 지원합니다. 무한 스크롤에서는 한 번에 최대 건수를 요청하지 말고 다음과 같이
필요한 페이지를 순서대로 조회합니다.

```text
GET /api/v1/help/faqs?page=1&perPage=20
```

응답의 `meta.page`가 `meta.totalPages`보다 작을 때만 `page`를 1 증가시켜 다음 목록을
요청합니다. `perPage` 기본값은 20, 최댓값은 100입니다.

## 데이터베이스

```bash
composer db:check
composer db:status
composer db:migrate
composer db:seed
composer test:seed
```

- `db:check`: MariaDB 연결, 버전, Charset, Collation 확인
- `db:status`: Migration 적용 상태 확인
- `db:migrate`: 적용되지 않은 Migration 실행
- `db:seed`: 기준 정보와 개발용 데모 콘텐츠를 중복 없이 추가
- `db:seed:reference`: 카테고리·서비스·지역·브랜드·FAQ만 추가
- `db:seed:demo`: 기준 정보 적용 후 화면 확인용 데모 업체·작업 사례 추가
- `test:seed`: 필수 Seed 건수와 연결 관계 확인

기본 Seed에는 상위 카테고리 8개와 하위 카테고리 13개, 서비스 18개, 전국 시·도와 서울 자치구 42개 지역, 브랜드 20개, FAQ 11개가 포함됩니다. 데모 업체 3개와 작업 사례 4개는 이름에 `[데모]`를 표시하여 실제 업체와 구분합니다.

Schema 변경은 `database/migrations/`의 버전 관리 SQL 파일로만 기록합니다. 운영 환경에서 애플리케이션 요청 중 Migration을 자동 실행하지 않습니다.

현재 적용 Migration:

```text
001_initial_schema
002_add_reputation_schema
003_add_app_api_schema
004_add_review_idempotency
005_add_semas_business_import
006_add_korean_schema_comments
007_publish_semas_businesses
```

## 소상공인시장진흥공단 업체 수집

[소상공인시장진흥공단 상가(상권)정보 API](https://www.data.go.kr/data/15012005/openapi.do)의
업종별 상가업소 조회를 사용합니다. `S2 > S206 > S20601/S20602/S20603/S20699` 원본을 먼저
`semas_business_imports`에 적재하고, 설정된 키워드로 분류한 결과를
`semas_business_import_keywords`에 저장합니다. 매칭된 데이터만 기존 업체·주소·카테고리·서비스·출처
테이블로 동기화됩니다. 키워드에 매칭된 외부 수집 업체는 `ACTIVE` 상태와 수집 시각의 공개일로 생성되어
앱 업체 목록에 노출됩니다.

인증키는 코드에 커밋하지 않습니다. 아래 파일을 만든 뒤 공공데이터포털의 **일반 인증키(Decoding)** 를
입력합니다.

```bash
cp config/public_data.local.php.example config/public_data.local.php
```

또는 `DATA_GO_KR_SERVICE_KEY` 환경 변수를 사용할 수 있습니다. 대상 업종 코드와 키워드는
`config/public_data.php`에서 관리하며, 로컬 파일에서 필요한 값만 재정의할 수 있습니다.

Migration과 기준 Seed를 먼저 적용한 뒤 전체 작업을 실행합니다.

```bash
composer db:migrate
composer db:seed:reference
composer db:import:semas
```

세 단계를 나누어 실행하거나 중단 지점부터 재실행할 수도 있습니다.

```bash
composer db:import:semas:collect
composer db:import:semas:classify
composer db:import:semas:sync
```

수집은 상가업소번호 기준 Upsert 방식이며, 동일 명령을 다시 실행해도 업체가 중복 생성되지 않습니다.
공유호스팅 HTTP 요청이나 상시 Worker에서는 실행하지 않고, 승인된 로컬/배포 유지보수 절차에서만 실행합니다.

## 로컬 서버

```bash
composer serve
```

기본 주소는 `http://127.0.0.1:8080`이며, 개발 중 실제 모바일 기기에서도 접근할 수 있도록 모든 로컬 네트워크 인터페이스에 바인딩됩니다.

`.docs/app_api/reform_app_api_requirements.md`의 P0/P1/P2 API 45개를 구현했습니다. 기존 이벤트·검색어 제안과 Cafe24 로컬 저장소용 업로드 PUT을 더해 OpenAPI에는 총 48개 operation이 등록되어 있습니다.

구현 영역:

```text
인증: SNS 로그인, 갱신, 로그아웃
공개 조회: 기준 정보, 업체, 작업 사례, 리뷰, FAQ, 광고, 앱 설정
사용자 기능: 프로필, 활동 수, 찜, 문의, 내 리뷰, 최근 본 업체
알림 기능: 알림 조회·읽음, 사용자 설정, 푸시 기기
미디어: 업로드 URL 발급, 이미지 검증·저장, 완료 처리
운영 보조: 이벤트, 검색어 제안, 광고 노출·클릭 추적
```

리뷰 작성은 선택적인 `Idempotency-Key`를 지원합니다. 토큰 원문은 저장하지 않고 SHA-256 hash만 저장하며, Refresh Token은 회전합니다. 이미지 업로드는 실제 MIME·크기·이미지 디코딩 여부를 검사합니다.

앱 연동 호환을 위해 업체 검색은 `query`/`latitude`/`longitude`/`limit`, 브랜드 검색은 `query`/`limit`을 기존 파라미터의 별칭으로 지원합니다. v1 응답과 페이지 번호 기반 Pagination은 기존 계약을 유지합니다.

SNS Provider 검증 구현체와 운영 TTL/URL/용량 값은 배포 환경별 설정 사항입니다. 설정 전 소셜 로그인과 관련 기능은 가짜 인증을 허용하지 않고 `503` 오류를 반환합니다. 공개 이미지 URL 변환 정책이 확정되기 전까지 조회 API의 이미지 URL은 `null` 또는 빈 배열입니다.

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

## 네이버 로그인

`config/social.local.php.example`을 `config/social.local.php`로 복사하고 Client ID, Client Secret, 허용 Callback URL을 설정합니다.
로컬 설정이 없으면 `NAVER_CLIENT_ID`, `NAVER_CLIENT_SECRET`, `NAVER_REDIRECT_URIS`(쉼표 구분)를 사용합니다.
Secret은 Git이나 모바일 앱에 포함하지 않습니다.
앱은 인가 코드와 state를 `/api/v1/auth/social-login`으로 전송하며, 서버가 네이버 토큰 교환과 회원정보 조회를 수행합니다.
`GET /api/v1/auth/naver/callback`은 code/state 또는 오류를 고정 앱 복귀 주소로 전달합니다.
PHP HTTPS stream 통신을 위해 `allow_url_fopen`과 CA 인증서 설정이 필요합니다.
서비스 토큰 만료 정책(`AUTH_ACCESS_TTL_SECONDS`, `AUTH_REFRESH_TTL_SECONDS`)은 기존 설정을 사용합니다.
[네이버 공식 인증 명세](https://developers.naver.com/docs/login/api/api.md)를 참고하세요.

## 카카오 네이티브 로그인

카카오 Developers에서 카카오 로그인과 OpenID Connect를 활성화합니다. 서버는 SDK가 발급한 ID 토큰의 RS256 서명, 발급자, Native 앱 키(aud), 만료 시각과 회원번호를 검증한 후 기존 회원 조회 및 서비스 토큰 발급을 수행합니다. REST API 키나 클라이언트가 전달한 회원번호로 인증하지 않습니다.

`config/kakao.local.php.example`을 `config/kakao.local.php`로 복사하고 `native_app_keys`에 앱의 Native 앱 키를 설정합니다. 배포 시 `KAKAO_NATIVE_APP_KEYS` 환경변수(여러 값은 쉼표 구분)로도 설정할 수 있습니다. PHP OpenSSL 확장이 필요합니다. 키 검증 방식은 현재 카카오 Native SDK의 ID 토큰 방식에 한정하며, 다른 방식은 별도 검토 후 추가합니다.

`POST /api/v1/auth/social-login`에 `provider: "kakao"`, `idToken`, `device.deviceId`를 전송합니다. ID 토큰 방식에는 `redirectUri`가 필요하지 않고, 인가 코드 방식에는 필수입니다. 두 인증 값을 동시에 보내면 거절됩니다. 개인정보 동의가 없으면 이름·이메일은 생략됩니다.

공개 서명키만 `storage/cache/kakao-jwks.json`에 1시간 보관하며, 알 수 없는 kid에는 키를 다시 조회합니다. 캐시를 기록할 수 없어도 로그인은 동작하고, 공개키 조회나 토큰 검증에 실패하면 인증을 거절합니다. 토큰은 로그에 기록하지 않습니다. 서비스 토큰 만료 정책은 기존 `AUTH_ACCESS_TTL_SECONDS` / `AUTH_REFRESH_TTL_SECONDS`를 그대로 사용합니다.

검증: `php tests/kakao_login.php`, `composer test`.

로컬 개발 서버의 만료 정책이 비어 있다면 `config/auth.local.php.example`을 `config/auth.local.php`로 복사합니다. 기존 통합 테스트와 같은 액세스 15분·리프레시 1시간의 개발용 값이며 최종 배포 정책이 아닙니다. 이 파일은 Git에서 제외되고 `APP_ENV=local`에서만 읽습니다. 운영에서는 서버 환경변수를 설정해야 하며, 값이 없으면 기존처럼 서비스 토큰 발급을 거절합니다.

## Google 로그인

앱에서 받은 Google ID 토큰을 `/api/v1/auth/social-login`에 `provider: "google"`,
`idToken`, `device.deviceId`로 전달한다. 서버는 Google의 고정 JWKS 주소에서 가져온
RSA 공개키로 RS256 서명을 검증하고 issuer, audience, 만료 시각, 발급 시각, subject,
존재하는 경우 authorized party(`azp`)를 검증한다. Google `sub`를 회원 식별자로 사용하고
검증된 이메일과 선택적인 이름만 매핑한다. 서비스 access/refresh token 발급은 기존 정책을 사용한다.

`config/google.local.php.example`을 `config/google.local.php`로 복사해 설정하거나 다음 환경변수를 사용한다.

```dotenv
GOOGLE_WEB_CLIENT_IDS=웹_애플리케이션_OAuth_클라이언트_ID
GOOGLE_AUTHORIZED_CLIENT_IDS=Android_ID,iOS_ID,웹_ID
```

로컬 파일이 환경변수보다 우선한다. `web_client_ids`에는 웹 유형 ID만 넣는다.
`authorized_client_ids`에는 같은 프로젝트의 Android/iOS/웹 ID를 넣는다.
Client Secret은 필요하지 않다. 공개키 캐시는 `storage/cache/google-jwks.json`이며,
캐시를 쓸 수 없어도 검증은 가능하다. HTTPS stream과 OpenSSL이 필요하다.
배포에는 코드와 운영 클라이언트 ID 설정을 함께 반영한다.

```sh
php tests/google_login.php
composer test
```
