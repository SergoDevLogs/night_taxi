<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBoxRequest;
use App\Http\Resources\BoxResource;
use App\Models\Box;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BoxController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BoxResource::collection(Box::orderBy('id')->get());
    }

    public function store(StoreBoxRequest $request): JsonResponse
    {
        $box = Box::create($request->validated());
        return (new BoxResource($box))->response()->setStatusCode(201);
    }

    public function show(int $id): BoxResource
    {
        return new BoxResource(Box::findOrFail($id));
    }

    public function update(StoreBoxRequest $request, int $id): BoxResource
    {
        $box = Box::findOrFail($id);
        $box->update($request->validated());
        return new BoxResource($box);
    }

    public function destroy(int $id): JsonResponse
    {
        Box::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
