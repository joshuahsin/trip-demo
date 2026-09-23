# Implementation Plan: Trips API

## Overview

Implement a REST API for managing travel trips in a Laravel 13 application. The plan proceeds in layers: database migration → Eloquent model → form request validator → controller → route registration → example-based tests → property-based tests. Each step integrates immediately so no code is left orphaned.

## Tasks

- [ ] 1. Add giorgiosironi/eris dev dependency and create the trips migration
  - [x] 1.1 Require `giorgiosironi/eris` as a dev dependency
    - Run `composer require --dev giorgiosironi/eris` (or manually add `"giorgiosironi/eris": "^0.11"` to `require-dev` in `composer.json` and run `composer update`)
    - Verify the package appears in `vendor/`
    - _Requirements: Testing Strategy (design.md)_
  - [ ] 1.2 Create the `create_trips_table` migration
    - Create `database/migrations/{timestamp}_create_trips_table.php`
    - Add columns: `id` (bigIncrements), `traveler_name` (string 255), `destination` (string 255), `start_date` (date), `end_date` (date), `cost` (decimal 12,2 unsigned), `timestamps()`
    - _Requirements: 4.1_

- [ ] 2. Implement the Trip Eloquent model
  - [ ] 2.1 Create `app/Models/Trip.php`
    - Extend `Illuminate\Database\Eloquent\Model`
    - Set `$fillable` to `['traveler_name', 'destination', 'start_date', 'end_date', 'cost']`
    - Add `$casts`: `start_date → date:Y-m-d`, `end_date → date:Y-m-d`, `cost → decimal:2`
    - _Requirements: 1.8, 4.1, 4.2_

- [ ] 3. Implement the StoreTripRequest form request
  - [ ] 3.1 Create `app/Http/Requests/StoreTripRequest.php`
    - Extend `Illuminate\Foundation\Http\FormRequest`; `authorize()` returns `true`
    - Add validation rules for all five fields per the design: `traveler_name` (required, string, min:1, max:255), `destination` (required, string, min:1, max:255), `start_date` (required, date_format:Y-m-d, after_or_equal:today), `end_date` (required, date_format:Y-m-d, after:start_date), `cost` (required, numeric, min:0, max:99999.99, decimal:0,2)
    - Add custom `after` closure on `end_date` to reject durations > 364 days
    - _Requirements: 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8_

- [ ] 4. Implement TripController
  - [ ] 4.1 Create `app/Http/Controllers/TripController.php`
    - Add `index()` method: fetch `Trip::orderBy('id')->get()`, return 200 JSON; catch `QueryException` → 500 JSON with generic message
    - Add `store(StoreTripRequest $request)` method: call `Trip::create($request->validated())`, return 201 JSON; catch `QueryException` → 503 JSON; catch `\Throwable` → 500 JSON
    - Log errors server-side using `Log::error()` before returning error responses
    - _Requirements: 1.1, 1.9, 2.1, 2.2, 2.4, 3.1, 3.2, 3.3, 4.2, 4.3_

- [ ] 5. Register API routes
  - [ ] 5.1 Create `routes/api.php` with GET and POST `/trips` routes pointing to `TripController`
    - Define `Route::get('/trips', [TripController::class, 'index'])` and `Route::post('/trips', [TripController::class, 'store'])`
    - _Requirements: 1.1, 2.1_
  - [ ] 5.2 Update `bootstrap/app.php` to register `routes/api.php` with the `api` middleware group
    - Add `api: __DIR__.'/../routes/api.php'` to the `withRouting()` call
    - _Requirements: 1.1, 2.1_

- [ ] 6. Checkpoint — run migrations and smoke-test routing
  - Run `php artisan migrate` and verify the `trips` table is created with correct columns.
  - Run `php artisan route:list` and confirm `/api/trips` appears for both GET and POST.
  - Ensure all tests pass so far; ask the user if questions arise.

- [ ] 7. Write example-based feature tests
  - [ ] 7.1 Create `tests/Feature/TripTest.php` with the `RefreshDatabase` trait (SQLite in-memory)
    - Add test: empty table returns `[]` with HTTP 200 — _Requirements: 2.2, 3.3_
    - Add test: valid POST creates trip and returns 201 with all submitted fields — _Requirements: 1.1, 3.2_
    - Add test: missing required field returns 422 with that field in `errors` — _Requirements: 1.2, 1.3, 3.4_
    - Add test: `start_date` in the past returns 422 — _Requirements: 1.4_
    - Add test: `end_date` not after `start_date` returns 422 — _Requirements: 1.5_
    - Add test: duration > 364 days returns 422 — _Requirements: 1.6_
    - Add test: negative cost returns 422 — _Requirements: 1.7_
    - Add test: both endpoints return `Content-Type: application/json` — _Requirements: 3.1_
    - Add test: DB unavailable on POST returns 503 (mock `Trip::create` to throw `QueryException`) — _Requirements: 1.9, 4.3_
    - Add test: DB unavailable on GET returns 500 — _Requirements: 2.4_
    - Add test: migration creates correct columns via `Schema::hasColumn` assertions — _Requirements: 4.1_
    - _Requirements: 1.1–1.9, 2.2, 2.4, 3.1, 3.2, 3.4, 4.1, 4.3_

- [ ] 8. Write property-based feature tests
  - [ ] 8.1 Create `tests/Feature/TripPropertyTest.php` using giorgiosironi/eris generators with 100 iterations each
    - Configure `RefreshDatabase` (SQLite in-memory) and set up eris `ForAll` scaffolding
    - _Requirements: Testing Strategy (design.md)_
  - [ ]* 8.2 Write property test for Property 1 — Trip creation round-trip
    - Generate arbitrary valid payloads; POST each; assert 201 and all field values present unchanged
    - **Property 1: Trip creation round-trip**
    - **Validates: Requirements 1.1, 1.8, 3.2, 4.2**
  - [ ]* 8.3 Write property test for Property 2 — Missing required fields always produce 422
    - Generate non-empty subsets of the five required field names; omit them; assert 422 with those keys in `errors`
    - **Property 2: Missing required fields always produce 422 with field errors**
    - **Validates: Requirements 1.2, 1.3, 3.4**
  - [ ]* 8.4 Write property test for Property 3 — Past start dates are always rejected
    - Generate dates strictly before today; assert 422 with error on `start_date`
    - **Property 3: Past start dates are always rejected**
    - **Validates: Requirements 1.4**
  - [ ]* 8.5 Write property test for Property 4 — End date not after start date is always rejected
    - Generate date pairs where `end_date ≤ start_date`; assert 422 with error on `end_date`
    - **Property 4: End date not after start date is always rejected**
    - **Validates: Requirements 1.5**
  - [ ]* 8.6 Write property test for Property 5 — Duration > 364 days is always rejected
    - Generate future `start_date` and `end_date` at least 365 days later; assert 422 with error on `end_date`
    - **Property 5: Trip duration exceeding 364 days is always rejected**
    - **Validates: Requirements 1.6**
  - [ ]* 8.7 Write property test for Property 6 — Negative cost is always rejected
    - Generate negative float values for `cost`; assert 422 with error on `cost`
    - **Property 6: Negative cost is always rejected**
    - **Validates: Requirements 1.7**
  - [ ]* 8.8 Write property test for Property 7 — List response ordering invariant
    - Insert N ≥ 2 trips in random order; GET /api/trips; assert `id` values are strictly ascending
    - **Property 7: List response ordering invariant**
    - **Validates: Requirements 2.1**
  - [ ]* 8.9 Write property test for Property 8 — List response shape and field completeness
    - Generate any valid trip; create it; GET /api/trips; assert each item has exactly the 8 required fields with correct formats
    - **Property 8: List response shape and field completeness**
    - **Validates: Requirements 2.3, 3.3**
  - [ ]* 8.10 Write property test for Property 9 — All created trips appear in list response
    - Create N (1–20) trips sequentially; GET /api/trips; assert array length equals N and all IDs present
    - **Property 9: All created trips appear in list response**
    - **Validates: Requirements 2.1, 4.4**

- [ ] 9. Final checkpoint — Ensure all tests pass
  - Run `php artisan test` and confirm the full suite (example-based + property-based) is green.
  - Ensure all tests pass; ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP
- The `phpunit.xml` environment variables `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` must be set for test isolation; verify they exist before running tests
- Requirement 4.3 mentions HTTP 500 for DB write failures, but the design specifies 503 for `POST /api/trips` `QueryException` — tasks follow the design; the discrepancy is noted
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation before moving to the next layer

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["2.1"] },
    { "id": 2, "tasks": ["3.1"] },
    { "id": 3, "tasks": ["4.1"] },
    { "id": 4, "tasks": ["5.1", "5.2"] },
    { "id": 5, "tasks": ["7.1", "8.1"] },
    { "id": 6, "tasks": ["8.2", "8.3", "8.4", "8.5", "8.6", "8.7", "8.8", "8.9", "8.10"] }
  ]
}
```
