<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email verification code</title>
</head>
<body style="margin: 0; padding: 32px 16px; background: #f8fafc; color: #0f172a; font-family: Arial, sans-serif;">
    <main style="max-width: 520px; margin: 0 auto; padding: 32px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;">
        <p style="margin: 0 0 8px; color: #2563eb; font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;">Barangay Citizen Services Portal</p>
        <h1 style="margin: 0 0 16px; font-size: 24px;">Verify your email</h1>
        <p style="margin: 0 0 24px; color: #475569; line-height: 1.6;">Hello {{ $name }}, enter this code in the registration page to verify your resident account.</p>
        <p style="margin: 0 0 24px; padding: 18px; border-radius: 12px; background: #eff6ff; color: #1d4ed8; font-size: 32px; font-weight: 700; letter-spacing: 10px; text-align: center;">{{ $code }}</p>
        <p style="margin: 0; color: #64748b; font-size: 13px; line-height: 1.6;">This code expires in 10 minutes. If you did not request it, you can ignore this email.</p>
    </main>
</body>
</html>
