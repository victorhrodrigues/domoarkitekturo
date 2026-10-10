<?php

namespace App\Http\Requests;

use App\Enums\PostStatus;
use App\Models\Post;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
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
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('posts', 'title')->ignore($this->route('post')),
                function (string $attribute, mixed $value, Closure $fail) {
                    if (str($value)->slug()->isEmpty()) {
                        $fail('O título precisa conter letras ou números.');
                    }
                },
            ],
            'status' => ['sometimes', Rule::enum(PostStatus::class)],
            'excerpt' => ['nullable', 'string', 'max:300', Rule::requiredIf(fn () => $this->estaPublicando())],
            'content' => ['nullable', 'string', Rule::requiredIf(fn () => $this->estaPublicando())],
            'published_at' => ['nullable', 'date'],
            'cover_image' => ['nullable', 'string', 'max:255'],
            'categories' => ['nullable', 'array', Rule::requiredIf(fn () => $this->estaPublicando())],
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
            'excerpt.required' => 'O resumo da postagem é obrigatório para a publicação.',
            'content.string' => 'O conteúdo da postagem deve ser uma string.',
            'content.required' => 'O conteúdo da postagem é obrigatório para a publicação.',
            'published_at.date' => 'A data de publicação informada é inválida.',
            'cover_image.string' => 'O caminho da imagem de capa deve ser um texto.',
            'cover_image.max' => 'O caminho da imagem de capa não pode ter mais de 255 caracteres.',
            'categories.array' => 'As categorias devem ser fornecidas como um array.',
            'categories.required' => 'Pelo menos uma categoria deve ser selecionada para a publicação.',
            'categories.*.uuid' => 'Cada categoria deve ser um UUID válido.',
            'categories.*.exists' => 'Uma ou mais categorias fornecidas não existem no banco de dados.',
            'slug.prohibited' => 'O campo slug não pode ser atualizado.',
        ];
    }

    /**
     * A postagem fica (ou continua) publicada depois deste update? Usa o status enviado
     * e, se não vier, o status atual da postagem.
     */
    private function estaPublicando(): bool
    {
        $post = $this->route('post');
        $statusAtual = $post instanceof Post ? $post->status?->value : null;

        return $this->input('status', $statusAtual) === PostStatus::Published->value;
    }
}
