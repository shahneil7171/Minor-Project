<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Create Account | KDP SMART MART</title>
    <style>
        :root {
            color-scheme: dark;
            font-family: Inter, Arial, sans-serif;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #020617 0%, #111827 100%);
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 1040px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            border-radius: 24px;
            border: 1px solid #1f2937;
            background: #111827;
            box-shadow: 0 20px 45px rgba(0,0,0,0.35);
        }
        .hero {
            background: linear-gradient(135deg, #f97316 0%, #f59e0b 50%, #fde68a 100%);
            color: #111827;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .hero h1 { font-size: 2.3rem; margin: 0 0 12px; line-height: 1.1; }
        .hero p { margin: 0; font-size: 1rem; line-height: 1.6; }
        .hero-badge {
            margin-top: 30px;
            background: rgba(255,255,255,0.65);
            border: 1px solid rgba(17, 24, 39, 0.18);
            border-radius: 16px;
            padding: 16px;
            backdrop-filter: blur(8px);
        }
        .form-panel { padding: 40px; }
        .eyebrow { font-size: 0.8rem; letter-spacing: 0.3em; text-transform: uppercase; color: #fb923c; font-weight: 700; }
        .form-panel h2 { margin: 10px 0 8px; font-size: 1.8rem; }
        .form-panel .muted { color: #94a3b8; margin: 0 0 24px; }
        .field { margin-bottom: 16px; }
        label { display: block; margin-bottom: 8px; font-size: 0.95rem; color: #e2e8f0; }
        .password-wrap {
            position: relative;
        }
        input {
            width: 100%;
            border: 1px solid #334155;
            background: #0f172a;
            color: #f8fafc;
            padding: 13px 14px;
            border-radius: 12px;
            font-size: 0.95rem;
            outline: none;
        }
        input:focus { border-color: #fb923c; box-shadow: 0 0 0 3px rgba(249,115,22,0.2); }
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
        .btn {
            width: 100%;
            padding: 13px 14px;
            border: none;
            border-radius: 12px;
            background: #f97316;
            color: white;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .btn:hover { background: #ea580c; }
        .footer { text-align: center; margin-top: 18px; color: #94a3b8; font-size: 0.95rem; }
        .footer a { color: #fb923c; text-decoration: none; }
        .error-box {
            margin-bottom: 16px;
            border: 1px solid rgba(248,113,113,0.4);
            background: rgba(248,113,113,0.12);
            color: #fecaca;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 0.95rem;
        }
        @media (max-width: 768px) {
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
                <p>KDP SMART MART</p>
                <h1>Create your account</h1>
                <p><p>
<p1>Join KDP SMART MART and enjoy easy shopping, secure checkout, exclusive offers, and a better online shopping experience.</p>
            </div>
            <div class="hero-badge">
                <strong>Already a member?</strong>
                <p>Sign in to continue shopping and manage your orders.</p>
            </div>
        </div>

        <div class="form-panel">
            <p class="eyebrow">New User</p>
            <h2>Create one</h2>
            <p class="muted">Fill in your details to get started.</p>

            <?php if($errors->any()): ?>
                <div class="error-box">
                    <ul style="margin: 0; padding-left: 18px;">
                        <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo e(route('register.post')); ?>">
                <?php echo csrf_field(); ?>
                <div class="field">
                    <label for="name">Full name</label>
                    <input id="name" name="name" type="text" value="<?php echo e(old('name')); ?>" required placeholder="John Doe">
                </div>

                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="<?php echo e(old('email')); ?>" required placeholder="you@example.com">
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="password-wrap">
                        <input id="password" name="password" type="password" required placeholder="Enter at least 8 characters">
                        <button type="button" class="toggle-password" data-target="password" aria-label="Show password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirm password</label>
                    <div class="password-wrap">
                        <input id="password_confirmation" name="password_confirmation" type="password" required placeholder="Confirm your password">
                        <button type="button" class="toggle-password" data-target="password_confirmation" aria-label="Show password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn">Create account</button>
            </form>

            <p class="footer">
                Already registered?
            <a href="<?php echo e(route('login')); ?>">Login</a>
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
<?php /**PATH C:\Minor Project\Minor-Project\OpensourceE-commercewebsite\resources\views/auth/register.blade.php ENDPATH**/ ?>