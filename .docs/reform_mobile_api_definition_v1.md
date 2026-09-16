# re:form Mobile API Definition v1.0

> 서비스: **re:form**  
> 목적: 기획문서, 시스템/DB 설계, 외부 평판 설계 변경, 확정 모바일 스토리보드를 기준으로 React Native 앱과 PHP Backend 사이의 REST API Contract를 정의한다.  
> Backend: PHP 8.4 + Symfony Routing/HttpFoundation + PDO + MariaDB  
> API Prefix: `/api/v1`  
> 작성 기준일: 2026-09-16

---

# 1. 문서 기준 및 우선순위

본 API 정의서는 다음 문서를 기준으로 통합하였다.

```text
1. 확정 모바일 스토리보드
   - re:form 모바일 앱 화면/사용 흐름

2. 외부 평판/리뷰 설계 변경
   - Google / Naver
   - reviews
   - business_external_refs
   - business_external_ratings
   - business_reputation_stats

3. Database Design
   - reform_platform
   - business_offerings
   - work_cases
   - favorites
   - auth_sessions 등

4. System / Development Design
   - /api/v1
   - Controller → Service → Repository → PDO
   - Social Login
   - 공통 응답 Contract
```

문서 간 충돌 시 다음 원칙을 적용한다.

```text
확정된 최신 스토리보드
→ 최신 설계 변경 문서
→ DB 설계
→ 기존 시스템 설계
```

단, 스토리보드에 있지만 DB 설계에 아직 없는 기능은 본 문서에서 별도로 표시한다.

---

# 2. API Scope 상태

상태 표기:

```text
CORE
현재 DB와 기획에 포함되어 바로 구현 가능한 v1 API.

SCHEMA-ADD
확정 스토리보드에 필요하지만 현재 DB 스키마 추가가 필요한 API.

FUTURE
화면에는 존재하거나 향후 필요하지만 현재 v1 핵심 범위에서는 보류.
```

주요 판단:

| 기능 | 상태 | 비고 |
|---|---|---|
| 카테고리/서비스/지역/브랜드 | CORE | 기존 Reference Data |
| 업체 검색 | CORE | `business_offerings` 중심 |
| 지도 검색 | CORE | 동일 업체 검색 API 사용 |
| 업체 상세 | CORE | 업체/위치/영업시간/링크/평판 |
| 작업사례 | CORE | Before / After |
| Google 평점/리뷰 수 | CORE | 공식 API 연동, 자체 평판과 분리 |
| Naver Place 링크 | CORE | 평점/리뷰 수 수집하지 않음 |
| SNS 로그인 | CORE | Kakao/Naver/Google/Apple 구조 지원 |
| 찜 | CORE | `user_favorites` |
| 자체 리뷰 | CORE | 변경 설계 반영 |
| 리뷰 이미지 | CORE | `review_images`, 업로드 API 필요 |
| 프로필 | CORE | `users` |
| 사용자 행동 이벤트 | CORE | `event_logs` |
| 상담 신청 | SCHEMA-ADD | 확정 스토리보드 신규 기능 |
| 상담 신청 내역 | SCHEMA-ADD | 완료 화면의 “신청 내역 보기” 대응 |
| 알림/마케팅 설정 서버 동기화 | FUTURE | 현재 preference/device DB 없음 |
| 앱 내 결제/정산 | FUTURE | v1 검색 서비스 범위 밖 |
| 실시간 채팅 | FUTURE | v1 범위 밖 |

---

# 3. Base URL

Production 예시:

```text
https://{API_HOST}/api/v1
```

본 문서의 Endpoint는 `/api/v1` 이후 Path만 표시할 수 있다.

예:

```text
GET /api/v1/businesses
```

---

# 4. Content Type

일반 API:

```http
Content-Type: application/json
Accept: application/json
```

이미지 업로드:

```http
Content-Type: multipart/form-data
```

문자 인코딩:

```text
UTF-8
```

---

# 5. Authentication

Mobile API 인증은 Bearer Token을 사용한다.

```http
Authorization: Bearer {accessToken}
```

인증이 필요한 API:

```text
/me/*
/favorites/*
리뷰 작성/수정/삭제
리뷰 이미지 업로드/삭제
상담 신청/상담 내역
```

검색/업체/작업사례/리뷰 조회는 비로그인 사용자도 접근 가능하다.

SNS Provider Token은 앱 API Token과 별개다.

```text
SNS SDK
↓
Provider Token
↓
POST /api/v1/auth/social
↓
Backend Provider Verification
↓
re:form Access / Refresh Token 발급
```

---

# 6. Token Storage

Backend:

```text
auth_sessions.access_token_hash
auth_sessions.refresh_token_hash
```

DB에는 Raw Token을 저장하지 않는다.

React Native:

```text
Access Token
Refresh Token
```

은 Secure Storage에 저장한다.

Token 만료 시간은 아직 기존 설계에서 확정되지 않았으므로 구현 전 별도 확정한다.

---

# 7. 공통 Response Contract

## 7.1 Object Success

```json
{
  "success": true,
  "data": {}
}
```

## 7.2 List Success

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

## 7.3 Error

```json
{
  "success": false,
  "error": {
    "code": "BUSINESS_NOT_FOUND",
    "message": "업체를 찾을 수 없습니다."
  }
}
```

Validation Error는 필요 시 `details`를 추가할 수 있다.

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "입력값을 확인해주세요.",
    "details": {
      "rating": [
        "평점은 1~5 사이여야 합니다."
      ]
    }
  }
}
```

---

# 8. HTTP Status

| Status | 용도 |
|---|---|
| 200 | 조회/수정 성공 |
| 201 | 생성 성공 |
| 204 | 삭제 성공, Response Body 없음 |
| 400 | 잘못된 Request 형식 |
| 401 | 인증 필요/Token 오류 |
| 403 | 접근 권한 없음 |
| 404 | Resource 없음 |
| 409 | 중복/현재 상태와 충돌 |
| 422 | Validation 실패 |
| 429 | Rate Limit |
| 500 | Server Error |

Production에서는 Stack Trace, SQL Error, 파일 경로를 반환하지 않는다.

---

# 9. Naming Convention

Request / Response JSON:

```text
camelCase
```

예:

```json
{
  "businessId": 123,
  "reviewCount": 24,
  "publishedAt": "2026-09-16T10:00:00+09:00"
}
```

DB:

```text
snake_case
```

API DTO와 DB Column Name은 동일하게 만들 필요가 없다.

---

# 10. 날짜/시간 Contract

API에서는 ISO 8601 형식으로 반환한다.

```text
2026-09-16T10:00:00+09:00
```

Database의 `DATETIME` 값은 API Layer에서 ISO 8601 문자열로 변환한다.

---

# 11. Pagination

기본:

```text
page = 1
perPage = 20
```

Validation:

```text
page >= 1
1 <= perPage <= 100
```

목록 API는 가능한 경우 동일한 Pagination Contract를 사용한다.

---

# 12. Screen → API Mapping

| Screen | 주요 API |
|---|---|
| Splash | 필요 시 `/auth/refresh`; 그 외 Client 초기화 |
| Onboarding | API 없음, App Static Content |
| SNS Login | `POST /auth/social` |
| Home | `/categories`, `/services`, `/businesses`, `/work-cases` |
| Service Select | `/categories`, `/services?categoryId=` |
| Region Select | `/regions` |
| Search Result List | `/businesses` |
| Search Result Map | `/businesses` |
| Filter | `/categories`, `/services`, `/brands`, `/businesses` |
| Business Detail | `/businesses/{id}` |
| Work Cases | `/work-cases?businessId=` |
| Work Case Detail | `/work-cases/{id}` |
| Reviews | `/businesses/{id}/reviews` |
| Review Create | `POST /businesses/{id}/reviews` |
| Favorite | `/favorites` |
| My Guest | API 없음 |
| My Logged In | `/me`, `/favorites`, `/me/reviews` |
| Profile Edit | `PATCH /me`, `/me/profile-image` |
| Settings | 일부 Local, Server Preference는 FUTURE |
| Consultation | `POST /inquiries` — SCHEMA-ADD |
| Consultation Complete | `/me/inquiries/{id}` — SCHEMA-ADD |
| Empty/Error | 기존 API 오류/빈 결과 처리 |

---

# 13. Reference API

---

## 13.1 Categories

### Endpoint

```http
GET /api/v1/categories
```

### Authentication

```text
Public
```

### Query

| Field | Type | Required | 설명 |
|---|---|---:|---|
| parentId | integer | N | 하위 카테고리 조회 |
| level | integer | N | 특정 Depth 조회 |

Query가 없으면 앱에서 사용할 활성 카테고리를 `sort_order` 기준으로 반환한다.

### Response

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "parentId": null,
      "name": "가방",
      "slug": "bag",
      "description": null,
      "sortOrder": 10
    },
    {
      "id": 2,
      "parentId": null,
      "name": "신발",
      "slug": "shoes",
      "description": null,
      "sortOrder": 20
    }
  ]
}
```

Public API는 `ACTIVE` 데이터만 반환한다.

---

## 13.2 Services

### Endpoint

```http
GET /api/v1/services
```

### Query

| Field | Type | Required | 설명 |
|---|---|---:|---|
| categoryId | integer | N | 해당 카테고리에서 가능한 작업만 반환 |
| limit | integer | N | Home 등 제한 노출 시 사용 |

`categoryId`가 있으면 반드시 `category_services`를 기준으로 조회한다.

### Example

```http
GET /api/v1/services?categoryId=1
```

### Response

```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "name": "염색",
      "slug": "dye",
      "description": "가죽/원단 등의 색상을 복원하거나 변경합니다.",
      "sortOrder": 10
    },
    {
      "id": 11,
      "name": "가죽 복원",
      "slug": "leather-restoration",
      "description": null,
      "sortOrder": 20
    }
  ]
}
```

스토리보드의 “잘 모르겠어요”는 실제 `services` Row가 아니라 App UI Option으로 처리한다.

선택 시 `serviceId`를 보내지 않고 Category 기반 검색을 수행한다.

---

## 13.3 Regions

### Endpoint

```http
GET /api/v1/regions
```

### Query

| Field | Type | Required | 설명 |
|---|---|---:|---|
| parentId | integer | N | 시/도 하위 시/군/구 조회 |
| level | integer | N | Region Level |

### Root Example

```http
GET /api/v1/regions?level=1
```

### Child Example

```http
GET /api/v1/regions?parentId=1
```

### Response

```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "parentId": 1,
      "code": "11680",
      "name": "강남구",
      "slug": "gangnam",
      "level": 2,
      "sortOrder": 10
    }
  ]
}
```

---

## 13.4 Brands

### Endpoint

```http
GET /api/v1/brands
```

### Query

| Field | Type | Required |
|---|---|---:|
| keyword | string | N |
| page | integer | N |
| perPage | integer | N |

### Response

```json
{
  "success": true,
  "data": [
    {
      "id": 100,
      "name": "Chanel",
      "slug": "chanel"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 1,
    "totalPages": 1
  }
}
```

---

# 14. Business Search API

## 14.1 Business List / Search Result

### Endpoint

```http
GET /api/v1/businesses
```

Search Result List와 Search Result Map에서 동일한 Endpoint를 사용한다.

### Authentication

```text
Public
Optional Bearer Token
```

Bearer Token이 있으면 `isFavorite` 값을 사용자 기준으로 채울 수 있다.

### Query

| Field | Type | Required | 설명 |
|---|---|---:|---|
| keyword | string | N | 업체명/검색 Keyword |
| categoryId | integer | N | 물품 카테고리 |
| serviceId | integer | N | 작업 종류 |
| regionId | integer | N | 지역 |
| brandId | integer | N | 취급 브랜드 |
| lat | decimal | N | 현재/지도 중심 Latitude |
| lng | decimal | N | 현재/지도 중심 Longitude |
| radiusKm | decimal | N | 거리 반경 |
| verifiedOnly | boolean | N | 인증 업체만 |
| sort | string | N | 정렬 |
| page | integer | N | 기본 1 |
| perPage | integer | N | 기본 20 |

### Radius

스토리보드 기본 UI:

```text
1km
3km
5km
10km
전체
```

`전체` 선택 시 `radiusKm`을 전달하지 않는다.

### Sort

v1 Contract:

```text
recommended
distance
rating
reviewCount
recent
```

기본:

```text
recommended
```

`recommended`의 정확한 Ranking Weight는 별도 검색 Ranking 정책으로 관리하며 Mobile Client Contract에 노출하지 않는다.

### Validation

```text
serviceId + categoryId가 함께 전달되면
category_services에 유효한 조합이어야 한다.

lat/lng는 함께 전달해야 한다.

sort=distance이면 lat/lng가 필요하다.
```

잘못된 조합:

```json
{
  "success": false,
  "error": {
    "code": "FILTER_COMBINATION_INVALID",
    "message": "선택한 카테고리에서 지원하지 않는 작업입니다."
  }
}
```

### Example

```http
GET /api/v1/businesses?categoryId=1&serviceId=10&regionId=10&sort=recommended&page=1&perPage=20
```

### Response

```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "name": "ABC 가죽공방",
      "slug": "abc-leather",
      "shortDescription": "가죽 가방 복원 및 염색 전문",
      "thumbnail": {
        "url": "https://example.com/uploads/business/123/main.webp",
        "alt": "ABC 가죽공방"
      },
      "verification": {
        "isVerified": true
      },
      "primaryLocation": {
        "regionId": 10,
        "regionName": "강남구",
        "roadAddress": "서울 강남구 ...",
        "latitude": 37.5000000,
        "longitude": 127.0300000,
        "distanceKm": 1.2
      },
      "offerings": [
        {
          "id": 1001,
          "categoryId": 1,
          "categoryName": "가방",
          "serviceId": 10,
          "serviceName": "염색"
        },
        {
          "id": 1002,
          "categoryId": 1,
          "categoryName": "가방",
          "serviceId": 11,
          "serviceName": "가죽 복원"
        }
      ],
      "reputation": {
        "internal": {
          "rating": 4.8,
          "reviewCount": 24
        },
        "google": {
          "rating": 4.6,
          "reviewCount": 128
        }
      },
      "workCaseCount": 18,
      "workCasePreview": {
        "beforeImageUrl": "https://example.com/uploads/case/before.webp",
        "afterImageUrl": "https://example.com/uploads/case/after.webp"
      },
      "isFavorite": false
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 28,
    "totalPages": 2
  }
}
```

### Map 사용

지도 화면도 동일한 Response를 사용한다.

```text
primaryLocation.latitude
primaryLocation.longitude
```

를 Marker 위치에 사용한다.

사용자가 지도를 이동한 후 “이 지역 검색”을 누르면 Client가 현재 지도 중심 좌표와 화면 범위를 기준으로 `lat`, `lng`, `radiusKm`을 다시 계산해 요청한다.

---

# 15. Unified Search

## 15.1 Search

### Endpoint

```http
GET /api/v1/search
```

### 목적

상단 Keyword Search에서 업체/서비스/브랜드 등 통합 Keyword 검색이 필요한 경우 사용한다.

업체 리스트 필터링의 기준 Endpoint는 `/businesses`다.

### Query

```text
keyword
page
perPage
```

### Response 예시

```json
{
  "success": true,
  "data": {
    "businesses": [],
    "services": [],
    "brands": []
  }
}
```

---

## 15.2 Search Suggestions

### Endpoint

```http
GET /api/v1/search/suggestions
```

### Query

| Field | Type | Required |
|---|---|---:|
| keyword | string | Y |
| limit | integer | N |

### Example

```http
GET /api/v1/search/suggestions?keyword=가죽&limit=10
```

### Response

```json
{
  "success": true,
  "data": {
    "businesses": [
      {
        "id": 123,
        "name": "ABC 가죽공방"
      }
    ],
    "services": [
      {
        "id": 11,
        "name": "가죽 복원"
      }
    ],
    "brands": []
  }
}
```

---

# 16. Business Detail

## 16.1 Business Detail

### Endpoint

```http
GET /api/v1/businesses/{businessId}
```

### Authentication

```text
Public
Optional Bearer Token
```

### Response

```json
{
  "success": true,
  "data": {
    "id": 123,
    "name": "ABC 가죽공방",
    "slug": "abc-leather",
    "shortDescription": "가죽 가방 수선 및 복원 전문",
    "description": "20년 이상의 경험을 바탕으로 ...",
    "phone": "02-1234-5678",
    "supportModes": {
      "visit": true,
      "shipping": true,
      "pickup": false
    },
    "verification": {
      "isVerified": true,
      "types": [
        "BUSINESS_REGISTRATION",
        "PHONE"
      ]
    },
    "primaryLocation": {
      "regionId": 10,
      "regionName": "강남구",
      "postalCode": "00000",
      "roadAddress": "서울 강남구 ...",
      "jibunAddress": null,
      "addressDetail": null,
      "latitude": 37.5000000,
      "longitude": 127.0300000,
      "distanceKm": 1.2
    },
    "hours": [
      {
        "dayOfWeek": 1,
        "periods": [
          {
            "openTime": "10:00",
            "closeTime": "19:00"
          }
        ],
        "isClosed": false
      },
      {
        "dayOfWeek": 7,
        "periods": [],
        "isClosed": true
      }
    ],
    "categories": [
      {
        "id": 1,
        "name": "가방",
        "isPrimary": true
      }
    ],
    "offerings": [
      {
        "id": 1001,
        "categoryId": 1,
        "categoryName": "가방",
        "serviceId": 10,
        "serviceName": "염색",
        "title": null,
        "description": null,
        "priceMin": null,
        "priceMax": null,
        "priceNote": "제품 상태에 따라 상담",
        "durationMinDays": null,
        "durationMaxDays": null,
        "isPrimary": true
      }
    ],
    "brands": [
      {
        "id": 100,
        "name": "Chanel",
        "slug": "chanel"
      }
    ],
    "images": [
      {
        "id": 501,
        "type": "STORE",
        "url": "https://example.com/uploads/business/123/store.webp",
        "alt": "ABC 가죽공방 매장",
        "isPrimary": true
      }
    ],
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
          "url": "https://maps.google.com/...",
          "fetchedAt": "2026-09-16T09:30:00+09:00"
        },
        {
          "provider": "NAVER",
          "rating": null,
          "reviewCount": null,
          "url": "https://m.place.naver.com/..."
        }
      ]
    },
    "links": [
      {
        "type": "WEBSITE",
        "label": "홈페이지",
        "url": "https://example.com"
      },
      {
        "type": "INSTAGRAM",
        "label": "Instagram",
        "url": "https://instagram.com/..."
      }
    ],
    "stats": {
      "workCaseCount": 18,
      "favoriteCount": 52
    },
    "isFavorite": false
  }
}
```

### 중요

```text
re:form 자체 평점과 Google 평점을 합산하지 않는다.

NAVER는 공식 허용 전까지:
rating = null
reviewCount = null
Place URL만 제공한다.
```

---

# 17. Work Case API

## 17.1 Work Case List

### Endpoint

```http
GET /api/v1/work-cases
```

### Query

| Field | Type | Required |
|---|---|---:|
| businessId | integer | N |
| categoryId | integer | N |
| serviceId | integer | N |
| brandId | integer | N |
| page | integer | N |
| perPage | integer | N |

### Business Work Cases

```http
GET /api/v1/work-cases?businessId=123&page=1&perPage=20
```

### Response

```json
{
  "success": true,
  "data": [
    {
      "id": 555,
      "businessId": 123,
      "title": "샤넬 클래식백 가죽 복원",
      "slug": "chanel-classic-leather-restore",
      "category": {
        "id": 1,
        "name": "가방"
      },
      "brand": {
        "id": 100,
        "name": "Chanel"
      },
      "services": [
        {
          "id": 11,
          "name": "가죽 복원"
        },
        {
          "id": 10,
          "name": "염색"
        }
      ],
      "beforeImageUrl": "https://example.com/uploads/case/555/before.webp",
      "afterImageUrl": "https://example.com/uploads/case/555/after.webp",
      "publishedAt": "2026-09-15T09:00:00+09:00"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 18,
    "totalPages": 1
  }
}
```

---

## 17.2 Work Case Detail

### Endpoint

```http
GET /api/v1/work-cases/{workCaseId}
```

### Response

```json
{
  "success": true,
  "data": {
    "id": 555,
    "title": "샤넬 클래식백 가죽 복원",
    "slug": "chanel-classic-leather-restore",
    "description": "표면 마모와 색 빠짐이 발생한 가방을 ...",
    "business": {
      "id": 123,
      "name": "ABC 가죽공방"
    },
    "category": {
      "id": 1,
      "name": "가방"
    },
    "brand": {
      "id": 100,
      "name": "Chanel"
    },
    "services": [
      {
        "id": 11,
        "name": "가죽 복원"
      }
    ],
    "images": [
      {
        "id": 10001,
        "role": "BEFORE",
        "url": "https://example.com/uploads/case/555/before-1.webp",
        "sortOrder": 1
      },
      {
        "id": 10002,
        "role": "AFTER",
        "url": "https://example.com/uploads/case/555/after-1.webp",
        "sortOrder": 1
      }
    ],
    "publishedAt": "2026-09-15T09:00:00+09:00"
  }
}
```

---

# 18. Review API

## 18.1 Review List

### Endpoint

```http
GET /api/v1/businesses/{businessId}/reviews
```

### Authentication

```text
Public
```

### Query

| Field | Type | Required | 설명 |
|---|---|---:|---|
| rating | integer | N | 특정 별점 |
| hasImages | boolean | N | 사진 리뷰 |
| sort | string | N | `latest`, `ratingDesc`, `ratingAsc` |
| page | integer | N | |
| perPage | integer | N | |

### Response

```json
{
  "success": true,
  "data": [
    {
      "id": 9001,
      "rating": 5,
      "content": "가방 염색 결과가 만족스럽습니다.",
      "user": {
        "id": 77,
        "name": "김지윤",
        "profileImage": "https://example.com/profile/77.webp"
      },
      "images": [
        {
          "id": 3001,
          "url": "https://example.com/reviews/9001/1.webp",
          "sortOrder": 1
        }
      ],
      "createdAt": "2026-09-10T13:00:00+09:00",
      "updatedAt": "2026-09-10T13:00:00+09:00"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 24,
    "totalPages": 2,
    "summary": {
      "averageRating": 4.8,
      "reviewCount": 24,
      "distribution": {
        "5": 17,
        "4": 5,
        "3": 2,
        "2": 0,
        "1": 0
      }
    }
  }
}
```

---

## 18.2 Create Review

### Endpoint

```http
POST /api/v1/businesses/{businessId}/reviews
```

### Authentication

```text
Required
```

### Request

```json
{
  "rating": 5,
  "content": "가방 염색 결과가 만족스럽습니다."
}
```

### Validation

```text
rating: required, integer, 1~5
content: nullable text
```

리뷰 1인 1업체 제한 여부, 실제 이용 인증 여부는 기존 설계에서 아직 확정되지 않았다.

따라서 구현 시 해당 정책을 임의로 추가하지 않는다.

### Response

```json
{
  "success": true,
  "data": {
    "id": 9001,
    "businessId": 123,
    "rating": 5,
    "content": "가방 염색 결과가 만족스럽습니다.",
    "images": [],
    "createdAt": "2026-09-16T10:00:00+09:00"
  }
}
```

HTTP:

```text
201 Created
```

리뷰 저장과 `business_reputation_stats` 갱신은 같은 Transaction에서 처리한다.

---

## 18.3 Update Review

### Endpoint

```http
PATCH /api/v1/reviews/{reviewId}
```

### Authentication

```text
Required
Owner Only
```

### Request

```json
{
  "rating": 4,
  "content": "내용을 수정했습니다."
}
```

### Response

```json
{
  "success": true,
  "data": {
    "id": 9001,
    "rating": 4,
    "content": "내용을 수정했습니다.",
    "updatedAt": "2026-09-16T10:10:00+09:00"
  }
}
```

리뷰 평점 수정 시 `business_reputation_stats`를 같은 Transaction에서 다시 갱신한다.

---

## 18.4 Delete Review

### Endpoint

```http
DELETE /api/v1/reviews/{reviewId}
```

### Authentication

```text
Required
Owner Only
```

### 처리

`reviews.deleted_at`을 사용한 Soft Delete를 기본으로 한다.

### Response

```text
204 No Content
```

삭제 후 업체 집계 통계를 갱신한다.

---

## 18.5 Add Review Image

### Endpoint

```http
POST /api/v1/reviews/{reviewId}/images
```

### Authentication

```text
Required
Review Owner Only
```

### Content Type

```http
multipart/form-data
```

### Field

```text
image
```

### 검증

```text
파일 크기
허용 확장자
MIME Type
Random Filename
실행 파일 차단
```

정확한 최대 이미지 수/최대 용량은 리뷰 정책에서 아직 확정되지 않았다.

### Response

```json
{
  "success": true,
  "data": {
    "id": 3001,
    "url": "https://example.com/reviews/9001/1.webp",
    "sortOrder": 1
  }
}
```

HTTP:

```text
201 Created
```

---

## 18.6 Delete Review Image

### Endpoint

```http
DELETE /api/v1/reviews/{reviewId}/images/{imageId}
```

### Response

```text
204 No Content
```

---

# 19. Favorite API

## 19.1 Favorite List

### Endpoint

```http
GET /api/v1/favorites
```

### Authentication

```text
Required
```

### Query

```text
page
perPage
```

### Response

Business Search Card와 최대한 동일한 Summary DTO를 사용한다.

```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "name": "ABC 가죽공방",
      "thumbnail": {
        "url": "https://example.com/business/123.webp"
      },
      "primaryLocation": {
        "regionName": "강남구",
        "distanceKm": null
      },
      "reputation": {
        "internal": {
          "rating": 4.8,
          "reviewCount": 24
        }
      },
      "isFavorite": true,
      "favoritedAt": "2026-09-15T12:00:00+09:00"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 5,
    "totalPages": 1
  }
}
```

---

## 19.2 Add Favorite

### Endpoint

```http
POST /api/v1/favorites/{businessId}
```

### Authentication

```text
Required
```

### Response

```json
{
  "success": true,
  "data": {
    "businessId": 123,
    "isFavorite": true
  }
}
```

첫 생성:

```text
201 Created
```

이미 찜 상태인 요청은 중복 Row를 만들지 않는다.

Client Double Tap 때문에 실패시키기보다 현재 상태를 반환하는 Idempotent 처리 방식 사용을 권장한다.

---

## 19.3 Remove Favorite

### Endpoint

```http
DELETE /api/v1/favorites/{businessId}
```

### Response

```text
204 No Content
```

`business_reputation_stats.favorite_count`를 같은 Transaction 범위에서 갱신한다.

---

# 20. Social Authentication

## 20.1 Social Login

### Endpoint

```http
POST /api/v1/auth/social
```

### Authentication

```text
Public
```

### Request

```json
{
  "provider": "KAKAO",
  "providerToken": "provider-issued-token",
  "providerTokenType": "ACCESS_TOKEN"
}
```

Provider:

```text
KAKAO
NAVER
GOOGLE
APPLE
```

Provider Token Type 예:

```text
ACCESS_TOKEN
ID_TOKEN
```

Backend는 Provider별 공식 Verification 절차를 수행한다.

Client가 전달한 Provider User ID를 신뢰하지 않는다.

### Response

```json
{
  "success": true,
  "data": {
    "user": {
      "id": 77,
      "name": "김지윤",
      "email": "user@example.com",
      "profileImage": null,
      "status": "ACTIVE"
    },
    "tokens": {
      "accessToken": "reform-access-token",
      "refreshToken": "reform-refresh-token",
      "accessExpiresAt": "2026-09-16T11:00:00+09:00",
      "refreshExpiresAt": "2026-10-16T10:00:00+09:00"
    },
    "isNewUser": false
  }
}
```

위 Expiration 시간은 Response 형식 예시이며 실제 TTL은 별도 확정한다.

### Error

```json
{
  "success": false,
  "error": {
    "code": "SOCIAL_AUTH_FAILED",
    "message": "소셜 로그인 인증에 실패했습니다."
  }
}
```

---

## 20.2 Refresh Token

### Endpoint

```http
POST /api/v1/auth/refresh
```

### Request

```json
{
  "refreshToken": "reform-refresh-token"
}
```

### Response

```json
{
  "success": true,
  "data": {
    "accessToken": "new-access-token",
    "refreshToken": "new-refresh-token",
    "accessExpiresAt": "2026-09-16T12:00:00+09:00",
    "refreshExpiresAt": "2026-10-16T10:00:00+09:00"
  }
}
```

Refresh Token Rotation을 전제로 설계할 수 있으나 정확한 Rotation 정책은 인증 구현 시 최종 확정한다.

---

## 20.3 Logout

### Endpoint

```http
POST /api/v1/auth/logout
```

### Request

```json
{
  "refreshToken": "reform-refresh-token"
}
```

### 처리

해당 `auth_sessions`를 revoke 처리한다.

### Response

```text
204 No Content
```

---

# 21. My API

## 21.1 My Profile

### Endpoint

```http
GET /api/v1/me
```

### Response

```json
{
  "success": true,
  "data": {
    "id": 77,
    "name": "김지윤",
    "email": "user@example.com",
    "profileImage": "https://example.com/profile/77.webp",
    "status": "ACTIVE",
    "socialAccounts": [
      {
        "provider": "KAKAO"
      },
      {
        "provider": "GOOGLE"
      }
    ],
    "stats": {
      "favoriteCount": 5,
      "reviewCount": 3
    },
    "createdAt": "2026-08-01T12:00:00+09:00"
  }
}
```

---

## 21.2 Update My Profile

### Endpoint

```http
PATCH /api/v1/me
```

### Request

현재 DB 기준 수정 가능 필드:

```json
{
  "name": "김지윤"
}
```

`email`은 Social Provider에서 전달된 값일 수 있으므로 일반 프로필 수정 필드로 임의 허용하지 않는다.

### Response

```json
{
  "success": true,
  "data": {
    "id": 77,
    "name": "김지윤",
    "email": "user@example.com",
    "profileImage": null
  }
}
```

---

## 21.3 Upload Profile Image

### Endpoint

```http
POST /api/v1/me/profile-image
```

### Content Type

```http
multipart/form-data
```

Field:

```text
image
```

### Response

```json
{
  "success": true,
  "data": {
    "profileImage": "https://example.com/profile/77.webp"
  }
}
```

---

## 21.4 Delete Profile Image

### Endpoint

```http
DELETE /api/v1/me/profile-image
```

### Response

```text
204 No Content
```

---

## 21.5 My Reviews

### Endpoint

```http
GET /api/v1/me/reviews
```

### Query

```text
page
perPage
```

### Response

```json
{
  "success": true,
  "data": [
    {
      "id": 9001,
      "business": {
        "id": 123,
        "name": "ABC 가죽공방"
      },
      "rating": 5,
      "content": "가방 염색 결과가 만족스럽습니다.",
      "images": [],
      "createdAt": "2026-09-10T13:00:00+09:00"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 3,
    "totalPages": 1
  }
}
```

---

## 21.6 Withdraw

### Endpoint

```http
DELETE /api/v1/me
```

### 처리

```text
users.status = WITHDRAWN
users.deleted_at = NOW()
현재 인증 세션 revoke
```

개인정보 삭제/보존 범위는 개인정보처리방침 및 법적 보존 정책 확정 후 구체화한다.

### Response

```text
204 No Content
```

---

# 22. Event API

## 22.1 Create Event

### Endpoint

```http
POST /api/v1/events
```

### Authentication

```text
Public
Optional Bearer Token
```

Anonymous 사용자는 App이 생성한 `sessionKey`를 사용할 수 있다.

### Request

```json
{
  "sessionKey": "app-session-uuid",
  "eventName": "BUSINESS_VIEW",
  "entityType": "BUSINESS",
  "entityId": 123,
  "metadata": {
    "source": "search_result",
    "position": 3
  }
}
```

### Event Name

기존 설계:

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

평판/리뷰 변경 설계 추가:

```text
EXTERNAL_GOOGLE_CLICK
EXTERNAL_NAVER_CLICK
REVIEW_VIEW
REVIEW_CREATE
REVIEW_UPDATE
REVIEW_DELETE
```

상담 기능 추가 시:

```text
INQUIRY_START
INQUIRY_CREATE
```

### 중요

`metadata`에는 다음을 넣지 않는다.

```text
Access Token
Refresh Token
SNS Provider Token
Password
API Key
과도한 개인정보
```

### Response

```text
204 No Content
```

---

# 23. Search Log

`search_logs`는 Mobile Client가 직접 생성하지 않는 것을 기본으로 한다.

Business/Search Service가 검색 완료 후 내부적으로 기록한다.

```text
GET /businesses
↓
SearchService
↓
검색 실행
↓
result_count 계산
↓
search_logs INSERT
```

따라서 별도의 Public `POST /search-logs` API는 만들지 않는다.

---

# 24. External Reputation Contract

외부 평판 데이터는 업체 Detail/List DTO 내부에서 제공한다.

별도의 Mobile용 Google/Naver Proxy API를 우선 만들지 않는다.

구조:

```text
React Native
↓
re:form API
↓
Provider Integration
↓
Google Places API
```

Mobile App에 Google API Key를 노출하지 않는다.

Naver:

```text
Place ID / URL
```

만 제공하며 공식 허용 전에는 방문자 평점/리뷰 수를 DB에 저장하지 않는다.

---

# 25. Inquiry / Consultation API

> 상태: **SCHEMA-ADD**
>
> 확정된 두 번째 스토리보드의 `상담 신청 → 신청 완료 → 신청 내역 보기`를 구현하기 위해 추가한다.
>
> 기존 DB 설계에는 `inquiries`가 없으므로 Migration 추가가 필요하다.

---

## 25.1 Required DB Addition

권장 최소 스키마:

```text
inquiries
────────────────────────────────
id                  BIGINT PK
user_id             BIGINT FK
business_id         BIGINT FK
offering_id         BIGINT NULL FK
category_id         BIGINT NULL FK
service_id          BIGINT NULL FK

contact_name        VARCHAR(100)
contact_phone       VARCHAR(30)

preferred_date      DATE NULL
content             TEXT

status              VARCHAR(20)

created_at          DATETIME
updated_at          DATETIME
```

예상 Status:

```text
PENDING
RECEIVED
CONTACTED
CLOSED
CANCELED
```

업체용 관리 기능이 아직 없으므로 초기 생성 상태는:

```text
PENDING
```

정확한 상담 Status Workflow는 Admin/업체 기능 설계 시 확정한다.

---

## 25.2 Create Inquiry

### Endpoint

```http
POST /api/v1/inquiries
```

### Authentication

```text
Required
```

### Request

일반:

```json
{
  "businessId": 123,
  "offeringId": 1001,
  "contactName": "김지윤",
  "contactPhone": "010-1234-5678",
  "preferredDate": "2026-09-20",
  "content": "갈색 가방 염색을 상담받고 싶습니다."
}
```

작업 종류를 모르는 경우:

```json
{
  "businessId": 123,
  "categoryId": 1,
  "serviceId": null,
  "contactName": "김지윤",
  "contactPhone": "010-1234-5678",
  "preferredDate": null,
  "content": "어떤 작업이 필요한지 잘 모르겠습니다."
}
```

### Validation

```text
businessId required
contactName required
contactPhone required
content required

offeringId가 있으면 해당 업체의 ACTIVE Offering이어야 한다.

offeringId가 없고 categoryId/serviceId가 있다면
유효한 Category/Service 조합인지 검증한다.
```

### Response

```json
{
  "success": true,
  "data": {
    "id": 7001,
    "business": {
      "id": 123,
      "name": "ABC 가죽공방"
    },
    "status": "PENDING",
    "preferredDate": "2026-09-20",
    "createdAt": "2026-09-16T10:00:00+09:00"
  }
}
```

HTTP:

```text
201 Created
```

---

## 25.3 My Inquiry List

### Endpoint

```http
GET /api/v1/me/inquiries
```

### Authentication

```text
Required
```

### Query

```text
status
page
perPage
```

### Response

```json
{
  "success": true,
  "data": [
    {
      "id": 7001,
      "business": {
        "id": 123,
        "name": "ABC 가죽공방"
      },
      "status": "PENDING",
      "preferredDate": "2026-09-20",
      "createdAt": "2026-09-16T10:00:00+09:00"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 1,
    "totalPages": 1
  }
}
```

---

## 25.4 My Inquiry Detail

### Endpoint

```http
GET /api/v1/me/inquiries/{inquiryId}
```

### Response

```json
{
  "success": true,
  "data": {
    "id": 7001,
    "business": {
      "id": 123,
      "name": "ABC 가죽공방"
    },
    "offering": {
      "id": 1001,
      "categoryName": "가방",
      "serviceName": "염색"
    },
    "contactName": "김지윤",
    "contactPhone": "010-1234-5678",
    "preferredDate": "2026-09-20",
    "content": "갈색 가방 염색을 상담받고 싶습니다.",
    "status": "PENDING",
    "createdAt": "2026-09-16T10:00:00+09:00"
  }
}
```

본인 Inquiry만 조회 가능하다.

---

## 25.5 Cancel Inquiry

Status Workflow가 확정될 경우 다음 Endpoint를 사용할 수 있다.

```http
POST /api/v1/me/inquiries/{inquiryId}/cancel
```

현재는 `FUTURE`로 두며, Status 정책 확정 후 활성화한다.

---

# 26. Settings API 범위

스토리보드의 설정 화면 중 다음은 API가 필요 없다.

```text
앱 버전
OS 알림 권한 이동
이용약관 링크
개인정보처리방침 링크
```

다음은 서버 기능 추가 시 별도 설계한다.

```text
마케팅 알림 동의
Push Device Token
알림 수신 설정
```

현재 DB에는 `user_preferences`, `user_devices`가 없으므로 v1 Core API에서 제외한다.

---

# 27. Home Screen API 호출 전략

Home 전용 Aggregate API를 v1 최초 구현에서는 만들지 않는다.

초기 호출:

```text
GET /categories
GET /services?limit=...
GET /businesses?lat=...&lng=...&radiusKm=...&sort=recommended&perPage=...
GET /work-cases?page=1&perPage=...
```

장점:

```text
Domain API 재사용
Cache 단순화
화면 변경에 대한 결합도 감소
```

Home API 호출 수가 실제 성능 문제가 되면 이후 다음 형태를 검토한다.

```text
GET /api/v1/home
```

이는 현재 확정 Endpoint가 아니라 최적화 후보다.

---

# 28. Business Detail API 호출 전략

Business Detail 진입 시:

```text
GET /businesses/{id}
GET /work-cases?businessId={id}&page=1&perPage=4
```

Review Tab 선택 시:

```text
GET /businesses/{id}/reviews?page=1&perPage=20
```

초기 Detail Response에 리뷰 원문 전체를 포함하지 않는다.

---

# 29. Favorite + Login Story

비로그인 사용자가 찜 버튼을 누르는 경우:

```text
♡ Tap
↓
Client Auth Check
↓
미로그인
↓
Login Bottom Sheet / Login Screen
↓
POST /auth/social
↓
성공
↓
POST /favorites/{businessId}
↓
Business Detail 복귀
```

Server는 로그인 전의 Pending Action을 저장하지 않는다.

Pending Action은 React Native Client Navigation State에서 관리한다.

---

# 30. Review Create Story

```text
Review 작성 화면
↓
POST /businesses/{businessId}/reviews
↓
reviewId 획득
↓
사진이 있으면
POST /reviews/{reviewId}/images
↓
완료
```

리뷰 생성 자체와 이미지 업로드를 분리한다.

이유:

```text
PHP Backend에서 JSON API와 File Upload 책임 분리
PATCH multipart 처리 복잡도 감소
파일 일부 실패 시 재시도 가능
```

---

# 31. Error Code Catalog

## Common

```text
BAD_REQUEST
VALIDATION_ERROR
UNAUTHORIZED
FORBIDDEN
NOT_FOUND
RATE_LIMITED
INTERNAL_ERROR
```

## Authentication

```text
SOCIAL_AUTH_FAILED
PROVIDER_TOKEN_INVALID
ACCESS_TOKEN_INVALID
ACCESS_TOKEN_EXPIRED
REFRESH_TOKEN_INVALID
REFRESH_TOKEN_EXPIRED
SESSION_REVOKED
USER_SUSPENDED
USER_WITHDRAWN
```

## Reference

```text
CATEGORY_NOT_FOUND
SERVICE_NOT_FOUND
REGION_NOT_FOUND
BRAND_NOT_FOUND
FILTER_COMBINATION_INVALID
```

## Business

```text
BUSINESS_NOT_FOUND
BUSINESS_INACTIVE
```

## Work Case

```text
WORK_CASE_NOT_FOUND
```

## Favorite

```text
FAVORITE_NOT_FOUND
```

## Review

```text
REVIEW_NOT_FOUND
REVIEW_FORBIDDEN
REVIEW_RATING_INVALID
REVIEW_IMAGE_NOT_FOUND
```

리뷰 중복 정책은 아직 확정되지 않았으므로 `REVIEW_ALREADY_EXISTS`를 현 단계에서 강제하지 않는다.

## Upload

```text
UPLOAD_TOO_LARGE
UNSUPPORTED_FILE_TYPE
INVALID_MIME_TYPE
UPLOAD_FAILED
```

## Inquiry

```text
INQUIRY_NOT_FOUND
INQUIRY_FORBIDDEN
INQUIRY_INVALID_OFFERING
INQUIRY_STATUS_CONFLICT
```

---

# 32. Controller Mapping

권장 Controller:

```text
src/Controller/Api/V1/

ReferenceController
BusinessController
WorkCaseController
SearchController

AuthController
MeController
FavoriteController
ReviewController

EventController

InquiryController
```

`InquiryController`는 SCHEMA-ADD 기능이다.

---

# 33. Service Mapping

```text
ReferenceService
BusinessService
SearchService
WorkCaseService

AuthService
UserService
FavoriteService
ReviewService

EventService

InquiryService
ExternalReputationService
```

`ExternalReputationService`는 Google Places 연동 등 Server 내부용 Service이며 Mobile Client가 Provider API를 직접 호출하지 않는다.

---

# 34. Repository Mapping

SQL은 Repository Layer에만 둔다.

```text
CategoryRepository
ServiceRepository
RegionRepository
BrandRepository

BusinessRepository
BusinessOfferingRepository
BusinessLocationRepository
BusinessReputationRepository

WorkCaseRepository
ReviewRepository
FavoriteRepository

UserRepository
SocialAccountRepository
AuthSessionRepository

SearchLogRepository
EventLogRepository

InquiryRepository
```

---

# 35. Transaction Boundary

다음 작업은 Transaction을 사용한다.

## Review Create

```text
reviews INSERT
+
business_reputation_stats UPDATE
```

## Review Update/Delete

```text
reviews UPDATE
+
business_reputation_stats UPDATE
```

## Favorite Add/Delete

```text
user_favorites 변경
+
business_reputation_stats.favorite_count UPDATE
```

## Social Signup

```text
users INSERT
+
user_social_accounts INSERT
+
auth_sessions INSERT
```

## Inquiry Create

```text
inquiries INSERT
```

추후 Notification/업체 전달 로그가 추가되면 같은 Use Case에서 Transaction 범위를 재검토한다.

---

# 36. SQL / Input Security

모든 사용자 입력 SQL:

```text
PDO Prepared Statement
+
Parameter Binding
```

PDO:

```php
PDO::ATTR_EMULATE_PREPARES => false
PDO::ATTR_PERSISTENT => false
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
```

동적 ORDER BY:

```text
Client sort 값
↓
Server Whitelist Map
↓
실제 SQL Column
```

Client 값을 SQL Identifier에 직접 연결하지 않는다.

---

# 37. File Upload Security

적용 대상:

```text
Profile Image
Review Image
향후 Inquiry Image
```

필수 검증:

```text
File Size
Extension
MIME Type
Random Filename
Safe Directory
Executable File 차단
```

원본 파일명을 저장 경로로 사용하지 않는다.

---

# 38. API Rate Limit

HTTP `429` Contract는 유지한다.

정확한 Endpoint별 Limit 수치는 기존 기획/설계에서 아직 확정되지 않았으므로 본 문서에서 임의로 고정하지 않는다.

우선 보호 대상:

```text
/auth/social
/auth/refresh
/reviews
/inquiries
/events
/search/suggestions
```

---

# 39. Privacy / Logging

Log 금지:

```text
Password
SNS Provider Token
Access Token
Refresh Token
Google API Key
OAuth Secret
Session ID
불필요한 개인정보
```

전화번호가 포함되는 Inquiry는 Log Body 전체를 그대로 기록하지 않는다.

---

# 40. API Endpoint Summary

## Reference

```text
GET    /api/v1/categories
GET    /api/v1/services
GET    /api/v1/regions
GET    /api/v1/brands
```

## Search / Business

```text
GET    /api/v1/businesses
GET    /api/v1/businesses/{businessId}

GET    /api/v1/search
GET    /api/v1/search/suggestions
```

## Work Case

```text
GET    /api/v1/work-cases
GET    /api/v1/work-cases/{workCaseId}
```

## Review

```text
GET    /api/v1/businesses/{businessId}/reviews
POST   /api/v1/businesses/{businessId}/reviews

PATCH  /api/v1/reviews/{reviewId}
DELETE /api/v1/reviews/{reviewId}

POST   /api/v1/reviews/{reviewId}/images
DELETE /api/v1/reviews/{reviewId}/images/{imageId}
```

## Auth

```text
POST   /api/v1/auth/social
POST   /api/v1/auth/refresh
POST   /api/v1/auth/logout
```

## My

```text
GET    /api/v1/me
PATCH  /api/v1/me
DELETE /api/v1/me

POST   /api/v1/me/profile-image
DELETE /api/v1/me/profile-image

GET    /api/v1/me/reviews
```

## Favorites

```text
GET    /api/v1/favorites
POST   /api/v1/favorites/{businessId}
DELETE /api/v1/favorites/{businessId}
```

## Analytics

```text
POST   /api/v1/events
```

## Inquiry — SCHEMA-ADD

```text
POST   /api/v1/inquiries

GET    /api/v1/me/inquiries
GET    /api/v1/me/inquiries/{inquiryId}
```

Future:

```text
POST   /api/v1/me/inquiries/{inquiryId}/cancel
```

---

# 41. 스토리보드 기준 전체 API Flow

```text
Splash
  │
  ├── Token 있음
  │      └── POST /auth/refresh (필요 시)
  │
  ▼
Onboarding
  │
  │ Static
  ▼
Login
  │
  └── POST /auth/social
  │
  ▼
Home
  │
  ├── GET /categories
  ├── GET /services
  ├── GET /businesses
  └── GET /work-cases
  │
  ▼
Service Select
  │
  ├── GET /categories
  └── GET /services?categoryId=
  │
  ▼
Region Select
  │
  └── GET /regions
  │
  ▼
Search Result
  │
  └── GET /businesses?...filters
  │
  ├───────────────┐
  ▼               ▼
List             Map
  │               │
  └───────┬───────┘
          ▼
Business Detail
  │
  ├── GET /businesses/{id}
  ├── GET /work-cases?businessId=
  └── GET /businesses/{id}/reviews
          │
     ┌────┼─────────────┐
     ▼    ▼             ▼
WorkCase Reviews      Favorite
     │    │             │
     │    │             └── POST /favorites/{id}
     │    │
     │    └── POST /businesses/{id}/reviews
     │
     └── GET /work-cases/{id}
          │
          ▼
Consultation
  │
  └── POST /inquiries
          │
          ▼
Complete
  │
  └── GET /me/inquiries/{id}
```

---

# 42. 구현 우선순위

## Phase API-1 — 검색 Vertical Slice

```text
GET /categories
GET /services
GET /regions
GET /brands

GET /businesses
GET /businesses/{id}

GET /work-cases
GET /work-cases/{id}
```

완료 기준:

```text
Home
→ 서비스 선택
→ 지역 선택
→ 검색 결과
→ 업체 상세
→ 작업사례
```

가 실제 앱에서 동작한다.

## Phase API-2 — 인증 / 사용자 기능

```text
POST /auth/social
POST /auth/refresh
POST /auth/logout

GET/PATCH /me

GET/POST/DELETE favorites
```

## Phase API-3 — 리뷰

```text
GET/POST business reviews
PATCH/DELETE reviews
Review Images
My Reviews
```

## Phase API-4 — 상담

```text
inquiries Migration
POST /inquiries
GET /me/inquiries
GET /me/inquiries/{id}
```

상담은 확정 스토리보드에는 포함되지만 기존 Database Design 이후 추가된 기능이므로 Migration을 먼저 확정한다.

---

# 43. 현재 미확정 사항

다음은 API 구현 전에 Product Policy 확정이 필요하거나 Server 내부 정책으로 남겨야 한다.

```text
Access Token TTL
Refresh Token TTL / Rotation 상세

리뷰:
- 1인 1업체 1리뷰 제한 여부
- 실제 이용 인증 방식
- 최대 이미지 수
- 이미지 최대 용량
- 업체 답글
- 리뷰 신고/Moderation

검색:
- recommended Ranking Weight
- Google External Rating 갱신/Cache 정책

상담:
- 업체에게 전달되는 방식
- 상담 상태 Workflow
- 상담 취소 가능 시점
- 상담 이미지 첨부 여부

알림:
- Push Provider
- Device Token 저장
- 마케팅 동의 구조
```

위 사항은 본 API Contract에서 임의 확정하지 않는다.

---

# 44. 최종 결정

v1 Mobile App API의 핵심은 다음이다.

```text
Reference
+
Business Search
+
Business Detail
+
Work Cases
+
Social Auth
+
Favorites
+
Own Reviews
+
External Reputation
+
Events
+
Consultation (스토리보드 신규, DB 확장 필요)
```

외부 플랫폼 평판은 다음 원칙을 유지한다.

```text
re:form 평점
≠
Google 평점
≠
Naver
```

Google:

```text
공식 API
```

Naver:

```text
Place URL 중심
공식 허용 전까지 평점/리뷰 수 저장 안 함
```

Mobile App은 외부 Provider API Key를 보유하지 않는다.

모든 핵심 데이터는:

```text
React Native
↓
/api/v1
↓
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

구조를 따른다.
