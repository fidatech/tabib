# Data shipped with Tabib

## icd10cm.csv.gz

The diagnosis codes of ICD-10-CM, April 2026 release, published by the US National Center for
Health Statistics (CDC), a work of the US government in the public domain:
https://www.cdc.gov/nchs/icd/icd-10-cm/files.html

Columns: `code` (with its dot, as doctors write it), `description` (English), `billable` (1 = a
code that can be used on its own, 0 = a heading with more detailed codes under it). Only codes of
up to five characters are kept; the 6th and 7th characters (side, episode of an injury) are left
out for a small clinic.

Loaded into the central database with `php artisan icd10:import` (also run by every deploy).
