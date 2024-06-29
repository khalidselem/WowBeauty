<style type="text/css">
    @import url(https://fonts.googleapis.com/css?family=Open+Sans:400,700);

    * {
        box-sizing: border-box;
    }

    pre,
    p {
        padding: 0;
        margin: 0;
    }

    table {
        font-family: 'Open Sans', sans-serif;
        width: 100%;
        border-collapse: collapse;
        padding: 1px;
    }

    td,
    th {
        text-align: left;
    }

    .visibleMobile {
        display: none;
    }

    .hiddenMobile {
        display: block;
    }
</style>

{{-- header start --}}
<table style="width: 100%; table-layout: fixed">
    <tr>
        <td colspan="4"
            style="border-right: 1px solid #e4e4e4; width: 300px; font-family: 'Open Sans', sans-serif; color: #323232; line-height: 1.5; vertical-align: top;">
            <p
                style="font-size: 15px; font-family: 'Open Sans', sans-serif; color: #5b5b5b; font-weight: bold; line-height: 1; vertical-align: top; ">
                INVOICE</p>
            <br>
            <p
                style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #5b5b5b; line-height: 24px; vertical-align: top;">
                Invoice No : {{ getSetting('order_code_prefix') }}
                {{ $order->orderGroup->order_code }}<br>
                Order Date : {{ date('d M, Y', strtotime($order->created_at)) }}
            </p>

            @if ($order->location_id != null)
                <p>
                    {{ optional($order->location)->name }}
                </p>
            @endif
        </td>
        <td colspan="4" align="right"
            style="width: 300px; text-align: right; padding-left: 50px; line-height: 1.5; color: #323232;">
            <img src="{{ uploadedAsset(getSetting('favicon')) }}" alt="logo" border="0" />
            <p
                style="font-size: 12px; font-family: 'Open Sans', sans-serif;font-weight: bold; color: #5b5b5b; line-height: 1; vertical-align: top; ">
                {{ setting('app_name') }}</p>
            <p
                style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #5b5b5b; line-height: 24px; vertical-align: top;">
                Phone: {{ setting('helpline_number') }}
            </p>
        </td>
    </tr>
    <tr class="visibleMobile">
        <td height="10"></td>
    </tr>
    <tr>
        <td colspan="10" style="border-bottom:1px solid #e4e4e4"></td>
    </tr>
</table>
{{-- header end --}}

{{-- billing and shipping start --}}
<table class="table" style="width: 100%;">
    <tbody style="display: table-header-group">
        <tr class="visibleMobile">
            <td height="20"></td>
        </tr>
        <tr style=" margin: 0;">
            <td colspan="4" style="width: 300px;">
                <p
                    style="font-size: 12px; font-family: 'Open Sans', sans-serif; font-weight: bold; color: #5b5b5b; line-height: 1; vertical-align: top; ">
                    SHIPPING INFORMATION</p>

                @php
                    $shippingAddress = $order->orderGroup->shippingAddress;
                @endphp
                <p
                    style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #5b5b5b; line-height: 24px; vertical-align: top;">

                    @if ($order->orderGroup->is_pos_order)
                        {{ $order->orderGroup->pos_order_address }}
                    @else
                        {{ optional($shippingAddress)->address }},
                        {{ optional(optional($shippingAddress)->city)->name }},
                        {{ optional(optional($shippingAddress)->state)->name }},<br>
                        {{ optional(optional($shippingAddress)->country)->name }}<br>
                    @endif
                    @if ($order->orderGroup->alternative_phone_no)
                        <br>
                        {{ localize('Alternative Phone') }}: {{ $order->orderGroup->alternative_phone_no }}
                    @endif
                    <br>
                    Logistic: {{ $order->logistic_name }}
                    <br>
                    @php
                        $deliveryInfo = json_decode($order->scheduled_delivery_info);
                    @endphp

                <p class="mb-0">Delivery Type:
                    <span
                        class="badge bg-primary">{{ Str::title(Str::replace('_', ' ', $order->shipping_delivery_type)) }}</span>


                </p>

                @if ($order->shipping_delivery_type == getScheduledDeliveryType())
                    <p class="mb-0">
                        Delivery Time:
                        {{ date('d F', $deliveryInfo->scheduled_date) }},
                        {{ $deliveryInfo->timeline }}</p>
                @endif
                </p>

            </td>


            @if (!$order->orderGroup->is_pos_order)
                <td colspan="4" style="width: 300px;">
                    <p
                        style="font-size: 11px; font-family: 'Open Sans', sans-serif; font-weight: bold; color: #5b5b5b; line-height: 1; vertical-align: top; ">
                        BILLING INFORMATION</p>
                    @php
                        $billingAddress = $order->orderGroup->billingAddress;
                    @endphp
                    <p
                        style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #5b5b5b; line-height: 24px; vertical-align: top;">
                        {{ optional($billingAddress)->address }},
                        {{ optional(optional($billingAddress)->city)->name }},
                        {{ optional(optional($billingAddress)->state)->name }},<br>
                        {{ optional(optional($billingAddress)->country)->name }}
                    </p>
                </td>
            @endif


        </tr>

    </tbody>
</table>
{{-- billing and shipping end --}}

{{-- item details start --}}
<table width="100%" border="0" cellpadding="0" cellspacing="0" align="center" class="fullTable" bgcolor="#ffffff">
    <tbody>
        <tr>
            <td>
                <table width="600" border="0" cellpadding="0" cellspacing="0" align="center" class="fullTable"
                    bgcolor="#ffffff">
                    <tbody>
                        <tr class="visibleMobile">
                            <td height="40"></td>
                        </tr>
                        <tr>
                            <td>
                                <table width="100%" border="0" cellpadding="0" cellspacing="0" align="center"
                                    class="fullPadding">
                                    <tbody>
                                        <tr>
                                            <th style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #000000; font-weight: normal;
                  line-height: 1; vertical-align: top; padding: 0 10px 7px 0;"
                                                width="52%" align="left">
                                                Item
                                            </th>
                                            <th style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #000000; font-weight: normal;
                  line-height: 1; vertical-align: top; padding: 0 0 7px;"
                                                align="left">
                                                Price
                                            </th>
                                            <th style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #000000; font-weight: normal;
                  line-height: 1; vertical-align: top; padding: 0 0 7px; text-align: center; "
                                                align="center">
                                                Qty
                                            </th>
                                            <th style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #000000; font-weight:
                  normal; line-height: 1; vertical-align: top; padding: 0 0 7px; text-align: right; "
                                                align="right">
                                                Subtotal
                                            </th>
                                        </tr>
                                        <tr>
                                            <td height="1" style="background: #e4e4e4;" colspan="4"></td>
                                        </tr>

                                        @foreach ($order->orderItems as $key => $item)
                                            @php
                                                $product = $item->product_variation->product;
                                            @endphp
                                            <tr>
                                                <td style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #5b5b5b;  line-height: 18px;  vertical-align: top; padding:10px 0;"
                                                    class="article">
                                                    <div>{{ $product->collectLocalization('name') }}</div>
                                                    <div class="text-muted">
                                                        @foreach (generateVariationOptions($item->product_variation->combinations) as $variation)
                                                            <span class="fs-xs">
                                                                {{ $variation['name'] }}:
                                                                @foreach ($variation['values'] as $value)
                                                                    {{ $value['name'] }}
                                                                @endforeach
                                                                @if (!$loop->last)
                                                                    ,
                                                                @endif
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </td>
                                                <td
                                                    style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #646a6e;  line-height:
              18px;  vertical-align: top; padding:10px 0;">
                                                    {{ formatPrice($item->unit_price) }}</td>
                                                <td style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #646a6e;  line-height:
              18px;  vertical-align: top; padding:10px 0; text-align: center;"
                                                    align="center">{{ $item->qty }}</td>
                                                <td style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #1e2b33;  line-height:
              18px;  vertical-align: top; padding:10px 0; text-transform: capitalize !important;"
                                                    align="right">
                                                    @if ($item->refundRequest && $item->refundRequest->refund_status == 'refunded')
                                                        ({{ $item->refundRequest->refund_status }})
                                                    @endif
                                                    <strong>{{ \Currency::format($item->total_price) }}
                                                    </strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td height="1" style="background: #e4e4e4;" colspan="4"></td>
                                            </tr>
                                        @endforeach

                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td height="20"></td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>
{{-- item details end --}}

{{-- item total start --}}
<table width="100%" border="0" cellpadding="0" cellspacing="0" align="center" class="fullTable" bgcolor="#ffffff">
    <tbody>
        <tr>
            <td>
                <table width="600" border="0" cellpadding="0" cellspacing="0" align="center" class="fullTable"
                    bgcolor="#ffffff">
                    <tbody>
                        <tr>
                            <td>
                                <!-- Table Total -->
                                <table width="100%" border="0" cellpadding="0" cellspacing="0" align="center"
                                    class="fullPadding">
                                    <tbody>
                                        <tr>
                                            <td
                                                style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #646a6e; line-height: 22px; vertical-align: top; text-align:right; ">
                                                Subtotal
                                            </td>
                                            <td style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #646a6e; line-height: 22px; vertical-align: top; text-align:right; white-space:nowrap;"
                                                width="80">
                                                {{ \Currency::format($order->orderGroup->sub_total_amount) }}
                                            </td>
                                        </tr>

                                        <tr>
                                            <td
                                                style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #646a6e; line-height: 22px; vertical-align: top; text-align:right; ">
                                                Tips
                                            </td>
                                            <td style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #646a6e; line-height: 22px; vertical-align: top; text-align:right; white-space:nowrap;"
                                                width="80">
                                                {{ \Currency::format($order->orderGroup->total_tips_amount) }}
                                            </td>
                                        </tr>

                                        <tr>
                                            <td
                                                style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #646a6e; line-height: 22px; vertical-align: top; text-align:right; ">
                                                Shipping Cost
                                            </td>
                                            <td
                                                style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #646a6e; line-height: 22px; vertical-align: top; text-align:right; ">
                                                {{ \Currency::format($order->orderGroup->total_shipping_cost) }}
                                            </td>
                                        </tr>

                                        @if ($order->orderGroup->total_coupon_discount_amount > 0)
                                            <tr>
                                                <td
                                                    style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #646a6e; line-height: 22px; vertical-align: top; text-align:right; ">
                                                    Coupon Discount
                                                </td>
                                                <td
                                                    style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #646a6e; line-height: 22px; vertical-align: top; text-align:right; ">
                                                    {{ \Currency::format($order->orderGroup->total_coupon_discount_amount) }}
                                                </td>
                                            </tr>
                                        @endif

                                        <tr>
                                            <td
                                                style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #646a6e; line-height: 22px; vertical-align: top; text-align:right; ">
                                                Tax
                                            </td>
                                            <td
                                                style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #646a6e; line-height: 22px; vertical-align: top; text-align:right; ">
                                                {{ \Currency::format($order->orderGroup->total_tax_amount) }}
                                            </td>
                                        </tr>

                                        @if ($order->orderGroup->is_pos_order)
                                            <tr>
                                                <td
                                                    style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #646a6e; line-height: 22px; vertical-align: top; text-align:right; ">
                                                    Discount
                                                </td>
                                                <td
                                                    style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #646a6e; line-height: 22px; vertical-align: top; text-align:right; ">
                                                    {{ \Currency::format($order->orderGroup->total_discount_amount) }}
                                                </td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td
                                                style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #000; line-height: 22px; vertical-align: top; text-align:right; ">
                                                <strong>Grand Total</strong>
                                            </td>
                                            <td
                                                style="font-size: 12px; font-family: 'Open Sans', sans-serif; color: #000; line-height: 22px; vertical-align: top; text-align:right; ">
                                                <strong>{{ \Currency::format($order->orderGroup->grand_total_amount) }}</strong>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <!-- /Table Total -->
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>
{{-- item total end --}}

{{-- footer start --}}
<table width="100%" border="0" cellpadding="0" cellspacing="0" align="center" class="fullTable"
    bgcolor="#ffffff">

    <tr>
        <td>
            <table width="600" border="0" cellpadding="0" cellspacing="0" align="center" class="fullTable"
                bgcolor="#ffffff" style="border-radius: 0 0 10px 10px;">
                <tr>
                <tr class="hiddenMobile">
                    <td height="30"></td>
                </tr>
                <tr class="visibleMobile">
                    <td height="20"></td>
                </tr>
                <td>
                    <table width="100%" border="0" cellpadding="0" cellspacing="0" align="center"
                        class="fullPadding">
                        <tbody>
                            <tr>
                                <td
                                    style="font-size: 12px; color: #5b5b5b; font-family: 'Open Sans', sans-serif; line-height: 18px; vertical-align: top; text-align: left;">
                                    <p
                                        style="font-size: 12px; color: #5b5b5b; font-family: 'Open Sans', sans-serif; line-height: 18px; vertical-align: top; text-align: left;">
                                        Hello <strong>{{ optional($order->user)->name }},</strong>
                                        <br>
                                        {{ setting('spacial_note') }}
                                    </p>
                                    <br><br>
                                    <p
                                        style="font-size: 12px; color: #5b5b5b; font-family: 'Open Sans', sans-serif; line-height: 18px; vertical-align: top; text-align: left;">
                                        Best Regards,
                                        <br>{{ setting('app_name') }} <br>
                                        Email: {{ setting('inquriy_email') }}<br>
                                        Website: {{ env('APP_URL') }}
                                    </p>

                                </td>
                            </tr>
                        </tbody>
                    </table>
                </td>
    </tr>
</table>
</td>
</tr>
</table>
{{-- footer end --}}
