<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOfficeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('offices', 'name')->ignore($this->route('office')),
            ],
            'description' => 'nullable|string|max:1000',
            'office_head' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Office name is required',
            'name.unique' => 'An office with this name already exists',
            'name.max' => 'Office name must not exceed 255 characters',
            'description.max' => 'Description must not exceed 1000 characters',
            'office_head.max' => 'Office head must not exceed 255 characters',
            'location.max' => 'Location must not exceed 255 characters',
            'is_active.boolean' => 'Active status must be true or false',
        ];
    }
}
