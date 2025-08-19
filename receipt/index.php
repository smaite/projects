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

$shopFile = __DIR__ . '/shop.json';
$productsFile = __DIR__ . '/products.json';
$receiptsDir = __DIR__ . '/receipts';

// Load shop config
$shopConfig = [
  'shopName' => 'Glorious Trade Hub',
  'vatNo' => '',
  'hidePrices' => false,
  'dateISO' => ''
];
if (file_exists($shopFile)) {
  $raw = file_get_contents($shopFile);
  $decoded = json_decode($raw, true);
  if (is_array($decoded)) {
    $shopConfig = array_merge($shopConfig, $decoded);
  }
}

$shopName = (string)$shopConfig['shopName'];
$vatNo = (string)$shopConfig['vatNo'];
$hidePricesPref = (bool)$shopConfig['hidePrices'];
$dateISO = (string)$shopConfig['dateISO'];

// Settings save endpoint
$isSettingsSave = isset($_POST['action']) && $_POST['action'] === 'save_settings';
if ($isSettingsSave) {
  $shopName = isset($_POST['shop_name']) ? trim((string)$_POST['shop_name']) : $shopName;
  $vatNo = isset($_POST['vat_no']) ? trim((string)$_POST['vat_no']) : $vatNo;
  $hidePricesPref = isset($_POST['hide_prices']) && $_POST['hide_prices'] === 'on';
  $dateISO = isset($_POST['date_iso']) ? trim((string)$_POST['date_iso']) : $dateISO;
  $shopConfig = [
    'shopName' => $shopName,
    'vatNo' => $vatNo,
    'hidePrices' => $hidePricesPref,
    'dateISO' => $dateISO,
  ];
  @file_put_contents($shopFile, json_encode($shopConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  header('Content-Type: application/json');
  echo json_encode(['ok' => true, 'shop' => $shopConfig]);
  exit;
}

// Save named receipt endpoint
if (isset($_POST['action']) && $_POST['action'] === 'save_named_receipt') {
  header('Content-Type: application/json');
  $receiptName = isset($_POST['receipt_name']) ? trim((string)$_POST['receipt_name']) : '';
  $dataJson = isset($_POST['data']) ? (string)$_POST['data'] : '';
  $data = json_decode($dataJson, true);
  if ($receiptName === '' || !is_array($data)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid data']);
    exit;
  }
  if (!is_dir($receiptsDir)) {@mkdir($receiptsDir, 0777, true);} 
  $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-_]+/', '-', $receiptName), '-'));
  if ($slug === '') { $slug = 'receipt-' . date('Ymd-His'); }
  $path = $receiptsDir . '/' . $slug . '.json';
  $data['meta'] = [
    'name' => $receiptName,
    'savedAt' => date(DATE_ATOM)
  ];
  $ok = @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  if ($ok === false) {
    echo json_encode(['ok' => false, 'error' => 'Failed to write file']);
  } else {
    echo json_encode(['ok' => true, 'file' => basename($path), 'name' => $receiptName]);
  }
  exit;
}

// Load receipt endpoint
if (isset($_POST['action']) && $_POST['action'] === 'load_receipt') {
  header('Content-Type: application/json');
  $file = isset($_POST['receipt_file']) ? basename((string)$_POST['receipt_file']) : '';
  if ($file === '' || !preg_match('/\.json$/', $file)) { echo json_encode(['ok'=>false, 'error'=>'Invalid file']); exit; }
  $path = $receiptsDir . '/' . $file;
  if (!file_exists($path)) { echo json_encode(['ok'=>false, 'error'=>'Not found']); exit; }
  $raw = file_get_contents($path);
  $json = json_decode($raw, true);
  if (!is_array($json)) { echo json_encode(['ok'=>false, 'error'=>'Invalid JSON']); exit; }
  echo json_encode(['ok'=>true, 'data'=>$json]);
  exit;
}

$customerName = 'Your Name';
if (isset($_POST['customer_name']) && $_POST['customer_name'] !== '') {
  $customerName = trim((string)$_POST['customer_name']);
}

// If products file exists, load that, else defaults
$products = $defaultProducts;
if (file_exists($productsFile)) {
  $rawP = file_get_contents($productsFile);
  $decodedP = json_decode($rawP, true);
  if (is_array($decodedP)) {
    $products = $decodedP;
  }
}

// Start with current products; if POSTed products exist, hydrate from POST, and save
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
  @file_put_contents($productsFile, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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

// Date string like "17 Aug 2025". If a date is set in settings, use it.
$dateStr = date('d M Y');
if ($dateISO !== '') {
  $ts = strtotime($dateISO);
  if ($ts !== false) {
    $dateStr = date('d M Y', $ts);
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Custom Receipt Builder – Projects</title>
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
    /* Remove default field backgrounds, including autofill */
    input.cell-input:-webkit-autofill,
    input.cell-input:-webkit-autofill:hover,
    input.cell-input:-webkit-autofill:focus,
    input.cell-input:-webkit-autofill:active{
      -webkit-box-shadow: 0 0 0px 1000px transparent inset !important;
      -webkit-text-fill-color: var(--text) !important;
      transition: background-color 9999s ease-in-out 0s;
    }
    input.cell-input{background-color: transparent}
    .cell-input::placeholder{color:#6b7280}
    .cell-input.price{text-align:right}
    /* Hide per-item prices when toggled */
    .hide-prices .table thead th.price,
    .hide-prices .table tbody td.price{display:none}
    /* Keep remove button visible even when price column hidden */
    .hide-prices .table tbody td .row-remove{display:inline-block}
    /* Settings modal */
    .modal-overlay{position:fixed; inset:0; background:rgba(0,0,0,0.6); display:none; align-items:center; justify-content:center; z-index:9999}
    .modal{width:min(640px, 92vw); background:#0a0a0a; border:1px solid var(--line); border-radius:16px; padding:20px; box-shadow:0 30px 60px rgba(0,0,0,0.6)}
    .modal h2{margin:0 0 8px 0; font-size:18px}
    .form-grid{display:grid; grid-template-columns:1fr 1fr; gap:12px}
    .form-row{display:flex; flex-direction:column; gap:6px}
    .label{font-size:12px; color:#9aa0a6}
    .input{border:1px solid var(--line); border-radius:12px; padding:10px 12px; background:transparent; color:var(--text)}
    .input[type="checkbox"]{width:auto}
    .modal-actions{display:flex; justify-content:flex-end; gap:10px; margin-top:14px}
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
    <div class="card<?= $hidePricesPref ? ' hide-prices' : '' ?>" id="card-root">
      <form method="post" id="receipt-form">
        <div class="header">
          <div class="brand">
            <img src="Add a subheading.png" alt="Glorious Trade Hub" class="logo" width="50" height="50">
            <div>
              <h1><span id="shop-title"><?= htmlspecialchars($shopName) ?></span> - Receipt</h1>
              <div class="muted">Thank you for your purchase! <span id="vat-span"><?= $vatNo !== '' ? 'VAT No: <span class=\'mono\'>' . htmlspecialchars($vatNo) . '</span>' : '' ?></span></div>
            </div>
          </div>
          <div class="meta">
            <div class="kvs"><span>Customer</span><span class="mono" id="customer-name-display"><?= htmlspecialchars($customerName) ?></span></div>
            <div class="kvs"><span>Date</span><span class="mono" id="current-date" data-fixed-date="<?= $dateISO !== '' ? '1' : '0' ?>"><?= htmlspecialchars($dateStr) ?></span></div>
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
                    <button type="button" class="row-remove" onclick="removeRow(this)">✕</button>
                  </td>
                  <td>
                    <input class="cell-input" name="products[0][description]" placeholder="Description" value="">
                  </td>
                  <td class="price mono">
                    <input class="cell-input price" name="products[0][price]" placeholder="0" value="">
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($products as $i => $p): ?>
                  <tr>
                    <td>
                      <strong><input class="cell-input" name="products[<?= $i ?>][item]" value="<?= htmlspecialchars((string)$p['item']) ?>"></strong>
                      <button type="button" class="row-remove" onclick="removeRow(this)">✕</button>
                    </td>
                    <td>
                      <input class="cell-input" name="products[<?= $i ?>][description]" value="<?= htmlspecialchars((string)$p['description']) ?>">
                    </td>
                    <td class="price mono">
                      <input class="cell-input price" name="products[<?= $i ?>][price]" value="<?= htmlspecialchars(format_npr($p['price'])) ?>">
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
              <button class="btn secondary" type="button" onclick="openSettings()">Settings</button>
              <button class="btn secondary" type="button" onclick="addRow()">Add Product</button>
              <button class="btn ghost" type="button" onclick="newReceipt()">New Receipt</button>
              <button class="btn secondary" type="submit">Save</button>
              <button class="btn secondary" type="button" onclick="openSaveOverlay()">Save Receipt</button>
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
        <button type="button" class="row-remove" onclick="removeRow(this)">✕</button>
      </td>
      <td>
        <input class="cell-input" name="__INDEX__" placeholder="Description" value="">
      </td>
      <td class="price mono">
        <input class="cell-input price" name="__INDEX__" placeholder="0" value="">
      </td>
    </tr>
  </template>

  <div class="modal-overlay" id="settings-overlay">
    <div class="modal">
      <h2>Settings</h2>
      <form id="settings-form">
        <div class="form-grid">
          <div class="form-row">
            <label class="label">Shop Name</label>
            <input class="input" type="text" name="shop_name" id="set-shop-name" value="<?= htmlspecialchars($shopName) ?>">
          </div>
          <div class="form-row">
            <label class="label">VAT No</label>
            <input class="input" type="text" name="vat_no" id="set-vat-no" value="<?= htmlspecialchars($vatNo) ?>">
          </div>
          <div class="form-row">
            <label class="label">Customer Name</label>
            <input class="input" type="text" id="set-customer-name" value="<?= htmlspecialchars($customerName) ?>">
          </div>
          <div class="form-row">
            <label class="label">Date (optional)</label>
            <input class="input" type="date" name="date_iso" id="set-date-iso" value="<?= htmlspecialchars($dateISO) ?>">
          </div>
          <div class="form-row">
            <label class="label">Hide Prices</label>
            <input class="input" type="checkbox" name="hide_prices" id="set-hide-prices" <?= $hidePricesPref ? 'checked' : '' ?>>
          </div>
        </div>
        <div class="form-row" style="margin-top:12px;">
          <label class="label">Load Saved Receipt</label>
          <div class="stack">
            <select class="input" id="saved-receipts" style="min-width:240px">
              <option value="">-- Select saved receipt --</option>
              <?php
              if (is_dir($receiptsDir)) {
                foreach (glob($receiptsDir . '/*.json') as $f) {
                  $base = basename($f);
                  echo '<option value="' . htmlspecialchars($base) . '">' . htmlspecialchars($base) . '</option>';
                }
              }
              ?>
            </select>
            <button type="button" class="btn secondary" onclick="loadSelectedReceipt()">Load</button>
          </div>
        </div>
        <div class="form-row">
          <label class="label">Import Receipt (JSON file)</label>
          <input class="input" type="file" id="import-receipt-file" accept="application/json,.json">
        </div>
        <div class="modal-actions">
          <button type="button" class="btn secondary" onclick="closeSettings()">Close</button>
          <button type="submit" class="btn">Save Settings</button>
        </div>
        <input type="hidden" name="action" value="save_settings">
      </form>
    </div>
  </div>

  <div class="modal-overlay" id="save-overlay">
    <div class="modal">
      <h2>Save Receipt</h2>
      <form id="save-form">
        <div class="form-row">
          <label class="label">Receipt Name</label>
          <input class="input" type="text" id="save-receipt-name" placeholder="e.g., John Doe - 2025-08-19">
        </div>
        <div class="modal-actions">
          <button type="button" class="btn secondary" onclick="closeSaveOverlay()">Close</button>
          <button type="submit" class="btn">Save</button>
        </div>
        <input type="hidden" name="action" value="save_named_receipt">
      </form>
    </div>
  </div>

  <script>
    function openSettings(){
      document.getElementById('settings-overlay').style.display = 'flex';
      // seed customer input live from display
      document.getElementById('set-customer-name').value = document.getElementById('customer-name-display').textContent.trim();
      document.getElementById('set-hide-prices').checked = document.getElementById('card-root').classList.contains('hide-prices');
      document.body.style.overflow = 'hidden';
    }
    function closeSettings(){
      document.getElementById('settings-overlay').style.display = 'none';
      document.body.style.overflow = '';
    }
    document.getElementById('settings-overlay').addEventListener('click', function(e){
      if(e.target === this){ closeSettings(); }
    });

    document.getElementById('settings-form').addEventListener('submit', async function(e){
      e.preventDefault();
      const form = e.currentTarget;
      const data = new FormData(form);
      try{
        const resp = await fetch(window.location.href, { method: 'POST', body: data });
        const json = await resp.json();
        if(json && json.ok){
          // Apply UI updates without full reload
          const shopTitle = document.getElementById('shop-title');
          if(shopTitle) shopTitle.textContent = document.getElementById('set-shop-name').value || 'Glorious Trade Hub';
          const vatSpan = document.getElementById('vat-span');
          const vatVal = document.getElementById('set-vat-no').value.trim();
          vatSpan.innerHTML = vatVal ? ('VAT No: <span class="mono">' + vatVal.replace(/</g,'&lt;') + '</span>') : '';
          const customerVal = document.getElementById('set-customer-name').value.trim();
          document.getElementById('customer-name-display').textContent = customerVal || 'Your Name';
          const dateIso = document.getElementById('set-date-iso').value;
          const currentDateEl = document.getElementById('current-date');
          if(dateIso){
            const dt = new Date(dateIso);
            const opts = { year:'numeric', month:'short', day:'numeric' };
            currentDateEl.textContent = dt.toLocaleDateString('en-US', opts);
            currentDateEl.dataset.fixedDate = '1';
          }else{
            currentDateEl.dataset.fixedDate = '0';
            updateDate();
          }
          const hide = document.getElementById('set-hide-prices').checked;
          const card = document.getElementById('card-root');
          card.classList.toggle('hide-prices', hide);
          closeSettings();
        }
      }catch(err){
        console.error(err);
        alert('Failed to save settings');
      }
    });

    // Load from saved receipts in settings
    async function loadSelectedReceipt(){
      const sel = document.getElementById('saved-receipts');
      const file = sel.value;
      if(!file){ alert('Select a receipt'); return; }
      const fd = new FormData();
      fd.append('action','load_receipt');
      fd.append('receipt_file', file);
      try{
        const resp = await fetch(window.location.href, { method:'POST', body: fd });
        const json = await resp.json();
        if(json && json.ok){ applyReceiptData(json.data); closeSettings(); }
        else { alert('Failed to load: ' + (json && json.error ? json.error : 'Unknown')); }
      }catch(err){ alert('Failed to load'); }
    }

    // Import receipt from local JSON file
    document.getElementById('import-receipt-file').addEventListener('change', function(e){
      const file = e.target.files[0];
      if(!file) return;
      const reader = new FileReader();
      reader.onload = function(){
        try{
          const data = JSON.parse(reader.result);
          applyReceiptData(data);
          closeSettings();
        }catch(err){ alert('Invalid JSON'); }
      };
      reader.readAsText(file);
    });

    function openSaveOverlay(){
      document.getElementById('save-overlay').style.display = 'flex';
      const nameInput = document.getElementById('save-receipt-name');
      const today = new Date();
      const y = today.getFullYear();
      const m = String(today.getMonth()+1).padStart(2,'0');
      const d = String(today.getDate()).padStart(2,'0');
      nameInput.value = document.getElementById('customer-name-display').textContent.trim() + ' - ' + `${y}-${m}-${d}`;
      document.body.style.overflow = 'hidden';
    }
    function closeSaveOverlay(){
      document.getElementById('save-overlay').style.display = 'none';
      document.body.style.overflow = '';
    }
    document.getElementById('save-overlay').addEventListener('click', function(e){ if(e.target===this){ closeSaveOverlay(); } });

    function collectCurrentData(){
      const rows = Array.from(document.querySelectorAll('#products-body tr'));
      const products = rows.map(tr => {
        const inputs = tr.querySelectorAll('input');
        return {
          item: inputs[0] ? inputs[0].value.trim() : '',
          description: inputs[1] ? inputs[1].value.trim() : '',
          price: inputs[2] ? inputs[2].value.trim() : ''
        };
      }).filter(p => p.item || p.description || p.price);
      const card = document.getElementById('card-root');
      const data = {
        shop: {
          shopName: document.getElementById('shop-title').textContent.trim(),
          vatNo: (document.querySelector('#vat-span .mono') ? document.querySelector('#vat-span .mono').textContent.trim() : ''),
          hidePrices: card.classList.contains('hide-prices'),
          dateISO: (document.getElementById('set-date-iso') ? document.getElementById('set-date-iso').value : '')
        },
        customerName: document.getElementById('customer-name-display').textContent.trim(),
        dateText: document.getElementById('current-date').textContent.trim(),
        products: products,
      };
      return data;
    }

    document.getElementById('save-form').addEventListener('submit', async function(e){
      e.preventDefault();
      const name = document.getElementById('save-receipt-name').value.trim();
      if(!name){ alert('Enter receipt name'); return; }
      const payload = collectCurrentData();
      const fd = new FormData();
      fd.append('action','save_named_receipt');
      fd.append('receipt_name', name);
      fd.append('data', JSON.stringify(payload));
      try{
        const resp = await fetch(window.location.href, { method:'POST', body: fd });
        const json = await resp.json();
        if(json && json.ok){
          // update dropdown list in settings if present
          const sel = document.getElementById('saved-receipts');
          if(sel){
            const opt = document.createElement('option');
            opt.value = json.file; opt.textContent = json.file; sel.appendChild(opt);
          }
          closeSaveOverlay();
          alert('Saved');
        }else{ alert('Failed to save'); }
      }catch(err){ alert('Failed to save'); }
    });

    function applyReceiptData(data){
      try{
        if(data.shop){
          if(data.shop.shopName !== undefined){ document.getElementById('shop-title').textContent = String(data.shop.shopName || 'Glorious Trade Hub'); }
          const vatSpan = document.getElementById('vat-span');
          const vat = String(data.shop.vatNo || '');
          vatSpan.innerHTML = vat ? ('VAT No: <span class="mono">' + vat.replace(/</g,'&lt;') + '</span>') : '';
          const card = document.getElementById('card-root');
          card.classList.toggle('hide-prices', !!data.shop.hidePrices);
          if(document.getElementById('set-date-iso')){ document.getElementById('set-date-iso').value = data.shop.dateISO || ''; }
        }
        if(data.customerName !== undefined){
          document.getElementById('customer-name-display').textContent = String(data.customerName || 'Your Name');
        }
        if(data.dateText){ document.getElementById('current-date').textContent = data.dateText; }
        // rebuild products
        const body = document.getElementById('products-body');
        body.innerHTML = '';
        const items = Array.isArray(data.products) ? data.products : [];
        items.forEach((p, idx) => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>
              <strong><input class="cell-input" name="products[${idx}][item]" value="${(p.item||'').replace(/"/g,'&quot;')}"></strong>
              <button type="button" class="row-remove" onclick="removeRow(this)">✕</button>
            </td>
            <td>
              <input class="cell-input" name="products[${idx}][description]" value="${(p.description||'').replace(/"/g,'&quot;')}">
            </td>
            <td class="price mono">
              <input class="cell-input price" name="products[${idx}][price]" value="${(p.price||'').replace(/"/g,'&quot;')}">
            </td>`;
          body.appendChild(tr);
        });
        reindex();
        computeTotal();
      }catch(err){ console.error(err); alert('Failed to apply receipt'); }
    }

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
      const el = document.getElementById('current-date');
      if(el.dataset.fixedDate !== '1'){
        el.textContent = now.toLocaleDateString('en-US', dateOptions);
      }
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

