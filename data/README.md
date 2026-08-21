# Official holidays dataset

`Storage\Official` loads `official-holidays-{jalaliYear}.json` from this
directory (e.g. `official-holidays-1404.json`). Until a file exists for a
given Jalali year, the admin calendar simply shows no official-holiday
markers for it — this has no effect on holiday resolution either way (see
`Domain\Rules\Chain`; official holidays are informational only).

## `official-holidays-1405.json` — partial, verify before relying on it

1405 (Mar 2026 – Mar 2027) is populated with **18 of the ~25-28 official
holiday days** for the year, researched via web search in August 2026.
`time.ir/event-year` — Iran's official calendar authority, and the
requested primary source — could not actually be fetched directly (it
consistently returned HTTP 503, likely bot-blocking); everything below
came from other calendar sites plus independent arithmetic cross-checks
against known Hijri month lengths, not from time.ir itself. Treat this
as a solid starting point, not a verified-against-the-authoritative-
source dataset.

Three different kinds of dates went into it, with different confidence
levels:

- **Fixed solar-calendar dates** (highest confidence — same Jalali day
  every year, doesn't depend on 1405 specifically): Nowruz (1-4
  Farvardin), Islamic Republic Day (12 Farvardin), Nature Day (13
  Farvardin), Khomeini's death anniversary (14 Khordad), the 15 Khordad
  uprising, the Islamic Revolution's anniversary (22 Bahman), Oil
  Nationalization Day (29 Esfand).
- **Lunar (Hijri) dates cross-checked by day-offset arithmetic** — each
  one's distance in days from a fixed anchor (1 Farvardin 1405 = 1
  Shawwal 1447, i.e. Eid al-Fitr) was computed independently from known
  Hijri month lengths and matched what search results reported, which
  is a real (if imperfect) consistency check: Imam Sadegh's martyrdom
  (25 Farvardin, = 25 Shawwal), Eid al-Adha (6 Khordad), Tasua/Ashura
  (3-4 Tir), Arbaeen (13 Mordad, matched exactly via the fixed 40-day
  offset from Ashura), the Prophet's birthday/Imam Sadegh's birth
  (8 Shahrivar, matched via the fixed offset from Hijra).
- **Lunar dates taken from a single search result with no independent
  check**: Imam Reza's martyrdom (22 Mordad) and Imam Hasan Askari's
  martyrdom (30 Mordad) — kept because the source's own claimed count
  ("3 non-Friday holidays in Mordad") was internally consistent with
  exactly these three dates once an obvious typo (a stray "21 Mordad"
  contradicting the same paragraph's own day list) was discounted.

**Discarded as unreliable**: two other claims — Eid al-Fitr landing in
Esfand, and Imam Ali's martyrdom on 9 Esfand 1405 — were dropped because
they directly contradict the anchor fact above (Eid al-Fitr = 1
Farvardin) and basic Hijri-date arithmetic (21 Ramadan falls in Esfand
**1404**, the prior Jalali year, not 1405). Multiple search summaries
produced this kind of contradiction, which is exactly the failure mode
this file is trying to avoid propagating.

**Still missing**: Eid al-Ghadir, Eid al-Fitr's second day, Fatima
Zahra's martyrdom, and others — no source found gave these a 1405 date
confident enough to include. Fill them in once Iran's official calendar
for 1405 is actually published (Vice Presidency for Legal Affairs,
typically a few months before Nowruz), by editing this JSON file or
writing to the `whw_official_holidays_1405` option (see
`Storage\Official::saveYear()`; there is no admin-UI edit control for
this yet, see below).

## Format

```json
{
    "1": {
        "1": "نوروز و عید سعید فطر",
        "13": "روز طبیعت (سیزده‌بدر)"
    },
    "11": {
        "22": "پیروزی انقلاب اسلامی"
    }
}
```

Keys are Jalali month numbers (1-12); values are `{day: "occasion name"}`
maps (not just a boolean/list — the admin calendar shows the name, e.g.
"۲۰ مرداد — روز جهانی مادر"). Populate from an authoritative source (e.g.
Iran's official calendar, or the Vice Presidency for Legal Affairs
gazette) and verify each date for the specific Jalali year before
shipping it, since lunar-calendar-based holidays are not fixed year to
year.

`Storage\Official::saveYear()` also lets an admin-facing import tool write
this data as a `wp_option` instead of (or as an override of) the bundled
file — the option always takes priority when present. The
`whw_official_holidays` filter is available for a future external data
provider.
