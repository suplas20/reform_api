"""Verify source compilation without network access or third-party dependencies."""
import contextlib
import csv
import importlib.util
import io
import json
import pathlib
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('office_builder', pathlib.Path(__file__).parents[1] / 'bin/build-community-centers-reference.py')
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)


class OfficeReferenceTests(unittest.TestCase):
    def compile(self, facilities=(), chargers=(), municipal=(), town_names=None):
        with tempfile.TemporaryDirectory() as folder:
            root = pathlib.Path(folder)
            regions = [dict(id=i, level=3, status='ACTIVE', name=name,
                ancestor_name='서울특별시', parent_name='동대문구') for i, name in enumerate(town_names or ['휘경1동', '휘경2동'], 392)]
            (root / 'regions.json').write_text(json.dumps(regions))
            (root / 'manifest.json').write_text('[]')
            (root / 'facilities.json').write_text(json.dumps(list(facilities)))
            (root / 'chargers.json').write_text(json.dumps(list(chargers)))
            with (root / 'municipal.csv').open('w', newline='') as output:
                writer = csv.DictWriter(output, fieldnames=builder.COLUMNS)
                writer.writeheader(); writer.writerows(municipal)
            with contextlib.redirect_stdout(io.StringIO()):
                builder.main([root / 'regions.json', root, root / 'municipal.csv',
                    root / 'facilities.json', root / 'chargers.json', root / 'result.csv'])
            with (root / 'result.csv').open() as result:
                rows = list(csv.DictReader(result))
            return rows, json.loads((root / 'result.conflicts.json').read_text())

    def facility(self, name='휘경1동 주민센터', address='서울특별시 동대문구 외대역동로6길 3', latitude='37.59303', longitude='127.06576'):
        return dict(OPEN_LC_NM=name, RDNMADR=address, LATITUDE=latitude,
                    LONGITUDE=longitude, REFERENCE_DATE='2026-10-01')

    def test_neighbouring_towns_and_duplicate_rooms_keep_the_actual_office(self):
        first = self.facility()
        second = self.facility('휘경2동 행정복지센터', '서울특별시 동대문구 한천로 329', '37.59021', '127.06866')
        rows, conflicts = self.compile([first, first, second])
        by_town = {row['dong']: row for row in rows}
        self.assertEqual(set(by_town), {'휘경1동', '휘경2동'})
        self.assertEqual(float(by_town['휘경1동']['latitude']), 37.59303)
        self.assertEqual(float(by_town['휘경2동']['latitude']), 37.59021)
        self.assertEqual(conflicts, [])

    def test_combined_dong_separator_cannot_become_one_of_its_individual_dongs(self):
        point = self.facility(name='휘경1-2동 주민센터')
        rows, _ = self.compile([point], town_names=['휘경1·2동', '휘경1동', '휘경2동'])
        self.assertEqual([row['dong'] for row in rows], ['휘경1·2동'])

    def test_different_district_and_nearby_park_are_never_substituted(self):
        other = self.facility(address='서울특별시 중구 외대역동로6길 3')
        park = dict(FCLTY_NM='휘경1동 주민센터 옆 공원', RDNMADR='서울특별시 동대문구 외대역동로6길 3',
                    LATITUDE='37.59303', LONGITUDE='127.06576', REFERENCE_DATE='2026-10-01')
        rows, _ = self.compile([other], [park])
        self.assertEqual(rows, [])

    def test_conflicting_office_locations_do_not_pick_an_arbitrary_point(self):
        rows, conflicts = self.compile([self.facility(), self.facility(latitude='37.59')])
        self.assertEqual(rows, [])
        self.assertEqual(conflicts[0]['reason'], 'CONFLICTING_OFFICE_POINTS')

    def test_shared_municipality_point_is_rejected_for_different_office_buildings(self):
        rows, conflicts = self.compile([self.facility(), self.facility('휘경2동 주민센터', '서울특별시 동대문구 한천로 329')])
        self.assertEqual(rows, [])
        self.assertEqual(len(conflicts), 2)

    def test_floor_descriptions_do_not_turn_one_building_into_multiple_addresses(self):
        self.assertEqual(builder.building_address('서울특별시 동대문구 외대역동로6길 3 (휘경동) 2층'),
                         builder.building_address('서울특별시 동대문구 외대역동로6길 3'))


if __name__ == '__main__':
    unittest.main()
