<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTripRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'traveler_name' => ['required', 'string', 'min:1', 'max:255'],
            'destination'   => ['required', 'string', 'min:1', 'max:255'],
            'start_date'    => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date'      => ['required', 'date_format:Y-m-d', 'after:start_date'],
            'cost'          => ['required', 'numeric', 'min:0', 'max:99999.99', 'decimal:0,2'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * Adds a custom closure rule to enforce the 364-day maximum trip duration.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $startDate = $this->input('start_date');
            $endDate   = $this->input('end_date');

            // Only run the duration check if both dates passed earlier validation
            if ($startDate && $endDate && ! $validator->errors()->has('start_date') && ! $validator->errors()->has('end_date')) {
                $start = \Carbon\Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
                $end   = \Carbon\Carbon::createFromFormat('Y-m-d', $endDate)->startOfDay();

                // Duration is the number of days between start and end (exclusive of start)
                // Jan 1 → Dec 31 = 364 days (accepted); Jan 1 → Jan 1 next year = 365 days (rejected)
                if ($start->diffInDays($end) > 364) {
                    $validator->errors()->add('end_date', 'The end date must be within 364 days of the start date.');
                }
            }
        });
    }
}
