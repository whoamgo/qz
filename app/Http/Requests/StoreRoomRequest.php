<?php

namespace App\Http\Requests;

use App\Models\Quiz;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates room creation. Beyond the basic exists() checks it enforces the
 * critical rule that the chosen quiz really belongs to the chosen category and
 * is published — the frontend is never trusted for this.
 */
class StoreRoomRequest extends FormRequest {

    public function authorize(): bool {
        return auth()->check();
    }

    public function rules(): array {
        return [
            'category_id'    => ['required', 'integer', 'exists:categories,id'],
            'quiz_id'        => ['required', 'integer', 'exists:quizzes,id'],
            'max_players'    => ['required', 'integer', 'min:2', 'max:100'],
            'room_type'      => ['nullable', 'in:public,private'],
            'question_count' => ['nullable', 'integer', 'min:1', 'max:500'],
            'time_limit'     => ['nullable', 'integer', 'min:0', 'max:180'],   // minutes; 0 = no limit
        ];
    }

    public function messages(): array {
        return [
            'category_id.required' => 'Please select a category.',
            'category_id.exists'   => 'The selected category is not available.',
            'quiz_id.required'     => 'Please select a quiz.',
            'quiz_id.exists'       => 'The selected quiz is not available.',
            'max_players.min'      => 'A room needs to allow at least 2 players.',
            'max_players.max'      => 'A room can allow at most 100 players.',
        ];
    }

    /** Server-side relationship + status check: quiz must be published AND in the category. */
    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $quiz = Quiz::find($this->quiz_id);

            if (!$quiz || $quiz->status !== Quiz::STATUS_PUBLISHED || $quiz->questions()->count() === 0) {
                $validator->errors()->add('quiz_id', 'The selected quiz is not available.');
                return;
            }

            if ((int) $quiz->category_id !== (int) $this->category_id) {
                $validator->errors()->add('quiz_id', 'The selected quiz does not belong to this category.');
                return;
            }

            // A room cannot ask for more questions than the quiz actually has.
            if ($this->filled('question_count')) {
                $bank = $quiz->questions()->count();
                if ((int) $this->question_count > $bank) {
                    $validator->errors()->add('question_count', "This quiz only has {$bank} questions.");
                }
            }
        });
    }
}
