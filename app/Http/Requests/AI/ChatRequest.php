<?php

namespace App\Http\Requests\AI;

use App\Services\AI\Chat\PageContextHint;
use Illuminate\Foundation\Http\FormRequest;

/**
 * AI Chat Request
 *
 * Validates incoming chat requests to the AI agent.
 * See: docs/ai/architecture/api-contracts.md
 */
class ChatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by middleware (OptionalAuthMiddleware)
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
            'message' => ['required', 'string', 'max:500'],
            'session_id' => ['nullable', 'string', 'uuid'],
            'context' => ['nullable', 'array'],
            'context.location' => ['nullable', 'array'],
            'context.location.latitude' => ['required_with:context.location', 'numeric', 'min:-90', 'max:90'],
            'context.location.longitude' => ['required_with:context.location', 'numeric', 'min:-180', 'max:180'],
            'context.preferences' => ['nullable', 'array'],
            'context.preferences.max_distance_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'context.preferences.surface_types' => ['nullable', 'array'],
            'context.preferences.surface_types.*' => ['string', 'in:sand,pebble,rock,concrete'],
            'context.preferences.facilities' => ['nullable', 'array'],
            'context.preferences.facilities.*' => ['string'],
            // TASK-010: page the chat widget is on (untrusted hint, see PageContextHint)
            ...PageContextHint::rules(),
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Message is required',
            'message.max' => 'Message must not exceed 500 characters',
            'session_id.uuid' => 'Session ID must be a valid UUID',
            'context.location.latitude.required_with' => 'Latitude is required when location is provided',
            'context.location.latitude.min' => 'Latitude must be between -90 and 90',
            'context.location.latitude.max' => 'Latitude must be between -90 and 90',
            'context.location.longitude.required_with' => 'Longitude is required when location is provided',
            'context.location.longitude.min' => 'Longitude must be between -180 and 180',
            'context.location.longitude.max' => 'Longitude must be between -180 and 180',
            'context.preferences.surface_types.*.in' => 'Surface type must be one of: sand, pebble, rock, concrete',
        ];
    }
}

