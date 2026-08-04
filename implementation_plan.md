# Snaptyx MCU — Complete Architecture Plan

## Stack
- Laravel 13 / PHP 8.3+
- Filament PHP v5 (TALL Stack)
- MySQL 8.0
- Spatie Permission + Activitylog
- Queued Jobs (Laravel Horizon / DB driver)

## Deliverable Map
| Step | Files |
|------|-------|
| 1 | 12 Migration files |
| 2 | 12 Eloquent Models |
| 3 | MedicalAiService + GenerateMedicalDraftJob |
| 4 | McuRegistrationResource (Form + Table) |
| 5 | MedicalReviewResource (Doctor view) |
