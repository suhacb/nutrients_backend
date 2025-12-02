<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Foundation\Http\FormRequest;

abstract class DynamicRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $method = $this->getActionMethod();
        $methodName = 'rulesFor' . Str::studly($method);

        if (method_exists($this, $methodName)) {
            return $this->{$methodName}();
        }

        return [];
    }

    public function messages(): array
    {
        $method = $this->getActionMethod();
        $methodName = 'messagesFor' . Str::studly($method);

        if (method_exists($this, $methodName)) {
            return $this->{$methodName}();
        }

        return [];
    }

    protected function getActionMethod(): ?string
    {
        return optional($this->route())->getActionMethod();
    }
}