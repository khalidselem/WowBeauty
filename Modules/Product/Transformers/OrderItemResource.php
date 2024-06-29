<?php

namespace Modules\Product\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        return [

            'id' => $this->id,
            'product_name' => $this->product_variation->product->name,
            'qty' => $this->qty,
            'product_image' => optional($this->product_variation)->product ? $this->product_variation->product->media->pluck('original_url')->first() : null,
            'product_id' => optional($this->product_variation)->product_id,
            'product_variation_id' => optional($this->product_variation)->id,
            'product_variation_type' => $this->product_variation?->combination?->variation_combination_data?->name ?? null,
            'product_variation_name' => $this->product_variation?->combination?->variation_combination_value?->name ?? null,
            'product_variation_value' => $this->product_variation?->combination->variation_combination_value?->value ?? null,
            'tax_include_product_price' => optional($this->product_variation)->price,
            'get_product_price' => optional($this->product_variation) ? getDiscountedProductPrice($this->product_variation->price, $this->product_variation->product_id) : null,
            'product_amount' => optional($this->product_variation) ? getDiscountedProductPrice($this->product_variation->price, $this->product_variation->product_id) * $this->qty : null,
            'discount_value' => optional(optional($this->product_variation)->product)->discount_value,
            'discount_type' => optional(optional($this->product_variation)->product)->discount_type,
            'product_review' => $this->review,
        ];
    }
}
