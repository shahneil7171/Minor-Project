<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | KDP MART</title>
    <style>
        :root {
            color-scheme: dark;
            font-family: Inter, Arial, sans-serif;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #020617 0%, #0f3d91 38%, #1d4ed8 58%, #7f1d1d 100%);
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        body::before,
        body::after {
            content: '';
            position: fixed;
            border-radius: 999px;
            filter: blur(60px);
            opacity: 0.45;
            pointer-events: none;
            animation: drift 14s ease-in-out infinite alternate;
        }
        body::before {
            width: 420px;
            height: 420px;
            top: -80px;
            left: -90px;
            background: radial-gradient(circle, #60a5fa 0%, rgba(96,165,250,0) 70%);
        }
        body::after {
            width: 460px;
            height: 460px;
            right: -110px;
            bottom: -100px;
            background: radial-gradient(circle, #f87171 0%, rgba(248,113,113,0) 70%);
            animation-duration: 18s;
        }
        .card {
            width: 100%;
            max-width: 1040px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.16);
            background: linear-gradient(135deg, rgba(2, 6, 23, 0.92), rgba(15, 23, 42, 0.84));
            box-shadow: 0 30px 60px rgba(0,0,0,0.35);
            backdrop-filter: blur(20px);
            position: relative;
            z-index: 1;
        }
        .card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255,255,255,0.06), transparent 35%, rgba(255,255,255,0.04));
            pointer-events: none;
        }
        .hero {
            background: linear-gradient(135deg, #1e40af 0%, #0f172a 45%, #7f1d1d 100%);
            color: #f8fafc;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        .hero::after {
            content: '';
            position: absolute;
            inset: auto -40px -70px auto;
            width: 220px;
            height: 220px;
            border: 1px solid rgba(255,255,255,0.16);
            border-radius: 50%;
            transform: rotate(20deg);
        }
        .hero h1 { font-size: 2.3rem; margin: 0 0 12px; line-height: 1.1; }
        .hero p { margin: 0; font-size: 1rem; line-height: 1.6; }
        .hero-badge {
            margin-top: 30px;
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid rgba(255,255,255,0.14);
            border-radius: 16px;
            padding: 16px;
            backdrop-filter: blur(8px);
        }
        .hero-cta {
            display: inline-block;
            margin-top: 12px;
            background: #ffffff;
            color: #111827;
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: 700;
        }
        .hero-cta:hover { background: #e2e8f0; }
        .form-panel { padding: 40px; background: rgba(6, 12, 24, 0.65); }
        .eyebrow { font-size: 0.8rem; letter-spacing: 0.3em; text-transform: uppercase; color: #93c5fd; font-weight: 700; }
        .form-panel h2 { margin: 10px 0 8px; font-size: 1.8rem; }
        .form-panel .muted { color: #cbd5e1; margin: 0 0 24px; }
        .field { margin-bottom: 16px; }
        label { display: block; margin-bottom: 8px; font-size: 0.95rem; color: #e2e8f0; }
        .password-wrap { position: relative; }
        input {
            width: 100%;
            border: 1px solid rgba(148, 163, 184, 0.35);
            background: rgba(15, 23, 42, 0.9);
            color: #f8fafc;
            padding: 13px 14px;
            border-radius: 12px;
            font-size: 0.95rem;
            outline: none;
        }
        input:focus { border-color: #60a5fa; box-shadow: 0 0 0 3px rgba(96,165,250,0.2); }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #cbd5e1;
            cursor: pointer;
            padding: 6px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .toggle-password:hover { color: #f8fafc; background: rgba(148, 163, 184, 0.15); }
        .toggle-password svg { width: 18px; height: 18px; }
        .row { display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; color: #cbd5e1; margin-bottom: 18px; }
        .row a { color: #93c5fd; text-decoration: none; }
        .btn {
            width: 100%;
            padding: 13px 14px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 12px 24px rgba(37,99,235,0.24);
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 28px rgba(37,99,235,0.28);
        }
        .footer { text-align: center; margin-top: 18px; color: #cbd5e1; font-size: 0.95rem; }
        .footer a { color: #93c5fd; text-decoration: none; }
        .error-box {
            margin-bottom: 16px;
            border: 1px solid rgba(248,113,113,0.4);
            background: rgba(248,113,113,0.12);
            color: #fecaca;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 0.95rem;
        }
        @keyframes drift {
            from { transform: translate3d(0,0,0) scale(1); }
            to { transform: translate3d(20px,-20px,0) scale(1.08); }
        }
        @media (max-width: 768px) {
            body { padding: 16px; }
            .card { grid-template-columns: 1fr; }
            .hero { padding: 32px; }
            .form-panel { padding: 32px; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="hero">
            <div>
                <p>KDP MART</p>
                <h1>Welcome back</h1>
                <p>Sign in to your account and continue shopping your favorite products with a faster checkout.</p>
            </div>
            <div class="hero-badge">
                <strong>New here?</strong>
                <p>Create an account to unlock deals and exclusive offers.</p>
                <a href="<?php echo e(route('register')); ?>" class="hero-cta">Create your account</a>
            </div>
        </div>

        <div class="form-panel">
            <p class="eyebrow">Secure Login</p>
            <h2>Sign in to your account</h2>
            <p class="muted">Enter your email and password to get started.</p>

            <?php if($errors->any()): ?>
                <div class="error-box">
                    <ul style="margin: 0; padding-left: 18px;">
                        <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo e(route('login.post')); ?>">
                <?php echo csrf_field(); ?>
                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="<?php echo e(old('email')); ?>" required placeholder="you@example.com">
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="password-wrap">
                        <input id="password" name="password" type="password" required placeholder="Enter your password">
                        <button type="button" class="toggle-password" data-target="password" aria-label="Show password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="row">
                    <label style="display:flex; align-items:center; gap:8px; margin:0;">
                        <input type="checkbox" name="remember" style="width:auto; padding:0; margin:0;">
                        Remember me
                    </label>
                    <a href="<?php echo e(route('password.request')); ?>">Forgot password?</a>
                </div>

                <button type="submit" class="btn">Sign in</button>
            </form>

            <p class="footer">
                Don’t have an account?
                <a href="<?php echo e(route('register')); ?>">Create one</a>
            </p>
        </div>
    </div>

    <script>
        const eyeOpen = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        const eyeClosed = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18"></path><path d="M10.58 10.58A2 2 0 0 0 13.42 13.42"></path><path d="M9.88 5.08A10.94 10.94 0 0 1 12 5c6.5 0 10 7 10 7a18.18 18.18 0 0 1-3.1 4.02"></path><path d="M6.61 6.61A18.89 18.89 0 0 0 2 12s3.5 6 10 6a10.85 10.85 0 0 0 4.08-.78"></path></svg>';

        document.querySelectorAll('.toggle-password').forEach(function (button) {
            button.addEventListener('click', function () {
                const input = document.getElementById(this.dataset.target);
                const showing = input.type === 'text';

                input.type = showing ? 'password' : 'text';
                this.innerHTML = showing ? eyeOpen : eyeClosed;
                this.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            });
        });
    </script>
</body>
</html>
<?php /**PATH C:\Minor Project\Minor-Project\OpensourceE-commercewebsite\resources\views/auth/login.blade.php ENDPATH**/ ?>