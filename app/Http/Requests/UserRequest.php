<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class UserRequest extends FormRequest
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
        // Get the controller@method string
        $action = $this->route()->getActionMethod();
        Log::info("Action method: " . $action);

        // Check if a private method exists with that name
        if (method_exists($this, $action)) {
            return $this->{$action}();
        }

        // Fallback if no method found
        return [];
    }

public function messages(): array
    {
        return [
            'uname.required' => 'Username is required.',
            'uname.string' => 'Username must be a valid string.',
            'uname.max' => 'Username cannot exceed 255 characters.',
            'uname.unique' => 'This username is already taken.',

            'fname.required' => 'First name is required.',
            'fname.string' => 'First name must be a valid string.',
            'fname.max' => 'First name cannot exceed 255 characters.',

            'lname.required' => 'Last name is required.',
            'lname.string' => 'Last name must be a valid string.',
            'lname.max' => 'Last name cannot exceed 255 characters.',

            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.max' => 'Email cannot exceed 255 characters.',
            'email.unique' => 'This email is already registered.',

            'password.required' => 'Password is required.',
            'password.string' => 'Password must be a valid string.',
            'password.min' => 'Password must be at least 8 characters long.',
        ];
    }

    private function store(): array
    {
        return [
            'uname' => 'required|string|max:255|unique:users,uname',
            'fname' => 'required|string|max:255',
            'lname' => 'required|string|max:255',
            'email'=> 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8', // NOTE: Later, add complexity of rules as well as confirmation
        ];
    }

    private function update(): array
    {
        return [
            'uname' => 'sometimes|string|max:255|unique:users,uname,' . $this->route('user')->id,
            'fname' => 'sometimes|string|max:255',
            'lname' => 'sometimes|string|max:255',
            'email'=> 'sometimes|string|email|max:255|unique:users,email,' . $this->route('user')->id
            // 'password' => 'required|string|min:8', NOTE: Password update will be handled separately
        ];
    }
}
