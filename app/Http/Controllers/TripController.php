<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Models\Trip;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TripController extends Controller
{
    /**
     * Return all trips ordered by id ascending.
     */
    public function index(): JsonResponse
    {
        try {
            $trips = Trip::orderBy('id')->get();

            return response()->json($trips, 200);
        } catch (QueryException $e) {
            Log::error('Trip listing failed: ' . $e->getMessage());

            return response()->json(['message' => 'Unable to retrieve trips. Please try again later.'], 500);
        }
    }

    /**
     * Create a new trip from validated request data.
     */
    public function store(StoreTripRequest $request): JsonResponse
    {
        try {
            $trip = Trip::create($request->validated());

            return response()->json($trip, 201);
        } catch (QueryException $e) {
            Log::error('Trip creation failed: ' . $e->getMessage());

            return response()->json(['message' => 'The trip could not be saved. Please try again later.'], 503);
        } catch (\Throwable $e) {
            Log::error('Unexpected error during trip creation: ' . $e->getMessage());

            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }
}
