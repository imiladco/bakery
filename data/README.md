# Official holidays dataset

`Storage\Official` loads `official-holidays-{jalaliYear}.json` from this
directory (e.g. `official-holidays-1404.json`). Until a file exists for a
given Jalali year, the admin calendar simply shows no official-holiday
markers for it — this has no effect on holiday resolution either way (see
`Domain\Rules\Chain`; official holidays are informational only).

## `official-holidays-1405.json` — partial, verify before relying on it

1405 (Mar 2026 – Mar 2027) is populated with **13 of the ~25-28 official
holiday days** for the year, researched via web search in August 2026.
Two different kinds of dates went into it, with very different
confidence levels:

- **Fixed solar-calendar dates** — Nowruz (1-4 Farvardin), Islamic
  Republic Day (12 Farvardin), Nature Day (13 Farvardin), Khomeini's
  death anniversary (14 Khordad), the 15 Khordad uprising, the Islamic
  Revolution's anniversary (22 Bahman), Oil Nationalization Day (29
  Esfand). These fall on the same Jalali day every year — high
  confidence regardless of which specific year.
- **Lunar (Hijri) calendar dates**, which shift every Gregorian year and
  had to be converted for 1405/1447-48 AH specifically: Imam Sadegh's
  martyrdom (25 Farvardin), Eid al-Adha (6 Khordad), Tasua/Ashura
  (3-4 Tir). These came from a single fetched calendar page plus an
  independent day-offset sanity check from known Hijri month lengths —
  reasonably confident, but lunar dates in Iran's official calendar can
  still shift by a day around the actual moon sighting/government
  announcement.

**Deliberately left out**: Iran observes official holidays across nearly
every month (Mordad and Esfand are typically the next-heaviest after
Farvardin) — Eid al-Ghadir, Eid al-Fitr's second day, Arbaeen, the
Prophet's death/Imam Hassan's martyrdom, Fatima Zahra's martyrdom, Imam
Reza's martyrdom, the Prophet's birthday week, and others. Web searches
for these kept returning inconsistent or contradictory dates across
sources (including one that put Eid al-Fitr in Esfand, which is not
possible), so rather than guess, they were left out entirely. Fill them
in once Iran's official calendar for 1405 is published (Vice Presidency
for Legal Affairs, typically a few months before Nowruz) — either by
editing this JSON file or writing to the `whw_official_holidays_1405`
option (see `Storage\Official::saveYear()`; there is no admin-UI edit
control for this yet, see below).

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
