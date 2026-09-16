# reform_platform 외부 평판/리뷰 설계 변경 문서

> 본 문서는 기존 `reform_platform` DB 및 시스템 설계에서 외부 플랫폼 평점/리뷰 연동과 자체 리뷰 기능 추가로 인해 변경되는 부분만 정리한 변경 설계서다.
> 기존 설계 문서의 나머지 구조는 유지하며, 본 문서의 내용이 충돌하는 경우 이 변경 설계를 우선한다.

- 문서 버전: v0.1
- 기준일: 2026-09-15
- 대상 시스템: `reform_platform`
- DBMS: MariaDB
- Backend: PHP 8.4
- Mobile: React Native + TypeScript
- Public Web: SEO / Marketing
- ORM: 사용하지 않음
- DB Access: PDO + Repository

---

# 1. 변경 배경

기존 설계에서는 업체 검색 시 다음 정보가 중심이었다.

```text
업체명
카테고리
서비스
지역
브랜드
작업사례
업체 인증 여부
```

하지만 실제 사용자는 업체를 선택할 때 다음 신뢰 지표를 중요하게 본다.

```text
평점
리뷰 수
외부 플랫폼 평가
자체 사용자 리뷰
작업사례 수
업체 인증 여부
```

따라서 업체 검색 결과와 상세 화면에 **평판 정보(Reputation Data)**를 추가할 수 있도록 구조를 확장한다.

---

# 2. 외부 플랫폼 정책 방향

## 2.1 Google

Google은 공식 Places API를 통해 다음 정보를 사용할 수 있다.

```text
Google Place ID
평점
리뷰 수
Google Maps URL
```

Google 관련 데이터는 공식 API를 통해 조회한다.

Google Place ID는 외부 업체 식별자로 저장한다.

외부 평점 데이터는 우리 플랫폼 자체 평점과 분리하여 표시한다.

## 2.2 Naver

현재 공개 API 기준으로 네이버 플레이스의 다음 데이터를 공식적으로 제공받는 구조는 전제로 하지 않는다.

```text
평균 별점
방문자 리뷰 수
리뷰 원문
```

따라서 v1에서는 네이버에 대해 다음 정보만 관리한다.

```text
Naver Place ID
Naver Place URL
```

앱에서는 다음 형태로 제공한다.

```text
[네이버 플레이스에서 리뷰 보기]
```

향후 네이버의 공식 API 또는 제휴를 통해 평점/리뷰 수 사용이 허용될 경우 동일한 외부 평판 구조에 `NAVER` Provider를 추가한다.

---

# 3. 외부 리뷰 원문 저장 금지

외부 플랫폼 리뷰 원문은 우리 DB에 저장하지 않는다.

저장하지 않는 데이터:

```text
Google 리뷰 본문
Google 리뷰 작성자
Google 리뷰 사진

Naver 리뷰 본문
Naver 리뷰 작성자
Naver 리뷰 사진
```

외부 플랫폼 정보는 **평점/리뷰 수 등의 허용된 통계값 또는 외부 링크** 수준으로 사용한다.

---

# 4. 신규 테이블: business_external_refs

기존 `business_links`와 역할을 구분한다.

`business_links`는 업체 공식 채널을 저장한다.

```text
업체 공식 홈페이지
Instagram
Naver Blog
Kakao Channel
YouTube
```

`business_external_refs`는 외부 플랫폼에서 해당 업체를 식별하기 위한 Reference를 저장한다.

```text
Google Maps 업체 페이지
Naver Place 업체 페이지
기타 외부 업체 식별 시스템
```

## 4.1 Schema

```text
business_external_refs
────────────────────────────────
id                  BIGINT PK
business_id         BIGINT FK
provider            VARCHAR(30)
external_id         VARCHAR(255) NULL
external_url        VARCHAR(1000)
status              VARCHAR(20)
created_at          DATETIME
updated_at          DATETIME
```

예상 Provider:

```text
GOOGLE
NAVER
```

예상 Status:

```text
ACTIVE
INACTIVE
```

## 4.2 Constraints

```text
UNIQUE(business_id, provider)
```

가능한 경우 다음도 고려한다.

```text
UNIQUE(provider, external_id)
```

단, `external_id`가 NULL 가능한 Provider를 고려하여 실제 DDL 단계에서 MariaDB 동작을 확인한다.

---

# 5. 신규 테이블: business_external_ratings

외부 플랫폼의 평점/리뷰 수를 사용할 수 있을 경우 저장하는 통계 테이블이다.

현재 v1 기준:

```text
GOOGLE → 사용 가능
NAVER  → 공식 허용 전까지 저장하지 않음
```

## 5.1 Schema

```text
business_external_ratings
────────────────────────────────
id                  BIGINT PK
business_id         BIGINT FK
provider            VARCHAR(30)
rating              DECIMAL(3,2) NULL
review_count        INT NULL
fetched_at          DATETIME
expires_at          DATETIME NULL
created_at          DATETIME
updated_at          DATETIME
```

## 5.2 Constraints / Index

```text
UNIQUE(business_id, provider)

INDEX(provider)
INDEX(rating)
INDEX(review_count)
INDEX(fetched_at)
INDEX(expires_at)
```

---

# 6. 외부 평판 데이터는 자체 평판과 분리

외부 플랫폼의 평점과 우리 서비스의 평점을 절대 합산하지 않는다.

잘못된 예:

```text
우리 리뷰 20개
+
Google 리뷰 100개
+
Naver 리뷰 200개

= 총 리뷰 320개
```

사용하지 않는다.

올바른 표시 예:

```text
ABC 가죽공방

Reform Platform
★ 4.8
리뷰 24개

Google
★ 4.6
리뷰 128개

Naver
[네이버 플레이스에서 리뷰 보기]
```

---

# 7. 자체 리뷰 시스템 추가

장기적으로 우리 플랫폼의 핵심 평판 데이터는 외부 플랫폼이 아니라 자체 사용자 리뷰가 되어야 한다.

따라서 다음 테이블을 추가한다.

```text
reviews
review_images
```

---

# 8. reviews

앱 사용자가 업체에 작성하는 자체 리뷰.

```text
reviews
────────────────────────────────
id                  BIGINT PK
business_id         BIGINT FK
user_id             BIGINT FK
rating              SMALLINT
content             TEXT NULL
status              VARCHAR(20)
created_at          DATETIME
updated_at          DATETIME
deleted_at          DATETIME NULL
```

Rating:

```text
1 <= rating <= 5
```

예상 Status:

```text
ACTIVE
HIDDEN
REPORTED
DELETED
```

Index:

```text
INDEX(business_id)
INDEX(user_id)
INDEX(status)
INDEX(created_at)
INDEX(business_id, status)
```

---

# 9. review_images

자체 리뷰 이미지.

```text
review_images
────────────────────────────────
id                  BIGINT PK
review_id           BIGINT FK
storage_type        VARCHAR(20)
file_path           VARCHAR(1000)
sort_order          INT DEFAULT 0
created_at          DATETIME
```

Storage Type:

```text
LOCAL
R2
S3
```

초기에는 `LOCAL`을 사용한다.

---

# 10. 자체 리뷰 작성 정책

v1에서는 세부 정책을 최종 확정하지 않는다.

후속 검토 항목:

```text
회원당 업체 리뷰 1개 제한 여부
리뷰 수정 허용 여부
리뷰 삭제 정책
업체 이용 인증 방식
사진 리뷰 여부
신고 기능
업체 답글
허위 리뷰 검수
```

AI 또는 구현자는 위 정책을 임의로 확정하지 않는다.

---

# 11. 신규 테이블: business_reputation_stats

검색 결과에서 매번 리뷰 전체를 집계하면 성능이 떨어질 수 있으므로 업체별 자체 평판 집계값을 별도 관리할 수 있도록 한다.

```text
business_reputation_stats
────────────────────────────────
business_id             BIGINT PK
review_count            INT DEFAULT 0
rating_avg              DECIMAL(3,2) NULL
favorite_count          INT DEFAULT 0
work_case_count         INT DEFAULT 0
updated_at              DATETIME
```

목적:

```text
★ 4.8
리뷰 24
작업사례 18
즐겨찾기 52
```

를 검색 결과에서 빠르게 표시한다.

---

# 12. 통계 업데이트 방식

Cafe24 공유호스팅에서는 Queue Worker를 전제로 하지 않는다.

따라서 v1에서는 다음처럼 동기식 집계를 사용한다.

```text
리뷰 생성
    ↓
reviews INSERT
    ↓
같은 Transaction 내
business_reputation_stats 갱신
```

또는:

```text
즐겨찾기 생성/삭제
    ↓
user_favorites 변경
    ↓
business_reputation_stats.favorite_count 갱신
```

복잡한 Background Aggregation 시스템을 만들지 않는다.

---

# 13. 검색 결과 표시 구조 변경

기존:

```text
업체명
카테고리
서비스
지역
작업사례
```

변경:

```text
업체명
카테고리 / 서비스
지역
자체 평점
자체 리뷰 수
작업사례 수
업체 인증 여부
Google 평점/리뷰 수
Naver 리뷰 페이지 링크
```

예:

```text
ABC 명품수선

가방 · 복원 · 염색
서울 강남구

Reform ★ 4.8 (24)
작업사례 18
인증업체

Google ★ 4.6 (128)

[상세보기]
[네이버 리뷰 보기]
```

---

# 14. 검색 Ranking 변경

외부 리뷰 수를 검색 Ranking의 절대 기준으로 사용하지 않는다.

특히 다음 구조는 피한다.

```text
Google 리뷰 수가 많을수록 무조건 상위
Naver 리뷰 수가 많을수록 무조건 상위
```

플랫폼 핵심 Ranking은 우리 자체 데이터 중심으로 설계한다.

예상 요소:

```text
검색 조건 적합도
지역 적합도
Category / Service 적합도
업체 인증 여부
작업사례 수
프로필 완성도
자체 리뷰 평점
자체 리뷰 수
즐겨찾기
최근 정보 검증일
```

외부 평점은 보조 신뢰 신호로만 사용한다.

초기 Weight 예시는 아직 확정값이 아니다.

---

# 15. Google 연동 흐름

```text
businesses
     │
     ▼
business_external_refs
provider = GOOGLE
external_id = Place ID
     │
     ▼
Google Places API
     │
     ├── rating
     └── review count
     │
     ▼
business_external_ratings
     │
     ▼
App 표시
```

---

# 16. Naver 연동 흐름

현재 v1:

```text
businesses
     │
     ▼
business_external_refs
provider = NAVER
external_id = Naver Place ID
external_url = Naver Place URL
     │
     ▼
App
     │
     ▼
[네이버 플레이스에서 리뷰 보기]
```

현재 단계에서는 다음을 자동 수집하여 저장하는 구조를 사용하지 않는다.

```text
NAVER rating
NAVER review_count
```

---

# 17. 향후 Naver 공식 API/제휴 가능 시

공식 사용이 허용되면 다음 구조에 그대로 추가한다.

```text
business_external_ratings

provider = NAVER
rating
review_count
fetched_at
expires_at
```

DB Schema를 다시 설계할 필요가 없도록 Provider 기반 구조를 사용한다.

---

# 18. 기존 business_links와 역할 구분

`business_links`

```text
업체가 직접 운영하는 채널
```

예:

```text
홈페이지
Instagram
Naver Blog
Kakao Channel
YouTube
```

`business_external_refs`

```text
외부 플랫폼에서 해당 업체를 식별하는 Reference
```

예:

```text
Google Place
Naver Place
```

두 테이블을 합치지 않는다.

---

# 19. ERD 변경 후 관계

```text
                           businesses
                               │
          ┌────────────────────┼─────────────────────┐
          │                    │                     │
          ▼                    ▼                     ▼
 business_external_refs   reviews         business_reputation_stats
          │                    │
          │                    ▼
          │              review_images
          │
          ▼
 business_external_ratings
```

Provider 구조:

```text
business_external_refs
          │
          ├── GOOGLE
          │      │
          │      └── business_external_ratings
          │
          └── NAVER
                 │
                 └── external_url
```

---

# 20. 신규 테이블 요약

기존 DB 설계에 다음 테이블을 추가한다.

```text
business_external_refs
business_external_ratings
reviews
review_images
business_reputation_stats
```

기존 예상 테이블 수:

```text
27
```

변경 후 예상:

```text
32
```

---

# 21. 제거되는 설계

외부 리뷰를 저장하는 다음 구조는 만들지 않는다.

```text
external_reviews
naver_reviews
google_reviews
```

다음 데이터도 DB에 복제하지 않는다.

```text
외부 리뷰 원문
외부 리뷰 사진
외부 리뷰 작성자
```

---

# 22. API 변경 예상

향후 업체 상세 API에 다음 구조를 포함할 수 있다.

```json
{
  "id": 123,
  "name": "ABC 가죽공방",
  "reputation": {
    "internal": {
      "rating": 4.8,
      "reviewCount": 24
    },
    "external": [
      {
        "provider": "GOOGLE",
        "rating": 4.6,
        "reviewCount": 128,
        "url": "..."
      },
      {
        "provider": "NAVER",
        "rating": null,
        "reviewCount": null,
        "url": "..."
      }
    ]
  }
}
```

정확한 API DTO는 API 상세 설계에서 확정한다.

---

# 23. 자체 리뷰 API 예상

향후 예상 Endpoint:

```text
GET    /api/v1/businesses/{id}/reviews
POST   /api/v1/businesses/{id}/reviews
PATCH  /api/v1/reviews/{id}
DELETE /api/v1/reviews/{id}
```

정확한 권한/작성 정책은 후속 설계에서 확정한다.

---

# 24. External Provider API 보안

외부 Provider 데이터 갱신은 일반 Mobile Client에서 직접 수행하지 않는다.

권장 구조:

```text
React Native
→ reform_platform API
→ Provider Integration
```

외부 Provider API Key는 React Native App에 직접 노출하지 않는다.

로그 금지:

```text
Google API Key
Provider Secret
OAuth Secret
Access Token
Refresh Token
```

---

# 25. Analytics 확장

향후 다음 Event를 추가할 수 있다.

```text
EXTERNAL_GOOGLE_CLICK
EXTERNAL_NAVER_CLICK
REVIEW_VIEW
REVIEW_CREATE
REVIEW_UPDATE
REVIEW_DELETE
```

이를 통해 다음을 분석할 수 있다.

```text
외부 리뷰 확인 비율
자체 리뷰 이용률
업체별 외부 플랫폼 클릭률
리뷰가 검색 전환에 미치는 영향
```

---

# 26. 최종 변경 사항

기존:

```text
업체 정보
+
작업사례
+
즐겨찾기
+
업체 인증
```

변경:

```text
업체 정보
+
작업사례
+
자체 리뷰/평점
+
외부 평판 Reference
+
Google 공식 API 평점/리뷰 수
+
Naver Place 외부 링크
+
업체 평판 집계
+
즐겨찾기
+
업체 인증
```

---

# 27. 현재 확정 사항

```text
Google
→ 공식 API 기반 연동

Naver
→ Place ID / URL 저장
→ 리뷰 페이지 링크 제공
→ 공식 API/제휴 허용 전까지 평점/리뷰 수 DB 저장 안 함

자체 리뷰
→ reform_platform DB 직접 저장

외부 리뷰 원문
→ 저장하지 않음

검색 결과
→ 자체 평판 + 외부 평판을 분리 표시

검색 Ranking
→ 외부 플랫폼 리뷰 수에 과도하게 의존하지 않음

DB
→ Provider 확장 가능한 구조
```

---

# 28. 후속 설계 대상

아직 확정하지 않은 항목:

```text
자체 리뷰 작성 조건
리뷰 1인 1업체 제한 여부
리뷰 사진 최대 개수
리뷰 신고
업체 답글
리뷰 이용 인증
Google Rating 데이터 Cache 정책
External Rating 갱신 주기
검색 Ranking Weight
리뷰 Moderation
```

구현 단계에서 임의로 확정하지 않는다.

---

# 29. Schema 적용 상태

적용 Migration:

```text
database/migrations/002_add_reputation_schema.sql
```

적용된 구조:

```text
business_external_refs
business_external_ratings
reviews
review_images
business_reputation_stats
```

확정된 DDL 세부사항:

- `business_external_refs`는 `(business_id, provider)`와 `(provider, external_id)`를 각각 Unique로 관리한다.
- MariaDB의 Unique Index는 `external_id = NULL`인 Row를 여러 개 허용한다.
- `business_external_ratings`는 `(business_id, provider)`로 `business_external_refs`를 참조한다.
- `reviews.rating`에는 `1 <= rating <= 5` CHECK Constraint를 적용한다.
- `reviews`는 Soft Delete 대상이다.
- Child Table은 부모가 실제 Hard Delete될 때 `ON DELETE CASCADE`를 적용한다.
- 기존 업체에는 Migration 적용 시 `business_reputation_stats` Row를 생성한다.

자체 리뷰의 작성·수정·삭제·중복·신고·답글·이용 인증 정책은 여전히 미확정이며 이번 Schema 적용으로 확정하지 않는다.

추가로 다음 교차 Domain 정책도 미확정 상태를 유지한다.

- `business_sources`와 `business_external_refs` 중 Provider 식별자의 Source of Truth
- 기존 `business_sources` Provider 데이터의 이관 여부
- `reviews.status = DELETED`와 `deleted_at`의 역할 구분
- `work_case_count`에 포함되는 WorkCase Status와 갱신 시점
- 신규 Business 생성 시 `business_reputation_stats` Row 생성 시점
