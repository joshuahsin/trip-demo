<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * DB-unavailability tests for the Trips API.
 *
 * Uses DatabaseMigrations (full migrate:fresh per test) instead of
 * RefreshDatabase so that Schema::rename() — which issues a DDL statement —
 * does not break the transaction-based teardown used by RefreshDatabase with
 * SQLite :memory: databases.
 *
 * Requirements: 1.9, 2.4, 4.3
 */
class TripDbErrorTest extends TestCase
{
    use DatabaseMigrations;

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
    // Requirements 1.9, 4.3 — DB unavailable on POST returns 503
    // -------------------------------------------------------------------------

    public function test_db_unavailable_on_post_returns_503(): void
    {
        // Rename the trips table so the INSERT finds no target table and
        // Laravel wraps the PDO error in a QueryException → controller returns 503.
        Schema::rename('trips', 'trips_broken');

        $response = $this->postJson('/api/trips', $this->validPayload());

        $response->assertStatus(503)
                 ->assertJsonStructure(['message']);
    }

    // -------------------------------------------------------------------------
    // Requirement 2.4 — DB unavailable on GET returns 500
    // -------------------------------------------------------------------------

    public function test_db_unavailable_on_get_returns_500(): void
    {
        // Same technique: rename the table so SELECT throws a QueryException.
        Schema::rename('trips', 'trips_broken');

        $response = $this->getJson('/api/trips');

        $response->assertStatus(500)
                 ->assertJsonStructure(['message']);
    }
}
