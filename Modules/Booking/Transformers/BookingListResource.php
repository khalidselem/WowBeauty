<?php

namespace Modules\Booking\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Promotion\Models\UserCouponRedeem;
class BookingListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
       $service_amount = $this->booking_service ? $this->booking_service->sum(function ($service) {
            return $service->service_price * $service->service_amount;
        }) : 0;
        $couponAmount = UserCouponRedeem::where('booking_id', $this->id)->value('discount');
        $couponcut_amount = $service_amount - $couponAmount;
       $tax_details = getBookingTaxamount(($this->booking_service ? $this->booking_service->sum(function ($service) {
            return $service->service_price * $service->service_amount;
        }) : 0) + ($this->products ? $this->products->sum('discounted_price') : 0),($couponAmount), $this->payment ? $this->payment->tax_percentage : null);
        return [
            'id' => $this->id,
            'note' => $this->note,
            'start_date_time' => $this->start_date_time,
            'location' => $this->location,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch->name,
            'address_line_1' => $this->branch->address->address_line_1,
            'address_line_2' => $this->branch->address->address_line_2,
            'phone' => $this->branch->contact_number,
            'employee_id' => optional($this->booking_service->first())->employee_id,
            'employee_name' => optional($this->booking_service->first())->employee->full_name ?? '-',
            'employee_image' => optional($this->booking_service->first())->employee->profile_image ?? '-',
            'services' => $this->booking_service->map(function ($booking_service) {
                unset($booking_service['employee']);

                $booking_service['service_name'] = $booking_service['service']->name;
                $booking_service['service_image'] = $booking_service['service']->feature_image ?? '-';
                unset($booking_service['service']);

                return $booking_service;
            }),
            'user_id' => $this->user_id,
            'user_name' => optional($this->user)->full_name ?? default_user_name(),
            'user_profile_image' => optional($this->user)->profile_image ?? default_user_avatar(),
            'user_created' => optional($this->user)->created_at,
            'status' => $this->status,
            'created_by_name' => optional($this->createdUser)->full_name ?? default_user_name(),
            'updated_by_name' => optional($this->updatedUser)->full_name ?? default_user_name(),
            'created_at' => date('D, M Y', strtotime($this->created_at)),
            'updated_at' => date('D, M Y', strtotime($this->updated_at)),
            'payment' => $this->payment,
            'sumOfServicePrices' => $this->booking_service ? $this->booking_service->sum(function ($service) {
                return $service->service_price * $service->service_amount;
            }) : 0,
            'sumOfProductPrices' => $this->products ? $this->products->sum('discounted_price') : 0,
            'tax_amount' => $tax_details['total_tax_amount'],
            'total_amount' => ($couponcut_amount + ($this->products ? $this->products->sum('discounted_price') : 0) + $tax_details['total_tax_amount'] + ($this->payment ? $this->payment->tip_amount : 0)),
            'coupon_amount' => $couponAmount ?? 0,
        ];
    }
}
