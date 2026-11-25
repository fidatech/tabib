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

## medicines.csv

A starter list of medicines for Tabib, written for it: 328 generic medicines in 713 strengths
and forms, with the class used to match allergies (penicillin, cephalosporin, nsaid, sulfonamide ...),
whether it is a controlled medicine, the milligrams in one unit (one tablet, one ml, ...) where
that is a single number, and common brand names in Pakistan (PK), India (IN), Turkey (TR) and the
United States (US). Brand names are trademarks of their owners and are listed only so staff can
find the generic medicine. Only brands we were sure of are listed.

**To be reviewed by a pharmacist before use.** Dose limits are not in this file on purpose: each
clinic writes the limits its own pharmacist or doctors approve (Clinic Setup > Medicines).

The file is only the start: `php artisan medicines:import` loads it into the platform list in the
central database (new codes only; `--refresh` writes the file over the rows). From then on the list
is kept in the central panel (Clinical lists > Medicines): every change there goes to every clinic
at once, and a medicine taken off it can no longer be prescribed anywhere. Clinics cannot change
the listed medicines; they write their dose limits, switch off what they do not use and add their
own. A clinic also gets the list when it is opened and after an update (`php artisan tenants:migrate`).

Columns: code, name, strength, form, route, class, mg_per_unit, controlled (1/0), brands
("PK: A, B | IN: C | TR: D | US: E").
