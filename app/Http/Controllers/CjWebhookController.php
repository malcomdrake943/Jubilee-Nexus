<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\StoreProduct;
use App\Notifications\OrderShippedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CjWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $configuredSecret = config('services.cjdropshipping.webhook_secret');
        if (filled($configuredSecret)) {
            $providedSecret = $request->header('X-CJ-Webhook-Secret') ?: $request->query('secret');
            if (! hash_equals((string) $configuredSecret, (string) $providedSecret)) {
                return response()->json(['error' => 'Unauthorized webhook'], 401);
            }
        }

        $payload = $request->all();
        $type = strtoupper((string) ($payload['type'] ?? ''));
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : $payload;

        Log::info('CJdropshipping webhook received', [
            'type' => $type,
            'keys' => array_keys($params),
        ]);

        if (in_array($type, ['ORDER', 'LOGISTICS', ''], true)) {
            $this->handleOrderOrLogisticsUpdate($params);
        }

        if (in_array($type, ['STOCK', 'PRODUCT'], true)) {
            $this->handleInventoryOrProductUpdate($params);
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleOrderOrLogisticsUpdate(array $params): void
    {
        $cjOrderId = $params['orderId'] ?? $params['cjOrderId'] ?? null;
        $orderNumber = $params['orderNumber'] ?? $params['orderNum'] ?? null;

        if (! $cjOrderId && ! $orderNumber) {
            return;
        }

        $order = Order::query()
            ->when($cjOrderId, fn ($q) => $q->where('cj_order_id', $cjOrderId))
            ->when(! $cjOrderId && $orderNumber, fn ($q) => $q->where('order_number', $orderNumber))
            ->first();

        if (! $order && $orderNumber) {
            $order = Order::where('order_number', $orderNumber)->first();
        }

        if (! $order) {
            return;
        }

        $cjStatus = $params['orderStatus'] ?? $params['status'] ?? null;
        $trackNumber = $params['trackNumber'] ?? $params['trackingNumber'] ?? null;
        $logisticName = $params['logisticName'] ?? $params['trackingCarrier'] ?? null;

        $updates = [];
        if (filled($cjOrderId) && blank($order->cj_order_id)) {
            $updates['cj_order_id'] = $cjOrderId;
        }
        if (filled($cjStatus)) {
            $updates['cj_order_status'] = strtolower((string) $cjStatus);
        }
        if (filled($logisticName)) {
            $updates['tracking_carrier'] = (string) $logisticName;
        }
        if (filled($trackNumber)) {
            $updates['tracking_number'] = (string) $trackNumber;
        }

        if (! empty($updates)) {
            $order->update($updates);
        }

        if (filled($trackNumber) && $order->status !== 'shipped' && $order->status !== 'delivered') {
            $carrier = $order->tracking_carrier ?: 'CJ Logistics';
            $order->recordStatusChange('shipped', "CJ Webhook Tracking: {$carrier} {$trackNumber}");

            try {
                $order->notify(new OrderShippedNotification($order));
            } catch (\Throwable $e) {
                Log::warning('CJ webhook OrderShippedNotification failed: ' . $e->getMessage());
            }
        } elseif ($cjStatus && strtoupper((string) $cjStatus) === 'DELIVERED' && $order->status !== 'delivered') {
            $order->recordStatusChange('delivered', 'Marked delivered via CJdropshipping webhook');
        }
    }

    private function handleInventoryOrProductUpdate(array $params): void
    {
        $pid = $params['pid'] ?? $params['productId'] ?? null;
        $stock = $params['storageNum'] ?? $params['stock'] ?? null;

        if (blank($pid) || ! is_numeric($stock)) {
            return;
        }

        StoreProduct::where('cj_pid', $pid)->update([
            'stock' => max(0, (int) $stock),
        ]);
    }
}
