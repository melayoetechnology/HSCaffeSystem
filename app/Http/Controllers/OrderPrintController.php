<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderPrintController extends Controller
{
    protected function findOrder(Request $request, int $orderId): Order
    {
        $order = Order::with(['items.modifiers', 'table', 'customer', 'tenant'])
            ->find($orderId);

        if (! $order || $order->tenant_id !== $request->user()->tenant_id) {
            throw new NotFoundHttpException;
        }

        return $order;
    }

    public function waiter(Request $request, int $order): \Illuminate\View\View
    {
        $order = $this->findOrder($request, $order);

        return view('orders.print.waiter', ['order' => $order]);
    }

    public function kitchen(Request $request, int $order): \Illuminate\View\View
    {
        $order = $this->findOrder($request, $order);

        return view('orders.print.kitchen', ['order' => $order]);
    }

    public function receipt(Request $request, int $order): \Illuminate\View\View
    {
        $order = $this->findOrder($request, $order);

        return view('orders.print.receipt', ['order' => $order]);
    }
}
