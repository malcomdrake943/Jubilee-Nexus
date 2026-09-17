<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt - {{ $order->order_number }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; color: #333; margin: 0; padding: 0; }
        .container { width: 100%; margin: 0 auto; padding: 20px; }
        .header { border-bottom: 2px solid #047857; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { margin: 0; color: #047857; font-size: 28px; }
        .header p { margin: 5px 0 0 0; color: #6b7280; font-size: 14px; }
        .row { width: 100%; clear: both; margin-bottom: 20px; }
        .col-half { width: 48%; float: left; }
        .col-half.right { float: right; }
        h3 { font-size: 16px; color: #111827; margin-bottom: 10px; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; }
        p { margin: 0 0 5px 0; line-height: 1.5; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .table th { background-color: #f9fafb; font-weight: bold; color: #374151; }
        .totals-table { width: 50%; float: right; border-collapse: collapse; }
        .totals-table td { padding: 8px 12px; text-align: right; border-bottom: 1px solid #e5e7eb; }
        .totals-table td.label { font-weight: bold; color: #374151; text-align: left; }
        .totals-table tr.total td { font-size: 18px; font-weight: bold; color: #047857; border-bottom: none; border-top: 2px solid #047857; }
        .footer { text-align: center; color: #9ca3af; font-size: 12px; margin-top: 50px; border-top: 1px solid #e5e7eb; padding-top: 20px; clear: both; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; background: #ecfdf5; color: #065f46; font-size: 12px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Jubilee Nexus</h1>
            <p>Order Receipt &bull; {{ date('M d, Y') }}</p>
        </div>

        <div class="row">
            <div class="col-half">
                <h3>Order Details</h3>
                <p><strong>Order Number:</strong> {{ $order->order_number }}</p>
                <p><strong>Tracking:</strong> {{ $order->tracking_number ?? 'Pending' }}</p>
                <p><strong>Status:</strong> <span class="badge">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span></p>
                <p><strong>Date:</strong> {{ $order->created_at->format('M d, Y h:i A') }}</p>
            </div>
            <div class="col-half right">
                <h3>Customer Details</h3>
                <p><strong>Name:</strong> {{ $order->customer_name }}</p>
                <p><strong>Email:</strong> {{ $order->customer_email }}</p>
                <p><strong>Phone:</strong> {{ $order->customer_phone }}</p>
                <p><strong>Address:</strong><br>
                    {{ $order->shipping_address['line1'] ?? '' }}<br>
                    @if(!empty($order->shipping_address['line2']))
                        {{ $order->shipping_address['line2'] }}<br>
                    @endif
                    {{ $order->shipping_address['city'] ?? '' }}, {{ $order->shipping_address['state'] ?? '' }} {{ $order->shipping_address['postal_code'] ?? '' }}<br>
                    {{ $order->shipping_address['country'] ?? '' }}
                </p>
            </div>
        </div>

        <h3>Product Information</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Platform</th>
                    <th>Qty</th>
                    <th>Est. Price</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        {{ $order->product_name ?? 'Custom Product' }}<br>
                        <a href="{{ $order->product_url }}" style="font-size: 11px; color: #047857;">Original Link</a>
                    </td>
                    <td>{{ ucfirst($order->source_platform) }}</td>
                    <td>{{ $order->quantity }}</td>
                    <td>${{ number_format($order->estimated_product_price, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="row">
            <div class="col-half">
                <h3>Shipping & Handling</h3>
                <p><strong>Method:</strong> {{ $order->shipping_method ?? 'Standard' }}</p>
                <p><strong>Size Tier:</strong> {{ ucfirst($order->size_tier) }}</p>
                @if($order->product_weight)
                    <p><strong>Weight:</strong> {{ $order->product_weight }} kg</p>
                @endif
            </div>
            <div class="col-half right">
                <table class="totals-table">
                    <tr>
                        <td class="label">Product Subtotal (Est.)</td>
                        <td>${{ number_format($order->estimated_product_price * $order->quantity, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Service Fee</td>
                        <td>${{ number_format($order->service_fee, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Handling Fee ({{ ucfirst($order->size_tier) }})</td>
                        <td>${{ number_format($order->size_handling_fee, 2) }}</td>
                    </tr>
                    <tr class="total">
                        <td class="label">Total Paid</td>
                        <td>${{ number_format($order->total_charged, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="footer">
            Thank you for using Jubilee Nexus! If you have any questions about this receipt, please contact support.
        </div>
    </div>
</body>
</html>
