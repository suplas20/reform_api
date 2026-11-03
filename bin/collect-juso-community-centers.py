#!/usr/bin/env python3
"""Local maintenance: collect public community-office building search results.

Usage: collect-juso-community-centers.py CACHE_DIRECTORY OUTPUT_CSV [KEYWORD]
Uses the same anonymous, read-only search as https://www.juso.go.kr/map/totalMapView.
No provider keys, WFS calls, third-party libraries or production background jobs.
"""
import concurrent.futures
import csv
import json
import math
import pathlib
import re
import sys
import time
import urllib.error
import urllib.request

ENDPOINT = 'https://www.juso.go.kr/api/solr/solrKeywordSearch'
SIZE = 10
COLUMNS = ['province', 'district', 'dong', 'office_name', 'road_address', 'latitude',
           'longitude', 'source', 'source_url', 'verified_at']


def wgs84(easting, northing):
    """Inverse EPSG:5179 (GRS80), as defined in the official Juso map client."""
    a, scale = 6378137.0, .9996
    flattening = 1 / 298.257222101
    e2 = flattening * (2 - flattening)
    ep2 = e2 / (1 - e2)
    phi0 = math.radians(38)
    def meridian(phi):
        return a * ((1-e2/4-3*e2**2/64-5*e2**3/256)*phi
            -(3*e2/8+3*e2**2/32+45*e2**3/1024)*math.sin(2*phi)
            +(15*e2**2/256+45*e2**3/1024)*math.sin(4*phi)
            -35*e2**3/3072*math.sin(6*phi))
    arc = (northing-2000000)/scale + meridian(phi0)
    mu = arc/(a*(1-e2/4-3*e2**2/64-5*e2**3/256))
    e1 = (1-math.sqrt(1-e2))/(1+math.sqrt(1-e2))
    phi = mu + (3*e1/2-27*e1**3/32)*math.sin(2*mu) + (21*e1**2/16-55*e1**4/32)*math.sin(4*mu)
    phi += 151*e1**3/96*math.sin(6*mu) + 1097*e1**4/512*math.sin(8*mu)
    n = a/math.sqrt(1-e2*math.sin(phi)**2)
    radius = a*(1-e2)/(1-e2*math.sin(phi)**2)**1.5
    tangent, c = math.tan(phi)**2, ep2*math.cos(phi)**2
    d = (easting-1000000)/(n*scale)
    latitude = phi - n*math.tan(phi)/radius*(d*d/2-(5+3*tangent+10*c-4*c*c-9*ep2)*d**4/24
        +(61+90*tangent+298*c+45*tangent*tangent-252*ep2-3*c*c)*d**6/720)
    longitude = math.radians(127.5) + (d-(1+2*tangent+c)*d**3/6
        +(5-2*c+28*tangent-3*c*c+8*ep2+24*tangent*tangent)*d**5/120)/math.cos(phi)
    return math.degrees(latitude), math.degrees(longitude)


def search(page, cache, keyword):
    filename = cache / ('page-' + str(page).zfill(4) + '.json')
    if filename.exists():
        return json.loads(filename.read_text())
    query = {'keyword': keyword, 'strSearchType': 'HSTRY', 'strFirstSort': 'none',
        'strAblYn': 'N', 'strAotYn': 'N', 'strSynnYn': None, 'strHstryYn': 'Y',
        'reqFrom': 'RN_SEARCH_KOR_MAP', 'checkMoblieYn': 'N', 'strFunctionName': 'Y',
        'pageable': {'page': page, 'size': SIZE}}
    request = urllib.request.Request(ENDPOINT, data=json.dumps(query).encode(),
        headers={'Content-Type': 'application/json'}, method='POST')
    with urllib.request.urlopen(request, timeout=30) as response:
        result = json.load(response)
    if not isinstance(result.get('results', {}).get('content'), list):
        raise RuntimeError('Official search response changed')
    filename.write_text(json.dumps(result, ensure_ascii=False))
    # At most three concurrent read requests; pause between requests per thread.
    time.sleep(.5)
    return result


def extract_office_name(name):
    # An adjacent apartment or an ancillary warehouse must not become the office.
    if re.search(r'아파트|공원|인근|근처|숙직실|창고|사택|관사|주차장', name):
        return None
    match = re.search(r'([^(),]+?(?:주민센터|행정복지센터|[읍면동]사무소))', name)
    if not match:
        return None
    remainder = name[match.end():].strip()
    if remainder and remainder[0] not in '(),':
        return None
    return match.group(1).strip()


def main(args):
    cache, output_csv = map(pathlib.Path, args[:2])
    keyword = args[2] if len(args) == 3 else '주민센터'
    if keyword not in ('주민센터', '행정복지센터', '동사무소', '읍사무소', '면사무소'):
        raise ValueError('Unsupported community office search keyword')
    cache.mkdir(exist_ok=True, parents=True)
    first = search(0, cache, keyword)['results']
    total = first['totalElements']
    if not 1 <= total <= 10000:
        raise RuntimeError('Unexpected national office search count')
    pages = (total + SIZE-1)//SIZE
    print('Official office search records:', total, 'pages:', pages, flush=True)
    records = list(first['content'])
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
        for index, response in enumerate(pool.map(lambda page: search(page, cache, keyword), range(1, pages)), 1):
            page = response['results']
            if page['totalElements'] != total:
                raise RuntimeError('Search snapshot changed during collection; review before continuing')
            records.extend(page['content'])
            if index % 25 == 0:
                print('Collected pages:', index+1, '/', pages, flush=True)
    if len(records) != total:
        raise RuntimeError('Incomplete official search result')
    (cache / 'buildings.json').write_text(json.dumps(records, ensure_ascii=False))
    # Emit primary-source building records; the reference builder performs full
    # registered-province/district/town matching and conflict checks afterwards.
    rows, seen = [], set()
    for record in records:
        if record.get('ablYn') != '0' or str(record.get('hstryYn')) not in ('0', '0.0'):
            continue
        name = record.get('bdNm') or ''
        office = extract_office_name(name)
        if not office or not record.get('d') or not record.get('k'):
            continue
        key = record.get('bdMgtSn')
        if not key or key in seen:
            continue
        seen.add(key)
        # The public Juso map client applies this linear display conversion.
        latitude, longitude = wgs84(float(record['d'])/.3-333333, float(record['k'])/.3-333333)
        if not (32 <= latitude <= 39 and 124 <= longitude <= 132):
            continue
        dong = re.sub(r'(?:주민센터|행정복지센터|사무소)$', '', office).strip()
        rows.append(dict(zip(COLUMNS, [record.get('siNm', ''), record.get('sggNm', ''), dong, office,
            record.get('schKorRoadNm') or record.get('rdNm', ''), format(latitude,'.7f'), format(longitude,'.7f'),
            'OFFICE-JUSO-20261103', 'https://www.juso.go.kr/map/totalMapView', '2026-11-03'])))
    with output_csv.open('w',newline='') as output:
        writer=csv.DictWriter(output,fieldnames=COLUMNS,lineterminator='\n')
        writer.writeheader();writer.writerows(rows)
    print('Current office building records:',len(rows),flush=True)


if __name__ == '__main__':
    if len(sys.argv) not in (3, 4):
        raise SystemExit(__doc__)
    main(sys.argv[1:])
