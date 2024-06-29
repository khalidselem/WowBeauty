<?php

namespace Modules\Booking\Trait;

use App\Jobs\BulkNotification;
use Modules\Booking\Models\BookingProduct;
use Modules\Booking\Models\BookingService;
use Modules\BussinessHour\Models\BussinessHour;
use Modules\Promotion\Models\UserCouponRedeem;

trait BookingTrait
{
    public function updateBookingService($data, $booking_id)
    {
        $serviceData = collect($data);
        $serviceId = $serviceData->pluck('service_id')->toArray();
        $bookingService = BookingService::where('booking_id', $booking_id);
        if (count($serviceId) > 0) {
            $bookingService = $bookingService->whereNotIn('service_id', $serviceId);
        }
        $bookingService->delete();
        foreach ($serviceData as $key => $value) {
            BookingService::updateOrCreate(['booking_id' => $booking_id, 'service_id' => $value['service_id'], 'employee_id' => $value['employee_id']], [
                'sequance' => $key,
                'start_date_time' => $value['start_date_time'],
                'booking_id' => $booking_id,
                'service_id' => $value['service_id'],
                'employee_id' => $value['employee_id'],
                'service_price' => $value['service_price'] ?? 0,
                'service_amount' => $value['service_amount'] ?? 1,
                'duration_min' => $value['duration_min'] ?? 30,
            ]);
        }
    }

    public function updateBookingProduct($data, $booking_id)
    {
        $serviceData = collect($data);
        $serviceId = $serviceData->pluck('product_variation_id')->toArray();
        $bookingProduct = BookingProduct::where('booking_id', $booking_id);
        if (count($serviceId) > 0) {
            $bookingProduct = $bookingProduct->whereNotIn('product_variation_id', $serviceId);
        }
        $bookingProduct->delete();
        foreach ($serviceData as $key => $value) {
            BookingProduct::updateOrCreate(['booking_id' => $booking_id, 'product_variation_id' => $value['product_variation_id'], 'employee_id' => $value['employee_id']], [
                'booking_id' => $booking_id,
                'product_id' => $value['product_id'],
                'product_variation_id' => $value['product_variation_id'],
                'employee_id' => $value['employee_id'],
                'product_qty' => $value['product_qty'] ?? 1,
                'product_price' => $value['product_price'] ?? 0,
                'discounted_price' => $value['discounted_price'] ?? 0,
                'discount_value' => $value['discount_value'] ?? 0,
                'discount_type' => $value['discount_type'] ?? null,
                'variation_name' => $value['variation_name'] ?? null,

            ]);
        }
    }

    public function getSlots($date, $day, $branch_id, $employee_id = null)
    {
        $slotDay = BussinessHour::where(['day' => strtolower($day), 'branch_id' => $branch_id])->first();

        $slots[] = [
            'value' => '',
            'label' => 'No Slot Available',
            'disabled' => true,
        ];

        if (isset($slotDay)) {
            $start_time = strtotime($slotDay->start_time);
            $end_time = strtotime($slotDay->end_time);
            $slot_duration = setting('slot_duration');

            $slot_parts = explode(':', $slot_duration);
            $slot_hours = intval($slot_parts[0]);
            $slot_minutes = intval($slot_parts[1]);

            $slot_duration_minutes = $slot_hours * 60 + $slot_minutes;

            $current_time = $start_time;
            $slots = [];

            while ($current_time < $end_time) {

                // Check if the current date & time are greater than the slot time
                // Skip slots that overlap with break hours
                $is_break_hour = false;
                foreach ($slotDay->breaks as $break) {
                    $start_break = strtotime($break['start_break']);
                    $end_break = strtotime($break['end_break']);
                    if ($current_time >= $start_break && $current_time < $end_break) {
                        $current_time = $end_break;
                        $is_break_hour = true;
                        break;
                    }
                }

                if ($is_break_hour) {
                    continue; // Skip this iteration and proceed to the next slot
                }

                $slot_start = $current_time;
                $current_time += $slot_duration_minutes * 60;

                $startDateTime = date('Y-m-d', strtotime($date)).' '.date('H:i:s', $slot_start);
                $startTimestamp = strtotime($startDateTime);

                $endTimestamp = $startTimestamp + ($slot_duration_minutes * 60);

                // Check if the slot overlaps with any existing appointments
                $is_booked = false;
                if ($employee_id) {
                    $existingAppointments = BookingService::where('employee_id', $employee_id)
                        ->where('start_date_time', '<', date('Y-m-d H:i:s', $endTimestamp))
                        ->get();

                    foreach ($existingAppointments as $appointment) {
                        $appointment_start = strtotime($appointment->start_date_time);
                        $appointment_end = $appointment_start + ($appointment->duration_min * 60);

                        if ($startTimestamp >= $appointment_start && $startTimestamp < $appointment_end) {
                            $is_booked = true;
                            break;
                        }
                    }
                }

                if (! $is_booked) {
                    $slot = [
                        'value' => date('Y-m-d H:i:s', $startTimestamp),
                        'label' => date('h:i A', $slot_start),
                        'disabled' => false,
                    ];
                    $slots[] = $slot;
                }
            }
        }

        return $slots;
    }

    protected function sendNotificationOnBookingUpdate($type, $booking, $notify = true)
    {
        $data = mail_footer($type, $booking);

        $address = [
            'address_line_1' => $booking->branch->address->address_line_1,
            'address_line_2' => $booking->branch->address->address_line_2,
            'city' => $booking->branch->address->city,
            'state' => $booking->branch->address->state,
            'country' => $booking->branch->address->country,
            'postal_code' => $booking->branch->address->postal_code,
        ];

        $data['booking'] = [
            'id' => $booking->id,
            // 'logo' => config('setting_fields')['app']['elements'][8],
            'description' => $booking->note ?? 'Testing Note',
            'user_id' => $booking->user_id,
            'user_name' => optional($booking->user)->full_name ?? default_user_name(),
            'employee_id' => $booking->branch->employee->id,
            'employee_name' => $booking->services->first()->employee->full_name ?? 'Staff',
            'booking_date' => date('d/m/Y', strtotime($booking->start_date_time)),
            'booking_time' => date('h:i A', strtotime($booking->start_date_time)),
            'booking_duration' => $booking->services->sum('duration_min') ?? 0,
            'venue_address' => implode(', ', $address),
            'email' => $booking->user->email ?? null,
            'mobile' => $booking->user->mobile ?? null,
            'transaction_type' => optional($booking->payment)->transaction_type ?? 'default_value',
            'service_name' => implode(', ', $booking->mainServices->pluck('name')->toArray()),
            'service_price' => isset($booking->services[0]['service_price']) ? $booking->services[0]['service_price'] : 0,
            'serviceAmount' => $booking->detail['serviceAmount'] ?? 0,
            // 'services_total_amount' =>$booking->services->sum('service_price'),
            'product_name' => implode(', ', $booking->products->pluck('product_name')->toArray()),
            'product_price' => isset($booking->products[0]['product_price']) ? $booking->products[0]['product_price'] : 0,
            'product_qty' => isset($booking->products[0]['product_qty']) ? $booking->products[0]['product_qty'] : 0,
            // 'product_amount' => isset($booking->products[0]['product_amount']) ? $booking->products[0]['product_amount'] : 0,
              'tip_amount' => optional($booking->payment)->tip_amount ?? 'default_value',
            'tax_amount' => $booking->detail['tax_amount'] ?? 0,
            'grand_total' => $booking->detail['grand_total'] ?? 0,
            'coupon_discount'=>$booking->userCouponRedeem['discount']??0,
            'extra' => [
                'services' => $booking->services ? $booking->services->toArray() : [],
                'products' => $booking->products ? $booking->products->toArray() : [],
               'detail' => $booking->detail ? $booking->detail : []
            ]
        ];

        if($notify) {
            BulkNotification::dispatch($data);
        } else {
            return $data;
        }
    }
}
