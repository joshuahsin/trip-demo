# Requirements Document

## Introduction

This feature adds a REST API to a Laravel application for managing travel trips. The API exposes two endpoints: one to create a trip and one to list all trips. Each trip captures the traveler's name, destination, travel dates, and cost. The API enforces data integrity rules — trip dates must be in the future and end dates must follow start dates, and cost must be non-negative.

## Glossary

- **Trip**: A travel record containing a traveler name, destination, start date, end date, and cost.
- **API**: The HTTP REST interface exposed by the Laravel application.
- **Trips_Controller**: The Laravel controller responsible for handling trip-related HTTP requests.
- **Trip_Model**: The Eloquent model representing a trip record in the database.
- **Trip_Validator**: The Laravel validation layer that enforces data integrity rules on trip input.
- **Trips_Table**: The database table storing all trip records.

## Requirements

### Requirement 1: Create a Trip

**User Story:** As an API consumer, I want to create a trip by submitting trip details, so that the trip is persisted and I receive confirmation with the created record.

#### Acceptance Criteria

1. WHEN a POST request is made to `/api/trips` with valid trip data, THE Trips_Controller SHALL persist the trip to the Trips_Table and return the created Trip resource with HTTP status 201.
2. WHEN a POST request is made to `/api/trips`, THE Trip_Validator SHALL require `traveler_name`, `destination`, `start_date`, `end_date`, and `cost` fields to be present in the request body.
3. IF any required field is missing or invalid, THEN THE Trip_Validator SHALL return an HTTP 422 response with a JSON body containing field-level validation error messages.
4. IF the `start_date` value is a date strictly before the current calendar date, THEN THE Trip_Validator SHALL reject the request with a validation error on the `start_date` field.
5. IF the `end_date` value is not strictly after the `start_date` value, THEN THE Trip_Validator SHALL reject the request with a validation error on the `end_date` field.
6. IF the trip duration exceeds 364 days, THEN THE Trip_Validator shall reject the request with HTTP 442 and a field error on the `end_date` field. A Jan 1 to Jan 2 Trip will be considered a one day trip.
7. IF the `cost` value is less than 0, THEN THE Trip_Validator SHALL reject the request with a validation error on the `cost` field.
8. THE Trip_Model SHALL store `traveler_name` as a string of 1 to 255 characters, `destination` as a string of 1 to 255 characters, `start_date` as a date in `YYYY-MM-DD` format, `end_date` as a date in `YYYY-MM-DD` format, and `cost` as a non-negative decimal number with up to 2 decimal places and a maximum value of 99999.99.
9. IF a POST request is made to `/api/trips` and the Trips_Table is unavailable, THEN THE Trips_Controller SHALL return an HTTP 503 response with an error message indicating the trip could not be persisted.

### Requirement 2: List All Trips

**User Story:** As an API consumer, I want to retrieve a list of all trips, so that I can view all travel records in the system.

#### Acceptance Criteria

1. WHEN a GET request is made to `/api/trips`, THE Trips_Controller SHALL return all trips from the Trips_Table ordered by `id` ascending as a JSON array with HTTP status 200.
2. WHEN the Trips_Table contains no records, THE Trips_Controller SHALL return an empty JSON array with HTTP status 200.
3. WHEN a GET request is made to `/api/trips`, THE API SHALL return each trip record with the fields: `id`, `traveler_name`, `destination`, `start_date` (ISO 8601 date string), `end_date` (ISO 8601 date string), `cost` (decimal with 2 decimal places), `created_at`, and `updated_at`.
4. IF a GET request is made to `/api/trips` and the Trips_Table is unavailable, THEN THE Trips_Controller SHALL return an HTTP 500 response with an error message.

### Requirement 3: API Response Format

**User Story:** As an API consumer, I want consistent JSON responses from the API, so that I can reliably parse and use the data in client applications.

#### Acceptance Criteria

1. THE API SHALL return all responses with the `Content-Type: application/json` header.
2. WHEN a trip is successfully created, THE Trips_Controller SHALL return the trip as a JSON object at the top level of the response body with HTTP status 201.
3. WHEN all trips are listed, THE Trips_Controller SHALL return trips as a JSON array at the top level of the response body with HTTP status 200; if the list is empty, the array SHALL be empty and not null.
4. WHEN a validation error occurs, THE Trip_Validator SHALL return an HTTP 422 response with a JSON object containing an `errors` key mapping field names to arrays of error message strings.
5. WHEN a requested trip resource does not exist, THE Trips_Controller SHALL return an HTTP 404 response with a JSON object containing a `message` key describing the not-found condition.

### Requirement 4: Data Persistence

**User Story:** As an API consumer, I want trip data to be stored in the database, so that trips created in one request are retrievable in subsequent requests.

#### Acceptance Criteria

1. THE Trips_Table SHALL contain columns for `id` (auto-incrementing primary key integer), `traveler_name` (string, max 255 characters), `destination` (string, max 255 characters), `start_date` (date), `end_date` (date), `cost` (decimal, precision 12 scale 2, range 0.00–99999.99), `created_at` (timestamp), and `updated_at` (timestamp).
2. WHEN a trip is created via the API, THE Trip_Model SHALL persist all submitted fields to the Trips_Table within the same request lifecycle.
3. IF a database write operation fails during trip creation, THEN THE Trips_Controller roll back any partial writes, log the error details server-side, and return HTTP 500 with a generic error message that does not expose database details.
4. WHEN a GET request is made to `/api/trips` after one or more trips have been created, THE Trips_Controller SHALL include all previously created trips in the response, each containing all columns defined in criterion 1.
