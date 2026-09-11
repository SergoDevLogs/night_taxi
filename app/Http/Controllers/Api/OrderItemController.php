<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderItemRequest;
use App\Http\Resources\OrderItemResource;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderItemController extends Controller
{
    public function index(int $orderId): AnonymousResourceCollection
    {
        $order = Order::findOrFail($orderId);
        return OrderItemResource::collection(
            $order->items()->with('product')->get()
        );
    }

    public function store(StoreOrderItemRequest $request, int $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);

        $item = OrderItem::updateOrCreate(
            ['order_id' => $order->id, 'product_id' => $request->validated('product_id')],
            ['quantity' => $request->validated('quantity')],
        );

        return (new OrderItemResource($item->load('product')))
            ->response()->setStatusCode(201);
    }
}
