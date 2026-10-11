<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PostController extends Controller
{
    protected $post;

    public function __construct(Post $post)
    {
        $this->post = $post;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $data = $request->validated();
        /* salva o valor em $categories e remove o campo 'categories' do array $data, se essa campo não estiver presente retorna um array vazio */
        $categories = Arr::pull($data, 'categories', []);
        /* Busca o ID do usuário autenticado no Sanctum e adiciona ao array $data */
        $data['user_id'] = $request->user()->id;

        // TODO: sanitizar $data['content'] antes de gravar

        $post = DB::transaction(function () use ($data, $categories) {
            $post = $this->post->create($data);
            $post->categories()->sync($categories);

            return $post;
        });

        return response()->json($post->load('categories'), Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Post $post)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePostRequest $request, $id)
    {
        $post = $this->post->findOrFail($id);
        $data = $request->validated();

        /** Verifica se o campo 'categories' existe no array $data */
        $temCategorias = array_key_exists('categories', $data);

        /** Extrai o valor do campo 'categories' do array $data e remove o campo, se ele não existir, retorna um array vazio */
        $categories = Arr::pull($data, 'categories', []);

        /** Salva a imagem de capa antiga, podemos usar essa variável para comparar depois de a capa foi alterada */
        $capaAntiga = $post->cover_image;

        DB::transaction(function () use ($post, $data, $categories, $temCategorias) {
            $post->update($data);

            if ($temCategorias) {
                $post->categories()->sync($categories);
            }
        });

        /** Verifica se existe uma capa diferente de null e depois se as capas sao diferentes */
        if ($capaAntiga && $capaAntiga !== $post->cover_image) {
            try {
                Storage::disk('public')->delete($capaAntiga);
            } catch (Throwable $e) {
                Log::warning('Não foi possível apagar a capa antiga do post '.$post->id.' ('.$capaAntiga.'): '.$e->getMessage());
            }
        }

        return response()->json($post->load('categories'), Response::HTTP_OK);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post)
    {
        //
    }
}
