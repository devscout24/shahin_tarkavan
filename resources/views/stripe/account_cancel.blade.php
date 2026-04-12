<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Onboarding Not Completed</title>
    <style>
        :root {
            --bg: #1a0b15;
            --panel: rgba(45, 16, 30, 0.9);
            --warn: #ff9a8a;
            --text: #ffeef2;
            --muted: #e3bdc8;
            --ring: rgba(255, 154, 138, 0.3);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
            background:
                radial-gradient(1000px 600px at 10% 0%, #5a2239 0%, transparent 60%),
                radial-gradient(850px 470px at 90% 100%, #3f1627 0%, transparent 56%),
                var(--bg);
            padding: 24px;
        }

        .card {
            width: min(760px, 100%);
            background: var(--panel);
            border: 1px solid rgba(255, 154, 138, 0.28);
            border-radius: 20px;
            padding: 28px;
            backdrop-filter: blur(8px);
            box-shadow: 0 18px 44px rgba(0, 0, 0, 0.35);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 154, 138, 0.12);
            border: 1px solid rgba(255, 154, 138, 0.42);
            color: var(--warn);
            font-weight: 700;
            padding: 8px 14px;
            border-radius: 999px;
            margin-bottom: 16px;
        }

        .dot {
            width: 10px;
            height: 10px;
            background: var(--warn);
            border-radius: 50%;
            box-shadow: 0 0 0 6px var(--ring);
        }

        h1 {
            margin: 8px 0 10px;
            font-size: clamp(28px, 4vw, 40px);
            line-height: 1.15;
        }

        p {
            margin: 0;
            color: var(--muted);
            line-height: 1.7;
            font-size: 16px;
        }

        .actions {
            margin-top: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn {
            text-decoration: none;
            font-weight: 700;
            border-radius: 10px;
            padding: 12px 18px;
            transition: 0.2s ease;
            border: 1px solid transparent;
        }

        .btn-primary {
            background: var(--warn);
            color: #2f1219;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            filter: brightness(1.04);
        }

        .btn-ghost {
            color: var(--text);
            border-color: rgba(255, 238, 242, 0.35);
            background: rgba(255, 238, 242, 0.04);
        }

        .btn-ghost:hover {
            border-color: rgba(255, 238, 242, 0.58);
            background: rgba(255, 238, 242, 0.08);
        }

        @media (max-width: 520px) {
            .card { padding: 22px; border-radius: 16px; }
            .actions { flex-direction: column; }
            .btn { text-align: center; width: 100%; }
        }
    </style>
</head>
<body>
    <section class="card">
        <span class="badge"><span class="dot"></span> ONBOARDING NOT COMPLETED</span>
        <h1>Your Stripe Setup Was Not Completed</h1>
        <p>
            The onboarding flow was interrupted or cancelled. You can try connecting your Stripe account again
            from the app whenever you are ready.
        </p>

        <div class="actions">
            <a class="btn btn-primary" href="javascript:history.back()">Try Again</a>
            <a class="btn btn-ghost" href="/">Go To Home</a>
        </div>
    </section>
</body>
</html>
