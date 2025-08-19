<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Builder Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{
            --bg: #0b1020;
            --panel: #0e162b;
            --text: #e5e7eb;
            --muted: #9aa0a6;
            --line: rgba(255,255,255,0.08);
            --acc1: #60a5fa;
            --acc2: #34d399;
            --acc3: #a78bfa;
        }
        *{box-sizing:border-box}
        html,body{height:100%}
        body{
            margin:0; color:var(--text); background:radial-gradient(1200px 600px at -10% -10%, rgba(96,165,250,0.12), transparent), radial-gradient(1000px 600px at 110% -10%, rgba(167,139,250,0.12), transparent), linear-gradient(180deg, #0b1020, #0b1020);
            font-family:Inter, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial;
            letter-spacing:0.2px;
        }
        .wrap{max-width:980px; margin:48px auto; padding:24px}
        .hero{display:flex; align-items:center; justify-content:space-between; gap:16px}
        .title{font-size:28px; font-weight:800; margin:0}
        .subtitle{margin-top:6px; color:var(--muted)}
        .cards{display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:18px; margin-top:24px}
        .card{position:relative; background:linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.015)); border:1px solid var(--line); border-radius:18px; padding:20px; transition:transform .2s ease, box-shadow .2s ease; text-decoration:none; color:inherit; overflow:hidden}
        .card:hover{transform:translateY(-2px); box-shadow:0 10px 30px rgba(0,0,0,0.35)}
        .card .icon{width:48px; height:48px; border-radius:14px; display:grid; place-items:center; color:white; font-weight:800; letter-spacing:1px}
        .card h3{margin:14px 0 6px; font-size:18px}
        .card p{margin:0; color:#cbd5e1}
        .badge{position:absolute; top:14px; right:14px; font-size:12px; color:#a3a3a3}
        .row{display:flex; align-items:center; gap:12px}
        .kbd{border:1px solid var(--line); border-bottom-width:2px; padding:2px 6px; border-radius:8px; color:#cbd5e1; font:600 12px/1 Inter}
        .footer{margin-top:28px; color:#94a3b8; font-size:12px}
        @media (max-width: 720px){ .cards{grid-template-columns:1fr} }
        @media print{ .cards a{display:none} }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="hero">
            <div>
                <h1 class="title">Builder Dashboard</h1>
                <div class="subtitle">Quickly jump to your tools.</div>
            </div>
        </div>

        <div class="cards">
            <a class="card" href="receipt/" title="Open Receipt Builder">
                <div class="row">
                    <div class="icon" style="background:linear-gradient(135deg, var(--acc1), var(--acc3))">R</div>
                    <div>
                        <h3>Receipt Builder</h3>
                        <p>Create and save printable receipts.</p>
                    </div>
                </div>
                <div class="badge">Open →</div>
            </a>

            <a class="card" href="quatation/" title="Open Quotation Builder">
                <div class="row">
                    <div class="icon" style="background:linear-gradient(135deg, var(--acc2), var(--acc1))">Q</div>
                    <div>
                        <h3>Quotation Builder</h3>
                        <p>Build professional quotations with VAT.</p>
                    </div>
                </div>
                <div class="badge">Open →</div>
            </a>
        </div>

        <div class="footer">
            Tips: Press <span class="kbd">R</span> for Receipt, <span class="kbd">Q</span> for Quotation.
        </div>
    </div>

    <script>
        document.addEventListener('keydown', (e)=>{
            if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable)) return;
            if (e.key.toLowerCase() === 'r') window.location.href = 'receipt/';
            if (e.key.toLowerCase() === 'q') window.location.href = 'quatation/';
        });
    </script>
</body>
</html>