#!/usr/bin/env python3
"""Build an office-only reference from downloaded official files (local maintenance).

Usage: build-community-centers-reference.py REGIONS_JSON NATIVE_DIRECTORY MUNICIPAL_CSV FACILITIES_JSON CHARGERS_JSON OUTPUT_CSV [JUSO_CSV ...]
NATIVE_DIRECTORY/manifest.json lists the official CSV paths, titles and source URLs.
No network requests, API keys, third-party modules or production Python are needed.
"""
import csv
import hashlib
import io
import json
import math
import pathlib
import re
import sys

COLUMNS = ['province', 'district', 'dong', 'office_name', 'road_address', 'latitude',
           'longitude', 'source', 'source_url', 'verified_at']
SUFFIX = r'(?:행정복지센터|주민센터|[읍면동]사무소)'
ALIASES = {'서울시': '서울특별시', '서울': '서울특별시', '부산시': '부산광역시',
           '부산': '부산광역시', '경기': '경기도', '강원도': '강원특별자치도',
           '전라북도': '전북특별자치도', '제주도': '제주특별자치도',
           # Same approved root mapping as config/administrative_region_sync.php.
           '광주광역시': '전남광주통합특별시', '전라남도': '전남광주통합특별시'}


def norm(value):
    return re.sub(r'\s+', '', str(value)).translate(str.maketrans({'ㆍ': '·', '.': '·', ',': '·'}))


def decode_csv(filename):
    raw = pathlib.Path(filename).read_bytes()
    for encoding in ('utf-8-sig', 'cp949', 'utf-16'):
        try:
            return list(csv.DictReader(io.StringIO(raw.decode(encoding))))
        except UnicodeError:
            continue
    raise ValueError('Unsupported CSV encoding: ' + str(filename))


def field(row, names):
    row = {norm(key): str(value or '').strip() for key, value in row.items() if key}
    return next((row[norm(name)] for name in names if row.get(norm(name))), '')


def scope_prefixes(province, district):
    prefixes = ['', norm(district), norm(province), norm(province if province == district else province + district)]
    for alias, target in ALIASES.items():
        if target == province:
            prefixes.extend([norm(alias), norm(alias + district)])
    return prefixes


def building_address(value):
    # Floor/room descriptions at the same road address are the same building.
    match = re.match(r'^(.+?(?:로|길)\s*\d+(?:-\d+)?)(?=\s|[([]|$)', str(value).strip())
    return norm(match.group(1) if match else str(value).split('(')[0])


def distance(a, b):
    lat1, lon1, lat2, lon2 = map(math.radians, (float(a['latitude']), float(a['longitude']),
                                            float(b['latitude']), float(b['longitude'])))
    return 2 * 6370986 * math.asin(min(1, math.sqrt(math.sin((lat2-lat1)/2)**2
                + math.cos(lat1)*math.cos(lat2)*math.sin((lon2-lon1)/2)**2)))


def main(args):
    regions_file, native_dir, municipal_csv, facilities_json, chargers_json, output_csv, *extra = map(pathlib.Path, args)
    towns = [r for r in json.loads(regions_file.read_text()) if r['level'] == 3 and r['status'] == 'ACTIVE']
    scopes = {}
    for town in towns:
        scopes.setdefault((town['ancestor_name'], town['parent_name']), []).append(town)
    candidates = {}
    rejected = []
    address_requests = []
    manifests = []

    def add(name, address, latitude, longitude, source, url, reference_date, direct=0):
        needs_coordinate = latitude in (None, '') and longitude in (None, '')
        if not needs_coordinate:
            try:
                latitude, longitude = float(latitude), float(longitude)
                if not (32 <= latitude <= 39 and 124 <= longitude <= 132):
                    return
            except (TypeError, ValueError):
                return
        match = re.match(r'^(.+?' + SUFFIX + r')', name.strip())
        if not match or not address:
            return
        name = match.group(1).strip()
        raw_dong = re.sub(r'(?:행정복지센터|주민센터|사무소)$', '', norm(name))
        raw_dong = re.sub(r'(?<=\d)-(?=\d)', '·', raw_dong)
        address_normal = norm(address)
        for alias, province in ALIASES.items():
            if address_normal.startswith(alias) and not address_normal.startswith(province):
                address_normal = province + address_normal[len(alias):]
                break
        matches = []
        for (province, district), scoped_towns in scopes.items():
            if not address_normal.startswith(norm(province if province == district else province + district)):
                continue
            prefixes = scope_prefixes(province, district)
            for town in scoped_towns:
                if raw_dong in [prefix + norm(town['name']) for prefix in prefixes]:
                    matches.append(town)
        if not matches:
            ordinal_dong = re.sub(r'제(?=\d)', '', raw_dong)
            for (province, district), scoped_towns in scopes.items():
                if address_normal.startswith(norm(province if province == district else province + district)):
                    prefixes = scope_prefixes(province, district)
                    matches.extend(t for t in scoped_towns if ordinal_dong in
                                   [prefix + norm(t['name']) for prefix in prefixes])
        if len(matches) != 1:
            rejected.append({'name': name, 'address': address, 'reason': 'NO_UNIQUE_REGISTERED_TOWN', 'source': source})
            return
        town = matches[0]
        office_suffix = re.search(SUFFIX + '$', norm(name)).group()
        # Preserve the administrative 읍/면/동 when the suffix is 사무소.
        office_name = town['name'] + ('사무소' if office_suffix.endswith('사무소') else office_suffix)
        row = dict(zip(COLUMNS, [town['ancestor_name'], town['parent_name'], town['name'], office_name,
            address, '' if needs_coordinate else format(latitude, '.7f'), '' if needs_coordinate else format(longitude, '.7f'), source, url, '2026-11-03']))
        if needs_coordinate:
            address_requests.append((town['id'], reference_date, direct, row))
        else:
            candidates.setdefault(town['id'], []).append((reference_date, direct, row))

    for row in decode_csv(municipal_csv):
        add(row['office_name'], row['road_address'], row['latitude'], row['longitude'], row['source'],
            row['source_url'], row['verified_at'], 3)

    native_manifest = json.loads((native_dir / 'manifest.json').read_text())
    for entry in native_manifest:
        rows = decode_csv(entry['filename'])
        for row in rows:
            name = field(row, ['동주민센터명', '주민센터명', '주민센터', '시설명', '기관명', '행정동명',
                '행정공공기관명', '명칭', '동행정복지센터명', '읍면동', '읍면동명', '읍면명', '관할동', '구분명', '구분', '동'])
            if not re.search(SUFFIX, name) and re.search(r'[동읍면]$', name):
                name += ' 행정복지센터' if '행정복지센터' in entry['title'] else ' 주민센터'
            address = field(row, ['도로명주소', '주민센터 도로명주소', '소재지 도로명주소', '도로명 주소', '소재지도로명주소', '위치(도로명)', '주소', '소재지', '주 소'])
            title = norm(entry['title']).replace('_', '')
            for province, district in scopes:
                if norm(address).startswith(norm(district)) and title.startswith(norm(province + district)):
                    address = province + ' ' + address
                    break
            latitude = field(row, ['위도', '위도값'])
            longitude = field(row, ['경도', '경도값'])
            if entry['id'] == 15116616:
                # The official Songpa file labels longitude as 위도 and latitude as 경도.
                latitude, longitude = longitude, latitude
            date = field(row, ['데이터기준일자', '기준일자', '데이터 기준일자', '데이터기준일'])
            dates = re.findall(r'20\d{6}', entry['title'])
            if not re.match(r'^\d{4}-\d{2}-\d{2}$', date):
                date = dates[-1][:4]+'-'+dates[-1][4:6]+'-'+dates[-1][6:8] if dates else ''
            source = 'OFFICE-DATA-' + str(entry['id']) + '-' + (date.replace('-', '') or 'UNDATED')
            add(name, address, latitude, longitude, source, entry['source_url'], date, 2)
        manifests.append({'dataset': entry['id'], 'title': entry['title'], 'source_url': entry['source_url'],
            'download': entry['download'], 'sha256': hashlib.sha256(pathlib.Path(entry['filename']).read_bytes()).hexdigest(),
            'rows': len(rows)})
    for filename, dataset, name_field in ((facilities_json, 15013117, 'OPEN_LC_NM'), (chargers_json, 15034533, 'FCLTY_NM')):
        rows = json.loads(filename.read_text())
        for row in rows:
            if dataset == 15034533 and re.search('공원|인근|근처|옆|앞', row.get('FCLTY_NM', '')):
                continue
            date = row.get('REFERENCE_DATE', '')
            add(row.get(name_field, ''), row.get('RDNMADR', ''), row.get('LATITUDE'), row.get('LONGITUDE'),
                'OFFICE-DATA-' + str(dataset) + '-' + date.replace('-', ''),
                f'https://www.data.go.kr/data/{dataset}/standard.do', date, 1)
        manifests.append({'dataset': dataset, 'source_url': f'https://www.data.go.kr/data/{dataset}/standard.do',
            'sha256': hashlib.sha256(filename.read_bytes()).hexdigest(), 'rows': len(rows)})

    for juso_csv in extra:
        juso_rows = decode_csv(juso_csv)
        for row in juso_rows:
            add(row['office_name'], row['road_address'], row['latitude'], row['longitude'],
                row['source'], row['source_url'], row['verified_at'],
                3 if row['source'].startswith('OFFICE-JUSO-ADDR-') else 2)
        manifests.append({'dataset': 'JUSO-OFFICE-BUILDINGS',
            'source_url': 'https://www.juso.go.kr/map/totalMapView',
            'snapshot': juso_csv.name, 'sha256': hashlib.sha256(juso_csv.read_bytes()).hexdigest(), 'rows': len(juso_rows)})

    # Identical points for different addresses can be municipality centroids.
    # Keep verified building locations; never turn such shared placeholders into offices.
    point_addresses = {}
    for options in candidates.values():
        for _, _, row in options:
            point = (round(float(row['latitude']), 6), round(float(row['longitude']), 6))
            point_addresses.setdefault(point, set()).add(building_address(row['road_address']))
    selected, conflicts = [], []
    for town_id, options in candidates.items():
        options.sort(key=lambda item: (item[0], item[1]), reverse=True)
        options = [option for option in options if option[1] == 3 or len(point_addresses[
            (round(float(option[2]['latitude']), 6), round(float(option[2]['longitude']), 6))]) == 1]
        if not options:
            conflicts.append({'regionId': town_id, 'reason': 'SHARED_POINT_WITH_DIFFERENT_ADDRESSES'})
            continue
        newest = options[0]
        peers = [item[2] for item in options if item[:2] == newest[:2]]
        if any(distance(newest[2], peer) > 50 for peer in peers):
            conflicts.append({'regionId': town_id, 'reason': 'CONFLICTING_OFFICE_POINTS', 'candidates': peers})
            continue
        selected.append(newest[2])
    selected.sort(key=lambda row: (row['province'], row['district'], row['dong']))
    with output_csv.open('w', newline='') as output:
        writer = csv.DictWriter(output, fieldnames=COLUMNS, lineterminator='\n')
        writer.writeheader(); writer.writerows(selected)
    used_urls = {row['source_url'] for row in selected}
    manifests = [item for item in manifests if item['source_url'] in used_urls]
    manifests.insert(0, {'dataset': 'DDM-MUNICIPAL-MAP',
        'source_url': 'https://www.ddm.go.kr/www/contents.do?key=293',
        'sha256': hashlib.sha256(municipal_csv.read_bytes()).hexdigest(),
        'rows': len(decode_csv(municipal_csv))})
    output_csv.with_suffix('.sources.json').write_text(json.dumps(manifests, ensure_ascii=False, indent=2)+'\n')
    output_csv.with_suffix('.conflicts.json').write_text(json.dumps(conflicts, ensure_ascii=False, indent=2)+'\n')
    matched_keys = {(row['province'], row['district'], row['dong']) for row in selected}
    grouped_addresses = {}
    for town_id, date, direct, row in address_requests:
        key = (row['province'], row['district'], row['dong'])
        if key not in matched_keys:
            grouped_addresses.setdefault(town_id, []).append((date, direct, row))
    pending_addresses = {}
    for town_id, options in grouped_addresses.items():
        options.sort(key=lambda item: (item[0], item[1]), reverse=True)
        best = options[0]
        peers = [item[2] for item in options if item[:2] == best[:2]]
        if len({building_address(row['road_address']) for row in peers}) == 1:
            pending_addresses[town_id] = best[2]
    with output_csv.with_suffix('.address_requests.csv').open('w', newline='') as output:
        writer = csv.DictWriter(output, fieldnames=COLUMNS, lineterminator='\n')
        writer.writeheader(); writer.writerows(pending_addresses.values())

    print(json.dumps({'registeredTowns': len(towns), 'matched': len(selected), 'conflicts': len(conflicts),
                      'unmatched': len(towns)-len(selected), 'rejectedSourceRows': len(rejected),
                      'sha256': hashlib.sha256(output_csv.read_bytes()).hexdigest()}, ensure_ascii=False))


if __name__ == '__main__':
    if len(sys.argv) < 7:
        raise SystemExit(__doc__)
    main(sys.argv[1:])
