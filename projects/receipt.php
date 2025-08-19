<?php
declare(strict_types=1);

// Helper to format NPR currency with comma grouping
function format_npr(string|int|float $value): string {
  if ($value === '' || strtolower((string)$value) === 'included') {
    return 'Included';
  }
  $num = (float)preg_replace('/[^\d.]/', '', (string)$value);
  return number_format($num, 0, '.', ',');
}

$defaultProducts = [
  ['item' => 'CPU', 'description' => 'AMD Ryzen 5 5600G — 6 Cores (12 Threads), up to 4.4 GHz, Vega 11 Graphics', 'price' => 18999],
  ['item' => 'Motherboard', 'description' => 'ASRock B450M-HDV (Micro‑ATX, AM4 Socket, Ryzen Series Compatible)', 'price' => 11000],
  ['item' => 'RAM', 'description' => '16 GB DDR4 3200 MHz (2x8GB Dual Channel)', 'price' => 4800],
  ['item' => 'CPU Fan', 'description' => 'High Performance Air Cooler', 'price' => 1500],
  ['item' => 'Thermal Paste', 'description' => 'High Conductivity Thermal Compound', 'price' => 700],
  ['item' => 'Storage', 'description' => 'NVMe SSD 256GB (PCIe Gen3, High Speed Storage)', 'price' => 2700],
  ['item' => 'Case', 'description' => 'Ant Esports Gaming Case (ATX Mid Tower, RGB Ready)', 'price' => 4500],
  ['item' => 'Graphics', 'description' => 'Radeon Vega 11 Integrated Graphics (8GB Shared Memory)', 'price' => 'Included'],
  ['item' => 'Power Supply', 'description' => '750W PSU (80+ Bronze, Semi-Modular)', 'price' => 3500],
];

$customerName = 'Your Name';
if (isset($_POST['customer_name']) && $_POST['customer_name'] !== '') {
  $customerName = trim((string)$_POST['customer_name']);
}

// Start with defaults; if POSTed products exist, hydrate from POST
$products = $defaultProducts;
if (isset($_POST['products']) && is_array($_POST['products'])) {
  $products = [];
  foreach ($_POST['products'] as $row) {
    $item = isset($row['item']) ? trim((string)$row['item']) : '';
    $desc = isset($row['description']) ? trim((string)$row['description']) : '';
    $price = isset($row['price']) ? trim((string)$row['price']) : '';
    if ($item === '' && $desc === '' && $price === '') {
      continue;
    }
    $products[] = ['item' => $item, 'description' => $desc, 'price' => $price];
  }
  if (count($products) === 0) {
    $products = [];
  }
}

// Allow quick blank slate
if (isset($_GET['new'])) {
  $products = [];
  $customerName = 'Your Name';
}

// Compute total (numeric prices only)
$grandTotal = 0;
foreach ($products as $p) {
  $price = (string)$p['price'];
  if ($price === '' || strtolower($price) === 'included') {
    continue;
  }
  $grandTotal += (float)preg_replace('/[^\d.]/', '', $price);
}

// Date string like "17 Aug 2025"
$dateStr = date('d M Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Custom PC Build – Receipt</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg: #000000;
      --card: #0a0a0a;
      --text: #e6e6e6;
      --muted: #9aa0a6;
      --line: rgba(255,255,255,0.06);
      --accent1: #00f0ff;
      --accent2: #a855f7;
      --success: #22c55e;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      background:var(--bg);
      color:var(--text);
      font-family:Inter, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial;
      letter-spacing:0.2px;
    }
    .wrap{max-width:880px; margin:32px auto; padding:24px;}
    .card{background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0)); border:1px solid var(--line); border-radius:20px; overflow:hidden; box-shadow:0 0 0 1px rgba(255,255,255,0.02), 0 30px 60px rgba(0,0,0,0.6);}    
    .header{padding:28px 28px 20px; background: radial-gradient(1200px 280px at 10% -10%, rgba(168,85,247,0.25), transparent), radial-gradient(900px 280px at 90% -20%, rgba(0,240,255,0.25), transparent);}    
    .brand{display:flex; align-items:center; gap:14px;}
    .logo{width:40px; height:40px; border-radius:12px; background:conic-gradient(from 200deg, var(--accent1), var(--accent2));}
    h1{font-size:22px; margin:0}
    .muted{color:var(--muted)}
    .meta{display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:12px; margin-top:16px;}
    .kvs{display:flex; justify-content:space-between; border:1px solid var(--line); border-radius:14px; padding:12px 14px; background-color:rgba(255,255,255,0.01)}
    .kvs span:first-child{color:var(--muted)}
    .content{padding:8px 28px 28px}
    .table{width:100%; border-collapse:separate; border-spacing:0; margin-top:14px; border-radius:16px; border:1px solid var(--line); background-color:rgba(255,255,255,0.01);}    
    .table thead th{text-align:left; font-weight:600; font-size:13px; padding:14px 16px; border-bottom:1px solid var(--line); color:#cbd5e1; background:linear-gradient(180deg, rgba(255,255,255,0.02), transparent);}    
    .table tbody td{padding:14px 16px; border-bottom:1px solid var(--line); position:relative;}
    .table tbody tr:last-child td{border-bottom:none}
    .mono{font-family:"JetBrains Mono", monospace}
    .price{text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums;}
    .total{display:flex; justify-content:flex-end; gap:16px; align-items:center; margin-top:18px; padding:18px 16px; border:1px dashed var(--line); border-radius:14px; background:linear-gradient(90deg, rgba(0,240,255,0.08), rgba(168,85,247,0.08));}
    .total .label{color:#cbd5e1}
    .total .amount{font-size:24px; font-weight:800;}
    .fineprint{margin-top:18px; font-size:12px; color:#a3a3a3}
    .actions{display:flex; gap:10px; margin-top:22px; justify-content:space-between; align-items:center;}
    .btn{border:none; cursor:pointer; border-radius:14px; padding:12px 16px; font-weight:600; color:var(--text); background:linear-gradient(90deg, var(--accent1), var(--accent2));}
    .btn.secondary{background:#0f172a; border:1px solid var(--line)}
    .btn.ghost{background:transparent; border:1px dashed var(--line)}
    .stack{display:flex; gap:10px}
    .row-remove{position:absolute; right:8px; top:50%; transform:translateY(-50%); background:#111827; color:#e5e7eb; border:1px solid var(--line); border-radius:10px; padding:4px 8px; cursor:pointer; font-size:12px}
    .row-remove:hover{background:#0f172a}
    .cell-input{width:100%; background:transparent; border:none; color:inherit; font:inherit; outline:none}
    .cell-input::placeholder{color:#6b7280}
    .cell-input.price{text-align:right}
    /* Hide per-item prices when toggled */
    .hide-prices .table thead th.price,
    .hide-prices .table tbody td.price{display:none}
    @media (max-width: 640px){.meta{grid-template-columns:1fr}.wrap{padding:16px}}
    @media print{
      body{background:#000;color:#fff}
      .card{background:#0a0a0a; box-shadow:none; border-color:#333}
      .header{background: radial-gradient(1200px 280px at 10% -10%, rgba(168,85,247,0.25), transparent), radial-gradient(900px 280px at 90% -20%, rgba(0,240,255,0.25), transparent)}
      .kvs,.table,.total{border-color:#333}
      .btn,.row-remove,.editor-tools{display:none}
      a{color:#00f0ff}
      .table thead th{background:rgba(255,255,255,0.02)}
      .total{background:linear-gradient(90deg, rgba(0,240,255,0.08), rgba(168,85,247,0.08))}
      input.cell-input{appearance:none}
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card" id="card-root">
      <form method="post" id="receipt-form">
        <div class="header">
          <div class="brand">
            <img src="Add a subheading.png" alt="Glorious Trade Hub" class="logo" width="50" height="50">
            <div>
              <h1>Glorious Trade Hub - Receipt</h1>
              <div class="muted">Thank you for your purchase!</div>
            </div>
          </div>
          <div class="meta">
            <div class="kvs"><span>Customer</span><span class="mono" id="customer-name-display"><?= htmlspecialchars($customerName) ?></span></div>
            <div class="kvs"><span>Date</span><span class="mono" id="current-date"><?= htmlspecialchars($dateStr) ?></span></div>
          </div>
          <input type="hidden" name="customer_name" id="customer-name-input" value="<?= htmlspecialchars($customerName) ?>">
        </div>

        <div class="content">
          <table class="table" id="products-table">
            <thead>
              <tr>
                <th style="width:28%">Item</th>
                <th>Description</th>
                <th class="price" style="width:18%">Price (रु)</th>
              </tr>
            </thead>
            <tbody id="products-body">
              <?php if (count($products) === 0): ?>
                <tr>
                  <td>
                    <strong><input class="cell-input" name="products[0][item]" placeholder="Item" value=""></strong>
                  </td>
                  <td>
                    <input class="cell-input" name="products[0][description]" placeholder="Description" value="">
                  </td>
                  <td class="price mono">
                    <input class="cell-input price" name="products[0][price]" placeholder="0" value="">
                    <button type="button" class="row-remove" onclick="removeRow(this)">✕</button>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($products as $i => $p): ?>
                  <tr>
                    <td>
                      <strong><input class="cell-input" name="products[<?= $i ?>][item]" value="<?= htmlspecialchars((string)$p['item']) ?>"></strong>
                    </td>
                    <td>
                      <input class="cell-input" name="products[<?= $i ?>][description]" value="<?= htmlspecialchars((string)$p['description']) ?>">
                    </td>
                    <td class="price mono">
                      <input class="cell-input price" name="products[<?= $i ?>][price]" value="<?= htmlspecialchars(format_npr($p['price'])) ?>">
                      <button type="button" class="row-remove" onclick="removeRow(this)">✕</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>

          <div class="total">
            <div class="label">Grand Total</div>
            <div class="amount mono" id="grand-total">रु <?= htmlspecialchars(format_npr($grandTotal)) ?></div>
          </div>

          <p class="fineprint">Notice: All prices listed are <strong>cost price</strong>, not selling price. (Currency is Nepali Rupees).</p>

          <div class="actions">
            <div class="editor-tools stack">
              <button class="btn secondary" type="button" onclick="editName()">Edit Name</button>
              <button class="btn secondary" type="button" id="toggle-prices-btn" onclick="togglePrices()">Hide Prices</button>
              <button class="btn secondary" type="button" onclick="addRow()">Add Product</button>
              <button class="btn ghost" type="button" onclick="newReceipt()">New Receipt</button>
              <button class="btn secondary" type="submit">Save</button>
            </div>
            <div class="stack">
              <button class="btn" type="button" onclick="window.print()">Print / Save PDF</button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>

  <template id="row-template">
    <tr>
      <td>
        <strong><input class="cell-input" name="__INDEX__" placeholder="Item" value=""></strong>
      </td>
      <td>
        <input class="cell-input" name="__INDEX__" placeholder="Description" value="">
      </td>
      <td class="price mono">
        <input class="cell-input price" name="__INDEX__" placeholder="0" value="">
        <button type="button" class="row-remove" onclick="removeRow(this)">✕</button>
      </td>
    </tr>
  </template>

  <script>
    function editName(){
      const display = document.getElementById('customer-name-display');
      const current = display.textContent.trim();
      const name = prompt('Enter customer name:', current === 'Your Name' ? '' : current);
      if(name !== null){
        display.textContent = name || 'Your Name';
        document.getElementById('customer-name-input').value = name || 'Your Name';
      }
    }

    function updateDate() {
      const now = new Date();
      const dateOptions = { year: 'numeric', month: 'short', day: 'numeric' };
      document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', dateOptions);
    }

    function parsePrice(value){
      if(!value) return 0;
      const trimmed = String(value).trim();
      if (trimmed.toLowerCase() === 'included') return 0;
      const numeric = trimmed.replace(/[^\d.]/g, '');
      return Number(numeric || 0);
    }

    function formatPrice(n){
      const int = Math.round(Number(n||0));
      return int.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function computeTotal(){
      let total = 0;
      document.querySelectorAll('#products-body input.price').forEach(inp => {
        total += parsePrice(inp.value);
      });
      document.getElementById('grand-total').textContent = 'रु ' + formatPrice(total);
    }

    function reindex(){
      const rows = document.querySelectorAll('#products-body tr');
      rows.forEach((tr, i) => {
        const inputs = tr.querySelectorAll('input');
        if(inputs.length >= 3){
          inputs[0].name = `products[${i}][item]`;
          inputs[1].name = `products[${i}][description]`;
          inputs[2].name = `products[${i}][price]`;
        }
      });
    }

    function addRow(){
      const tpl = document.getElementById('row-template').content.cloneNode(true);
      document.getElementById('products-body').appendChild(tpl);
      reindex();
      computeTotal();
    }

    function removeRow(btn){
      const tr = btn.closest('tr');
      if (tr) {
        tr.parentNode.removeChild(tr);
        reindex();
        computeTotal();
      }
    }

    function newReceipt(){
      window.location.href = window.location.pathname + '?new=1';
    }

    function togglePrices(){
      const card = document.getElementById('card-root');
      const btn = document.getElementById('toggle-prices-btn');
      card.classList.toggle('hide-prices');
      const hidden = card.classList.contains('hide-prices');
      btn.textContent = hidden ? 'Show Prices' : 'Hide Prices';
    }

    document.addEventListener('input', function(e){
      if(e.target && e.target.matches('#products-body input')){
        if(e.target.classList.contains('price')){
          // keep numbers/comma only while typing is too aggressive; just total
        }
        computeTotal();
      }
    });

    // Initialize
    updateDate();
    computeTotal();
  </script>
</body>
</html>

