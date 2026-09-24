<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Property-based feature tests for the Trips API.
 *
 * Uses giorgiosironi/eris with 100 iterations per property. Each property
 * maps to a correctness property defined in design.md.
 *
 * Test database: SQLite in-memory via phpunit.xml env vars:
 *   DB_CONNECTION=sqlite, DB_DATABASE=:memory:
 */
class TripPropertyTest extends TestCase
{
    use RefreshDatabase;
    use TestTrait;

    // -------------------------------------------------------------------------
    // Helpers — date/payload generation
    // -------------------------------------------------------------------------

    /**
     * Return a Y-m-d string for today + $daysOffset days.
     */
    private function dateOffset(int $daysOffset): string
    {
        return Carbon::today()->addDays($daysOffset)->toDateString();
    }

    /**
     * Build a minimal valid payload from concrete values.
     *
     * @param string $travelerName   1–255 chars
     * @param string $destination    1–255 chars
     * @param int    $startOffset    days from today (≥ 0)
     * @param int    $duration       days after start_date (1–364)
     * @param int    $costCents      0–9_999_999 (cents, so ÷100 gives ≤99999.99)
     */
    private function buildPayload(
        string $travelerName,
        string $destination,
        int $startOffset,
        int $duration,
        int $costCents
    ): array {
        $start = Carbon::today()->addDays($startOffset);
        $end   = $start->copy()->addDays($duration);
        $cost  = number_format($costCents / 100, 2, '.', '');

        return [
            'traveler_name' => $travelerName,
            'destination'   => $destination,
            'start_date'    => $start->toDateString(),
            'end_date'      => $end->toDateString(),
            'cost'          => $cost,
        ];
    }

    // -------------------------------------------------------------------------
    // Property 1 — Trip creation round-trip
    // @group Feature:trips-api, Property 1
    // Validates: Requirements 1.1, 1.8, 3.2, 4.2
    // -------------------------------------------------------------------------

    /**
     * For any valid trip payload, POST /api/trips returns 201 and the response
     * body contains all submitted field values unchanged.
     *
     * **Validates: Requirements 1.1, 1.8, 3.2, 4.2**
     */
    public function test_property_1_trip_creation_round_trip(): void
    {
        $this->limitTo(100);

        $this->forAll(
            Generators::choose(1, 50),   // name length
            Generators::choose(1, 50),   // destination length
            Generators::choose(0, 365),  // start offset (days from today)
            Generators::choose(1, 364),  // duration (days, 1..364)
            Generators::choose(0, 9_999_999) // cost in cents (0..99999.99)
        )->then(function (int $nameLen, int $destLen, int $startOffset, int $duration, int $costCents) {
            // Build deterministic but varied strings from the lengths
            $travelerName = str_repeat('A', $nameLen);
            $destination  = str_repeat('B', $destLen);

            $payload = $this->buildPayload($travelerName, $destination, $startOffset, $duration, $costCents);

            $response = $this->postJson('/api/trips', $payload);

            $response->assertStatus(201);

            $data = $response->json();

            $this->assertArrayHasKey('id', $data, 'Response must contain id');
            $this->assertArrayHasKey('created_at', $data, 'Response must contain created_at');
            $this->assertArrayHasKey('updated_at', $data, 'Response must contain updated_at');

            $this->assertSame($payload['traveler_name'], $data['traveler_name']);
            $this->assertSame($payload['destination'], $data['destination']);
            $this->assertSame($payload['start_date'], $data['start_date']);
            $this->assertSame($payload['end_date'], $data['end_date']);
            $this->assertSame($payload['cost'], $data['cost']);
        });
    }

    // -------------------------------------------------------------------------
    // Property 2 — Missing required fields always produce 422
    // @group Feature:trips-api, Property 2
    // Validates: Requirements 1.2, 1.3, 3.4
    // -------------------------------------------------------------------------

    /**
     * For any non-empty subset of the five required fields omitted from the
     * request, the API returns 422 with those field names in `errors`.
     *
     * **Validates: Requirements 1.2, 1.3, 3.4**
     */
    public function test_property_2_missing_required_fields_always_produce_422(): void
    {
        $this->limitTo(100);

        $requiredFields = ['traveler_name', 'destination', 'start_date', 'end_date', 'cost'];

        // Generate a non-empty subset using the SubsetGenerator then filter out empty results
        $this->forAll(
            Generators::suchThat(
                fn (array $s) => count($s) > 0,
                Generators::subset($requiredFields)
            )
        )->then(function (array $missingFields) {
            $fullPayload = $this->buildPayload('Alice', 'Paris', 0, 7, 15000);

            foreach ($missingFields as $field) {
                unset($fullPayload[$field]);
            }

            $response = $this->postJson('/api/trips', $fullPayload);

            $response->assertStatus(422);

            $errors = $response->json('errors');
            $this->assertIsArray($errors, 'Response must contain an errors object');

            foreach ($missingFields as $field) {
                $this->assertArrayHasKey(
                    $field,
                    $errors,
                    "Missing field '{$field}' should appear in errors"
                );
            }
        });
    }

    // -------------------------------------------------------------------------
    // Property 3 — Past start dates are always rejected
    // @group Feature:trips-api, Property 3
    // Validates: Requirements 1.4
    // -------------------------------------------------------------------------

    /**
     * For any date strictly before today supplied as start_date, the API returns
     * 422 with a validation error on start_date.
     *
     * **Validates: Requirements 1.4**
     */
    public function test_property_3_past_start_dates_are_always_rejected(): void
    {
        $this->limitTo(100);

        // Generate an offset of 1..3650 days in the past
        $this->forAll(
            Generators::choose(1, 3650)
        )->then(function (int $daysInPast) {
            $pastDate = Carbon::today()->subDays($daysInPast)->toDateString();

            $payload = [
                'traveler_name' => 'Alice',
                'destination'   => 'Paris',
                'start_date'    => $pastDate,
                'end_date'      => Carbon::today()->addDays(7)->toDateString(),
                'cost'          => '100.00',
            ];

            $response = $this->postJson('/api/trips', $payload);

            $response->assertStatus(422);
            $this->assertArrayHasKey(
                'start_date',
                $response->json('errors') ?? [],
                "start_date '{$pastDate}' should produce a validation error"
            );
        });
    }

    // -------------------------------------------------------------------------
    // Property 4 — End date not after start date is always rejected
    // @group Feature:trips-api, Property 4
    // Validates: Requirements 1.5
    // -------------------------------------------------------------------------

    /**
     * For any pair of dates where end_date ≤ start_date, the API returns 422
     * with a validation error on end_date.
     *
     * **Validates: Requirements 1.5**
     */
    public function test_property_4_end_date_not_after_start_date_is_always_rejected(): void
    {
        $this->limitTo(100);

        // start offset: 0..365 days from today
        // end goes back 0..30 days from start (so end_date ≤ start_date)
        $this->forAll(
            Generators::choose(0, 365),
            Generators::choose(0, 30)
        )->then(function (int $startOffset, int $endBehind) {
            $startDate = Carbon::today()->addDays($startOffset)->toDateString();
            $endDate   = Carbon::today()->addDays($startOffset)->subDays($endBehind)->toDateString();

            $payload = [
                'traveler_name' => 'Alice',
                'destination'   => 'Paris',
                'start_date'    => $startDate,
                'end_date'      => $endDate,
                'cost'          => '100.00',
            ];

            $response = $this->postJson('/api/trips', $payload);

            $response->assertStatus(422);
            $errors = $response->json('errors') ?? [];
            $this->assertTrue(
                isset($errors['end_date']) || isset($errors['start_date']),
                "end_date '{$endDate}' ≤ start_date '{$startDate}' should produce a validation error"
            );
        });
    }

    // -------------------------------------------------------------------------
    // Property 5 — Duration > 364 days is always rejected
    // @group Feature:trips-api, Property 5
    // Validates: Requirements 1.6
    // -------------------------------------------------------------------------

    /**
     * For any future start_date with an end_date ≥ 365 days later, the API
     * returns 422 with a validation error on end_date.
     *
     * **Validates: Requirements 1.6**
     */
    public function test_property_5_duration_over_364_days_is_always_rejected(): void
    {
        $this->limitTo(100);

        // start offset: 0..365 days from today; extra: 0..3650 added on top of 365
        $this->forAll(
            Generators::choose(0, 365),
            Generators::choose(0, 3650)
        )->then(function (int $startOffset, int $extra) {
            $duration  = 365 + $extra; // always ≥ 365
            $startDate = Carbon::today()->addDays($startOffset)->toDateString();
            $endDate   = Carbon::today()->addDays($startOffset + $duration)->toDateString();

            $payload = [
                'traveler_name' => 'Alice',
                'destination'   => 'Paris',
                'start_date'    => $startDate,
                'end_date'      => $endDate,
                'cost'          => '100.00',
            ];

            $response = $this->postJson('/api/trips', $payload);

            $response->assertStatus(422);
            $this->assertArrayHasKey(
                'end_date',
                $response->json('errors') ?? [],
                "Duration of {$duration} days should produce a validation error on end_date"
            );
        });
    }

    // -------------------------------------------------------------------------
    // Property 6 — Negative cost is always rejected
    // @group Feature:trips-api, Property 6
    // Validates: Requirements 1.7
    // -------------------------------------------------------------------------

    /**
     * For any negative numeric value supplied as cost, the API returns 422
     * with a validation error on cost.
     *
     * **Validates: Requirements 1.7**
     */
    public function test_property_6_negative_cost_is_always_rejected(): void
    {
        $this->limitTo(100);

        // Generate a positive integer for cents (1..9_999_999) then negate it
        $this->forAll(
            Generators::choose(1, 9_999_999)
        )->then(function (int $positiveCents) {
            $negativeCost = '-' . number_format($positiveCents / 100, 2, '.', '');

            $payload = [
                'traveler_name' => 'Alice',
                'destination'   => 'Paris',
                'start_date'    => Carbon::today()->toDateString(),
                'end_date'      => Carbon::today()->addDays(7)->toDateString(),
                'cost'          => $negativeCost,
            ];

            $response = $this->postJson('/api/trips', $payload);

            $response->assertStatus(422);
            $this->assertArrayHasKey(
                'cost',
                $response->json('errors') ?? [],
                "Negative cost '{$negativeCost}' should produce a validation error"
            );
        });
    }

    // -------------------------------------------------------------------------
    // Property 7 — List response ordering invariant
    // @group Feature:trips-api, Property 7
    // Validates: Requirements 2.1
    // -------------------------------------------------------------------------

    /**
     * For any N ≥ 2 trips inserted in any order, GET /api/trips returns a JSON
     * array whose id values are strictly ascending.
     *
     * **Validates: Requirements 2.1**
     */
    public function test_property_7_list_response_ordering_invariant(): void
    {
        $this->limitTo(100);

        // N: 2..10 trips to insert
        $this->forAll(
            Generators::choose(2, 10)
        )->then(function (int $n) {
            // Truncate between iterations so each run sees exactly N trips
            DB::table('trips')->delete();

            // Insert N trips with randomised but valid data
            for ($i = 0; $i < $n; $i++) {
                $payload = $this->buildPayload(
                    'Traveler' . $i,
                    'Dest' . $i,
                    $i % 30,        // spread start offsets
                    1 + ($i % 100), // durations 1..100
                    ($i + 1) * 100  // costs 1.00..n.00
                );
                $this->postJson('/api/trips', $payload)->assertStatus(201);
            }

            $response = $this->getJson('/api/trips');
            $response->assertStatus(200);

            $trips = $response->json();
            $this->assertCount($n, $trips, "Expected {$n} trips in response");

            $ids = array_column($trips, 'id');
            for ($i = 1; $i < count($ids); $i++) {
                $this->assertGreaterThan(
                    $ids[$i - 1],
                    $ids[$i],
                    "Trip IDs should be strictly ascending"
                );
            }
        });
    }

    // -------------------------------------------------------------------------
    // Property 8 — List response shape and field completeness
    // @group Feature:trips-api, Property 8
    // Validates: Requirements 2.3, 3.3
    // -------------------------------------------------------------------------

    /**
     * For any valid trip that has been created, every item in GET /api/trips
     * contains the 8 required fields with correct formats.
     *
     * **Validates: Requirements 2.3, 3.3**
     */
    public function test_property_8_list_response_shape_and_field_completeness(): void
    {
        $this->limitTo(100);

        $this->forAll(
            Generators::choose(1, 40),  // name length
            Generators::choose(1, 40),  // destination length
            Generators::choose(0, 300), // start offset
            Generators::choose(1, 364), // duration
            Generators::choose(0, 9_999_999) // cost in cents
        )->then(function (int $nameLen, int $destLen, int $startOffset, int $duration, int $costCents) {
            $payload = $this->buildPayload(
                str_repeat('N', $nameLen),
                str_repeat('D', $destLen),
                $startOffset,
                $duration,
                $costCents
            );

            $this->postJson('/api/trips', $payload)->assertStatus(201);

            $response = $this->getJson('/api/trips');
            $response->assertStatus(200);

            $trips = $response->json();
            $this->assertIsArray($trips);
            $this->assertNotEmpty($trips);

            $requiredFields = ['id', 'traveler_name', 'destination', 'start_date', 'end_date', 'cost', 'created_at', 'updated_at'];

            foreach ($trips as $index => $trip) {
                foreach ($requiredFields as $field) {
                    $this->assertArrayHasKey($field, $trip, "Trip[{$index}] is missing field '{$field}'");
                }

                // start_date and end_date must be YYYY-MM-DD
                $this->assertMatchesRegularExpression(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $trip['start_date'],
                    "start_date must be in YYYY-MM-DD format"
                );
                $this->assertMatchesRegularExpression(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $trip['end_date'],
                    "end_date must be in YYYY-MM-DD format"
                );

                // cost must be a decimal string with exactly 2 decimal places
                $this->assertMatchesRegularExpression(
                    '/^\d+\.\d{2}$/',
                    (string) $trip['cost'],
                    "cost must be a decimal string with exactly 2 decimal places, got: " . $trip['cost']
                );
            }
        });
    }

    // -------------------------------------------------------------------------
    // Property 9 — All created trips appear in list response
    // @group Feature:trips-api, Property 9
    // Validates: Requirements 2.1, 4.4
    // -------------------------------------------------------------------------

    /**
     * For any N (1–20) trips created via POST /api/trips, GET /api/trips returns
     * exactly N trips and all their IDs are present.
     *
     * **Validates: Requirements 2.1, 4.4**
     */
    public function test_property_9_all_created_trips_appear_in_list_response(): void
    {
        $this->limitTo(100);

        $this->forAll(
            Generators::choose(1, 20)
        )->then(function (int $n) {
            // Truncate between iterations so each run sees exactly N trips
            DB::table('trips')->delete();

            $createdIds = [];

            for ($i = 0; $i < $n; $i++) {
                $payload  = $this->buildPayload('User' . $i, 'City' . $i, $i % 20, 1 + ($i % 50), ($i + 1) * 50);
                $response = $this->postJson('/api/trips', $payload);
                $response->assertStatus(201);
                $createdIds[] = $response->json('id');
            }

            $listResponse = $this->getJson('/api/trips');
            $listResponse->assertStatus(200);

            $trips = $listResponse->json();
            $this->assertCount($n, $trips, "Expected {$n} trips in list, got " . count($trips));

            $returnedIds = array_column($trips, 'id');
            foreach ($createdIds as $id) {
                $this->assertContains($id, $returnedIds, "Trip ID {$id} should be present in the list");
            }
        });
    }
}
