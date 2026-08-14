<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOfficeFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'office_id' => 'required|uuid|exists:offices,id',
            'visitor_type' => 'required|in:student,parent,faculty,alumni,visitor,others',
            'gender' => 'nullable|in:male,female,others',
            'visitor_name' => 'nullable|string|max:255',
            'student_number' => 'nullable|string|max:50',
            'contact_number' => 'nullable|string|max:50',
            'purpose_of_visit' => 'nullable|string|max:500',
            'comments' => 'nullable|string|max:2000',
            'answers' => 'required|array|min:1',
            'answers.*.question_id' => 'required|uuid|exists:office_questions,id',
            'answers.*.answer' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'office_id.required' => 'Please select an office',
            'office_id.exists' => 'Selected office does not exist',
            'visitor_type.required' => 'Please select your visitor type',
            'answers.required' => 'Please answer all the questions',
            'answers.min' => 'Please answer all the questions',
            'answers.*.answer.required' => 'Please answer each question',
            'answers.*.answer.boolean' => 'Each answer must be Yes or No',
            'answers.*.question_id.exists' => 'One of the questions is invalid',
        ];
    }
}
