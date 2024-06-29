<?php

namespace Modules\Booking\Trait;

use App\Models\Setting;
use App\Models\User;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingProduct;
use Modules\Booking\Models\BookingService;
use Modules\Booking\Models\BookingTransaction;

use Modules\Booking\Transformers\BookingResource;
use Modules\Commission\Models\CommissionEarning;
use Modules\Currency\Models\Currency;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\UserCouponRedeem;
use Modules\Tip\Models\TipEarning;
use Razorpay\Api\Api;
use Modules\Promotion\Models\Promotion;
trait PaymentTrait
{
    public function getpayment_method($data, $booking_id)
    {

        $data['booking_id'] = $booking_id;
        $data['transaction_type'] = $data['payment_method'];
        $data['tip_amount'] = $data['tip'] ?? 0;
        $data['tax_percentage'] = $data['taxes'];
        $data['coupon_code'] = $data['coupon_code'];

        $booking_transaction_data = BookingTransaction::where('booking_id', $booking_id)->first();

        if (!empty($booking_transaction_data)) {

            $booking_transaction_data->update($data);

            $booking_transaction = BookingTransaction::where('booking_id', $booking_id)->first();
        } else {

            $booking_transaction = BookingTransaction::create($data);

            $earning_data = $this->commissionData($booking_transaction);

            $booking = Booking::where('id', $data['booking_id'])->first();

            if (isset($earning_data['commission_data'])) {
                $booking->commission()->save(new CommissionEarning($earning_data['commission_data']));
            }

            if ($data['tip_amount'] > 0) {
                $booking->tip()->save(new TipEarning([
                    'employee_id' => $earning_data['employee_id'],
                    'tip_amount' => $data['tip_amount'],
                    'tip_status' => 'unpaid',
                    'payment_date' => null,
                ]));
            }
        }

        $total_amount = $this->getTotalAmount($data['booking_id'], $data['taxes'], $data['tip'], $data['coupon_code']);
        $couponDiscountamount = $this->couponDiscount($total_amount, $data['coupon_code'], $booking_id);
        $data['couponDiscountamount'] = $couponDiscountamount;
        $total_amount = $total_amount - $couponDiscountamount;
        $data['$total_amount'] = $total_amount;

        $currency = Currency::where('is_primary', 1)->first();

        switch ($data['transaction_type']) {
            case 'razorpay':

                $razorpay_key = $this->getrazorpaykey();

                $responseData = [
                    'status' => true,
                    'booking_transaction_id' => $booking_transaction['id'],
                    'total_amount' => $total_amount,
                    'currency' => $currency['currency_code'],
                    'payment_method' => $data['payment_method'],
                    'public_key' => isset($razorpay_key['razorpay_publickey']) ? $razorpay_key['razorpay_publickey'] : '',

                ];

                break;

            case 'stripe':

                $stripe_key = $this->getstripekey();

                $responseData = [

                    'booking_transaction_id' => $booking_transaction['id'],
                    'total_amount' => $total_amount,
                    'currency' => $currency['currency_code'],
                    'payment_method' => $data['payment_method'],
                    'public_key' => isset($stripe_key['stripe_secretkey']) ? $stripe_key['stripe_secretkey'] : '',

                ];

                break;

            default:

                $responseData = $this->getcashpayments($data, $booking_transaction['id']);

                break;
        }

        return $responseData;
    }

    //GET TOTAL AMOUNT

    public function getTotalAmount($booking_id, $tax = [], $tip_amount = 0)
    {
        $booking_services = BookingService::where('booking_id', $booking_id)->get();
        $total_service_amount = $booking_services->sum('service_price');
        $booking_products = BookingProduct::where('booking_id', $booking_id)->with('product')->get();
        $discounted_product_amount = getproductDiscountAmount($booking_products);
        $total_product_amount = BookingProduct::where('booking_id', $booking_id)->sum(\DB::raw('product_qty * product_price'));
        $product_amount = $total_product_amount - $discounted_product_amount;
        $tax_amount = 0;
        if ($tax != '') {
            foreach ($tax as $tax_value) {
                if ($tax_value['type'] == 'percent') {
                    $tax_amount = $tax_amount + (($total_service_amount + $product_amount ) * $tax_value['percent'] / 100);
                } elseif ($tax_value['type'] == 'fixed') {
                    $tax_amount = $tax_amount + $tax_value['tax_amount'];
                }
            }
        }
        $total_amount = $total_service_amount + $tax_amount + $tip_amount + $product_amount;
        $total_amount = number_format($total_amount, 2, '.', '');

        return $total_amount;
    }

    public function couponDiscount($total_amount, $coupon_code, $booking_id)
    {
        $coupon = Coupon::where('coupon_code', $coupon_code)->first();
        $couponDiscountamount = 0;
        if ($coupon) {
            $couponDiscountamount = $coupon->discount_type == 'percent' ? $total_amount * ($coupon->discount_percentage / 100) : $coupon->discount_amount;
        }

        return $couponDiscountamount;
    }

    public function couponExpired($coupon_code, $couponDiscountamount, $booking_id)
    {
        $coupon = Coupon::where('coupon_code', $coupon_code)->first();
        $user_id = Booking::where('id', $booking_id)->first();
        if ($coupon) {
            $redeemCoupon['coupon_code'] = $coupon_code;
            $redeemCoupon['discount'] = $couponDiscountamount;
            $redeemCoupon['user_id'] = $user_id->user_id;
            $redeemCoupon['coupon_id'] = $coupon->id;
            $redeemCoupon['booking_id'] = $booking_id;
            UserCouponRedeem::create($redeemCoupon);
            if (UserCouponRedeem::where('coupon_code', $coupon_code)->count() == $coupon->use_limit) {
                Coupon::where('coupon_code', $coupon_code)->update(['is_expired' => 1]);
                if ($coupon = Coupon::where('coupon_code', $coupon_code)->first()) {
                    Promotion::where('id', $coupon->promotion_id)->update(['status' => 0]);
                }
            }
        }
    }

    //CASH PAYMNET DATA

    public function getcashpayments($data, $booking_transaction_id)
    {
        $this->couponExpired($data['coupon_code'], $data['couponDiscountamount'], $data['booking_id']);
        BookingTransaction::where('id', $booking_transaction_id)->update(['external_transaction_id' => '', 'payment_status' => 1]);
        Booking::where('id', $data['booking_id'])->update(['status' => 'completed']);
        $queryData = Booking::with('services', 'products', 'user')->findOrFail($data['booking_id']);
        $queryData['detail'] = $this->bookingDetail($queryData);
        $this->sendNotificationOnBookingUpdate('complete_booking', $queryData);
        $responseData = [
            'message' => __('booking.payment_successfull'),
            'payment_method' => $data['payment_method'],
            'data' => new BookingResource($queryData),
            'status' => true,
        ];

        return $responseData;
    }

    // RAZORPAY PAYMENT DATA

    public function getrazorpaypayments($data, $booking_transaction_id)
    {
        $rezorpay_key_data = $this->getrazorpaykey();

        $key_id = $rezorpay_key_data['razorpay_publickey'];
        $secret = $rezorpay_key_data['razorpay_secretkey'];

        try {
            $currency = $data['response']['currency'];

            $floatTotalAmount = floatval($data['response']['total_amount']);
            $totalamount = $floatTotalAmount * 100;
            $api = new Api($key_id, $secret);
            $api->payment->fetch($data['response']['razorpay_payment_id'])->capture(['amount' => $totalamount, 'currency' => $currency]);
            $data = BookingTransaction::where('id', $booking_transaction_id)->update(['external_transaction_id' => $data['response']['razorpay_payment_id'], 'payment_status' => 1]);

            $booking_transaction = BookingTransaction::where('id', $booking_transaction_id)->first();
            Booking::where('id', $booking_transaction['booking_id'])->update(['status' => 'completed']);

            $queryData = Booking::with('services', 'user')->findOrFail($booking_transaction['booking_id']);

            $responseData = [
                'message' => __('booking.payment_successfull'),
                'booking' => new BookingResource($queryData),
                'status' => true,
            ];
        } catch (\Exception $e) {
            $message = $e->getMessage();

            $responseData = [
                'message' => $message,
                'status' => false,
            ];
        }

        return $responseData;
    }

    //OPEN STRIPE CHECKOUT PAGE

    public function getstripepayments($data)
    {
        $baseURL = env('APP_URL');

        $stripe_key_data = $this->getstripekey();

        $stripe_secret = $stripe_key_data['stripe_secretkey'];

        try {
            $stripe = new \Stripe\StripeClient($stripe_secret);
            $checkout_session = $stripe->checkout->sessions->create([

                'success_url' => $baseURL . '/app/bookings/payment_success/' . $data['booking_transaction_id'],
                'payment_method_types' => ['card'],
                'billing_address_collection' => 'required',
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => $data['currency'],
                            'product_data' => [
                                'name' => 'T-shirt',
                            ],
                            'unit_amount' => $data['total_amount'] * 100,
                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            $checkout_session = [
                'message' => $message,
                'status' => false,
            ];
        }

        return $checkout_session;
    }

    //GET STRIPE PAYMENT DATA

    public function getstripePaymnetId($request_token)
    {
        $stripe_key_data = $this->getstripekey();

        $stripe_secret = $stripe_key_data['stripe_secretkey'];

        $stripe = new \Stripe\StripeClient($stripe_secret);
        $session_object = $stripe->checkout->sessions->retrieve($request_token, []);

        return $session_object;
    }

    //GET RAZORPAY KEY DATA FROM DB

    public function getrazorpaykey()
    {
        $rezorpay_key = Setting::where('type', 'razor_payment_method')->get();

        $rezorpay_key_data = [];

        if ($rezorpay_key != '') {
            foreach ($rezorpay_key as $rezorpay) {
                $rezorpay_key_data[$rezorpay->name] = $rezorpay->val;
            }
        }

        return $rezorpay_key_data;
    }

    //GET STRIPE KEY DATA

    public function getstripekey()
    {
        $stripe_key = Setting::where('type', 'str_payment_method')->get();

        $stripe_key_data = [];

        if ($stripe_key != '') {
            foreach ($stripe_key as $stripe) {
                $stripe_key_data[$stripe->name] = $stripe->val;
            }
        }

        return $stripe_key_data;
    }

    public function commissionData($data)
    {
        $booking_id = $data['booking_id'];

        $booking_service = BookingService::where('booking_id', $booking_id)->first();

        $employee_id = $booking_service['employee_id'];

        $employee = User::role('employee')->where('id', $employee_id)->with('commissions')->first();

        $commission_amount = 0;
        $finalComissionAmount = 0;
        $commission_data = [];
        if (isset($employee->commissions)) {
            $booking_services = BookingService::where('booking_id', $booking_id)->get();

            $total_service_amount = $booking_services->sum('service_price');

            foreach ($employee->commissions as $key => $value) {
                if (isset($value->mainCommission)) {
                    $commission_type = $value->mainCommission->commission_type;

                    $commission_value = $value->mainCommission->commission_value;
                    if ($commission_type == 'fixed') {
                        $finalComissionAmount += $commission_value;
                    } else {
                        $commission_amount = $commission_value * $total_service_amount / 100;
                        $finalComissionAmount += $commission_amount;
                    }
                }
            }
        }

        if ($finalComissionAmount > 0) {
            $commission_data = [
                'employee_id' => $employee_id,
                'commission_amount' => $finalComissionAmount,
                'commission_status' => 'unpaid',
                'payment_date' => null,
            ];
        }
        $data = [
            'commission_data' => $commission_data ?? null,
            'employee_id' => $employee_id,
        ];

        return $data;
    }

    protected function bookingDetail($booking)
    {
        $bookingTransaction = BookingTransaction::where('booking_id', $booking->id)->where('payment_status', 1)->first();
        $booking_product = BookingProduct::where('booking_id', $booking->id);

        $sumDiscountedPrice = 0;

        $coupon_discount = $booking->userCouponRedeem['discount'] ?? 0;


        if ($booking_product != '') {
            $sumDiscountedPrice = $booking_product->sum('discounted_price');
            $total_product_amount = $booking_product->sum(\DB::raw('product_qty * product_price'));
        }

        $serviceAmount = $booking->services->sum('service_price');

        $tax_amount = 0;
        $tip_amount = 0;
        if (!empty($bookingTransaction)) {
            foreach ($bookingTransaction->tax_percentage as $key => $tax) {
                if ($tax['type'] == 'percent') {
                    $tax_amount += (($serviceAmount + $sumDiscountedPrice) * $tax['percent']) / 100;
                } else {
                    $tax_amount += $tax['tax_amount'];
                }
            }
            $tip_amount = $bookingTransaction->tip_amount;
        }

        return [
            'serviceAmount' => $serviceAmount,
            'bookingTransaction' => $bookingTransaction,
            'sumDiscountedPrice' => $sumDiscountedPrice,
            'tax_amount' => $tax_amount,
            'coupon_discount' => $coupon_discount,

            'grand_total' => ($tax_amount + $sumDiscountedPrice + $serviceAmount + $tip_amount) - $coupon_discount,
        ];
    }

    public function ExpireCoupon($data)
    {
        $coupon = UserCouponRedeem::where('coupon_code', $data['coupon_code'])->first();

        if ($coupon) {
            $coupon_data = Coupon::find($coupon->coupon_id);

            if ($coupon_data->is_expired == 1) {
                $message = 'Coupon has expired.';
                return response()->json(['message' => $message, 'status' => false], 200);
            } elseif ($coupon_data->use_limit && $coupon->count() >= $coupon_data->use_limit) {
                $message = 'Your coupon limit has been reached.';
                return response()->json(['message' => $message, 'status' => false], 200);
            } else {
                $redeemCoupon = [
                    'coupon_code' => $data['coupon_code'],
                    'discount' => $data['couponDiscountamount'],
                    'user_id' => $data['user_id'],
                    'coupon_id' => $coupon_data->id,
                    'booking_id' => $data['booking_id'],
                ];

                UserCouponRedeem::create($redeemCoupon);
                if ($coupon->count() == $coupon_data->use_limit) {
                    $expired['is_expired'] = 1;
                    Coupon::where('coupon_code', $data['coupon_code'])->update($expired);
                }
                return response()->json(['message' => 'Coupon redeemed successfully', 'status' => true, 'data' => $redeemCoupon], 200);
            }
        } else {
            $message = 'Coupon not found.';
            return response()->json(['message' => $message, 'status' => false], 404);
        }
    }
}
