# Region II boundary polygons

Source: geoBoundaries (gbOpen) release 41af8f1 (ADM2) / 9469f09 (ADM3),
derived from NAMRIA/PSA/OCHA data. License: CC BY 3.0 IGO.

- `provinces.geojson` — the 5 Region II provinces (ADM2 subset).
- `municipalities.geojson` — the LGUs present in our site data (ADM3 subset,
  matched by name + site coordinates). 3 LGUs have no polygon: `Cagaban`
  (workbook typo of Cabagan), `Uyugan` (absent from the gbOpen ADM3 set),
  `Cauayan` (variant spelling — its points fall inside the "Cauayan City"
  polygon, which is served instead).
- `districts.geojson` / `barangays.geojson` — not yet available from open
  sources; the boundaries endpoint degrades to an empty FeatureCollection.

Regenerate by re-running the matching script from the git history
("Region II boundary polygons" commit) after refreshing the downloads.
