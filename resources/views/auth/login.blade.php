<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff sign in · {{ config('app.name', 'Barangay Portal') }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at 15% 10%,#dbeafe,transparent 34%),#f3f6fb;color:#14243a;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}.card{width:min(100%,440px);padding:38px;background:#fff;border:1px solid #e3eaf3;border-radius:24px;box-shadow:0 24px 70px #1c35521a}.mark{width:48px;height:48px;display:grid;place-items:center;border-radius:15px;background:#e8f1ff;color:#2563eb;font-size:24px}.eyebrow{margin:24px 0 8px;color:#2563eb;font-size:12px;font-weight:800;letter-spacing:.13em;text-transform:uppercase}h1{margin:0;font-size:30px;letter-spacing:-.04em}p{color:#64748b;line-height:1.6}.field{margin-top:20px}label{display:block;margin-bottom:8px;font-size:13px;font-weight:700}input{width:100%;padding:13px 14px;border:1px solid #d9e2ee;border-radius:11px;background:#fbfdff;font:inherit;outline:none}input:focus{border-color:#4383ef;box-shadow:0 0 0 3px #2563eb1a}.error{margin:8px 0 0;color:#b42318;font-size:13px}.submit{width:100%;margin-top:24px;padding:14px;border:0;border-radius:11px;background:#175cd3;color:#fff;font:inherit;font-weight:750;cursor:pointer}.submit:hover{background:#1849a9}.hint{margin-top:22px;padding-top:18px;border-top:1px solid #edf1f6;color:#718096;font-size:13px}
        .password-control{position:relative}.password-control input{padding-right:48px}.password-toggle{position:absolute;top:50%;right:8px;display:grid;width:36px;height:36px;place-items:center;transform:translateY(-50%);border:0;border-radius:8px;background:transparent;color:#64748b;cursor:pointer}.password-toggle:hover{background:#edf3fb;color:#175cd3}.password-toggle:focus-visible{outline:2px solid #4383ef;outline-offset:2px}.password-toggle svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
    </style>
</head>
<body>
    <main class="card">
        <div class="mark" aria-hidden="true">✦</div>
        <div class="eyebrow">Barangay services</div>
        <h1>Staff sign in</h1>
        <p>Sign in with your admin or staff account to create and manage community announcements.</p>
        <form method="post" action="{{ route('login.store') }}">
            @csrf
            <div class="field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                @error('email')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="password-control">
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                    <button class="password-toggle" type="button" id="password-toggle" aria-label="Show password" aria-pressed="false" title="Show password">
                        <svg id="password-hidden-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"></path><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c5.5 0 9 7 9 7a15.8 15.8 0 0 1-3.1 3.9"></path><path d="M6.2 6.2C3.9 7.7 3 12 3 12s3.5 7 9 7a10.8 10.8 0 0 0 4-.8"></path></svg>
                        <svg id="password-visible-icon" viewBox="0 0 24 24" aria-hidden="true" hidden><path d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                    </button>
                </div>
                @error('password')<div class="error">{{ $message }}</div>@enderror
            </div>
            <button class="submit" type="submit">Sign in to announcements</button>
        </form>
        <div class="hint">Access is limited to authorized administrators and staff.</div>
    </main>
    <script>
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.getElementById('password-toggle');
        const hiddenIcon = document.getElementById('password-hidden-icon');
        const visibleIcon = document.getElementById('password-visible-icon');

        passwordToggle.addEventListener('click', () => {
            const isVisible = passwordInput.type === 'password';
            passwordInput.type = isVisible ? 'text' : 'password';
            hiddenIcon.hidden = isVisible;
            visibleIcon.hidden = !isVisible;
            passwordToggle.setAttribute('aria-pressed', String(isVisible));
            passwordToggle.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
            passwordToggle.title = isVisible ? 'Hide password' : 'Show password';
        });
    </script>
</body>
</html>
