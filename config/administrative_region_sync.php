<?php

declare(strict_types=1);

// Reviewed bridges for the collected 2025-04-01 snapshot and existing SEMAS regions.
// Statistical codes must never be interpreted as MOIS KR- codes.
return [
    'revision' => '2025-04-01',
    'root_targets' => ['24' => 'KR-12', '36' => 'KR-12'],
    'district_aliases' => ['29010' => '세종특별자치시'],
    // These historical districts must not be recreated.
    'replaced_districts' => [
        '23010' => ['KR-28125', 'KR-28155'],
        '23020' => ['KR-28125'],
        '23080' => ['KR-28275', 'KR-28290'],
        '31240' => ['KR-41591', 'KR-41593', 'KR-41595', 'KR-41597'],
    ],
    // Official municipal lists checked 2026-10-27. These supplement the exact
    // dong matches available in current SEMAS business records.
    // https://www.incheon.go.kr/IC01070101
    // https://www.hscity.go.kr/www/intro/ctyhllInfo/hsFax/department.jsp
    'verified_dong_parents' => [
        '23010' => [
            'KR-28125' => ['신포동', '연안동', '신흥동', '도원동', '율목동', '동인천동', '개항동'],
            'KR-28155' => ['영종동', '영종1동', '영종2동', '용유동'],
        ],
        '23020' => [
            'KR-28125' => ['만석동', '화수1·화평동', '화수2동', '송현1·2동', '송현3동',
                '송림1동', '송림2동', '송림3·5동', '송림4동', '송림6동', '금창동'],
        ],
        '23080' => [
            'KR-28275' => ['검암경서동', '연희동', '청라1동', '청라2동', '청라3동',
                '가정1동', '가정2동', '가정3동', '신현원창동', '석남1동', '석남2동', '석남3동',
                '가좌1동', '가좌2동', '가좌3동', '가좌4동'],
            'KR-28290' => ['검단동', '불로대곡동', '원당동', '당하동', '오류왕길동', '마전동'],
        ],
        '31240' => [
            'KR-41591' => ['우정읍', '향남읍', '남양읍', '마도면', '송산면', '서신면',
                '팔탄면', '장안면', '양감면', '새솔동'],
            'KR-41593' => ['매송면', '봉담읍', '비봉면', '정남면', '기배동'],
            'KR-41595' => ['진안동', '병점1동', '병점2동', '반월동', '화산동'],
            'KR-41597' => ['동탄1동', '동탄2동', '동탄3동', '동탄4동', '동탄5동',
                '동탄6동', '동탄7동', '동탄8동', '동탄9동'],
        ],
    ],
    // A split is not a 1:1 mapping; never reactivate the old dong or invent codes.
    'retired_dongs' => ['23010640', '23080880'], // 운서동 → 운서1·2동, 아라동 → 아라1·2동
];
