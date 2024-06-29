<?php

namespace Modules\Product\Trait;

use App\Jobs\BulkNotification;

trait OrderTrait
{
    protected function sendNotificationOnOrderUpdate($type, $order)
    {
        $data = mail_footer($type, $order);

        $data['order'] = $order;

        BulkNotification::dispatch($data);
    }
}
