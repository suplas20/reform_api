"""Projection checks for the official address portal's local maintenance collector."""
import importlib.util
import pathlib
import hashlib
import json
import tempfile
import unittest

spec=importlib.util.spec_from_file_location('juso_collector', pathlib.Path(__file__).parents[1]/'bin/collect-juso-community-centers.py')
collector=importlib.util.module_from_spec(spec)
spec.loader.exec_module(collector)


class ProjectionTests(unittest.TestCase):
    def test_only_an_office_building_can_supply_its_name(self):
        self.assertEqual(collector.extract_office_name('휘경1동 주민센터'), '휘경1동 주민센터')
        self.assertEqual(collector.extract_office_name('복합청사(휘경1동 주민센터)'), '휘경1동 주민센터')
        for name in ['휘경1동 주민센터 옆 아파트', '휘경1동 주민센터 창고', '휘경1동 주민센터 인근 공원']:
            self.assertIsNone(collector.extract_office_name(name))

    def test_epsg5179_false_origin_is_at_the_defined_origin(self):
        latitude,longitude=collector.wgs84(1000000,2000000)
        self.assertAlmostEqual(latitude,38,places=8)
        self.assertAlmostEqual(longitude,127.5,places=8)

    def test_official_hwigyeong_building_point_stays_at_the_actual_office(self):
        # Public Juso building 1123010900101350009041469, collected 2026-11-03.
        latitude,longitude=collector.wgs84(388498.66792558157/.3-333333,686476.8732733546/.3-333333)
        self.assertAlmostEqual(latitude,37.59291475,places=7)
        self.assertAlmostEqual(longitude,127.06573225,places=7)
        # Dongdaemun-gu official map marks the same office building about 13m away.
        self.assertLess(abs(latitude-37.59303),.0002)
        self.assertLess(abs(longitude-127.06576),.0002)


class ExactOfficeAddressTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        spec=importlib.util.spec_from_file_location('office_addresses', pathlib.Path(__file__).parents[1]/'bin/geocode-community-center-addresses.py')
        cls.geocoder=importlib.util.module_from_spec(spec)
        spec.loader.exec_module(cls.geocoder)

    def resolve(self, buildings):
        address='서울특별시 동대문구 외대역동로6길 3'
        with tempfile.TemporaryDirectory() as folder:
            cache=pathlib.Path(folder)
            query=cache/hashlib.sha256(address.encode()).hexdigest()
            query.mkdir()
            (query/'page-0000.json').write_text(json.dumps({'results':{'totalElements':len(buildings),'content':buildings}}))
            return self.geocoder.resolve({'road_address':address,'office_name':'휘경1동 주민센터'},cache)

    def building(self, **values):
        return dict({'ablYn':'0','hstryYn':'1.0','schKorRoadNm':'서울특별시 동대문구 외대역동로6길 3 (휘경동)',
            'd':'388498.66792558157','k':'686476.8732733546'},**values)

    def test_exact_current_street_address_can_resolve_without_an_indexed_building_name(self):
        point,_=self.resolve([self.building()])
        self.assertEqual(point['source'],'OFFICE-JUSO-ADDR-20261103')
        self.assertAlmostEqual(float(point['latitude']),37.59291475,places=6)

    def test_a_neighbouring_address_or_abolished_address_cannot_supply_the_point(self):
        for record in [self.building(schKorRoadNm='서울특별시 동대문구 한천로 329'),self.building(ablYn='1')]:
            point,_=self.resolve([record])
            self.assertIsNone(point)

    def test_multiple_distinct_buildings_at_one_address_are_not_guessed(self):
        point,_=self.resolve([self.building(),self.building(d='388598.66792558157')])
        self.assertIsNone(point)


if __name__=='__main__':
    unittest.main()
