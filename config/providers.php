<?php

// Provider registry (Plan.md #4a). One source for "which company is this",
// replacing free text in sites.cms_provider / link_provider / isp_provider.
//
// Why a config file and not a `providers` table: nothing in the app creates or
// edits providers — there is no admin surface and no owner workflow for one. A
// table would be a second place to keep the same list in sync with a file, and
// this repo has already deleted `config/psgc.php` for exactly that reason. Same
// role and same shape as `config/site_types.php`.
//
// `label` is the canonical spelling that goes into the site columns;
// `aliases` are the OTHER spellings measured in the workbook, so the registry
// resolves them instead of letting one company become two rows in a report.
// There is exactly one alias in the whole file, and it is there because the
// dataset contains both spellings — see `it-business-solutions` below. Keys
// are stable codes: safe in URLs, diffs and CSV exports. Matching is also
// case-insensitive and ignores a trailing "Inc."/"Ltd.", so the common variants
// need no alias of their own.
//
// `default_transport` is the modal `sites.last_mile_tech` for that provider,
// measured from the local dataset (1,132 sites). It is descriptive, not
// prescriptive: a site can and does deviate (DICT runs 325 RADIO but also 16
// LEO and 8 FIBER), so nothing filters on it.
//
// NO `nms_driver` KEY, ON PURPOSE. Plan#4's NMS binding needs one key per
// provider, and the honest value for all twelve is "unknown" — no provider has
// a confirmed machine interface yet. Writing twelve nulls would look like data
// and invite someone to read it as "no NMS". When the owner answers Plan#4's
// "which providers are actually in scope for live polling", that is the moment
// this key appears, with a real driver name on it.

return [
    'dict' => [
        'label' => 'DICT',
        'aliases' => [],
        'default_transport' => 'RADIO',
    ],
    'converge-ict' => [
        'label' => 'Converge ICT Solutions, Inc.',
        'aliases' => [],
        'default_transport' => 'FIBER',
    ],
    'data-lake' => [
        'label' => 'Data Lake Inc.',
        'aliases' => [],
        'default_transport' => 'LEO',
    ],
    'ebizolution' => [
        'label' => 'EBIZolution',
        'aliases' => [],
        'default_transport' => 'LEO',
    ],
    'innove' => [
        // Appears only in link_provider (on DICT RADIO rows), never as a CMS.
        'label' => 'Innove',
        'aliases' => [],
        'default_transport' => null,
    ],
    'it-business-solutions' => [
        // The split this registry exists to fix: the workbook carries both
        // "IT BUSINESS SOLUTIONS" (139 sites) and "IT Business Solutions" (1),
        // and a per-provider rollup counted them as two companies. Only that
        // one measured variant is aliased; a spelling nobody has recorded yet
        // is not guessed at here — `providers:normalize` reports it instead.
        'label' => 'IT Business Solutions',
        'aliases' => ['IT BUSINESS SOLUTIONS'],
        'default_transport' => 'LEO',
    ],
    'kingred' => [
        'label' => 'Kingred Network Solutions',
        'aliases' => [],
        'default_transport' => 'LEO',
    ],
    'limitless-tech' => [
        'label' => 'Limitless Tech Solutions, Inc.',
        'aliases' => [],
        'default_transport' => 'LEO',
    ],
    'philcomsat' => [
        'label' => 'PHILCOMSAT',
        'aliases' => [],
        'default_transport' => 'LEO',
    ],
    'revlv' => [
        'label' => 'Revlv Solutions, Inc.',
        'aliases' => [],
        'default_transport' => 'LEO',
    ],
    'smartlink' => [
        'label' => 'Smartlink Network Solutions',
        'aliases' => [],
        'default_transport' => 'RADIO',
    ],
    'we-are-it' => [
        'label' => 'We Are IT Philippines Inc.',
        'aliases' => [],
        'default_transport' => 'LEO',
    ],
];
