<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rocket Coding — API</title>
    <style>
        :root {
            --navy: #1E40AF;
            --primary: #2B6BFF;
            --pink: #FF2D92;
            --bg: linear-gradient(135deg, #3A4670 0%, #5A6A98 50%, #7788B8 100%);
            --fg: #ffffff;
            --fg-dim: #E0E7F2;
            --card: rgba(255, 255, 255, 0.12);
            --border: rgba(255, 255, 255, 0.28);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh;
            font-family: 'DM Sans', 'Inter', system-ui, sans-serif;
            color: var(--fg); background: var(--bg);
            display: flex; align-items: center; justify-content: center; padding: 40px 20px;
        }
        .container { max-width: 820px; width: 100%; }
        header { display: flex; align-items: center; gap: 16px; margin-bottom: 28px; }
        .logo {
            width: 56px; height: 56px; border-radius: 14px;
            background: linear-gradient(180deg, var(--primary), var(--navy));
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; position: relative;
            box-shadow: 0 4px 14px rgba(43, 107, 255, 0.45);
        }
        .logo::after {
            content: ''; position: absolute; top: -4px; right: -4px;
            width: 18px; height: 18px; background: #10B981;
            border-radius: 50%; border: 2px solid #fff;
        }
        h1 {
            margin: 0; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;
        }
        h1 .thin { font-weight: 300; color: #BFDBFE; }
        .tagline { margin: 2px 0 0; font-size: 13px; color: var(--fg-dim); }

        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; margin-bottom: 28px; }
        .card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 14px; padding: 18px 20px; backdrop-filter: blur(8px);
        }
        .card h2 { margin: 0 0 8px; font-size: 12px; font-weight: 700;
                    letter-spacing: 1px; text-transform: uppercase; color: var(--fg-dim); }
        .card a {
            color: #fff; text-decoration: none; font-family: ui-monospace, SF Mono, Menlo, monospace;
            font-size: 13px; display: block; padding: 2px 0;
        }
        .card a:hover { color: #FF6BB3; }
        .notice {
            background: rgba(255, 255, 255, 0.08); border-left: 3px solid var(--pink);
            border-radius: 8px; padding: 14px 18px; font-size: 13px; line-height: 1.6;
            color: var(--fg-dim);
        }
        footer {
            margin-top: 32px; text-align: center; font-size: 11px; color: var(--fg-dim); opacity: 0.7;
        }
        code { background: rgba(0, 0, 0, 0.22); padding: 1px 6px; border-radius: 4px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">🚀</div>
            <div>
                <h1>Rocket<span class="thin">Coding</span></h1>
                <div class="tagline">API backend · Laravel {{ app()->version() }} · env: <code>{{ config('app.env') }}</code></div>
            </div>
        </header>

        <div class="notice">
            This is a <strong>headless API backend</strong>. There is no UI served here.
            The React prototype (<code>files/rocket-coding.jsx</code>) is the frontend —
            it calls the endpoints below. Use a REST client (Postman, Insomnia, Bruno,
            <code>curl</code>) to interact with the API directly.
        </div>

        <div class="cards">
            <div class="card">
                <h2>Health &amp; ops</h2>
                <a href="/api/health" target="_blank">GET /api/health</a>
                <a href="/horizon" target="_blank">/horizon (admin+deployer)</a>
            </div>
            <div class="card">
                <h2>Auth</h2>
                <a>POST /api/auth/login</a>
                <a>POST /api/auth/refresh</a>
                <a>POST /api/auth/logout</a>
                <a>GET /api/auth/me</a>
            </div>
            <div class="card">
                <h2>Resources</h2>
                <a>/api/clinics</a>
                <a>/api/users · /api/providers</a>
                <a>/api/patients · /api/appointments</a>
                <a>/api/cases (+ /approve /send-back /rerun-ai)</a>
                <a>/api/files · /api/services</a>
            </div>
            <div class="card">
                <h2>Dev tools</h2>
                <a href="http://localhost:8025" target="_blank">Mailpit — localhost:8025</a>
                <a>MySQL — localhost:3307 (sail/password)</a>
                <a>Redis — localhost:6379</a>
            </div>
        </div>

        <div class="notice">
            Seeded credentials (password for all: <code>demo123</code>):
            <br><code>clinic@valleymed.com</code> · clinic user at Valley Medical
            <br><code>admin@rocketcoding.com</code> · Rocket admin (cross-clinic)
            <br><code>deployer@rocketcoding.com</code> · deployer (audit + ops)
        </div>

        <footer>
            Powered by SnapEcosystem · www.SnapSolutions.com
        </footer>
    </div>
</body>
</html>
