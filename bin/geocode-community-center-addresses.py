#!/usr/bin/env python3
"""Local maintenance: resolve only officially documented office street addresses.
Usage: geocode-community-center-addresses.py OFFICIAL_ADDRESS_CSV CACHE_DIRECTORY OUTPUT_CSV
No device locations, provider keys, paid APIs or approximate address matches.
"""
import concurrent.futures
import csv
import hashlib
import importlib.util
import json
import pathlib
import re
import sys


def module(name, filename):
    spec=importlib.util.spec_from_file_location(name,pathlib.Path(__file__).with_name(filename))
    result=importlib.util.module_from_spec(spec);spec.loader.exec_module(result)
    return result


collector=module('office_collector','collect-juso-community-centers.py')
builder=module('office_builder','build-community-centers-reference.py')


def canonical_address(value):
    result=builder.building_address(value)
    for alias,province in builder.ALIASES.items():
        if result.startswith(alias) and not result.startswith(province):
            return province+result[len(alias):]
    return result


def resolve(row, cache):
    address=row['road_address']
    match=re.match(r'^(.+?(?:로|길)\s*\d+(?:-\d+)?)(?=\s|[([]|$)',address.strip())
    if not match:
        return None,dict(row,reason='NO_EXACT_STREET_ADDRESS')
    query=match.group(1)
    folder=cache/hashlib.sha256(query.encode()).hexdigest()
    folder.mkdir(parents=True,exist_ok=True)
    result=collector.search(0,folder,query)['results']
    if result['totalElements']>10:
        return None,dict(row,reason='ADDRESS_HAS_TOO_MANY_BUILDINGS')
    candidates=[]
    for building in result['content']:
        if building.get('ablYn')!='0':
            continue
        if canonical_address(building.get('schKorRoadNm') or '')!=canonical_address(address):
            continue
        if not building.get('d') or not building.get('k'):
            continue
        lat,lng=collector.wgs84(float(building['d'])/.3-333333,float(building['k'])/.3-333333)
        if 32<=lat<=39 and 124<=lng<=132:
            candidates.append(dict(row,latitude=format(lat,'.7f'),longitude=format(lng,'.7f')))
    if not candidates or any(builder.distance(candidates[0],other)>5 for other in candidates[1:]):
        return None,dict(row,reason='NO_UNIQUE_OFFICE_BUILDING_AT_OFFICIAL_ADDRESS')
    point=dict(candidates[0],source='OFFICE-JUSO-ADDR-20261103',
        source_url='https://www.juso.go.kr/map/totalMapView',verified_at='2026-11-03')
    return point,dict(original=row,resolved=point)


def main(args):
    source,cache,output=map(pathlib.Path,args)
    with source.open() as file:rows=list(csv.DictReader(file))
    points,audit=[],[]
    cache.mkdir(parents=True,exist_ok=True)
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
        for point,detail in pool.map(lambda row:resolve(row,cache),rows):
            audit.append(detail)
            if point:points.append(point)
    with output.open('w',newline='') as file:
        writer=csv.DictWriter(file,fieldnames=collector.COLUMNS,lineterminator='\n')
        writer.writeheader();writer.writerows(points)
    output.with_suffix('.audit.json').write_text(json.dumps(audit,ensure_ascii=False,indent=2)+'\n')
    print('Official office addresses:',len(rows),'exact building points:',len(points),flush=True)


if __name__=='__main__':
    if len(sys.argv)!=4:raise SystemExit(__doc__)
    main(sys.argv[1:])
