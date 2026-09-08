<?php

// Philippine Standard Geographic Code — region reference (Plan_revision §Phase 1.3).
//
// The first two digits of any 10-digit PSGC id are the region code. Codes are
// verbatim from the PSA PSGC publication (psgc.cloud/api/regions); 15 and 18
// are retired and deliberately absent so an unexpected code falls through to
// NULL instead of being guessed.
return [
    'regions' => [
        '01' => 'I',
        '02' => 'II',
        '03' => 'III',
        '04' => 'IV-A',
        '05' => 'V',
        '06' => 'VI',
        '07' => 'VII',
        '08' => 'VIII',
        '09' => 'IX',
        '10' => 'X',
        '11' => 'XI',
        '12' => 'XII',
        '13' => 'NCR',
        '14' => 'CAR',
        '16' => 'XIII',
        '17' => 'MIMAROPA',
        '19' => 'BARMM',
    ],

    // Region code → island group, for sites.island_group backfill.
    'island_groups' => [
        '01' => 'Luzon',
        '02' => 'Luzon',
        '03' => 'Luzon',
        '04' => 'Luzon',
        '05' => 'Luzon',
        '06' => 'Visayas',
        '07' => 'Visayas',
        '08' => 'Visayas',
        '09' => 'Mindanao',
        '10' => 'Mindanao',
        '11' => 'Mindanao',
        '12' => 'Mindanao',
        '13' => 'Luzon',
        '14' => 'Luzon',
        '16' => 'Mindanao',
        '17' => 'Luzon',
        '19' => 'Mindanao',
    ],
];
