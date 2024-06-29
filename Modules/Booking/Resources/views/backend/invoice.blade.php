@extends('backend.layouts.app')

@section('title')
    {{ __($module_title) }}
@endsection

@section('content')
    <style>
        .alternate-list {
            display: flex;
            flex-direction: column;
            margin-bottom: 0;
        }
        .alternate-list li:not(:last-child){
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--bs-border-color);
        }
    </style>

<style type="text/css" media="print">
      @page :footer {
        display: none !important;
      }

      @page :header {
        display: none !important;
      }
      @page { size: landscape; }
      /* @page { margin: 0; } */

      .pr-hide {
        display: none;
        }

      button {
        display: none !important;
      }
      * {
        -webkit-print-color-adjust: none !important;   /* Chrome, Safari 6 – 15.3, Edge */
        color-adjust: none !important;                 /* Firefox 48 – 96 */
        print-color-adjust: none !important;           /* Firefox 97+, Safari 15.4+ */
      }
      .badge {
        font-size: 1rem;
        padding: 0;
      }
    </style>
    <div class="row pr-hide">
        <div class="col-12">
            <div class="card ">
                <div class="card-header border-bottom-0">
                    <div class="row pr-hide">
                        <div class="col-auto col-lg-12 mb-4 text-center text-lg-end">
                            <a class="btn btn-primary" href="{{route('backend.bookings.downloadinvoice', ['id' => request()->id])}}">
                                <i class="fa-solid fa-download"></i>
                                {{ __('booking.download_invoice') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!--Main Invoice-->
        <div class="col-xl-12 order-2 order-md-2 order-lg-2 order-xl-1">
            <div class="card mb-4" id="section-1">
                <div class="card-body">
                    <!--Order Detail-->
                    <div class="row justify-content-between align-items-center g-3 mb-4">
                        <div class="col-auto flex-grow-1">
                            <img src="{{ asset(setting('logo')) }}" alt="logo" class="img-fluid" width="200">
                        </div>
                        <div class="col-auto text-end">
                            <h5 class="mb-0">Invoice{{ __('booking.download_invoice') }}
                                <span
                                    class="text-accent">{{ setting('inv_prefix') }}{{ $data->booking->resource->id }}
                                </span>
                            </h5>
                            <span class="text-muted">{{ __('booking.invoice_data') }}:
                                {{ date('d M, Y', strtotime($data->booking->resource->created_at)) }}
                            </span>
                        </div>
                    </div>
                    <div class="d-flex justify-content-md-between justify-content-center g-3">
                        <div class="col-auto">
                            <!--Customer Detail-->
                            <div class="welcome-message">
                                <h5 class="mb-2">{{ __('booking.customer_info') }}</h6>
                                    <p class="mb-0">{{ __('booking.name') }}: <strong>{{ optional($data->booking->resource->user)->full_name }}</strong></p>
                                    <p class="mb-0">{{ __('booking.email') }}: <strong>{{ optional($data->booking->resource->user)->email }}</strong></p>
                                    <p class="mb-0">{{ __('booking.phone') }}: <strong>{{ optional($data->booking->resource->user)->mobile }}</strong></p>
                            </div>
                            <div class="col-auto mt-3">
                                <h6 class="d-inline-block">{{ __('booking.payment_method') }}: </h6>
                                <span class="badge bg-primary">{{ ucwords(str_replace('_', ' ', optional($data->booking_transaction)->transaction_type)) }}</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            @php
                                $billingAddress = $data->booking->resource->branch->address;
                                    $productPrice = 0;
                            @endphp
                            @if($billingAddress)
                                <div class="shipping-address d-flex justify-content-md-end gap-3 mb-3" style="min-width: 500px">
                                    <div class="w-25">
                                        <h5 class="mb-2">{{ __('booking.billing_address') }}</h5>
                                        <p class="mb-0 text-wrap">

                                            {{ optional($billingAddress)->address_line_1 }},
                                            {{ optional($billingAddress)->city }},
                                            {{ optional($billingAddress)->state }},
                                            {{ optional($billingAddress)->country }}
                                        </p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!--order details-->
                <table class="table table-bordered border-top" data-use-parent-width="true">
                    <thead>
                        <tr>
                            <th>{{ __('booking.services') }}/{{ __('booking.products') }}</th>
                            <th>{{ __('booking.unit_price') }}</th>
                            <th>QTY{{ __('booking.qty') }}</th>
                            <th class="text-end">{{ __('booking.total_price') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($data->booking->resource->services as $key => $value)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="ms-2">
                                        <h6 class="fs-sm mb-0">
                                            {{ $value->service_name }}
                                        </h6>
                                    </div>
                                </div>
                            </td>

                            <td class="">
                                <span class="fw-bold">{{ \Currency::format($value->service_price) }}
                                </span>
                            </td>

                            <td class="fw-bold">1</td>

                            <td class=" text-end">
                                <span class="text-accent fw-bold">{{ \Currency::format($value->service_price) }}
                                </span>

                            </td>

                        </tr>
                        @endforeach
                        @foreach ($data->booking->resource->products as $key => $value)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="ms-2">
                                            <h6 class="fs-sm mb-0">
                                                {{ $value->product_name }}
                                            </h6>
                                        </div>
                                    </div>
                                </td>
                                @php
                                    $price = $value->product_price;
                                    $delPrice = false;
                                    $discountType = $value->discount_type;
                                    $discountValue = $value->discount_value . ($discountType == 'percent' ? '%' : '');
                                    if($price != $value->discounted_price) {
                                        $delPrice = $price;
                                        $price = $value->discounted_price;
                                    }
                                    $productPrice=($price * $value->product_qty)+$productPrice
                                @endphp
                                <td class="">
                                    <div class="d-flex gap-3 align-items-center">
                                        <span class="fw-bold">
                                            {{ \Currency::format($price) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="fw-bold">{{ $value->product_qty }}</td>

                                <td class=" text-end">
                                    <span class="text-accent fw-bold">{{ \Currency::format($price * $value->product_qty) }}
                                    </span>
                                </td>

                            </tr>
                            @endforeach
                    </tbody>
                    <tfoot class="text-end">
                        <tr>
                            <td colspan="3">
                                <h6 class="d-inline-block me-3">{{ __('booking.sub_total') }}: </h6>
                            </td>
                            <td width="10%">
                                <strong>{{ \Currency::format($data->services_total_amount + $productPrice )}}</strong></td>
                        </tr>
                        <tr>
                            <td colspan="3">
                                <h6 class="d-inline-block me-3">{{ __('booking.tips') }}: </h6>
                            </td>
                            <td width="10%" class="text-end">
                                <strong>{{ \Currency::format(optional($data->booking_transaction)->tip_amount) }}</strong></td>
                        </tr>
                            <td colspan="3">
                                <h6 class="d-inline-block me-3">{{ __('booking.tax') }}: </h6>
                            </td>
                            <td width="10%" class="text-end">
                                <strong>{{ \Currency::format($data->tax_amount) }}</strong></td>
                        </tr>
                        @if($data->coupon_discount>0)
                        <tr>
                        <td colspan="3">
                                <h6 class="d-inline-block me-3">{{ __('booking.coupondiscount') }}: </h6>
                            </td>
                            <td width="10%" class="text-end">
                                <strong>{{ \Currency::format($data->coupon_discount) }}</strong></td>
                        </tr>
                        @endif
                        <tr>
                        <tr>
                            <td colspan="3">
                                <h6 class="d-inline-block me-3">{{ __('booking.grand_total') }}: </h6>
                            </td>
                            <td width="10%" class="text-end"><strong
                                    class="text-accent">{{ \Currency::format($data->grand_total) }}</strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <!--Note-->
                <div class="card-body">
                    <div class="card-footer border-top-0 px-4 py-4 rounded bg-soft-gray border border-2">
                        <p class="mb-0">{{ setting('spacial_note') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after-scripts')
    <script>
        function invoicePrint() {
            window.print()
        }

        function updateStatusAjax(__this, url) {
            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: {
                    order_id: {{ $data->booking->resource->id }},
                    status: __this.val(),
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    if (res.status) {
                        window.successSnackbar(res.message)
                        setTimeout(() => {
                            location.reload()
                        }, 100);
                    }
                }
            });
        }
        $('[name="payment_status"]').on('change', function() {
            if ($(this).val() !== '') {
                updateStatusAjax($(this), "{{ route('backend.orders.update_payment_status') }}")
            }
        })

        $('[name="delivery_status"]').on('change', function() {
            if ($(this).val() !== '') {
                updateStatusAjax($(this), "{{ route('backend.orders.update_delivery_status') }}")
            }
        })
    </script>
@endpush
