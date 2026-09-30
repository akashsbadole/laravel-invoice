<p>Hi {{ $customer->full_name }},</p>

<p>
    Here is your secure link to view your invoices and payment status.
    It expires in 30 minutes and works only once:
</p>

<p><a href="{{ $url }}">{{ $url }}</a></p>

<p>If you didn't request this, you can safely ignore this email.</p>
