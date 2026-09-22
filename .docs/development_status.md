# 개발 진행 상태

- 기준일: 2026-09-22
- 현재 단계: 고객 앱 API 45개 구현 및 통합 검증 완료
- 구현 기준: `AGENTS.MD`, `.docs/app_api/reform_app_api_requirements.md`, 기존 v1 계약

## 완료된 항목

- PHP 8.4, Symfony Routing/HttpFoundation 7.4, Monolog 3, PDO/MariaDB 기반 Foundation
- Controller → Service → Repository → PDO 구조
- 중앙 예외 처리와 공통 성공·목록·오류 응답
- Swagger UI와 OpenAPI 3.1
- 앱 요구사항 P0/P1/P2 API 45개
  - SNS 로그인·토큰 갱신·로그아웃
  - 카테고리·서비스·지역·브랜드
  - 업체 검색·상세
  - 작업 사례 목록·상세
  - 리뷰 목록·요약·작성·내 리뷰
  - 찜 목록·추가·삭제
  - 문의 작성·목록·상세
  - 프로필 조회·수정·탈퇴·활동 개수
  - 미디어 업로드 요청·완료
  - 최근 본 업체
  - 알림·사용자 설정·푸시 기기
  - FAQ
  - 광고 조회·노출·클릭
  - 앱 실행 설정
- 기존 보조 API
  - `POST /api/v1/events`
  - `GET /api/v1/search/suggestions`
- Cafe24 로컬 저장소 업로드용 `PUT /api/v1/media/uploads/{mediaId}`
- OpenAPI 총 48개 operation
- 기본 Seed 데이터와 재실행 가능한 `composer db:seed`
  - 카테고리 21개(상위 8개, 하위 13개)
  - 서비스 18개와 카테고리-서비스 조합
  - 전국 시·도 및 서울 자치구 지역 42개
  - 브랜드 20개
  - FAQ 카테고리 6개와 FAQ 11개
  - 개발용 데모 업체 3개와 작업 사례 4개
- 앱 기능용 DB Schema
  - 리뷰 태그·도움돼요·멱등성
  - 미디어 업로드
  - 문의·답변·첨부
  - 최근 본 업체·알림·사용자 설정·푸시 기기
  - FAQ·광고·광고 이벤트
- 보안 처리
  - PDO Prepared Statement와 Identifier Whitelist
  - Access/Refresh Token hash 저장과 Refresh 회전
  - SNS Provider ID 직접 신뢰 금지
  - 업로드 크기·MIME·이미지 디코딩 검증
  - 리뷰 `Idempotency-Key` 재시도 및 충돌 검증
  - 찜 추가·삭제 멱등 처리와 평판 수 동기 갱신
  - 리뷰 작성과 평판 통계 동기 Transaction

## Migration 상태

```text
[applied] 001_initial_schema
[applied] 002_add_reputation_schema
[applied] 003_add_app_api_schema
[applied] 004_add_review_idempotency
```

## 검증 상태

```text
composer test
→ Foundation smoke tests passed.
→ Business API tests passed.
→ Reference API tests passed.
→ Event API tests passed.
→ Search API tests passed.
→ Public content API tests passed.
→ API documentation tests passed.

composer test:database
→ Database schema tests passed.
→ Public repository integration tests passed.
→ App API integration tests passed.

composer test:seed
→ Seed data tests passed.

composer validate --strict
→ valid
```

실행 중인 로컬 서버의 `GET /api/docs/openapi.json`에서도 48개 operation을 확인했다. 인증 없는 `GET /api/v1/me`는 401, FAQ는 200, 설정되지 않은 SNS 로그인은 503을 반환하는 것을 실제 HTTP로 검증했다.

## 운영 설정이 필요한 항목

- Kakao/Naver/Google/Apple Provider credential 검증 구현체 및 운영 자격 증명
- Access/Refresh Token TTL
- 공개 API 기준 URL과 이미지 최대 용량
- 광고 추적 서명 Secret
- 앱 최소·최신 버전, 점검 메시지, 고객센터·약관 URL
- 공개 이미지 조회 URL/변환 정책

이 값이 없는 기능은 임의의 운영 정책이나 가짜 인증을 만들지 않고 명시적인 `503` 오류를 반환한다. 공개 이미지 URL 정책이 확정되기 전까지 조회 응답의 이미지 URL은 `null` 또는 빈 배열이다.

## 호환성 메모

기존 `/api/v1` 응답의 `success`, `data`, `meta` 및 page/perPage 계약을 깨지 않기 위해 앱 문서의 cursor 예시는 `page`, `perPage`와 `limit` 별칭으로 제공한다. 업체 검색은 `query`/`latitude`/`longitude` 별칭도 지원한다.

## 로컬 실행

```bash
composer db:migrate
composer db:seed
composer test
composer test:database
composer test:seed
composer serve
```

Swagger UI: `http://127.0.0.1:8080/api/docs`
