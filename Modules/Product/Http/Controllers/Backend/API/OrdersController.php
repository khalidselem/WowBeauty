<?php

namespace Modules\Product\Http\Controllers\Backend\API;

use App\Models\Setting;
use Auth;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Constant\Models\Constant;
use Modules\Logistic\Models\LogisticZone;
use Modules\Product\Http\Requests\OrderRequest;
use Modules\Product\Models\Cart;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderGroup;
use Modules\Product\Models\OrderItem;
use Modules\Product\Models\OrderUpdate;
use Modules\Product\Trait\OrderTrait;
use Modules\Product\Transformers\OrderDetailsResource;
use Modules\Product\Transformers\OrderResource;

class OrdersController extends Controller
{
    use OrderTrait;

    /**
     * Display a listing of the resource.
     *
     * @return Renderable
     */
    public function index()
    {
        return view('product::index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Renderable
     */
    public function create()
    {
        return view('product::create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Renderable
     */
    public function store(OrderRequest $request)
    {
        $userId = Auth::id();

        $location_id = $request['location_id'];

        $carts = Cart::where('user_id', $userId)->where('location_id', $location_id)->get();

        if (count($carts) > 0) {
            // check carts available stock -- todo::[update version] -> run this check while storing OrderItems
            foreach ($carts as $cart) {
                $productVariationStock = $cart->product_variation->product_variation_stock ? $cart->product_variation->product_variation_stock->stock_qty : 0;
                if ($cart->qty > $productVariationStock) {
                    $message = $cart->product_variation->product->name.' is out of stock';

                    return response()->json(['message' => $message, 'status' => false]);
                }
            }

            // create new order group
            $orderGroup = new OrderGroup;
            $orderGroup->user_id = $userId;
            $orderGroup->shipping_address_id = $request['shipping_address_id'];
            $orderGroup->billing_address_id = $request['billing_address_id'];
            $orderGroup->location_id = $location_id;
            $orderGroup->phone_no = $request['phone'];
            $orderGroup->alternative_phone_no = $request['alternative_phone'];
            $orderGroup->sub_total_amount = getSubTotal($carts, false, '', false);
            $orderGroup->payment_details = $request['payment_details'];

            $tax_data = getTaxamount($orderGroup->sub_total_amount);

            $orderGroup->total_tax_amount = $tax_data['total_tax_amount'];
            $orderGroup->total_coupon_discount_amount = 0;
            $orderGroup->type = 'online';
            $logisticZone = LogisticZone::where('id', $request['chosen_logistic_zone_id'])->first();
            // todo::[for eCommerce] handle exceptions for standard & express
            $orderGroup->total_shipping_cost = $logisticZone->standard_delivery_charge ?? 0;
            $orderGroup->total_tips_amount = $request['tips'] ?? 0;

            $orderGroup->grand_total_amount = $orderGroup->sub_total_amount + $orderGroup->total_tax_amount + $orderGroup->total_shipping_cost + $orderGroup->total_tips_amount - $orderGroup->total_coupon_discount_amount;
            $orderGroup->save();

            // order -> todo::[update version] make array for each vendor, create order in loop
            $order = new Order;
            $order->order_group_id = $orderGroup->id;
            $order->user_id = $userId;
            $order->location_id = $location_id;
            $order->total_admin_earnings = $orderGroup->grand_total_amount;
            $order->logistic_id = $logisticZone->logistic_id ?? null;
            $order->logistic_name = ($logisticZone->logistic)->name ?? null;
            // $order->shipping_delivery_type          = $request['shipping_delivery_type'];
            $order->payment_status = $request['payment_status'];
            $order->shipping_cost = $orderGroup->total_shipping_cost; // todo::[update version] calculate for each vendors
            $order->tips_amount = $orderGroup->total_tips_amount; // todo::[update version] calculate for each vendors

            $order->save();

            // order items
            $total_points = 0;
            foreach ($carts as $cart) {
                $discounted_price = variationDiscountedPrice($cart->product_variation->product, $cart->product_variation);
                $tax_data = getTaxamount($discounted_price);

                $orderItem = new OrderItem;
                $orderItem->order_id = $order->id;
                $orderItem->product_variation_id = $cart->product_variation_id;
                $orderItem->qty = $cart->qty;
                $orderItem->location_id = $location_id;
                $orderItem->unit_price = $discounted_price;
                $orderItem->total_tax = $tax_data['total_tax_amount'];
                $orderItem->total_price = $orderItem->unit_price * $orderItem->qty;
                $orderItem->save();

                $product = $cart->product_variation->product;
                $product->total_sale_count += $orderItem->qty;

                // minus stock qty
                try {
                    $productVariationStock = $cart->product_variation->product_variation_stock;
                    $productVariationStock->stock_qty -= $orderItem->qty;
                    $productVariationStock->save();
                } catch (\Throwable $th) {
                    //throw $th;
                }

                $product->stock_qty -= $orderItem->qty;
                $product->save();

                // category sales count
                if ($product->categories()->count() > 0) {
                    foreach ($product->categories as $category) {
                        $category->total_sale_count += $orderItem->qty;
                        $category->save();
                    }
                }
                $cart->delete();
            }

            $order->save();
            // payment gateway integration & redirection
            $orderGroup->payment_method = $request['payment_method'];
            $orderGroup->payment_status = $request['payment_status'];
            $orderGroup->save();

            $order_status = [

                'order_id' => $order->id,
                'user_id' => $userId,
                'note' => 'Your Order has been placed.',

            ];

            OrderUpdate::create($order_status);

            $order_prefix_data = Setting::where('name', 'inv_prefix')->first();
            $order_prefix = $order_prefix_data ? $order_prefix_data->val : '';

            try {
                $notification_data = [

                    'id' => $order->id,
                    'order_code' => $order_prefix.optional($order->orderGroup)->order_code,
                    'user_id' => $order->user_id,
                    'user_name' => optional($order->user)->first_name.' '.optional($order->user)->last_name ?? default_user_name(),
                    'order_date' => $order->created_at->format('d/m/Y'),
                    'order_time' => $order->created_at->format('h:i A'),
                ];

                $this->sendNotificationOnOrderUpdate('order_placed', $notification_data);
            } catch (\Exception $e) {
                \Log::error($e->getMessage());
            }

             $message = __('product.order_palce');

            return response()->json(['message' => $message, 'product' => $order, 'status' => true], 200);
        } else {
            $message = __('product.empty_cart');

            return response()->json(['message' => $message, 'product' => $order, 'status' => true], 200);
        }
    }

    public function statusList()
    {
        $order_status = Constant::where('type', 'ORDER_STATUS')->get();

        return response()->json([
            'status' => true,
            'data' => $order_status,
            'message' => __('product.order_status_list'),
        ], 200);
    }

    public function orderList(Request $request)
    {
        $perPage = $request->input('per_page', 10);

        $userId = Auth::id();

        $orderQuery = Order::where('user_id', $userId)->with('orderItems');

        if ($request->has('delivery_status') && $request->delivery_status != '') {

            $delivery_status = explode(',', $request->delivery_status);

            $orderQuery->whereIn('delivery_status', $delivery_status);

        }

        $orderQuery = $orderQuery->orderBy('created_at', 'desc');

        $orderQuery = $orderQuery->paginate($perPage);

        $orderCollection = OrderResource::collection($orderQuery);

        return response()->json([
            'status' => true,
            'data' => $orderCollection,
            'message' => __('product.order_list'),
        ], 200);
    }

    public function cancleOrder(Request $request)
    {
        $userId = Auth::id();

        if ($request->has('id') && $request->id != '') {
            $order = Order::where('id', $request->id)->first();

            if ($order) {
                $order->delivery_status = 'cancelled';

                $order->save();

                $order_status = [

                    'order_id' => $order->id,
                    'user_id' => $userId,
                    'note' => 'Your Order has been Canclled.',

                ];

                OrderUpdate::create($order_status);

                $notify_type = 'order_cancelled';

                $order_prefix_data = Setting::where('name', 'inv_prefix')->first();
                $order_prefix = $order_prefix_data ? $order_prefix_data->val : '';

                try {
                    $notification_data = [

                        'id' => $order->id,
                        'order_code' => $order_prefix.optional($order->orderGroup)->order_code,
                        'user_id' => $order->user_id,
                        'user_name' => optional($order->user)->first_name.' '.optional($order->user)->last_name ?? default_user_name(),
                        'order_date' => $order->updated_at->format('d/m/Y'),
                        'order_time' => $order->updated_at->format('h:i A'),
                    ];

                    $this->sendNotificationOnOrderUpdate($notify_type, $notification_data);
                } catch (\Exception $e) {
                    \Log::error($e->getMessage());
                }

                return response()->json([
                    'status' => true,
                    'message' => __('product.order_canclled'),
                ], 200);
            } else {
                return response()->json([
                    'status' => true,
                    'message' => __('product.order_not_found'),
                ], 200);
            }
        }
    }

    public function orderDetails(Request $request)
    {
        $orderId = $request->order_id;

        $orderQuery = Order::where('id', $orderId)->with('orderItems', 'orderGroup')->first();

        $orderQuery = Order::where('id', $orderId)->with('orderItems', 'orderGroup')->first();

        $orderCollection = new OrderDetailsResource($orderQuery);

        return response()->json([
            'status' => true,
            'data' => $orderCollection,
            'message' => __('product.order_details'),
        ], 200);
    }
}
