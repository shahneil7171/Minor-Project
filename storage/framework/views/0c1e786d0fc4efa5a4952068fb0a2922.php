<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP | KDP MART</title>
    <style>
        :root {
            color-scheme: dark;
            font-family: Inter, Arial, sans-serif;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #dc2626 45%, #000000 100%);
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 860px;
            overflow: hidden;
            border-radius: 24px;
            border: 1px solid #1f2937;
            background: #111827;
            box-shadow: 0 20px 45px rgba(0,0,0,0.35);
        }
        .form-panel { padding: 40px; }
        .eyebrow { font-size: 0.8rem; letter-spacing: 0.3em; text-transform: uppercase; color: #fb923c; font-weight: 700; }
        .form-panel h2 { margin: 10px 0 8px; font-size: 1.8rem; }
        .form-panel .muted { color: #94a3b8; margin: 0 0 24px; }
        .field { margin-bottom: 16px; }
        label { display: block; margin-bottom: 8px; font-size: 0.95rem; color: #e2e8f0; }
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
        .message-box {
            margin-bottom: 16px;
            border: 1px solid rgba(96, 165, 250, 0.4);
            background: rgba(59, 130, 246, 0.12);
            color: #bfdbfe;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 0.95rem;
        }
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
            .card { max-width: 100%; }
            .form-panel { padding: 32px; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="form-panel">
            <p class="eyebrow">Verify code</p>
            <h2>Enter the 6-digit code</h2>
            <p class="muted">We sent a six digit OTP to your email. Enter it below to continue.</p>

            <?php if(session('status')): ?>
                <div class="message-box"><?php echo e(session('status')); ?></div>
            <?php endif; ?>

            <?php if($errors->any()): ?>
                <div class="error-box">
                    <ul style="margin: 0; padding-left: 18px;">
                        <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo e(route('password.verify.post')); ?>">
                <?php echo csrf_field(); ?>

                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="<?php echo e(old('email')); ?>" required placeholder="you@example.com">
                </div>

                <div class="field">
                    <label for="otp">6-digit code</label>
                    <input id="otp" name="otp" type="text" maxlength="6" value="<?php echo e(old('otp')); ?>" required placeholder="123456">
                </div>

                <button type="submit" class="btn">Verify code</button>
            </form>

            <p class="footer">
                Didn’t receive a code? <a href="<?php echo e(route('password.request')); ?>">Send again</a>
            </p>
        </div>
    </div>
</body>
</html>
<?php /**PATH C:\Minor Project\Minor-Project\OpensourceE-commercewebsite\resources\views/auth/verify-otp.blade.php ENDPATH**/ ?>