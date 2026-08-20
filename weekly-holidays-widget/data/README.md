# Official holidays dataset

`Storage\Official` loads `official-holidays-{jalaliYear}.json` from this
directory (e.g. `official-holidays-1404.json`). No file is shipped by
default — official Iranian holiday dates were deliberately **not**
hand-authored here, to avoid presenting unverified calendar facts as
correct data in a plugin (several fall on the lunar Islamic calendar and
shift every Gregorian year; getting them wrong silently is worse than
leaving them empty).

Until a file is added, the admin calendar simply shows no official-holiday
markers — this has no effect on holiday resolution either way (see
`Domain\Rules\Chain`; official holidays are informational only).

## Format

```json
{
    "1": [1, 2, 3, 4, 13],
    "11": [22],
    "12": [29]
}
```

Keys are Jalali month numbers (1-12), values are lists of day-of-month
numbers. Populate from an authoritative source (e.g. the official
calendar published by Iran's Vice Presidency for Legal Affairs) and
verify each date for the specific Jalali year before shipping it, since
lunar-calendar-based holidays are not fixed year to year.

`Storage\Official::saveYear()` also lets an admin-facing import tool write
this data as a `wp_option` instead of (or as an override of) the bundled
file — the option always takes priority when present. The
`whw_official_holidays` filter is available for a future external data
provider.
