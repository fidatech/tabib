<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>New registration</title></head>
<body style="margin:0;padding:24px 12px;background:#f3f5f9;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#2a3142">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">
<table role="presentation" width="560" cellspacing="0" cellpadding="0" style="max-width:560px;width:100%;background:#ffffff;border:1px solid #e6eaf1;border-radius:14px">
    <tr><td style="padding:24px 28px 8px">
        <p style="margin:0;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#7b8498">{{ config('app.name') }}</p>
        <h1 style="margin:6px 0 4px;font-size:20px;line-height:1.3">New registration</h1>
        <p style="margin:0;font-size:14px;color:#5b6478">A clinic registered on the website. Nothing is made for it yet: its database and address are created when you approve.</p>
    </td></tr>
    <tr><td style="padding:12px 28px">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px;border-top:1px solid #edf0f5">
            @foreach (array_filter([
                'Clinic' => trim($signup->name.($signup->name_local ? ' · '.$signup->name_local : '')),
                'Address' => $signup->domain(),
                'Licence number' => $signup->licence_no,
                'Province' => $signup->province,
                'Dependent branches' => (string) $signup->branches,
                'Owner' => $signup->owner_name,
                'Email' => $signup->owner_email,
                'Phone' => $signup->owner_phone,
                'Reseller' => $signup->seller?->name,
                'Message' => $signup->message,
                'Sent on' => $signup->created_at?->format('Y-m-d H:i'),
            ], fn ($v) => filled($v)) as $label => $value)
                <tr><td style="padding:9px 0;border-bottom:1px solid #edf0f5;color:#7b8498;width:38%;vertical-align:top">{{ $label }}</td><td style="padding:9px 0;border-bottom:1px solid #edf0f5;font-weight:600">{{ $value }}</td></tr>
            @endforeach
        </table>
    </td></tr>
    <tr><td style="padding:14px 28px 8px">
        <a href="{{ $approveUrl }}" style="display:inline-block;margin:0 8px 8px 0;padding:12px 26px;border-radius:10px;background:#16a34a;color:#ffffff;font-weight:700;font-size:15px;text-decoration:none">Approve</a>
        <a href="{{ $rejectUrl }}" style="display:inline-block;margin:0 0 8px;padding:12px 26px;border-radius:10px;background:#dc2626;color:#ffffff;font-weight:700;font-size:15px;text-decoration:none">Reject</a>
    </td></tr>
    <tr><td style="padding:4px 28px 24px;font-size:12.5px;color:#7b8498">Each button opens a page where you confirm the decision; nothing changes before that. The buttons work for 30 days. You can also decide in the panel under Registration requests, where spam can be deleted.</td></tr>
</table>
</td></tr></table>
</body>
</html>
