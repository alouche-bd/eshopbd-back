<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New distributor order</title>
</head>
<body style="font-family: Arial, sans-serif; color: #222;">
    <h2>New distributor order received</h2>

    <p>A distributor has just placed an order on the eshop. The full export is attached as an .xlsx file.</p>

    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse; border: 1px solid #ccc;">
        <tr><td><strong>Customer reference</strong></td><td>{{ $order->customer_reference }}</td></tr>
        <tr><td><strong>Order ID</strong></td><td>{{ $order->id }}</td></tr>
        <tr><td><strong>Client code</strong></td><td>{{ $order->client_code }}</td></tr>
        <tr><td><strong>Client</strong></td><td>{{ $order->raison_sociale }}</td></tr>
        <tr><td><strong>Currency</strong></td><td>{{ $order->currency }}</td></tr>
        <tr><td><strong>Total HT</strong></td><td>{{ $order->total_ht }}</td></tr>
        <tr><td><strong>Total TTC</strong></td><td>{{ $order->total_ttc }}</td></tr>
    </table>

    <p>Please import the attached Excel file via the ADV_INTER upload page to forward it to Sage X3.</p>

    <p style="color: #888; font-size: 12px;">Automated message — do not reply.</p>
</body>
</html>
