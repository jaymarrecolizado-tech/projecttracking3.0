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

## Barangay totals vs PSA

The layer holds **2,262 barangays** for Region II's 92 LGUs. Spot checks match
PSA exactly where it matters most — Tuguegarao City 49, Ilagan City 91,
Cauayan City 65, City of Santiago 37, Batanes 29 — but the layer total is
**49 short of the PSA figure of 2,311**, because the OCHA COD-AB snapshot
(2025-02) predates some barangay renamings/ratifications. The authoritative
counts for the barangay coverage report live in the `barangay_references`
table (`php artisan barangays:sync-reference` rebuilds it from this layer;
upsert-only, so manually added PSA rows are never deleted). Add the missing
barangays there and the coverage percentages update immediately.

## Known caveats

- `Cagaban` is a workbook typo of Cabagan — the alias polygon is Cabagan's.
- Barangay names keep their OCHA spelling (may differ slightly from the
  spelling encoders write into sites).
- Regenerate by re-running the extraction scripts from git history
  ("Region II district/barangay boundaries" commit) after refreshing the
  source downloads.
