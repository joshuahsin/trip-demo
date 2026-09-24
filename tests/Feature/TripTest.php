<?php

namespace Tests\Feature;

use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Example-based feature tests for the Trips API.
 *
 * Covers all happy-path and validation scenarios. DB-unavailability tests
 * live in TripDbErrorTest.php which uses DatabaseMigrations so that table
 * manipulation does not interfere with RefreshDatabase's transaction rollback.
 */
class TripTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function validPayload(array $overrides = []): array
    {
        $today = Carbon::today();

        return array_merge([
            'traveler_name' => 'Alice Smith',
            'destination'   => 'Paris, France',
            'start_date'    => $today->toDateString(),
            'end_date'      => $today->copy()->addDays(7)->toDateString(),
            'cost'          => '150.00',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Requirements 2.2, 3.3 — empty table returns [] with HTTP 200
    // -------------------------------------------------------------------------

    public function test_empty_table_returns_empty_array_with_200(): void
    {
        $response = $this->getJson('/api/trips');

        $response->assertStatus(200)
                 ->assertExactJson([]);
    }

    // -------------------------------------------------------------------------
    // Requirements 1.1, 3.2 — valid POST creates trip and returns 201
    // -------------------------------------------------------------------------

    public function test_valid_post_creates_trip_and_returns_201(): void
    {
        $payload  = $this->validPayload();
        $response = $this->postJson('/api/trips', $payload);

        $response->assertStatus(201);

        $response->assertJsonFragment([
            'traveler_name' => $payload['traveler_name'],
            'destination'   => $payload['destination'],
            'start_date'    => $payload['start_date'],
            'end_date'      => $payload['end_date'],
            'cost'          => $payload['cost'],
        ]);

        $this->assertDatabaseHas('trips', [
            'traveler_name' => $payload['traveler_name'],
            'destination'   => $payload['destination'],
        ]);
    }

    // -------------------------------------------------------------------------
    // Requirements 1.2, 1.3, 3.4 — missing required fields return 422
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\DataProvider('missingFieldProvider')]
    public function test_missing_required_field_returns_422(string $missingField): void
    {
        $payload = $this->validPayload();
        unset($payload[$missingField]);

        $response = $this->postJson('/api/trips', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors([$missingField]);
    }

    public static function missingFieldProvider(): array
    {
        return [
            'missing traveler_name' => ['traveler_name'],
            'missing destination'   => ['destination'],
            'missing start_date'    => ['start_date'],
            'missing end_date'      => ['end_date'],
            'missing cost'          => ['cost'],
        ];
    }

    // -------------------------------------------------------------------------
    // Requirement 1.4 — start_date in the past returns 422
    // -------------------------------------------------------------------------

    public function test_past_start_date_returns_422(): void
    {
        $payload  = $this->validPayload([
            'start_date' => Carbon::yesterday()->toDateString(),
            'end_date'   => Carbon::today()->toDateString(),
        ]);
        $response = $this->postJson('/api/trips', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['start_date']);
    }

    // -------------------------------------------------------------------------
    // Requirement 1.5 — end_date not after start_date returns 422
    // -------------------------------------------------------------------------

    public function test_end_date_same_as_start_date_returns_422(): void
    {
        $today   = Carbon::today()->toDateString();
        $payload = $this->validPayload(['start_date' => $today, 'end_date' => $today]);

        $this->postJson('/api/trips', $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['end_date']);
    }

    public function test_end_date_before_start_date_returns_422(): void
    {
        $today   = Carbon::today();
        $payload = $this->validPayload([
            'start_date' => $today->copy()->addDays(5)->toDateString(),
            'end_date'   => $today->copy()->addDays(3)->toDateString(),
        ]);

        $this->postJson('/api/trips', $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['end_date']);
    }

    // -------------------------------------------------------------------------
    // Requirement 1.6 — duration > 364 days returns 422; exactly 364 accepted
    // -------------------------------------------------------------------------

    public function test_duration_over_364_days_returns_422(): void
    {
        $start   = Carbon::today();
        $payload = $this->validPayload([
            'start_date' => $start->toDateString(),
            'end_date'   => $start->copy()->addDays(365)->toDateString(), // 365 days — rejected
        ]);

        $this->postJson('/api/trips', $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['end_date']);
    }

    public function test_duration_of_364_days_is_accepted(): void
    {
        $start   = Carbon::today();
        $payload = $this->validPayload([
            'start_date' => $start->toDateString(),
            'end_date'   => $start->copy()->addDays(364)->toDateString(), // exactly 364 — accepted
        ]);

        $this->postJson('/api/trips', $payload)->assertStatus(201);
    }

    // -------------------------------------------------------------------------
    // Requirement 1.7 — negative cost returns 422
    // -------------------------------------------------------------------------

    public function test_negative_cost_returns_422(): void
    {
        $this->postJson('/api/trips', $this->validPayload(['cost' => '-0.01']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['cost']);
    }

    // -------------------------------------------------------------------------
    // Requirement 3.1 — both endpoints return Content-Type: application/json
    // -------------------------------------------------------------------------

    public function test_get_trips_returns_json_content_type(): void
    {
        $this->getJson('/api/trips')
             ->assertStatus(200)
             ->assertHeader('Content-Type', 'application/json');
    }

    public function test_post_trips_returns_json_content_type(): void
    {
        $this->postJson('/api/trips', $this->validPayload())
             ->assertStatus(201)
             ->assertHeader('Content-Type', 'application/json');
    }

    // -------------------------------------------------------------------------
    // Requirement 4.1 — migration creates correct columns
    // -------------------------------------------------------------------------

    public function test_migration_creates_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('trips'), 'trips table should exist');

        foreach (['id', 'traveler_name', 'destination', 'start_date', 'end_date', 'cost', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('trips', $column),
                "trips table should have column: {$column}"
            );
        }
    }
}
