<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared validation for previewing and joining a room by its code. The code is
 * normalised to uppercase and trimmed before the rules run.
 */
class RoomCodeRequest extends FormRequest {

    public function authorize(): bool {
        return auth()->check();
    }

    protected function prepareForValidation(): void {
        $this->merge([
            'room_code' => strtoupper(trim((string) $this->room_code)),
        ]);
    }

    public function rules(): array {
        return [
            'room_code' => ['required', 'string', 'min:4', 'max:12', 'exists:quiz_rooms,room_code'],
        ];
    }

    public function messages(): array {
        return [
            'room_code.required' => 'Please enter a room code.',
            'room_code.exists'   => 'No room was found with that code.',
        ];
    }
}
