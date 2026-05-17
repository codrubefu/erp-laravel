<?php

namespace App\Articles\Http\Controllers\Api;

use App\Articles\Http\Requests\StoreArticleRequest;
use App\Articles\Http\Requests\UpdateArticleRequest;
use App\Articles\Http\Resources\ArticleResource;
use App\Articles\Models\Article;
use App\Users\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ArticleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $articles = Article::query()
            ->with(['author', 'groups', 'locations'])
            ->when($request->boolean('with_trashed'), fn ($query) => $query->withTrashed())
            ->when($request->boolean('only_trashed'), fn ($query) => $query->onlyTrashed())
            ->when($request->filled('group_id'), function ($query) use ($request): void {
                $groupId = $request->integer('group_id');

                $query->whereHas('groups', fn ($groupQuery) => $groupQuery->whereKey($groupId));
            })
            ->when($request->filled('location_id'), function ($query) use ($request): void {
                $locationId = $request->integer('location_id');

                $query->whereHas('locations', fn ($locationQuery) => $locationQuery->whereKey($locationId));
            })
            ->when($request->filled('user_id'), fn ($query) => $query->where('created_by', $request->integer('user_id')))
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ArticleResource::collection($articles);
    }

    public function store(StoreArticleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $groupIds = $data['groups'] ?? [];
        $locationIds = $data['locations'] ?? [];
        unset($data['groups']);
        unset($data['locations']);
        $data['created_by'] = $request->user()->id;

        $article = DB::transaction(function () use ($data, $groupIds, $locationIds): Article {
            $article = Article::query()->create($data);
            $article->groups()->sync($groupIds);
            $article->locations()->sync($locationIds);

            return $article;
        });

        return (new ArticleResource($article->load(['author', 'groups', 'locations'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Article $article): ArticleResource
    {
        return new ArticleResource($article->load(['author', 'groups', 'locations']));
    }

    public function update(UpdateArticleRequest $request, Article $article): ArticleResource
    {
        $data = $request->validated();
        $groupIds = $data['groups'] ?? null;
        $locationIds = $data['locations'] ?? null;
        unset($data['groups']);
        unset($data['locations']);

        DB::transaction(function () use ($article, $data, $groupIds, $locationIds): void {
            $article->update($data);

            if ($groupIds !== null) {
                $article->groups()->sync($groupIds);
            }

            if ($locationIds !== null) {
                $article->locations()->sync($locationIds);
            }
        });

        return new ArticleResource($article->load(['author', 'groups', 'locations']));
    }

    public function destroy(Article $article): JsonResponse
    {
        $article->delete();

        return response()->json(status: 204);
    }
}