# Region II boundary polygons

Simplified administrative boundaries for Map View (Plan §Map 4.2). Served
through the authenticated `/map/boundaries` endpoint with a 12h cache.

## Sources & license

- Provinces: geoBoundaries gbOpen PHL ADM2 (release 41af8f1), derived from
  NAMRIA/PSA/OCHA. License CC BY 3.0 IGO.
- Municipalities: geoBoundaries gbOpen PHL ADM3 (9469f09), name-matched to
  site spellings; `Uyugan` (absent from gbOpen ADM3) is dissolved from its
  OCHA COD-AB barangays; `Cagaban`/`Cauayan` are alias features sharing the
  Cabagan / Cauayan City polygons so every site spelling highlights.
- Barangays + Uyugan source: OCHA Philippines COD-AB
  `phl_admin_boundaries.geojson.zip` (data.humdata.org, CC BY 3.0 IGO),
  ADM4 subset for the LGUs present in our site data, matched by normalized
  name (accents, parenthesized disambiguators and "City" suffixes stripped).
- Districts: dissolved (shapely unary_union) from the municipality polygons
  via the `legislative_districts` lookup — not an official source boundary.

## Properties

Every feature carries `name`, `level`, `psgc` when available, plus the geo
keys the boundary filter clips by: `province`, `district`, `municipality`
(null where not applicable to that level).

## Known caveats

- `Cagaban` is a workbook typo of Cabagan — the alias polygon is Cabagan's.
- Barangay names keep their OCHA spelling (may differ slightly from the
  spelling encoders write into sites).
- Regenerate by re-running the extraction scripts from git history
  ("Region II district/barangay boundaries" commit) after refreshing the
  source downloads.
