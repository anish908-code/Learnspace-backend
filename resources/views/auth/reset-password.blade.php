<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - LearnSpace</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #0f0f0f;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
        }
        h2 {
            color: #fff;
            font-size: 22px;
            margin-bottom: 8px;
        }
        p {
            color: #888;
            font-size: 14px;
            margin-bottom: 24px;
        }
        label {
            color: #ccc;
            font-size: 13px;
            display: block;
            margin-bottom: 6px;
        }
        input {
            width: 100%;
            padding: 12px 14px;
            background: #111;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            margin-bottom: 16px;
            outline: none;
            transition: border 0.2s;
        }
        input:focus { border-color: #6c5ce7; }
        button {
            width: 100%;
            padding: 12px;
            background: #6c5ce7;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover { background: #5a4bd1; }
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        .msg {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
            display: none;
        }
        .msg.success { display: block; background: #0d3320; color: #00e676; border: 1px solid #1b5e20; }
        .msg.error { display: block; background: #331212; color: #ff5252; border: 1px solid #b71c1c; }
        .expired {
            text-align: center;
            color: #888;
        }
        .expired a { color: #6c5ce7; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <div id="form-section">
            <h2>Reset Password</h2>
            <p>Enter your new password below.</p>

            <div id="msg" class="msg"></div>

            <form id="resetForm">
                <label>New Password</label>
                <input type="password" id="password" placeholder="Min 8 characters" required>

                <label>Confirm Password</label>
                <input type="password" id="password_confirmation" placeholder="Re-enter password" required>

                <button type="submit" id="btn">Reset Password</button>
            </form>
        </div>

        <div id="expired-section" class="expired" style="display:none;">
            <h2>Link Expired</h2>
            <p style="margin-top:10px;">This reset link is invalid or has expired.</p>
            <p style="margin-top:10px;"><a href="{{ config('app.frontend_url') }}/forgot-password">Request a new link</a></p>
        </div>
    </div>

    <script>
        const params = new URLSearchParams(window.location.search);
        const token = params.get('token');
        const email = params.get('email');

        if (!token || !email) {
            document.getElementById('form-section').style.display = 'none';
            document.getElementById('expired-section').style.display = 'block';
        }

        document.getElementById('resetForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btn');
            const msg = document.getElementById('msg');
            const password = document.getElementById('password').value;
            const password_confirmation = document.getElementById('password_confirmation').value;

            btn.disabled = true;
            btn.textContent = 'Resetting...';
            msg.className = 'msg';
            msg.style.display = 'none';

            try {
                const res = await fetch('/api/auth/reset-password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, token, password, password_confirmation })
                });

                const data = await res.json();

                if (res.ok) {
                    msg.className = 'msg success';
                    msg.textContent = 'Password reset successful! Redirecting to login...';
                    msg.style.display = 'block';
                    setTimeout(() => { window.location.href = '{{ config('app.frontend_url') }}/login'; }, 2000);
                } else {
                    msg.className = 'msg error';
                    msg.textContent = data.message || 'Something went wrong';
                    msg.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = 'Reset Password';
                }
            } catch (err) {
                msg.className = 'msg error';
                msg.textContent = 'Network error. Please try again.';
                msg.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Reset Password';
            }
        });
    </script>
</body>
</html>
