<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Account Connected</title>
    <style>
        :root {
            --bg: #081926;
            --panel: rgba(10, 31, 45, 0.9);
            --ok: #5fe3a3;
            --text: #e8f5ff;
            --muted: #9bc1d7;
            --ring: rgba(95, 227, 163, 0.35);
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
                radial-gradient(1000px 520px at 18% 0%, #154c62 0%, transparent 58%),
                radial-gradient(900px 450px at 84% 100%, #0a4e3a 0%, transparent 55%),
                var(--bg);
            padding: 24px;
        }

        .card {
            width: min(760px, 100%);
            background: var(--panel);
            border: 1px solid rgba(95, 227, 163, 0.28);
            border-radius: 20px;
            padding: 28px;
            backdrop-filter: blur(8px);
            box-shadow: 0 18px 44px rgba(0, 0, 0, 0.35);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(95, 227, 163, 0.12);
            border: 1px solid rgba(95, 227, 163, 0.45);
            color: var(--ok);
            font-weight: 700;
            padding: 8px 14px;
            border-radius: 999px;
            margin-bottom: 16px;
        }

        .dot {
            width: 10px;
            height: 10px;
            background: var(--ok);
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
            background: var(--ok);
            color: #0d2a1f;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            filter: brightness(1.04);
        }

        .btn-ghost {
            color: var(--text);
            border-color: rgba(232, 245, 255, 0.35);
            background: rgba(232, 245, 255, 0.04);
        }

        .btn-ghost:hover {
            border-color: rgba(232, 245, 255, 0.58);
            background: rgba(232, 245, 255, 0.08);
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
        <span class="badge"><span class="dot"></span> STRIPE CONNECTED</span>
        <h1>Your Stripe Account Is Connected</h1>
        <p>
            Onboarding is complete. You can now receive payouts to your connected Stripe account.
            Return to the app and continue your workflow.
        </p>

        <div class="actions">
            <a class="btn btn-primary" href="/">Go To Home</a>
            <a class="btn btn-ghost" href="javascript:history.back()">Back</a>
        </div>
    </section>
</body>
</html>
