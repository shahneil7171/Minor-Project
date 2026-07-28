<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KDP MART</title>
    <style>
        :root {
            color-scheme: dark;
            font-family: Inter, Arial, sans-serif;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            overflow: hidden;
            background: linear-gradient(120deg, #020617 0%, #0f172a 25%, #1d4ed8 55%, #7f1d1d 100%);
            color: #f8fafc;
            position: relative;
        }
        body::before, body::after {
            content: '';
            position: fixed;
            border-radius: 999px;
            filter: blur(90px);
            opacity: 0.45;
            pointer-events: none;
            animation: drift 8s ease-in-out infinite alternate;
        }
        body::before {
            width: 300px;
            height: 300px;
            top: -50px;
            left: -60px;
            background: radial-gradient(circle, #60a5fa 0%, rgba(96,165,250,0) 70%);
        }
        body::after {
            width: 360px;
            height: 360px;
            bottom: -80px;
            right: -70px;
            background: radial-gradient(circle, #f43f5e 0%, rgba(244,63,94,0) 70%);
            animation-duration: 10s;
        }
        .splash {
            width: min(520px, 92vw);
            padding: 34px 28px;
            border-radius: 28px;
            background: rgba(2, 6, 23, 0.72);
            border: 1px solid rgba(255,255,255,0.16);
            box-shadow: 0 25px 60px rgba(0,0,0,0.32);
            backdrop-filter: blur(18px);
            text-align: center;
            position: relative;
            z-index: 1;
            animation: splashZoom 3s ease-in-out forwards;
        }
        .logo {
            width: 96px;
            height: 96px;
            border-radius: 24px;
            margin: 0 auto 18px;
            display: grid;
            place-items: center;
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #60a5fa, #f43f5e);
            box-shadow: 0 18px 35px rgba(37,99,235,0.24);
            animation: fadeIn 1.2s ease-out both;
        }
        .brand {
            font-size: clamp(2.2rem, 5vw, 3rem);
            font-weight: 800;
            letter-spacing: 0.16em;
            margin-bottom: 10px;
            animation: zoomText 1.2s ease-out both;
        }
        .subtitle {
            color: #cbd5e1;
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 20px;
        }
        .loader {
            width: 100%;
            height: 8px;
            border-radius: 999px;
            overflow: hidden;
            background: rgba(255,255,255,0.12);
            margin-top: 16px;
        }
        .loader-bar {
            height: 100%;
            width: 32%;
            border-radius: inherit;
            background: linear-gradient(90deg, #60a5fa, #f43f5e);
            animation: loading 2.4s ease-in-out infinite;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes zoomText {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes loading {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(260%); }
        }
        @keyframes drift {
            from {
                 transform: translate3d(0,0,0) scale(1); }
            to { 
                transform: translate3d(20px,-20px,0) scale(1.08); 
            }
        }
        @keyframes splashZoom {
            0% {
                 transform: scale(1);
            }

            70% {
                transform: scale(1.15);
            }

            100% {
                transform: scale(2.3);
                opacity: 0;
            }
        }
        }
        @media (max-width: 640px) {
            .splash { padding: 24px 20px; }
        }
    </style>
</head>
<body>
    <div class="splash">
        <div class="logo">KDP</div>
        <div class="brand">KDP SMART MART</div>
        <p class="subtitle">Smart Shopping Platform</p>
        <p class="microcopy">KDP SMART MART | E-Commerce Platform</p>
        <div class="loader">
            <div class="loader-bar"></div>
        </div>
    </div>

    <script>
        window.setTimeout(function () {
            window.location.href = '{{ route('login') }}';
        }, 3000);
    </script>
</body>
</html>
