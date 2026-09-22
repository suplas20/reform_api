# re:form 고객 앱 API 요구사항

> 작성 기준: 2026-09-22 현재 `reform_app` 구현 화면과 Repository 인터페이스
> 대상: 고객용 모바일 앱. 업체용 앱과 업체 등록 API는 포함하지 않는다.

## 1. 목적

현재 앱은 `EXPO_PUBLIC_DATA_SOURCE=mock`으로 동작하며 업체, 검색, 리뷰, 문의, 프로필 데이터를 로컬 Mock Repository에서 가져온다. 이 문서는 Mock 데이터를 실제 서버로 교체하기 위해 필요한 API 계약을 정리한다.

우선순위는 다음과 같다.

| 등급 | 의미 |
|---|---|
| P0 | 현재 구현된 핵심 화면을 실제 데이터로 동작시키는 데 필수 |
| P1 | 현재 화면에서 정적 데이터로 표시되는 기능을 서버화하는 데 필요 |
| P2 | 운영, 광고, 푸시, 분석에 필요 |

## 2. 범위

### 포함

- 소셜 로그인과 세션 갱신
- 카테고리, 서비스, 지역, 브랜드 기준 정보
- 업체 검색, 지도 검색, 업체 상세
- 작업 사례
- 리뷰 조회와 작성
- 찜한 업체
- 상담 및 문의
- 내 프로필과 활동 개수
- 최근 본 업체
- 알림과 앱 설정
- 고객센터 FAQ
- 이미지 업로드
- 광고 슬롯과 광고 이벤트

### 제외

- 업체 등록과 업체 정보 관리
- 업체용 상담 답변 및 작업 사례 등록
- 결제와 환불 실행
- 관리자 운영 API

업체용 기능은 별도 앱과 별도 API 명세에서 정의한다.

## 3. 공통 규칙

### 3.1 기본 주소와 버전

```text
https://api.example.com/api/v1
```

- URL은 복수형 리소스 명사를 사용한다.
- 요청과 응답 필드는 앱 TypeScript 모델에 맞춰 `camelCase`를 사용한다.
- 날짜와 시각은 ISO 8601 형식을 사용한다.
  - 날짜: `2026-09-22`
  - 시각: `2026-09-22T10:30:00+09:00`
- 금액은 부동소수점 대신 원 단위 정수로 전달한다.
- ID는 현재 앱 모델과 호환되도록 정수형을 사용한다.

### 3.2 인증

```http
Authorization: Bearer {accessToken}
```

- 공개 API: 기준 정보, 업체 검색 및 상세, 작업 사례, 공개 리뷰, FAQ, 광고
- 인증 API: 찜, 문의, 리뷰 작성, 프로필, 최근 본 업체, 알림, 설정
- Access Token 만료 시 앱은 Refresh Token으로 1회 갱신한 뒤 원 요청을 다시 실행한다.
- Refresh Token은 Secure Store에 저장하며 일반 AsyncStorage에 저장하지 않는다.

### 3.3 성공 응답

단건:

```json
{
  "data": {
    "id": 1
  }
}
```

목록:

```json
{
  "data": {
    "items": [],
    "nextCursor": null,
    "total": 0
  }
}
```

### 3.4 오류 응답

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "요청값을 확인해주세요.",
    "fieldErrors": {
      "content": "문의 내용을 입력해주세요."
    },
    "requestId": "req_01J..."
  }
}
```

권장 오류 코드:

| HTTP | 코드 | 용도 |
|---:|---|---|
| 400 | `INVALID_REQUEST` | 형식이 잘못된 요청 |
| 401 | `UNAUTHENTICATED` | 로그인 또는 토큰 갱신 필요 |
| 403 | `FORBIDDEN` | 접근 권한 없음 |
| 404 | `NOT_FOUND` | 리소스를 찾을 수 없음 |
| 409 | `CONFLICT` | 중복 요청 또는 상태 충돌 |
| 413 | `FILE_TOO_LARGE` | 업로드 용량 초과 |
| 422 | `VALIDATION_ERROR` | 필드 검증 실패 |
| 429 | `RATE_LIMITED` | 요청 횟수 제한 |
| 500 | `INTERNAL_ERROR` | 서버 오류 |

### 3.5 페이지네이션

목록 API는 커서 방식을 기본으로 한다.

```text
?cursor=eyJpZCI6MTAwfQ&limit=20
```

- `limit` 기본값은 20, 최댓값은 100이다.
- `nextCursor`가 `null`이면 마지막 페이지다.
- 검색 결과는 화면에 전체 개수를 표시하므로 `total`을 함께 반환한다.

### 3.6 이미지

- API는 앱 번들 경로가 아닌 HTTPS URL을 반환한다.
- 목록 이미지는 썸네일 URL을 별도로 제공한다.
- 이미지가 없으면 `null`을 반환한다.
- 리뷰와 문의 이미지 업로드는 12절의 사전 서명 URL 방식을 사용한다.

## 4. 전체 엔드포인트 목록

### 4.1 P0 핵심 API

| 영역 | Method | Endpoint | 인증 | 용도 |
|---|---|---|:---:|---|
| 인증 | POST | `/auth/social-login` | - | 소셜 로그인 및 가입 |
| 인증 | POST | `/auth/refresh` | Refresh | 토큰 갱신 |
| 인증 | POST | `/auth/logout` | O | 로그아웃 및 토큰 폐기 |
| 기준 정보 | GET | `/categories` | - | 카테고리 목록 |
| 기준 정보 | GET | `/services` | - | 세부 서비스 목록 |
| 기준 정보 | GET | `/regions` | - | 검색 지역 목록 |
| 기준 정보 | GET | `/brands` | - | 취급 브랜드 분류 목록 |
| 업체 | GET | `/businesses` | - | 업체 검색 및 목록 |
| 업체 | GET | `/businesses/{businessId}` | - | 업체 상세 |
| 작업 사례 | GET | `/work-cases` | - | 작업 사례 목록 |
| 작업 사례 | GET | `/work-cases/{workCaseId}` | - | 작업 사례 상세 |
| 리뷰 | GET | `/businesses/{businessId}/reviews` | - | 업체 리뷰 목록 |
| 리뷰 | GET | `/businesses/{businessId}/review-summary` | - | 평점 요약 |
| 리뷰 | GET | `/me/reviews` | O | 내가 작성한 리뷰 |
| 리뷰 | POST | `/businesses/{businessId}/reviews` | O | 리뷰 작성 |
| 찜 | GET | `/me/favorites` | O | 찜한 업체 목록 |
| 찜 | PUT | `/me/favorites/{businessId}` | O | 찜 추가 |
| 찜 | DELETE | `/me/favorites/{businessId}` | O | 찜 삭제 |
| 문의 | GET | `/me/inquiries` | O | 내 문의 목록 |
| 문의 | POST | `/businesses/{businessId}/inquiries` | O | 상담 문의 작성 |
| 문의 | GET | `/me/inquiries/{inquiryId}` | O | 문의와 답변 상세 |
| 프로필 | GET | `/me` | O | 내 프로필 |
| 프로필 | PATCH | `/me` | O | 프로필 수정 |
| 프로필 | GET | `/me/activity-counts` | O | 찜, 리뷰, 문의 개수 |
| 파일 | POST | `/media/presigned-uploads` | O | 업로드 URL 발급 |
| 파일 | POST | `/media/complete` | O | 업로드 완료 처리 |

### 4.2 P1 화면 완성 API

| 영역 | Method | Endpoint | 인증 | 용도 |
|---|---|---|:---:|---|
| 최근 본 업체 | GET | `/me/recent-businesses` | O | 최근 본 업체 목록 |
| 최근 본 업체 | PUT | `/me/recent-businesses/{businessId}` | O | 열람 기록 저장 |
| 최근 본 업체 | DELETE | `/me/recent-businesses` | O | 기록 전체 삭제 |
| 알림 | GET | `/me/notifications` | O | 알림 목록과 필터 |
| 알림 | GET | `/me/notifications/{notificationId}` | O | 알림 상세 |
| 알림 | PATCH | `/me/notifications/{notificationId}/read` | O | 읽음 처리 |
| 알림 | PATCH | `/me/notifications/read-all` | O | 전체 읽음 처리 |
| 설정 | GET | `/me/settings` | O | 알림과 위치 설정 조회 |
| 설정 | PATCH | `/me/settings` | O | 설정 변경 |
| 계정 | DELETE | `/me` | O | 회원 탈퇴 |
| FAQ | GET | `/help/categories` | - | 도움말 카테고리 |
| FAQ | GET | `/help/faqs` | - | FAQ 검색과 목록 |
| FAQ | GET | `/help/faqs/{faqId}` | - | FAQ 상세 |

### 4.3 P2 운영 API

| 영역 | Method | Endpoint | 인증 | 용도 |
|---|---|---|:---:|---|
| 푸시 | PUT | `/me/devices/{deviceId}` | O | 푸시 토큰 등록 및 갱신 |
| 푸시 | DELETE | `/me/devices/{deviceId}` | O | 로그아웃 시 토큰 해제 |
| 광고 | GET | `/ads` | 선택 | 광고 슬롯 조회 |
| 광고 | POST | `/ads/{adId}/impressions` | 선택 | 노출 기록 |
| 광고 | POST | `/ads/{adId}/clicks` | 선택 | 클릭 기록 |
| 앱 설정 | GET | `/app-config` | - | 최소 버전, 점검, 고객센터 정보 |

## 5. 인증 API

### 5.1 소셜 로그인

```http
POST /auth/social-login
```

요청:

```json
{
  "provider": "kakao",
  "authorizationCode": "provider_authorization_code",
  "idToken": null,
  "redirectUri": "reform://auth/callback",
  "device": {
    "deviceId": "install_uuid",
    "platform": "android",
    "appVersion": "1.0.0"
  }
}
```

`provider` 허용값:

```text
kakao | naver | google | apple
```

응답:

```json
{
  "data": {
    "accessToken": "...",
    "refreshToken": "...",
    "accessTokenExpiresAt": "2026-09-22T11:00:00+09:00",
    "isNewUser": false,
    "user": {
      "id": 1,
      "name": "김리폼",
      "nickname": "리폼하는사람",
      "avatarUrl": null
    }
  }
}
```

로그인 완료 후 앱은 로그인 전에 보류한 찜, 문의, 리뷰 작성 동작을 다시 실행한다. 찜 추가 API는 중복 호출되어도 성공하도록 멱등성을 보장해야 한다.

### 5.2 토큰 갱신

```http
POST /auth/refresh
```

```json
{
  "refreshToken": "...",
  "deviceId": "install_uuid"
}
```

응답은 새 Access Token과 회전된 Refresh Token을 반환한다.

### 5.3 로그아웃

```http
POST /auth/logout
```

```json
{
  "refreshToken": "...",
  "deviceId": "install_uuid"
}
```

## 6. 기준 정보 API

### 6.1 카테고리

```http
GET /categories
```

```json
{
  "data": {
    "items": [
      {
        "id": 1,
        "name": "가방",
        "iconUrl": "https://cdn.example.com/categories/bag.png",
        "sortOrder": 1
      }
    ]
  }
}
```

### 6.2 서비스

```http
GET /services?categoryId=1
```

```json
{
  "data": {
    "items": [
      {
        "id": 1,
        "categoryId": 1,
        "name": "가방 수선",
        "sortOrder": 1
      }
    ]
  }
}
```

### 6.3 지역

```http
GET /regions?parentId=1&query=강남
```

```json
{
  "data": {
    "items": [
      {
        "id": 10101,
        "name": "강남구",
        "fullName": "서울 강남구",
        "parentId": 1,
        "level": "district",
        "latitude": 37.5172,
        "longitude": 127.0473
      }
    ]
  }
}
```

`level` 허용값은 `province`, `city`, `district`다. 최근 검색 지역은 초기에는 앱 로컬 저장소로 관리해도 된다.

### 6.4 브랜드

```http
GET /brands?query=명품
```

## 7. 업체 검색 및 상세 API

### 7.1 업체 검색

```http
GET /businesses
```

쿼리:

| 이름 | 타입 | 설명 |
|---|---|---|
| `query` | string | 업체명, 서비스, 설명 통합 검색 |
| `categoryId` | number | 홈에서 선택한 카테고리 |
| `serviceId` | number | 세부 서비스 |
| `regionId` | number | 행정 지역 |
| `brandId` | number | 취급 브랜드 분류 |
| `latitude` | number | 현재 위치 위도 |
| `longitude` | number | 현재 위치 경도 |
| `radiusKm` | number | 1, 3, 5, 10, 50km 등 검색 반경 |
| `minRating` | number | 최소 평점 |
| `priceLevels` | string | 쉼표 구분값: `1,2,3` |
| `options` | string | `parking,pickup,sameDay,weekend` |
| `sort` | string | `recommended`, `distance`, `rating`, `reviewCount` |
| `cursor` | string | 다음 페이지 커서 |
| `limit` | number | 페이지 크기 |

위치 기반 검색 시 `latitude`, `longitude`, `radiusKm`를 함께 보낸다. 지역 검색 시 `regionId`를 보낸다.

응답:

```json
{
  "data": {
    "items": [
      {
        "id": 1,
        "name": "브라운 리페어",
        "categoryId": 1,
        "regionId": 10101,
        "serviceIds": [1, 2, 3],
        "brandIds": [1, 4],
        "description": "파손된 가죽 가방을 원형에 가깝게 복원합니다.",
        "rating": 4.9,
        "reviewCount": 120,
        "thumbnailUrl": "https://cdn.example.com/businesses/1/thumb.jpg",
        "verified": true,
        "address": "서울 강남구 도산대로 123",
        "distanceKm": 0.8,
        "priceLevel": 3,
        "options": ["parking", "pickup", "weekend"],
        "latitude": 37.5012,
        "longitude": 127.0396,
        "isFavorite": false
      }
    ],
    "nextCursor": null,
    "total": 1
  }
}
```

- 게스트 요청에서 `isFavorite`은 `false` 또는 생략할 수 있다.
- 거리순 정렬에는 위치 좌표가 필요하다. 위치가 없으면 지역 중심점 또는 추천순으로 대체한다.
- 홈 화면은 `sort=distance&limit=5`를 사용한다.
- 지도 화면은 동일 검색 조건으로 좌표가 포함된 결과를 사용한다.

### 7.2 업체 상세

```http
GET /businesses/{businessId}
```

목록 필드에 다음 필드를 추가한다.

```json
{
  "data": {
    "id": 1,
    "name": "브라운 리페어",
    "heroImageUrls": ["https://cdn.example.com/businesses/1/hero-1.jpg"],
    "phone": "02-1234-5678",
    "kakaoChatUrl": "https://pf.kakao.com/reform",
    "hours": "월 - 토 10:00 - 19:00 (일요일 휴무)",
    "googleRating": 4.8,
    "naverUrl": "https://map.naver.com/...",
    "transportationGuide": "압구정로데오역 5번 출구 도보 6분",
    "parkingGuide": "매장 앞 주차 가능 (2대)",
    "isFavorite": false
  }
}
```

전화번호, 카카오 상담 URL, 외부 지도 URL은 없을 수 있으므로 `null`을 허용한다.

## 8. 작업 사례 API

### 8.1 목록

```http
GET /work-cases?businessId=1&categoryId=1&cursor=...&limit=20
```

```json
{
  "data": {
    "items": [
      {
        "id": 1,
        "businessId": 1,
        "categoryId": 1,
        "title": "가죽 가방 손잡이 교체",
        "description": "마모된 손잡이를 교체했습니다.",
        "beforeImageUrl": "https://cdn.example.com/work-cases/1/before.jpg",
        "afterImageUrl": "https://cdn.example.com/work-cases/1/after.jpg",
        "tags": ["가방 수선", "손잡이 교체"],
        "completedAt": "2026-09-01"
      }
    ],
    "nextCursor": null,
    "total": 1
  }
}
```

### 8.2 상세

```http
GET /work-cases/{workCaseId}
```

상세 응답에는 작업 기간, 비용, 사용 소재, 작업 과정 이미지가 추가로 필요하다.

```json
{
  "data": {
    "id": 1,
    "durationDays": 3,
    "price": 180000,
    "material": "이탈리아산 소가죽",
    "processImageUrls": ["https://cdn.example.com/work-cases/1/process-1.jpg"]
  }
}
```

## 9. 리뷰 API

### 9.1 업체 리뷰 목록

```http
GET /businesses/{businessId}/reviews?sort=latest&cursor=...&limit=20
```

`sort`: `latest`, `ratingHigh`, `ratingLow`, `helpful`

```json
{
  "data": {
    "items": [
      {
        "id": 1,
        "businessId": 1,
        "authorName": "김지안",
        "avatarUrl": null,
        "rating": 5,
        "content": "작업 전 과정을 자세히 설명해주셨어요.",
        "tags": ["친절해요", "설명이 자세해요"],
        "imageUrls": ["https://cdn.example.com/reviews/1/1.jpg"],
        "helpfulCount": 12,
        "isHelpful": false,
        "createdAt": "2026-09-01T15:30:00+09:00"
      }
    ],
    "nextCursor": null,
    "total": 120
  }
}
```

### 9.2 평점 요약

```http
GET /businesses/{businessId}/review-summary
```

```json
{
  "data": {
    "average": 4.9,
    "total": 120,
    "distribution": [
      { "score": 5, "count": 96, "ratio": 0.8 },
      { "score": 4, "count": 18, "ratio": 0.15 }
    ]
  }
}
```

### 9.3 리뷰 작성

```http
POST /businesses/{businessId}/reviews
```

```json
{
  "rating": 5,
  "content": "친절하고 결과도 만족스러웠습니다.",
  "tags": ["친절해요", "꼼꼼해요"],
  "mediaIds": ["media_01J..."]
}
```

- 평점은 1~5의 정수다.
- 내용은 최대 500자다.
- 이미지는 최대 5장이다.
- 중복 제출을 막기 위해 `Idempotency-Key` 헤더를 지원한다.

### 9.4 내 리뷰

```http
GET /me/reviews?type=written&cursor=...&limit=20
```

`type`은 `written`, `helpful`을 지원한다. 응답에는 목록 표시용 업체명과 업체 썸네일을 포함한다.

향후 리뷰 수정과 삭제가 필요하면 다음 API를 추가한다.

```text
PATCH  /me/reviews/{reviewId}
DELETE /me/reviews/{reviewId}
PUT    /reviews/{reviewId}/helpful
DELETE /reviews/{reviewId}/helpful
```

## 10. 찜 API

### 10.1 찜 목록

```http
GET /me/favorites?cursor=...&limit=20
```

응답의 `items`는 업체 검색 카드와 같은 `BusinessSummary` 구조를 사용한다.

### 10.2 추가와 삭제

```text
PUT    /me/favorites/{businessId}
DELETE /me/favorites/{businessId}
```

추가는 이미 찜한 상태에서도 `204 No Content`, 삭제는 이미 삭제된 상태에서도 `204 No Content`를 반환한다.

## 11. 상담 및 문의 API

현재 앱의 문의 상세 화면에는 상태, 첨부 이미지, 업체 답변이 필요하지만 Mock 모델에는 일부 필드가 없다. 실제 API는 아래 구조를 기준으로 한다.

### 11.1 문의 작성

```http
POST /businesses/{businessId}/inquiries
```

```json
{
  "type": "quote",
  "itemCategoryId": 1,
  "preferredDateType": "thisWeek",
  "preferredDate": null,
  "message": "가방 손잡이 교체 비용과 기간이 궁금합니다.",
  "mediaIds": ["media_01J..."]
}
```

허용값:

```text
type: repair | quote | restoration | etc
preferredDateType: thisWeek | nextWeek | thisMonth | afterConsultation | exactDate
```

문의 내용은 최대 500자, 이미지는 최대 5장이다.

### 11.2 내 문의 목록

```http
GET /me/inquiries?status=answered&cursor=...&limit=20
```

`status`: `waiting`, `inProgress`, `answered`, `closed`

```json
{
  "data": {
    "items": [
      {
        "id": 1001,
        "businessId": 1,
        "businessName": "브라운 리페어",
        "businessThumbnailUrl": "https://cdn.example.com/businesses/1/thumb.jpg",
        "title": "가방 수선 비용 문의",
        "type": "quote",
        "status": "answered",
        "createdAt": "2026-09-20T14:30:00+09:00",
        "lastRepliedAt": "2026-09-20T16:20:00+09:00"
      }
    ],
    "nextCursor": null,
    "total": 1
  }
}
```

### 11.3 문의 상세

```http
GET /me/inquiries/{inquiryId}
```

```json
{
  "data": {
    "id": 1001,
    "businessId": 1,
    "businessName": "브라운 리페어",
    "type": "quote",
    "status": "answered",
    "itemCategoryId": 1,
    "preferredDateType": "thisWeek",
    "message": "가방 손잡이 교체 비용과 기간이 궁금합니다.",
    "imageUrls": ["https://cdn.example.com/inquiries/1001/1.jpg"],
    "createdAt": "2026-09-20T14:30:00+09:00",
    "replies": [
      {
        "id": 5001,
        "senderType": "business",
        "senderName": "브라운 리페어",
        "senderAvatarUrl": null,
        "message": "예상 비용은 8~12만원이며 약 7일이 소요됩니다.",
        "createdAt": "2026-09-20T16:20:00+09:00"
      }
    ]
  }
}
```

## 12. 미디어 업로드 API

리뷰, 문의, 프로필 사진은 다음 순서로 업로드한다.

1. 앱이 업로드 URL을 요청한다.
2. 앱이 스토리지에 파일을 직접 업로드한다.
3. 앱이 업로드 완료를 서버에 알린다.
4. 반환된 `mediaId`를 리뷰나 문의 작성 요청에 넣는다.

### 12.1 URL 발급

```http
POST /media/presigned-uploads
```

```json
{
  "files": [
    {
      "fileName": "bag.jpg",
      "contentType": "image/jpeg",
      "size": 1820345,
      "purpose": "inquiry"
    }
  ]
}
```

`purpose`: `profile`, `review`, `inquiry`

응답:

```json
{
  "data": {
    "uploads": [
      {
        "mediaId": "media_01J...",
        "uploadUrl": "https://storage.example.com/...",
        "headers": {
          "Content-Type": "image/jpeg"
        },
        "expiresAt": "2026-09-22T10:10:00+09:00"
      }
    ]
  }
}
```

### 12.2 업로드 완료

```http
POST /media/complete
```

```json
{
  "mediaIds": ["media_01J..."]
}
```

서버는 MIME type, 실제 크기, 이미지 디코딩 가능 여부를 확인한 후 공개 또는 서명된 조회 URL을 생성한다.

## 13. 프로필 API

### 13.1 내 프로필

```http
GET /me
```

```json
{
  "data": {
    "id": 1,
    "name": "김리폼",
    "nickname": "리폼하는사람",
    "tagline": "좋은 것을, 다시 오래.",
    "email": "reform@example.com",
    "phone": "010-1234-5678",
    "avatarUrl": null,
    "socialProviders": ["kakao"],
    "createdAt": "2026-01-01T10:00:00+09:00"
  }
}
```

### 13.2 프로필 수정

```http
PATCH /me
```

```json
{
  "name": "김리폼",
  "nickname": "리폼하는사람",
  "email": "reform@example.com",
  "phone": "010-1234-5678",
  "avatarMediaId": "media_01J..."
}
```

### 13.3 활동 개수

```http
GET /me/activity-counts
```

```json
{
  "data": {
    "favorites": 12,
    "reviews": 8,
    "inquiries": 2,
    "unreadNotifications": 3
  }
}
```

## 14. 최근 본 업체 API

```text
GET    /me/recent-businesses?cursor=...&limit=20
PUT    /me/recent-businesses/{businessId}
DELETE /me/recent-businesses
```

`PUT` 요청은 업체 상세 화면이 정상 표시된 뒤 호출한다. 동일 업체를 다시 보면 최신 시각으로 갱신한다. 목록 응답에는 `viewedAt`과 `BusinessSummary`를 포함한다.

게스트의 최근 본 업체는 로그인 전까지 앱 로컬 저장소에서 관리하고, 로그인 직후 서버와 병합할 수 있다.

## 15. 알림과 설정 API

### 15.1 알림 목록

```http
GET /me/notifications?type=inquiry&unreadOnly=false&cursor=...&limit=20
```

`type`: `inquiry`, `review`, `event`, `system`

```json
{
  "data": {
    "items": [
      {
        "id": 1,
        "type": "inquiry",
        "title": "업체의 답변이 도착했습니다.",
        "body": "브라운 리페어에서 답변이 도착했습니다.",
        "imageUrl": null,
        "isRead": false,
        "createdAt": "2026-09-22T09:30:00+09:00",
        "deepLink": "reform://inquiries/1001"
      }
    ],
    "nextCursor": null,
    "total": 1,
    "unreadCount": 1
  }
}
```

알림 클릭 시 서버가 내려준 허용된 앱 딥링크로 이동한다. 임의의 외부 URL은 그대로 실행하지 않는다.

### 15.2 사용자 설정

```http
GET /me/settings
PATCH /me/settings
```

```json
{
  "pushEnabled": true,
  "inquiryNotificationsEnabled": true,
  "reviewNotificationsEnabled": true,
  "marketingConsent": false,
  "nearbyRecommendationsEnabled": true
}
```

위치 권한 자체는 운영체제 설정이며 서버 설정과 구분한다. 서버에는 위치 기반 추천 사용 여부만 저장한다.

### 15.3 푸시 기기 등록

```http
PUT /me/devices/{deviceId}
```

```json
{
  "platform": "android",
  "pushToken": "ExponentPushToken[...]",
  "appVersion": "1.0.0",
  "locale": "ko-KR",
  "timezone": "Asia/Seoul"
}
```

## 16. 고객센터 API

```text
GET /help/categories
GET /help/faqs?categoryId=1&query=리뷰&cursor=...&limit=20
GET /help/faqs/{faqId}
```

FAQ 응답 필드:

```json
{
  "id": 1,
  "categoryId": 1,
  "question": "리뷰는 수정할 수 있나요?",
  "answerHtml": "<p>...</p>",
  "sortOrder": 1,
  "updatedAt": "2026-09-01T10:00:00+09:00"
}
```

`answerHtml`은 앱에서 허용 태그만 렌더링하거나 Markdown으로 대체한다. 고객센터 전화번호와 운영 시간은 `/app-config`에서 내려받는다.

## 17. 광고 API

외부 광고 SDK를 사용하면 광고 소재 조회와 클릭 처리는 SDK가 담당하므로 자체 API는 필요하지 않다. 자체 광고를 운영할 경우 아래 계약을 사용한다.

### 17.1 광고 조회

```http
GET /ads?placement=home_feed_01
```

현재 배치 위치:

```text
home_feed_01
search_result_01
```

```json
{
  "data": {
    "id": "ad_01J...",
    "placement": "home_feed_01",
    "advertiser": "오래오래 케어",
    "headline": "오래 쓰는 물건을 위한 관리 습관",
    "body": "가죽과 패브릭을 위한 데일리 케어 키트",
    "imageUrl": "https://cdn.example.com/ads/1.jpg",
    "callToAction": "자세히",
    "targetUrl": "https://example.com/campaign",
    "trackingToken": "signed_tracking_token"
  }
}
```

노출과 클릭 이벤트는 중복 집계를 막기 위한 `eventId`를 받는다.

## 18. 앱 설정 API

```http
GET /app-config?platform=android&appVersion=1.0.0
```

```json
{
  "data": {
    "minimumVersion": "1.0.0",
    "latestVersion": "1.1.0",
    "forceUpdate": false,
    "maintenance": {
      "enabled": false,
      "message": null
    },
    "customerCenter": {
      "phone": "1588-1234",
      "hours": "평일 09:00 - 18:00"
    },
    "terms": {
      "termsOfServiceUrl": "https://example.com/terms",
      "privacyPolicyUrl": "https://example.com/privacy",
      "openSourceLicenseUrl": "https://example.com/licenses"
    }
  }
}
```

## 19. 화면별 API 연결표

| 화면 | 필요한 API |
|---|---|
| 스플래시 | `/auth/refresh`, `/app-config` |
| 온보딩 | API 없음, 완료 여부는 로컬 저장 |
| 로그인 | `/auth/social-login` |
| 홈 | `/categories`, `/regions`, `/businesses`, `/ads` |
| 검색 홈 | `/regions`, `/services`, `/businesses`, `/ads` |
| 검색 결과 | `/businesses`, `/services` |
| 지도 결과 | `/businesses` |
| 필터 | `/categories`, `/services`, `/brands` |
| 지역 선택 | `/regions` |
| 위치 권한 | 운영체제 위치 API, 이후 `/businesses` |
| 업체 상세 | `/businesses/{id}`, 작업 사례, 리뷰 요약, 찜 |
| 작업 사례 목록/상세 | `/work-cases`, `/work-cases/{id}` |
| 리뷰 목록 | 업체 리뷰와 평점 요약 API |
| 리뷰 작성 | 미디어 업로드, 리뷰 작성 API |
| 찜한 업체 | `/me/favorites` |
| 상담 신청 | 업체 상세, 미디어 업로드, 문의 작성 API |
| 상담 목록/상세 | `/me/inquiries`, `/me/inquiries/{id}` |
| 마이페이지 | `/me`, `/me/activity-counts` |
| 프로필 수정 | `/me`, 미디어 업로드 |
| 최근 본 업체 | `/me/recent-businesses` |
| 내 리뷰 | `/me/reviews` |
| 알림 | `/me/notifications` |
| 설정 | `/me/settings`, 로그아웃, 회원 탈퇴 |
| 고객센터 | `/help/categories`, `/help/faqs`, `/app-config` |

## 20. 현재 앱 모델과 실제 API의 차이

실제 연동 전에 다음 모델 확장이 필요하다.

| 현재 상태 | 필요한 변경 |
|---|---|
| 인증이 `mockLogin`으로 처리됨 | Provider SDK 결과를 `/auth/social-login`으로 교환하고 토큰 저장 |
| 업체 이미지가 로컬 에셋으로 표시됨 | `thumbnailUrl`, `heroImageUrls` 사용 |
| 업체 검색에 offset/cursor가 없음 | `cursor`, `limit`, `nextCursor` 추가 |
| 찜 Repository가 ID 배열만 반환 | 서버 목록 응답과 명시적 PUT/DELETE로 변경 |
| 리뷰가 `imageCount`만 보유 | `imageUrls`, `mediaIds`, 도움돼요 상태 추가 |
| 문의에 물품, 상태, 사진, 답변이 없음 | `itemCategoryId`, `status`, `imageUrls`, `replies` 추가 |
| 문의 상세가 목록에서 항목을 검색함 | `/me/inquiries/{id}` 전용 조회 추가 |
| 알림, 설정, FAQ가 화면 상수임 | P1 Repository와 Query 구현 |
| 최근 본 업체가 일반 업체 목록을 재사용 | 전용 기록 저장 및 목록 API 구현 |
| 광고가 Mock Provider임 | 광고 SDK 또는 자체 광고 API Provider 구현 |

## 21. 캐시와 갱신 기준

| 데이터 | 권장 캐시 |
|---|---|
| 카테고리, 서비스, 지역, 브랜드 | 24시간 |
| 업체 검색 결과 | 1~5분 |
| 업체 상세 | 5분 |
| 작업 사례와 공개 리뷰 | 1~5분 |
| 내 프로필과 활동 개수 | 1분 |
| 찜, 문의, 알림 | 요청 후 즉시 갱신 |
| 앱 설정 | 앱 실행당 1회, 최대 1시간 |

Mutation 성공 시 관련 캐시를 무효화한다.

- 찜 변경: 찜 목록, 업체 상세, 검색 결과
- 리뷰 작성: 업체 리뷰, 평점 요약, 내 리뷰, 활동 개수
- 문의 작성: 문의 목록, 활동 개수
- 프로필 수정: 내 프로필
- 알림 읽음: 알림 목록, 읽지 않은 알림 개수

## 22. 구현 순서

1. HTTP Client, 토큰 저장, Refresh 재시도, 공통 오류 변환
2. 소셜 로그인과 `/me`
3. 기준 정보 API
4. 업체 검색과 상세
5. 작업 사례와 공개 리뷰
6. 찜 기능
7. 미디어 업로드
8. 문의 작성, 목록, 상세
9. 리뷰 작성과 내 리뷰
10. 프로필과 활동 개수
11. 최근 본 업체, 알림, 설정, FAQ
12. 광고, 푸시, 앱 설정

## 23. 완료 조건

- `EXPO_PUBLIC_DATA_SOURCE=api`에서 모든 P0 화면이 Mock 없이 동작한다.
- 게스트는 홈, 검색, 업체 상세, 작업 사례, 공개 리뷰를 조회할 수 있다.
- 인증 기능은 401 응답 시 로그인 화면으로 연결되고 로그인 후 원래 동작을 이어간다.
- 홈 카테고리 선택값이 업체 검색의 `categoryId`로 전달된다.
- 검색, 찜, 문의, 리뷰 Mutation 후 화면 데이터가 즉시 갱신된다.
- 목록 화면에서 로딩, 빈 결과, 오류, 재시도, 다음 페이지 상태가 모두 처리된다.
- 이미지 업로드 실패와 부분 성공을 사용자가 다시 시도할 수 있다.
- 앱 로그에 Access Token, Refresh Token, 개인정보, 문의 내용이 출력되지 않는다.
