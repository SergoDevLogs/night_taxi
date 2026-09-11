<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->orderBy('id')
            ->paginate(
                perPage: (int) $request->integer('limit', 20),
                page:    (int) $request->integer('page', 1),
            );

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());
        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function show(int $id): ProductResource
    {
        return new ProductResource(Product::findOrFail($id));
    }

    public function update(StoreProductRequest $request, int $id): ProductResource
    {
        $product = Product::findOrFail($id);
        $product->update($request->validated());
        return new ProductResource($product);
    }

    public function destroy(int $id): JsonResponse
    {
        Product::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
