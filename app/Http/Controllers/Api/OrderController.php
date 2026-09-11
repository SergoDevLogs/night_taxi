<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderDetailResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::query()->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(
            perPage: (int) $request->integer('limit', 20),
            page:    (int) $request->integer('page', 1),
        );

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = Order::create([
            'number' => $request->validated('number'),
            'status' => OrderStatus::NEW,
        ]);
        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function show(int $id): OrderDetailResource
    {
        $order = Order::with('items.product')->findOrFail($id);
        return new OrderDetailResource($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, int $id): OrderResource
    {
        $order = Order::findOrFail($id);
        $order->update(['status' => $request->validated('status')]);
        return new OrderResource($order);
    }
}
