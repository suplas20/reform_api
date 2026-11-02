# 지역 대표좌표

출처: **기상청, 동네예보 지점 좌표(위경도), 2026-07-01 기준**.

- 공식 안내: https://apihub.kma.go.kr/apiList.do?seqApi=10&seqApiSub=286
- 원본: https://apihub.kma.go.kr/getAttachFile.do?fileName=%EB%8F%99%EB%84%A4%EC%98%88%EB%B3%B4%EC%A7%80%EC%A0%90%EC%A2%8C%ED%91%9C(%EC%9C%84%EA%B2%BD%EB%8F%84)_260701.xlsx
- 공공데이터 안내 및 이용조건: https://www.data.go.kr/data/15084084/openapi.do
- 원본 SHA-256: `746b5e5be10430106abccc795a16c16d0f1fd0f081e8ab2765d0adc983a003c1`
- CSV SHA-256: `9c5879ff94d0ba965498c7a0593f908c9796fa6ce02e26a605997d758fbf1b6f`

공개 좌표표의 코드, 시도명, 시군구명, 읍면동명, 소수점 위경도 열을 추출하고 소수점 일곱
자리로 반올림했다. 기상청이 설명하는 동사무소 중심의 행정구역 **대표지점**이며 행정경계
다각형의 기하학적 중심이나 사용자 GPS 위치가 아니다. 지역 선택의 거리 기준점으로 사용한다.
정수 격자 X/Y를 위경도로 사용하거나 업체 좌표를 평균 내서 생성하지 않는다.

원본을 다운로드한 뒤 아래 명령으로 CSV를 재현할 수 있다. 변환은 로컬 유지보수용이며
운영 PHP 런타임에 Python, 엑셀 라이브러리 또는 네트워크 호출이 필요하지 않다.

```sh
python3 bin/convert-kma-region-centers.py source.xlsx database/reference/region_centers_kma_20260701.csv
composer db:migrate
composer db:import:region-centers:plan
composer db:import:region-centers
```

CSV 해시를 검증한 뒤 코드 또는 시도·시군구·읍면동 계층이 유일하게 대응되는 지역에만
대표좌표를 반영한다. 기존 KR- 코드와 KOSTAT- 통계 코드는 서로 변환하지 않는다. 공백,
가운뎃점 표기와 기상청의 `창신제1동`·`홍제제1동` 같은 순서 표기만 비교 시 정규화한다.
구분할 수 없는 후보, 이름이 변경되거나 통합된 지역에는 임의 좌표를 넣지 않는다.
반영되지 않은 지역은 명령 결과에 표시한다. 기준일 변경 시 매칭 결과와 해시를 재검토해야 한다.

초기 반영: 3,828개 지역 중 3,816개 매칭. 나머지는 구 광주광역시·전라남도, 용신동,
박달1·2동, 양지면, 유림동, 오포읍, 탑·대성동, 대소면, 중부동, 벌룡동이다. 이들에는
이번 파일에서 확인하지 못한 좌표를 추정하여 등록하지 않았다. 기존 지역의 이름·코드·부모와
업체 연결은 유지하며, 원본 전체 또는 예보 데이터를 별도 DB에 수집하지 않는다.
