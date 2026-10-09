<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function booted()
    {
        static::creating(function (Category $category) {
            if ($category->slug !== null) {
                return;
            }

            $baseSlug = str($category->name)->slug()->toString();
            $aux = $baseSlug;
            $count = 2;

            // VERIFICA SE O SLUG JÁ EXISTE NO BANCO DE DADOS, CASO EXISTA ADICIONA UM SUFIXO NUMÉRICO PARA GARANTIR QUE O SLUG SEJA ÚNICO
            while (static::where('slug', $aux)->exists()) {
                $aux = $baseSlug.'-'.$count;
                $count++;
            }

            $category->slug = $aux;
        });

        /* Não permite a atualização do slug */
        static::updating(function (Category $category) {
            if ($category->isDirty('slug')) {
                $category->slug = $category->getOriginal('slug');
            }
        });
    }
}
