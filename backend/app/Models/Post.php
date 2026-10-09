<?php

namespace App\Models;

use App\Enums\PostStatus;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'cover_image',
        'status',
        'published_at',
    ];

    protected $casts = [
        'status' => PostStatus::class,
        'published_at' => 'datetime',
    ];

    protected static function booted()
    {
        self::creating(function (Post $post) {
            if ($post->slug !== null) {
                return;
            }

            $baseSlug = str($post->title)->slug()->toString();
            $aux = $baseSlug;
            $count = 2;

            // VERIFICA SE O SLUG JÁ EXISTE NO BANCO DE DADOS, CASO EXISTA ADICIONA UM SUFIXO NUMÉRICO PARA GARANTIR QUE O SLUG SEJA ÚNICO
            while (static::where('slug', $aux)->exists()) {
                $aux = $baseSlug.'-'.$count;
                $count++;
            }

            $post->slug = $aux;
        });

        /* Verifica se existe uma imagem de capa e caso sim a apaga após a exclusão do post */
        self::deleted(function (Post $post) {
            if ($post->cover_image) {
                try {
                    Storage::disk('public')->delete($post->cover_image);
                } catch (Throwable $e) {
                    Log::warning('Não foi possível apagar a capa do post '.$post->id.' ('.$post->cover_image.'): '.$e->getMessage());
                }
            }
        });
    }

    /* Relação entre as categorias de uma postagem e a tabela de categorias */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_post', 'post_id', 'category_id');
    }

    /* Relação entre o id do autor de uma postagem e o id do usuário */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
