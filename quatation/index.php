<?php
declare(strict_types=1);

function format_npr(string|int|float $value): string {
  $num = (float)preg_replace('/[^\d.]/', '', (string)$value);
  return number_format($num, 2, '.', ',');
}

$quotesDir = __DIR__ . '/quotes';
$settingsFile = __DIR__ . '/settings.json';

// Default settings
$settings = [
  'companyName' => 'Your Company Name',
  'vatNo' => '',
  'vatPercent' => 13,
  'quoteDateISO' => '',
  'validTillISO' => ''
];
if (file_exists($settingsFile)) {
  $raw = file_get_contents($settingsFile);
  $decoded = json_decode($raw, true);
  if (is_array($decoded)) { $settings = array_merge($settings, $decoded); }
}

$companyName = (string)$settings['companyName'];
$vatNo = (string)$settings['vatNo'];
$vatPercent = (float)$settings['vatPercent'];
$quoteDateISO = (string)$settings['quoteDateISO'];
$validTillISO = (string)$settings['validTillISO'];

// Save settings
if (isset($_POST['action']) && $_POST['action'] === 'save_settings') {
  $companyName = trim((string)($_POST['company_name'] ?? $companyName));
  $vatNo = trim((string)($_POST['vat_no'] ?? $vatNo));
  $vatPercent = (float)($_POST['vat_percent'] ?? $vatPercent);
  $quoteDateISO = trim((string)($_POST['quote_date_iso'] ?? $quoteDateISO));
  $validTillISO = trim((string)($_POST['valid_till_iso'] ?? $validTillISO));
  $settings = [
    'companyName' => $companyName,
    'vatNo' => $vatNo,
    'vatPercent' => $vatPercent,
    'quoteDateISO' => $quoteDateISO,
    'validTillISO' => $validTillISO,
  ];
  @file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  header('Content-Type: application/json');
  echo json_encode(['ok' => true, 'settings' => $settings]);
  exit;
}

// Save named quotation
if (isset($_POST['action']) && $_POST['action'] === 'save_named_quote') {
  header('Content-Type: application/json');
  $name = trim((string)($_POST['quote_name'] ?? ''));
  $dataJson = (string)($_POST['data'] ?? '');
  $data = json_decode($dataJson, true);
  if ($name === '' || !is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid data']); exit; }
  if (!is_dir($quotesDir)) { @mkdir($quotesDir, 0777, true); }
  $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-_]+/', '-', $name), '-'));
  if ($slug === '') { $slug = 'quote-' . date('Ymd-His'); }
  $path = $quotesDir . '/' . $slug . '.json';
  $data['meta'] = ['name'=>$name, 'savedAt'=>date(DATE_ATOM)];
  $ok = @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  echo json_encode($ok === false ? ['ok'=>false] : ['ok'=>true,'file'=>basename($path),'name'=>$name]);
  exit;
}

// Load quotation
if (isset($_POST['action']) && $_POST['action'] === 'load_quote') {
  header('Content-Type: application/json');
  $file = basename((string)($_POST['quote_file'] ?? ''));
  if ($file === '' || !preg_match('/\.json$/', $file)) { echo json_encode(['ok'=>false]); exit; }
  $path = $quotesDir . '/' . $file;
  if (!file_exists($path)) { echo json_encode(['ok'=>false]); exit; }
  $raw = file_get_contents($path);
  $json = json_decode($raw, true);
  echo json_encode(['ok'=>is_array($json), 'data'=>$json]);
  exit;
}

$customerName = 'Customer Name';
if (isset($_POST['customer_name']) && $_POST['customer_name'] !== '') {
  $customerName = trim((string)$_POST['customer_name']);
}

// Build date strings
$quoteDate = $quoteDateISO ? date('d M Y', strtotime($quoteDateISO)) : date('d M Y');
$validTill = $validTillISO ? date('d M Y', strtotime($validTillISO)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Custom Quotation Builder – Projects</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="apple-touch-icon" href="apple-touch-icon.png">
  <link rel="icon" href="favicon.ico">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg:#f5f7fb; --card:#ffffff; --text:#0f172a; --muted:#64748b; --line:#e5e7eb; --accent:#2563eb;
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--text);font-family:Inter,system-ui,Segoe UI,Roboto,Arial}
    .wrap{max-width:900px;margin:28px auto;padding:24px}
    .card{background:var(--card);border:1px solid var(--line);border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(2,6,23,.06)}
    .header{padding:22px 24px;background:linear-gradient(180deg,#fff, #f8fafc)}
    .brand{display:flex;justify-content:space-between;align-items:center}
    .title{font-size:26px;margin:0;color:#1f2937}
    .muted{color:var(--muted)}
    .meta{display:flex;gap:18px;flex-wrap:wrap;margin-top:8px}
    .kvs{display:flex;gap:8px;padding:8px 10px;border:1px solid var(--line);border-radius:12px;background:#fff}
    .kvs span:first-child{color:var(--muted)}
    .content{padding:18px 24px}
    .table{width:100%;border-collapse:separate;border-spacing:0;margin-top:6px;border:1px solid var(--line);border-radius:14px;overflow:hidden}
    .table thead th{background:#f8fafc;text-align:left;font-size:13px;font-weight:600;color:#334155;padding:12px 12px;border-bottom:1px solid var(--line)}
    .table tbody td{padding:10px 12px;border-bottom:1px solid var(--line);position:relative}
    .table tbody tr:last-child td{border-bottom:none}
    .cell-input{width:100%;border:none;background:transparent;outline:none;color:inherit;font:inherit}
    .num{font-variant-numeric:tabular-nums;text-align:right}
    .row-remove{position:absolute;left:8px;top:50%;transform:translateY(-50%);border:1px solid var(--line);background:#f1f5f9;color:#334155;border-radius:10px;padding:4px 8px;cursor:pointer}
    .totals{display:flex;justify-content:flex-end;margin-top:14px}
    .totals-card{min-width:320px;border:1px dashed var(--line);border-radius:12px;padding:12px 14px;background:#fff}
    .tot-row{display:flex;justify-content:space-between;margin:6px 0}
    .tot-row.total{font-weight:800;font-size:18px}
    .actions{display:flex;justify-content:space-between;gap:10px;margin-top:18px}
    .btn{border:none;border-radius:12px;padding:10px 14px;font-weight:600;color:#fff;background:var(--accent);cursor:pointer}
    .btn.secondary{background:#0ea5e9}
    .btn.ghost{background:#fff;color:#334155;border:1px solid var(--line)}
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);display:none;align-items:center;justify-content:center;z-index:9999}
    .modal{width:min(680px,92vw);background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .form-row{display:flex;flex-direction:column;gap:6px}
    .label{font-size:12px;color:#64748b}
    .input{border:1px solid var(--line);border-radius:10px;padding:10px 12px;background:#fff;color:#0f172a}
    @media print{ .btn,.row-remove,.editor-tools{ display:none } body{background:#fff} .card{border:none;box-shadow:none} .header{background:#fff} }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card" id="card-root">
      <div class="header">
        <div class="brand">
          <div>
            <h1 class="title">Price Quote</h1>
            <div class="muted" id="company-line"><strong id="company-name"><?= htmlspecialchars($companyName) ?></strong><?= $vatNo !== '' ? ' • VAT: <span class="mono">'.htmlspecialchars($vatNo).'</span>' : '' ?></div>
          </div>
          <div class="muted" style="text-align:right">
            <div>Valid From: <span id="quote-date"><?= htmlspecialchars($quoteDate) ?></span></div>
            <div>To: <span id="valid-till"><?= htmlspecialchars($validTill) ?></span></div>
          </div>
        </div>
        <div class="meta">
          <div class="kvs"><span>Quote To</span><span class="mono" id="customer-name-display"><?= htmlspecialchars($customerName) ?></span></div>
          <input type="hidden" id="customer-name-input" value="<?= htmlspecialchars($customerName) ?>">
        </div>
      </div>

      <div class="content">
        <table class="table" id="items-table">
          <thead>
            <tr>
              <th style="width:28%">Product Name</th>
              <th>Description</th>
              <th class="num" style="width:10%">Qty</th>
              <th class="num" style="width:16%">Unit Price</th>
              <th class="num" style="width:16%">Amount</th>
            </tr>
          </thead>
          <tbody id="items-body">
            <tr>
              <td>
                <button type="button" class="row-remove" onclick="removeRow(this)">✕</button>
                <input class="cell-input" name="items[0][name]" placeholder="Item" value="">
              </td>
              <td>
                <input class="cell-input" name="items[0][desc]" placeholder="Description" value="">
              </td>
              <td class="num"><input class="cell-input num qty" name="items[0][qty]" placeholder="0" value=""></td>
              <td class="num"><input class="cell-input num unit" name="items[0][unit]" placeholder="0" value=""></td>
              <td class="num amount mono">0.00</td>
            </tr>
          </tbody>
        </table>

        <div class="totals">
          <div class="totals-card">
            <div class="tot-row"><span>Sub Amount</span><span class="mono" id="subtotal">0.00</span></div>
            <div class="tot-row"><span>VAT <span id="vat-percent-label"><?= (int)$vatPercent ?></span>%</span><span class="mono" id="vat-amount">0.00</span></div>
            <div class="tot-row total"><span>Total Amount</span><span class="mono" id="total">0.00</span></div>
          </div>
        </div>

        <div class="actions">
          <div class="editor-tools" style="display:flex;gap:10px">
            <button class="btn ghost" type="button" onclick="openSettings()">Settings</button>
            <button class="btn ghost" type="button" onclick="addRow()">Add Row</button>
            <button class="btn secondary" type="button" onclick="openSaveOverlay()">Save Quotation</button>
          </div>
          <div>
            <button class="btn" type="button" onclick="window.print()">Print / Save PDF</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <template id="row-template">
    <tr>
      <td>
        <button type="button" class="row-remove" onclick="removeRow(this)">✕</button>
        <input class="cell-input" name="__INDEX__" placeholder="Item" value="">
      </td>
      <td>
        <input class="cell-input" name="__INDEX__" placeholder="Description" value="">
      </td>
      <td class="num"><input class="cell-input num qty" name="__INDEX__" placeholder="0" value=""></td>
      <td class="num"><input class="cell-input num unit" name="__INDEX__" placeholder="0" value=""></td>
      <td class="num amount mono">0.00</td>
    </tr>
  </template>

  <div class="modal-overlay" id="settings-overlay">
    <div class="modal">
      <h3 style="margin:0 0 8px 0">Settings</h3>
      <form id="settings-form">
        <div class="form-grid">
          <div class="form-row"><label class="label">Company Name</label><input class="input" type="text" id="set-company-name" name="company_name" value="<?= htmlspecialchars($companyName) ?>"></div>
          <div class="form-row"><label class="label">VAT No</label><input class="input" type="text" id="set-vat-no" name="vat_no" value="<?= htmlspecialchars($vatNo) ?>"></div>
          <div class="form-row"><label class="label">VAT Percent</label><input class="input" type="number" id="set-vat-percent" name="vat_percent" min="0" step="0.01" value="<?= htmlspecialchars((string)$vatPercent) ?>"></div>
          <div class="form-row"><label class="label">Quote Date</label><input class="input" type="date" id="set-quote-date" name="quote_date_iso" value="<?= htmlspecialchars($quoteDateISO) ?>"></div>
          <div class="form-row"><label class="label">Valid Till</label><input class="input" type="date" id="set-valid-till" name="valid_till_iso" value="<?= htmlspecialchars($validTillISO) ?>"></div>
          <div class="form-row"><label class="label">Customer Name</label><input class="input" type="text" id="set-customer-name" value="<?= htmlspecialchars($customerName) ?>"></div>
        </div>
        <div class="form-row" style="margin-top:12px;">
          <label class="label">Load Saved Quote</label>
          <div style="display:flex;gap:10px">
            <select class="input" id="saved-quotes" style="min-width:260px">
              <option value="">-- Select saved quote --</option>
              <?php if (is_dir($quotesDir)) { foreach (glob($quotesDir.'/*.json') as $f) { $b=basename($f); echo '<option value="'.htmlspecialchars($b).'">'.htmlspecialchars($b).'</option>'; } } ?>
            </select>
            <button type="button" class="btn ghost" onclick="loadSelectedQuote()">Load</button>
          </div>
        </div>
        <div class="form-row">
          <label class="label">Import Quote (JSON)</label>
          <input class="input" type="file" id="import-quote-file" accept="application/json,.json">
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:12px">
          <button type="button" class="btn ghost" onclick="closeSettings()">Close</button>
          <button type="submit" class="btn">Save Settings</button>
        </div>
        <input type="hidden" name="action" value="save_settings">
      </form>
    </div>
  </div>

  <div class="modal-overlay" id="save-overlay">
    <div class="modal">
      <h3 style="margin:0 0 8px 0">Save Quotation</h3>
      <form id="save-form">
        <div class="form-row"><label class="label">Quote Name</label><input class="input" type="text" id="save-quote-name" placeholder="Client - YYYY-MM-DD"></div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:12px">
          <button type="button" class="btn ghost" onclick="closeSaveOverlay()">Close</button>
          <button type="submit" class="btn">Save</button>
        </div>
        <input type="hidden" name="action" value="save_named_quote">
      </form>
    </div>
  </div>

  <script>
    function openSettings(){ document.getElementById('settings-overlay').style.display='flex'; document.getElementById('set-customer-name').value = document.getElementById('customer-name-display').textContent.trim(); document.body.style.overflow='hidden'; }
    function closeSettings(){ document.getElementById('settings-overlay').style.display='none'; document.body.style.overflow=''; }
    document.getElementById('settings-overlay').addEventListener('click', e=>{ if(e.target===e.currentTarget) closeSettings(); });

    document.getElementById('settings-form').addEventListener('submit', async (e)=>{
      e.preventDefault();
      const fd = new FormData(e.currentTarget);
      try{ const r=await fetch(location.href,{method:'POST',body:fd}); const j=await r.json(); if(j&&j.ok){
        document.getElementById('company-name').textContent = document.getElementById('set-company-name').value || 'Your Company Name';
        const vat = document.getElementById('set-vat-no').value.trim();
        document.getElementById('company-line').innerHTML = '<strong id="company-name">'+(document.getElementById('company-name').textContent)+'</strong>' + (vat? ' • VAT: <span class="mono">'+vat.replace(/</g,'&lt;')+'</span>':'' );
        document.getElementById('vat-percent-label').textContent = (document.getElementById('set-vat-percent').value || '0');
        const q = document.getElementById('set-quote-date').value, v = document.getElementById('set-valid-till').value;
        const opts={year:'numeric',month:'short',day:'numeric'};
        document.getElementById('quote-date').textContent = q? new Date(q).toLocaleDateString('en-US',opts): document.getElementById('quote-date').textContent;
        document.getElementById('valid-till').textContent = v? new Date(v).toLocaleDateString('en-US',opts): '';
        document.getElementById('customer-name-display').textContent = document.getElementById('set-customer-name').value || 'Customer Name';
        computeTotals();
        closeSettings();
      }}catch(err){ alert('Failed to save settings'); }
    });

    function addRow(){ const tpl = document.getElementById('row-template').content.cloneNode(true); document.getElementById('items-body').appendChild(tpl); reindex(); computeTotals(); }
    function removeRow(btn){ const tr = btn.closest('tr'); if(tr){ tr.remove(); reindex(); computeTotals(); } }
    function reindex(){ document.querySelectorAll('#items-body tr').forEach((tr,i)=>{ const inputs=tr.querySelectorAll('input'); if(inputs.length>=4){ inputs[0].name=`items[${i}][name]`; inputs[1].name=`items[${i}][desc]`; inputs[2].name=`items[${i}][qty]`; inputs[3].name=`items[${i}][unit]`; } }); }

    document.addEventListener('input', (e)=>{ if(e.target && (e.target.classList.contains('qty')||e.target.classList.contains('unit'))){ computeTotals(); }});

    function computeTotals(){
      let subtotal=0; const rows=document.querySelectorAll('#items-body tr');
      rows.forEach(tr=>{ const qty=parseFloat((tr.querySelector('.qty')?.value||'').replace(/[^\d.]/g,''))||0; const unit=parseFloat((tr.querySelector('.unit')?.value||'').replace(/[^\d.]/g,''))||0; const amt=qty*unit; tr.querySelector('.amount').textContent = amt.toFixed(2); subtotal+=amt; });
      document.getElementById('subtotal').textContent = subtotal.toFixed(2);
      const vatPercent = parseFloat(document.getElementById('vat-percent-label').textContent)||0; const vatAmt=subtotal*vatPercent/100; document.getElementById('vat-amount').textContent = vatAmt.toFixed(2);
      document.getElementById('total').textContent = (subtotal+vatAmt).toFixed(2);
    }

    function openSaveOverlay(){
      document.getElementById('save-overlay').style.display='flex';
      const today=new Date(), y=today.getFullYear(), m=String(today.getMonth()+1).padStart(2,'0'), d=String(today.getDate()).padStart(2,'0');
      document.getElementById('save-quote-name').value = (document.getElementById('customer-name-display').textContent||'Client') + ' - ' + `${y}-${m}-${d}`;
      document.body.style.overflow='hidden';
    }
    function closeSaveOverlay(){ document.getElementById('save-overlay').style.display='none'; document.body.style.overflow=''; }
    document.getElementById('save-overlay').addEventListener('click', e=>{ if(e.target===e.currentTarget) closeSaveOverlay(); });

    function collectQuoteData(){
      const items = Array.from(document.querySelectorAll('#items-body tr')).map(tr=>({
        name: tr.querySelector('input[name$="[name]"]')?.value||'',
        desc: tr.querySelector('input[name$="[desc]"]')?.value||'',
        qty: tr.querySelector('.qty')?.value||'',
        unit: tr.querySelector('.unit')?.value||'',
        amount: tr.querySelector('.amount')?.textContent||'0.00',
      })).filter(i=>i.name||i.desc||i.qty||i.unit);
      return {
        settings: {
          companyName: document.getElementById('company-name').textContent.trim(),
          vatNo: (document.querySelector('#company-line .mono')?.textContent||'').trim(),
          vatPercent: parseFloat(document.getElementById('vat-percent-label').textContent)||0,
          quoteDate: document.getElementById('quote-date').textContent.trim(),
          validTill: document.getElementById('valid-till').textContent.trim()
        },
        customerName: document.getElementById('customer-name-display').textContent.trim(),
        items,
        totals: { subtotal: document.getElementById('subtotal').textContent, vat: document.getElementById('vat-amount').textContent, total: document.getElementById('total').textContent }
      };
    }

    document.getElementById('save-form').addEventListener('submit', async (e)=>{
      e.preventDefault();
      const name=(document.getElementById('save-quote-name').value||'').trim(); if(!name){ alert('Enter a name'); return; }
      const fd=new FormData(); fd.append('action','save_named_quote'); fd.append('quote_name', name); fd.append('data', JSON.stringify(collectQuoteData()));
      try{ const r=await fetch(location.href,{method:'POST',body:fd}); const j=await r.json(); if(j&&j.ok){ const sel=document.getElementById('saved-quotes'); if(sel){ const opt=document.createElement('option'); opt.value=j.file; opt.textContent=j.file; sel.appendChild(opt);} closeSaveOverlay(); alert('Saved'); } else alert('Failed to save'); }catch(_e){ alert('Failed to save'); }
    });

    async function loadSelectedQuote(){
      const file=document.getElementById('saved-quotes').value; if(!file){ alert('Select a quote'); return; }
      const fd=new FormData(); fd.append('action','load_quote'); fd.append('quote_file', file);
      try{ const r=await fetch(location.href,{method:'POST',body:fd}); const j=await r.json(); if(j&&j.ok){ applyQuoteData(j.data); closeSettings(); } else alert('Failed to load'); }catch(_e){ alert('Failed to load'); }
    }

    document.getElementById('import-quote-file').addEventListener('change', (e)=>{ const f=e.target.files[0]; if(!f) return; const reader=new FileReader(); reader.onload=()=>{ try{ const data=JSON.parse(reader.result); applyQuoteData(data); closeSettings(); }catch(_e){ alert('Invalid JSON'); } }; reader.readAsText(f); });

    function applyQuoteData(data){
      try{
        if(data.settings){
          document.getElementById('company-name').textContent = data.settings.companyName || 'Your Company Name';
          const vat = data.settings.vatNo || '';
          document.getElementById('company-line').innerHTML = '<strong id="company-name">'+(data.settings.companyName||'Your Company Name')+'</strong>' + (vat? ' • VAT: <span class="mono">'+String(vat).replace(/</g,'&lt;')+'</span>':'');
          document.getElementById('vat-percent-label').textContent = String(data.settings.vatPercent||0);
          document.getElementById('quote-date').textContent = data.settings.quoteDate || '';
          document.getElementById('valid-till').textContent = data.settings.validTill || '';
        }
        if(typeof data.customerName === 'string'){ document.getElementById('customer-name-display').textContent = data.customerName || 'Customer Name'; }
        const body=document.getElementById('items-body'); body.innerHTML='';
        const items=Array.isArray(data.items)?data.items:[];
        items.forEach((it, i)=>{
          const tr=document.createElement('tr');
          tr.innerHTML = `
            <td>
              <button type="button" class="row-remove" onclick="removeRow(this)">✕</button>
              <input class="cell-input" name="items[${i}][name]" value="${String(it.name||'').replace(/"/g,'&quot;')}">
            </td>
            <td><input class="cell-input" name="items[${i}][desc]" value="${String(it.desc||'').replace(/"/g,'&quot;')}"></td>
            <td class="num"><input class="cell-input num qty" name="items[${i}][qty]" value="${String(it.qty||'')}"></td>
            <td class="num"><input class="cell-input num unit" name="items[${i}][unit]" value="${String(it.unit||'')}"></td>
            <td class="num amount mono">${String(it.amount||'0.00')}</td>`;
          body.appendChild(tr);
        });
        reindex(); computeTotals();
      }catch(e){ console.error(e); alert('Failed to apply quote'); }
    }

    // Init
    computeTotals();
  </script>
</body>
</html>

