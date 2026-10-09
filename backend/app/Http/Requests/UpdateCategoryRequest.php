<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'bail',
                'sometimes',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($this->route('category')),
                function (string $attribute, mixed $value, Closure $fail) {
                    if (str($value)->slug()->isEmpty()) {
                        $fail('O nome da categoria precisa conter letras ou números.');
                    }
                },
            ],
            'slug' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'O nome da categoria deve ser uma string.',
            'name.max' => 'O nome da categoria não pode ter mais de 255 caracteres.',
            'name.unique' => 'O nome da categoria informado já está em uso. Por favor, escolha outro nome.',
            'slug.prohibited' => 'O campo slug não pode ser atualizado.',
        ];
    }
}
