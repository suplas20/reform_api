#!/usr/bin/env python3
"""Local maintenance: convert the official KMA reference workbook to reviewed CSV.

No third-party modules or production spreadsheet runtime is required.
Usage: python3 bin/convert-kma-region-centers.py source.xlsx output.csv
"""
import csv
import re
import sys
import xml.etree.ElementTree as ET
import zipfile


def convert(source, destination):
    namespace = {"m": "http://schemas.openxmlformats.org/spreadsheetml/2006/main"}
    with zipfile.ZipFile(source) as archive:
        strings = ["".join(item.itertext()) for item in ET.fromstring(
            archive.read("xl/sharedStrings.xml")).findall("m:si", namespace)]
        sheet = ET.fromstring(archive.read("xl/worksheets/sheet1.xml"))
    rows = []
    for row in sheet.findall("m:sheetData/m:row", namespace):
        cells = {}
        for cell in row.findall("m:c", namespace):
            value = cell.find("m:v", namespace)
            inline = cell.find("m:is", namespace)
            text = value.text if value is not None else "".join(inline.itertext()) if inline is not None else ""
            if cell.get("t") == "s":
                text = strings[int(text)]
            cells[re.sub(r"\d+$", "", cell.get("r"))] = text
        rows.append(cells)
    if [rows[0].get(column) for column in ["B", "C", "D", "E", "N", "O"]] != [
            "행정구역코드", "1단계", "2단계", "3단계", "경도(초/100)", "위도(초/100)"]:
        raise ValueError("Unexpected KMA workbook columns")
    with open(destination, "w", encoding="utf-8", newline="") as output:
        writer = csv.writer(output, lineterminator="\n")
        writer.writerow(["source_code", "province", "district", "dong", "latitude", "longitude"])
        for row in rows[1:]:
            if not row.get("B"):
                continue
            if row.get("A") != "kor":
                raise ValueError("Unexpected country in KMA workbook")
            writer.writerow([row["B"], row.get("C", ""), row.get("D", ""), row.get("E", ""),
                             format(float(row["O"]), ".7f"), format(float(row["N"]), ".7f")])


if __name__ == "__main__":
    if len(sys.argv) != 3:
        raise SystemExit(__doc__)
    convert(sys.argv[1], sys.argv[2])
