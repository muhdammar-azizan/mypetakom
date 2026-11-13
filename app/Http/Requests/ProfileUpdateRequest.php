<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Students can only edit contact details; name, email and programme
     * come from the faculty record. Staff can edit their own identity fields.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'phone' => ['nullable', 'string', 'max:20'],
        ];

        if ($this->user()->isStudent()) {
            return $rules + [
                'emergency_name' => ['nullable', 'string', 'max:255'],
                'emergency_phone' => ['nullable', 'string', 'max:20'],
                'address' => ['nullable', 'string', 'max:500'],
                'alt_email' => ['nullable', 'email', 'max:255'],
            ];
        }

        return $rules + [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'programme' => ['nullable', 'string', 'max:255'],
        ];
    }
}
