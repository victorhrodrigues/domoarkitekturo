<?php

namespace App\Http\Requests;

use App\Enums\PostStatus;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
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
            'title' => [
                'bail',
                'required',
                'string',
                'max:255',
                'unique:posts,title',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (str($value)->slug()->isEmpty()) {
                        $fail('O título precisa conter letras ou números.');
                    }
                },
            ],
            'status' => ['sometimes', Rule::enum(PostStatus::class)],
            'excerpt' => ['nullable', 'string', 'max:300', 'required_if:status,'.PostStatus::Published->value],
            'content' => ['nullable', 'string', 'required_if:status,'.PostStatus::Published->value],
            'published_at' => ['nullable', 'date'],
            'cover_image' => ['nullable', 'string', 'max:255'],
            'categories' => ['nullable', 'array', 'required_if:status,'.PostStatus::Published->value],
            'categories.*' => ['uuid', 'exists:categories,id'],
            'slug' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'O título da postagem é obrigatório.',
            'title.string' => 'O título da postagem deve ser uma string.',
            'title.max' => 'O título da postagem não pode ter mais de 255 caracteres.',
            'title.unique' => 'O título da postagem informado já está em uso. Por favor, escolha outro título.',
            'status.enum' => 'O status da postagem informado é inválido.',
            'excerpt.string' => 'O resumo da postagem deve ser uma string.',
            'excerpt.max' => 'O resumo da postagem não pode ter mais de 300 caracteres.',
            'content.string' => 'O conteúdo da postagem deve ser uma string.',
            'published_at.date' => 'A data de publicação informada é inválida.',
            'cover_image.string' => 'A imagem de capa deve conter o path para o arquivo.',
            'cover_image.max' => 'A imagem de capa não pode ter mais de 255 caracteres no caminho do arquivo.',
            'categories.array' => 'As categorias devem ser fornecidas como um array.',
            'categories.*.uuid' => 'Cada categoria deve ser um UUID válido.',
            'categories.*.exists' => 'Uma ou mais categorias fornecidas não existem no banco de dados.',
            'slug.prohibited' => 'O campo slug não pode ser definido manualmente. Ele será gerado automaticamente com base no título da postagem.',
            'excerpt.required_if' => 'O resumo da postagem é obrigatório para a publicação.',
            'content.required_if' => 'O conteúdo da postagem é obrigatório para a publicação.',
            'categories.required_if' => 'Pelo menos uma categoria deve ser selecionada para a publicação.',
        ];
    }
}
