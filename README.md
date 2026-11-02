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

### 현재 위치 기반 업체 검색

기존 `GET /api/v1/businesses`에서 사용자 위도·경도, 반경(km), 거리순 정렬을 함께 전달합니다.
기존 `lat`/`lng` 별칭과 검색어·카테고리·서비스·지역·브랜드 필터 및 페이지네이션을 그대로 지원합니다.

```text
GET /api/v1/businesses?latitude=37.5&longitude=127.03&radiusKm=3&sort=distance&page=1&perPage=20
```

`radiusKm`는 1·3·5·10 등 양수를 지원합니다. 생략하면 반경을 제한하지 않으며,
거리순 정렬에는 위도·경도가 모두 필요합니다. 좌표는 위도 -90..90, 경도 -180..180 범위의 유한수여야 합니다.
사용자 좌표를 전달하면 정렬 방식에 관계없이 `distanceMeters`(m)와 기존 `distanceKm`(km)를 반환합니다.
사용자 좌표가 없거나 업체 대표 위치의 좌표가 없거나 유효하지 않으면 두 거리는 `null`입니다.
좌표가 없는 업체는 반경 검색에서 제외되며, 반경 없는 거리순 검색에서는 마지막에 표시됩니다.

대표 위치는 기존 주소 표시와 동일하게 `is_primary DESC, id ASC`의 첫 위치입니다.
여러 지점이 있어도 반경·정렬·응답 거리를 이 대표 위치에 맞춥니다.
반경 검색은 기존 `(latitude, longitude)` 인덱스로 bounding box 후보를 조회한 뒤
MariaDB `ST_DISTANCE_SPHERE(POINT(longitude, latitude), POINT(userLongitude, userLatitude), 6370986)`로
구면 직선거리를 검증합니다. 날짜변경선과 극지방을 가로지르는 반경도 처리합니다.
지도 업체 API 호출과 사용자 위치의 DB 저장은 하지 않습니다. DB 구조 변경은 없습니다.

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

## 국가데이터처 행정동 원본 수집

[행정동 정보 API](https://infuser.odcloud.kr/oas/docs?namespace=15136373/v1)의 기본 주소는
`https://api.odcloud.kr/api`입니다. 인증키는 `config/administrative_regions.local.php.example`을
`config/administrative_regions.local.php`로 복사해 `service_key`에 설정하거나,
`ADMINISTRATIVE_REGIONS_SERVICE_KEY` 환경변수로 전달합니다. 로컬 파일은 Git에서 제외됩니다.
기존 SEMAS 인증 설정과는 별도로 관리하며 인증키는 `Authorization: Infuser ...` 헤더에만 넣습니다.

```bash
composer db:migrate
composer db:import:regions
composer db:import:regions:status
```

수집은 로컬 유지보수 CLI에서만 실행합니다. HTTP 경로, 큐, 상시 Worker를 추가하지 않습니다.
`administrative_region_imports`에는 자료 버전, 전체 행 수, 완료 페이지, 검증 해시와 완료 상태를,
`administrative_region_records`에는 원본 아홉 필드와 검증된 날짜를 저장합니다.
실제 응답에는 여러 개정 시점, 같은 코드의 여러 행, 코드 없는 배경 설명과 줄바꿈도 포함됩니다.
행정동번호나 코드 하나를 고유키로 가정하지 않고 `스냅샷 + 원본 행 위치`를 식별자로 사용하여
동일한 내용의 원본 행까지 보존합니다. 각 페이지의 원본과 체크포인트는 같은 트랜잭션으로 저장됩니다.

중단 후 같은 명령을 실행하면 첫 페이지와 마지막 완료 페이지의 해시 및 전체 행 수를 비교하고
다음 페이지부터 재개합니다. 원본이 바뀌면 중단하며 이전 데이터를 덮어쓰지 않습니다.
완료된 스냅샷은 재실행해도 API 호출이나 중복 저장을 하지 않습니다. 새 자료를 수집할 때는
`config/administrative_regions.php`의 자료 버전·경로를 확인하고 새 `snapshot_key`를 지정합니다.
API가 정렬이나 스냅샷 일관성을 보장한다는 명세는 없으므로 페이지 해시 검증만으로 수집 도중의
모든 변경을 검출할 수는 없습니다. 제공 자료를 갱신하지 않는 시간대에 수집하는 것이 권장됩니다.

앱용 `regions` 반영은 별도의 로컬 명령으로 실행합니다.

```bash
composer db:import:regions:plan  # 변경 없이 추가·제외 항목 확인
composer db:import:regions:sync  # 한 트랜잭션으로 반영
```

완료된 스냅샷에서 가장 최근 개정일의 코드 있는 행만 사용합니다. 현재 수집본의 기준일은
2025-04-01이며 2026년 전국 행정구역 전체를 보장하는 자료는 아닙니다. 같은 코드의 중복 행은
합치고, 이름·부모가 충돌하면 전체 작업을 중단합니다. 코드 없는 설명과 하위 구를 가진 집계용
시 행은 앱 지역으로 추가하지 않습니다. 시군구의 자기 참조 부모는 시도로 정규화하고,
`수원시 장안구` 같은 복합 이름과 세종시 표기 차이를 맞춰 기존 지역 ID를 유지합니다.

통계 코드와 `KR-...` 코드는 직접 변환하지 않습니다. 추가 지역은 `KOSTAT-...` 코드를 사용하며,
원본 최신 행의 `region_id`에 확인된 대응을 기록합니다. 기존 `regions`와 업체 위치 연결은
수정하거나 삭제하지 않습니다. 재실행 시 중복을 추가하지 않습니다. 수집·반영 명령은 같은
로컬 파일 잠금을 사용합니다.

`config/administrative_region_sync.php`는 현재 DB의 통합 시도와 개편 시군구에 대한 검토된
연결 규칙입니다. 인천·화성의 변경된 소속은 현재 업체 원본의 일치 항목과
[인천광역시 관할 목록](https://www.incheon.go.kr/IC01070101),
[화성시 읍면동 목록](https://www.hscity.go.kr/www/intro/ctyhllInfo/hsFax/department.jsp)으로
확인합니다. 분동된 운서동·아라동은 예전 명칭으로 활성화하지 않고 제외 사유를 출력합니다.
후속 동의 새 코드는 이번 원본에 없어 별도 최신 자료가 필요합니다. 새 기준일 자료를
수집했다면 연결 규칙을 재검토해야 반영 명령이 실행됩니다. 원본 전체 이력은 삭제하지 않습니다.

앱은 기존 `GET /api/v1/regions`로 조회합니다. `level=1`은 시도, `level=2`는 시군구,
`level=3`은 읍면동이고 `parentId`로 바로 아래 지역을 조회합니다. 이번 작업은 지역 선택용
참조 데이터를 추가하며, 업체의 기존 시군구 연결을 읍면동으로 재배정하지 않습니다.

수동 위치 설정 화면은 인증 없이 다음 순서로 호출합니다. `{sidoId}`, `{sigunguId}`에는
앞선 응답에서 사용자가 선택한 `id`를 넣습니다.

```http
GET /api/v1/regions?level=1
GET /api/v1/regions?parentId={sidoId}&level=2
GET /api/v1/regions?parentId={sigunguId}&level=3
GET /api/v1/regions?level=3&query=역삼
```

각 지역의 `fullName`(예: `서울특별시 강남구 역삼1동`)으로 선택 위치를 표시하고,
`hasChildren`으로 활성 하위 지역이 있는지 확인합니다. `parentId`는 직계 하위 지역만
조회합니다. 모든 조건을 생략하면 기존처럼 전체 활성 지역을 반환합니다. API 계약과
응답 예시는 `/api/docs` 및 `.docs/reform_mobile_api_definition_v1.md` 13.3절에 있습니다.

업체 목록에 `regionId`를 보내면 해당 지역과 하위 지역을 검색하고, 전체 결과가 0개일 때만
동→구→시 순서로 범위를 넓힙니다. 예를 들어 역삼1동에 연결된 업체가 없으면 강남구를,
강남구에도 없으면 서울특별시를 검색합니다. 수원시처럼 여러 일반구가 있으면 같은 시의
구들까지만 검색합니다. 검색어·카테고리·서비스·브랜드·인증·반경 필터는 유지합니다.
요청 좌표가 없으면 선택한 지역의 대표좌표를 사용하고, 동→구→시로 범위를 넓혀도 최초
선택한 동의 기준점으로 거리를 계산합니다. 기준점이 있는 확장 검색은 가까운 순입니다.
응답의 `meta.regionSearch`에는 선택 ID, 실제 검색 ID 목록, 검색 지역명, 확장 여부가 포함됩니다.
`meta.distanceOrigin`은 요청 좌표인지 지역 대표좌표인지와 실제 계산 기준점을 제공합니다.
기준점이 전혀 없으면 거리는 null이고, 거리순·반경 요청은 422를 반환합니다. 범위를 넘긴
페이지가 비었다고 다른 지역으로 확대하지 않으며, 업체 연결과 사용자 좌표는 변경·저장하지 않습니다.

지역 대표좌표는 기상청 공개 동네예보 지점 좌표(2026-07-01)를 사용합니다. 동사무소 등을
기준으로 한 대표지점이며 경계의 기하학적 중심이 아닙니다. 출처·변환 방식과 매칭되지 않은
지역은 [좌표 자료 안내](database/reference/README.md)에 기록합니다. API 요청 중에는 외부
지도 API 또는 좌표 수집을 실행하지 않습니다.

```bash
composer db:migrate
composer db:import:region-centers:plan
composer db:import:region-centers
```

```bash
php tests/administrative_regions.php
php tests/administrative_region_sync.php
php tests/administrative_region_repository.php
php tests/administrative_region_sync_repository.php
```

Repository 테스트는 로컬 MariaDB를 사용하며 테스트 데이터는 롤백합니다.

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
