<?php

namespace App\Http\Requests;

use Orion\Http\Requests\Request;

class UserRequest extends Request
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
        // Only validate for POST, PUT, PATCH methods
        if (!in_array($this->method(), ['POST', 'PUT', 'PATCH'])) {
            return [];
        }

        // Base rules for creation (POST)
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ];

        // More lenient rules for updates (PUT/PATCH)
        if ($this->isMethod('patch') || $this->isMethod('put')) {
            $userId = $this->route('user');
            $rules = [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $userId,
                'password' => 'sometimes|string|min:8',
            ];
        }

        return $rules;
    }
}