<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled</title>
    <style>
        :root {
            --bg: #14080f;
            --panel: rgba(38, 13, 24, 0.9);
            --warn: #ff8e93;
            --text: #ffeef2;
            --muted: #e6b8c1;
            --ring: rgba(255, 142, 147, 0.28);
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
                radial-gradient(1200px 700px at 10% 0%, #5c1a31 0%, transparent 60%),
                radial-gradient(900px 500px at 90% 100%, #3b1020 0%, transparent 55%),
                var(--bg);
            padding: 24px;
        }

        .card {
            width: min(720px, 100%);
            background: var(--panel);
            border: 1px solid rgba(255, 142, 147, 0.3);
            border-radius: 20px;
            padding: 28px;
            backdrop-filter: blur(8px);
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.35);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 142, 147, 0.12);
            border: 1px solid rgba(255, 142, 147, 0.45);
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
            color: #2a0e16;
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
        <span class="badge"><span class="dot"></span> PAYMENT CANCELLED</span>
        <h1>Your Booking Was Not Completed</h1>
        <p>
            No worries. Your payment was cancelled and no successful charge was recorded.
            You can return and try booking again whenever you are ready.
        </p>

        <div class="actions">
            <a class="btn btn-primary" href="javascript:history.back()">Try Again</a>
            <a class="btn btn-ghost" href="/">Go To Home</a>
        </div>
    </section>
</body>
</html>
