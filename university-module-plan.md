# University/College Module — Schema Design Plan

Summary ng buong discussion tungkol sa pag-tweak ng iSkool (school management) system papunta sa university/college-style enrollment. Reference lang ito ng mga table-level decisions — wala pang code na ginalaw, planning stage pa lahat.

## Background / Findings

- Kasalukuyang system ay **K-12 focused** (`class` → `section`, taunang bulk promotion, walang semester/credit-unit system).
- May mga **naka-guard na "University" hooks** na sa core code (`moduleStatusCheck('University')`, mga klase tulad ng `UnSubject`, `UnAssignSubject`) pero **wala ang mismong `Modules/University/` package** — hindi ito naka-install/binili sa copy na ito. Laging `false` ang `moduleStatusCheck('University')` dahil walang `Modules/University/Providers/UniversityServiceProvider.php`.
- May ilang nullable/unused column stub (`un_faculty_id`, `un_semester_id`, atbp.) sa `graduates` at `direct_fees_settings` — reserved slots lang, walang aktibong logic.
- Walang existing per-course/per-semester registration engine — ang "enrollment" na meron ngayon ay `sm_student_promotions`: taunang bulk "move-up" ng buong class, hindi per-subject na pagpapatala.

## Design Principle

**Huwag tanggalin ang K-12 structure.** Panatilihin bilang "Basic Education" module, at ilagay ang College/University bilang parallel module na magkasalo sa common core (Auth, Fees engine, HR, Library, Communication, Front CMS, multi-tenant setup) — dahil karaniwan sa PH, may Basic Ed + College sa iisang "university."

## Final Table Plan

### Bagong table (talagang wala, kailangang gawin)

| Table | Layunin |
|---|---|
| `courses` | Ang degree program mismo (BSN, BSCS, atbp.) |
| `curriculum_versions` | Edisyon ng study plan per course (e.g. "BSN 2025 Curriculum") — dahil nagbabago ang curriculum over time, at ang mga estudyanteng naka-anchor sa lumang version ay hindi dapat maapektuhan |
| `semesters` | Generic lookup lang: 1st Sem, 2nd Sem, Summer — ginagamit sa CURRICULUM PLAN (di nakatali sa specific school year) |
| `academic_terms` | Join table ng `academic_year_id` + `semester_id` — ito ang AKTWAL na termino sa totoong panahon (e.g. "SY 2026-2027, 1st Sem") — dito tumuturo ang mga offering/enrollment |
| `subject_prerequisites` | Totoong relational na "kailangan muna ni Subject A bago si Subject B" (kasalukuyan, plain text lang ang `sm_courses.prerequisites`, walang FK) |

### Reuse / relabel lang (walang bagong table, palitan lang ang laman/gamit)

| Konsepto | Existing table | Paano gagamitin |
|---|---|---|
| Course Year (1st Yr, 2nd Yr) | `sm_classes` | Ilagay na lang "1st Year," "2nd Year" sa halip na "Grade 7" |
| Block | `sm_sections` | Ilagay na lang "Block A," "Block B" sa halip na "Section A" |
| Subject schedule pattern | `sm_class_routines` | Pwedeng gamitin/i-adapt ang pattern nito (per-day time+room) |

### Extend / dagdagan ng column (existing table, dagdag lang)

| Table | Dagdag na column | Para saan |
|---|---|---|
| `sm_subjects` | `course_id`, `curriculum_version_id`, `class_id` (Year), `semester_id`, `units`, `subject_type` (major/minor) | Ito na mismo ang **curriculum builder** — subject na naka-tag na sa course+curriculum version+year+sem+units. Kapag magkaparehong subject pero magkaibang course (hal. "Calculus 1" sa BSCS vs BSBA), gagawa ng hiwalay na row bawat isa (magkaiba ang units/type) |
| `sm_assign_subjects` | `academic_term_id`, `max_slots`, `sched_days`, `time_from`, `time_to` | Ang "course offering" layer — saang Block, anong totoong term, ilang slots, anong schedule |
| `sm_optional_subject_assigns` | `assign_subject_id` (FK papunta sa `sm_assign_subjects.id`, kapalit ng bare `subject_id`) | Ito na ang **enrolled_subjects** — dapat tumuro sa SPECIFIC na offering (block+term), hindi lang sa subject abstractly, dahil posibleng maraming block ang mag-alok ng parehong subject |
| `student_records` | `academic_term_id` (kapalit/dagdag sa `academic_id`) | Ito na ang **student_enrollments** header — kasalukuyan taunan lang, kailangang maging per-term |
| Student record (`sm_students` o extension) | `course_id`, `curriculum_version_id` | Naka-lock sa curriculum version na effective nung una siyang pumasok — hindi na magbabago kahit lumabas ng bagong version |

## Example: Isang Row ng Available Subject Listing

Format: `acad_year - sem - course+year - block - subject_code - subject_name - units - sched | slots`

```
2627-1-bsn1-b1-eng1-literature-1-mwf | 2/20
```

Mapping:
- `2627` → `sm_academic_years`
- `1` (sem) → `academic_terms.semester_id`
- `bsn` → `sm_subjects.course_id` → `courses`
- `1` (year) → `sm_subjects.class_id` → `sm_classes` ("1st Year")
- `b1` (block) → `sm_assign_subjects.section_id` → `sm_sections` ("Block 1")
- `eng1` / `literature` → `sm_subjects.subject_code` / `subject_name`
- `1` (units) → `sm_subjects.units`
- `mwf` → `sm_assign_subjects.sched_days`
- `2/20` → `sm_assign_subjects.max_slots` minus COUNT ng mga naka-enroll (`sm_optional_subject_assigns` na naka-link sa `assign_subject_id` na ito)

## Curriculum Builder Workflow

1. Gumawa ng `course` (hal. BSN) kung wala pa.
2. Gumawa ng `curriculum_versions` row para dito (hal. "2025 Curriculum").
3. Mag-add ng maraming `sm_subjects` rows sa ilalim ng version na 'yon — bawat isa may `class_id` (Year), `semester_id`, `units`, `subject_type`.
4. Ang lahat ng `sm_subjects` na may parehong `curriculum_version_id` = ang buong prospectus/curriculum ng course na 'yon.
5. Kada totoong school year+sem (`academic_terms`), gagawa ng `sm_assign_subjects` rows (offering) galing sa mga subjects ng ACTIVE curriculum_version ng bawat estudyante — dito papasok ang block/schedule/slots.
6. Bagong estudyante → naka-lock sa `curriculum_version_id` na `is_active=1` nung oras ng pagpasok — hindi na magbabago kahit lumabas ng bagong version.
7. Pag-enroll: gumagawa ng `student_records` row (na may `academic_term_id`) bilang header, tapos `sm_optional_subject_assigns` rows (na may `assign_subject_id`) bilang line items ng mga kinuhang subjects.

## Tally

- **Bagong table:** 5 (`courses`, `curriculum_versions`, `semesters`, `academic_terms`, `subject_prerequisites`)
- **Reuse/relabel lang:** 3 (`sm_classes`=Year, `sm_sections`=Block, `sm_class_routines`=schedule pattern)
- **Extend ng column lang:** 5 (`sm_subjects`, `sm_assign_subjects`, `sm_optional_subject_assigns`, `student_records`, student table)
