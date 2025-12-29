<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Itinerary Request Validation
 * 
 * Validates request for POST /api/v1/ai/itineraries endpoint.
 * 
 * Source: docs/ai/implementation/PHASE_1_PROPOSAL.md Section 2
 */
class ItineraryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by middleware (OptionalAuth)
        // Plan enforcement (402) is handled in controller
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
            // Destination (required)
            'destination' => 'required|array',
            'destination.country_code' => 'required|string|size:2|uppercase',
            'destination.region_slug' => 'nullable|string|max:100',
            'destination.city_slug' => 'nullable|string|max:100',
            
            // Duration (required, enum: 3|7|14)
            'duration_days' => 'required|integer|in:3,7,14',
            
            // Start date (optional)
            'start_date' => 'nullable|date|after_or_equal:today',
            
            // Travel party (optional)
            'travel_party' => 'nullable|array',
            'travel_party.adults' => 'nullable|integer|min:1|max:20',
            'travel_party.children' => 'nullable|integer|min:0|max:20',
            
            // Preferences (optional)
            'preferences' => 'nullable|array',
            'preferences.pace' => 'nullable|string|in:relaxed,balanced,packed',
            'preferences.interests' => 'nullable|array|max:10',
            'preferences.interests.*' => 'string|in:swimming,snorkeling,family,quiet,nightlife,nature,diving,surfing,kayaking,hiking',
            'preferences.must_have_facilities' => 'nullable|array|max:10',
            'preferences.must_have_facilities.*' => 'string|max:50',
            'preferences.surface_types' => 'nullable|array|max:10',
            'preferences.surface_types.*' => 'string|in:sand,pebble,rock,concrete,grass',
            'preferences.accessibility' => 'nullable|array|max:10',
            'preferences.accessibility.*' => 'string|in:wheelchair_accessible,ramp_available,accessible_parking,accessible_toilets',
            
            // Constraints (optional)
            'constraints' => 'nullable|array',
            'constraints.max_travel_km_per_day' => 'nullable|integer|min:10|max:500',
            'constraints.avoid_ferries' => 'nullable|boolean',
            
            // Locale (optional)
            'locale' => 'nullable|string|size:2|in:en,hr,sl,de,it,fr,es,pt,nl,pl,cs,sk,hu,ro',
            
            // Session ID (optional, for continuity with chat)
            'session_id' => 'nullable|uuid',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'destination.required' => 'Destination is required',
            'destination.country_code.required' => 'Country code is required',
            'destination.country_code.size' => 'Country code must be 2 characters (ISO 3166-1 alpha-2)',
            'duration_days.required' => 'Duration is required',
            'duration_days.in' => 'Duration must be 3, 7, or 14 days',
            'start_date.after_or_equal' => 'Start date must be today or in the future',
            'preferences.interests.max' => 'Maximum 10 interests allowed',
            'preferences.must_have_facilities.max' => 'Maximum 10 facilities allowed',
            'preferences.surface_types.max' => 'Maximum 10 surface types allowed',
            'preferences.accessibility.max' => 'Maximum 10 accessibility features allowed',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'destination.country_code' => 'country code',
            'destination.region_slug' => 'region',
            'destination.city_slug' => 'city',
            'duration_days' => 'duration',
            'travel_party.adults' => 'number of adults',
            'travel_party.children' => 'number of children',
            'preferences.pace' => 'pace',
            'preferences.interests' => 'interests',
            'preferences.must_have_facilities' => 'facilities',
            'preferences.surface_types' => 'surface types',
            'preferences.accessibility' => 'accessibility features',
            'constraints.max_travel_km_per_day' => 'maximum travel distance',
            'constraints.avoid_ferries' => 'avoid ferries',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set defaults for optional fields
        $this->merge([
            'travel_party' => array_merge([
                'adults' => 2,
                'children' => 0,
            ], $this->input('travel_party', [])),
            
            'preferences' => array_merge([
                'pace' => 'balanced',
            ], $this->input('preferences', [])),
            
            'constraints' => array_merge([
                'max_travel_km_per_day' => 80,
                'avoid_ferries' => false,
            ], $this->input('constraints', [])),
        ]);
    }
}

