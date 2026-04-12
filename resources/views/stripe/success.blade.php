<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <style>
        :root {
            --bg: #061122;
            --panel: rgba(10, 23, 45, 0.88);
            --ok: #79f26b;
            --text: #e9f2ff;
            --muted: #9bb0d3;
            --ring: rgba(121, 242, 107, 0.34);
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
                radial-gradient(1200px 600px at 20% 0%, #17386b 0%, transparent 60%),
                radial-gradient(1000px 500px at 80% 100%, #0b3f2c 0%, transparent 55%),
                var(--bg);
            padding: 24px;
        }

        .card {
            width: min(720px, 100%);
            background: var(--panel);
            border: 1px solid rgba(121, 242, 107, 0.2);
            border-radius: 20px;
            padding: 28px;
            backdrop-filter: blur(8px);
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.35);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(121, 242, 107, 0.12);
            border: 1px solid rgba(121, 242, 107, 0.45);
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
            color: #0c1e11;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            filter: brightness(1.04);
        }

        .btn-ghost {
            color: var(--text);
            border-color: rgba(233, 242, 255, 0.35);
            background: rgba(233, 242, 255, 0.04);
        }

        .btn-ghost:hover {
            border-color: rgba(233, 242, 255, 0.58);
            background: rgba(233, 242, 255, 0.08);
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
        <span class="badge"><span class="dot"></span> PAYMENT SUCCESSFUL</span>
        <h1>Your Program Booking Is Confirmed</h1>
        <p>
            Thank you for your payment. Your booking has been received and is now being processed.
            You can return to the app and continue exploring programs.
        </p>

        <div class="actions">
            <a class="btn btn-primary" href="/">Go To Home</a>
            <a class="btn btn-ghost" href="javascript:history.back()">Back</a>
        </div>
    </section>
</body>
</html>
