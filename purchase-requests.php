<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>سجل طلبات الشراء | مصنع المنار للبلاستيك</title>
  <link rel="icon" type="image/svg+xml" href="favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    * { box-sizing: border-box; font-family: 'Cairo', 'Inter', sans-serif; }
    body { background: #f1f5f9; }
    .table-scroll::-webkit-scrollbar { height: 8px; }
    .table-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
    @media print { .no-print { display: none !important; } }
  </style>
</head>
<body class="min-h-screen text-slate-900">
  <main class="max-w-7xl mx-auto p-4 md:p-7">
    <header class="no-print flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-5 bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-3">
        <div class="w-11 h-11 bg-slate-950 rounded-xl text-white grid place-items-center text-xl shadow-sm">▤</div>
        <div>
          <h1 class="text-xl font-black tracking-tight">سجل طلبات الشراء</h1>
          <p class="text-xs text-slate-500 font-medium">Central Purchase Requisition Register · قاعدة البيانات المركزية</p>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <a href="forms/PR-2026-0042.html" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black shadow-sm transition">+ طلب شراء جديد</a>
        <button onclick="loadRequests()" class="px-4 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-black transition">↻ تحديث السجل</button>
      </div>
    </header>

    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="relative w-full md:max-w-md">
          <input id="searchInput" type="search" oninput="queueSearch()" placeholder="ابحث برقم الطلب أو القسم أو مقدم الطلب..." class="w-full border border-slate-300 rounded-xl py-2.5 pr-10 pl-3 text-sm outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
          <span class="absolute right-3 top-2.5 text-slate-400">⌕</span>
        </div>
        <div id="recordCount" class="text-xs font-bold text-slate-500">جاري تحميل السجل...</div>
      </div>

      <div class="table-scroll overflow-x-auto">
        <table class="w-full min-w-[900px] text-right text-sm">
          <thead class="bg-slate-50 text-slate-600 text-xs font-black border-b border-slate-200">
            <tr>
              <th class="px-5 py-3">رقم الطلب</th>
              <th class="px-5 py-3">تاريخ الطلب</th>
              <th class="px-5 py-3">القسم الطالب</th>
              <th class="px-5 py-3">مقدم الطلب</th>
              <th class="px-5 py-3 text-center">الأولوية</th>
              <th class="px-5 py-3 text-center">البنود</th>
              <th class="px-5 py-3">آخر تحديث</th>
              <th class="px-5 py-3 text-center">إجراء</th>
            </tr>
          </thead>
          <tbody id="requestsBody" class="divide-y divide-slate-100"></tbody>
        </table>
      </div>
      <div id="emptyState" class="hidden px-6 py-16 text-center text-slate-500">
        <div class="text-4xl mb-3">▤</div>
        <p class="font-black text-slate-700">لا توجد طلبات شراء محفوظة بعد</p>
        <p class="text-xs mt-1">عند حفظ أو طباعة أي طلب جديد، سيظهر هنا تلقائيًا لكل الأجهزة.</p>
      </div>
      <div id="errorState" class="hidden px-6 py-12 text-center text-rose-700"></div>
    </section>
  </main>

  <div id="detailsModal" class="hidden fixed inset-0 z-50 p-4 bg-slate-950/60 backdrop-blur-sm overflow-y-auto">
    <div class="max-w-6xl mx-auto my-4 bg-white rounded-2xl shadow-2xl overflow-hidden">
      <div class="no-print flex items-center justify-between gap-3 p-4 border-b border-slate-200 bg-slate-50">
        <div>
          <h2 id="modalTitle" class="font-black text-slate-900">تفاصيل طلب الشراء</h2>
          <p id="modalMeta" class="text-xs text-slate-500 mt-0.5"></p>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="printCurrentRequest()" class="px-3 py-2 bg-slate-900 hover:bg-slate-700 text-white rounded-lg text-xs font-black">طباعة A4 أفقي</button>
          <button onclick="closeDetails()" class="w-9 h-9 rounded-lg border border-slate-300 hover:bg-slate-100 text-slate-600 font-black">×</button>
        </div>
      </div>
      <article id="printableRequest" class="p-5 md:p-7" dir="rtl"></article>
    </div>
  </div>

  <script>
    let searchTimer;
    let currentRecord = null;
    const priorityNames = { normal: 'عادي', urgent: 'عاجل', emergency: 'طارئ' };
    const priorityClasses = { normal: 'bg-slate-100 text-slate-700', urgent: 'bg-amber-100 text-amber-800', emergency: 'bg-rose-100 text-rose-800' };

    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#039;', '"':'&quot;' }[c]));
    const formatDate = value => value ? new Intl.DateTimeFormat('ar-SA', { year:'numeric', month:'short', day:'numeric' }).format(new Date(value + (value.length === 10 ? 'T00:00:00' : ''))) : '—';

    function queueSearch() {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(loadRequests, 260);
    }

    async function loadRequests() {
      const body = document.getElementById('requestsBody');
      const empty = document.getElementById('emptyState');
      const error = document.getElementById('errorState');
      const count = document.getElementById('recordCount');
      const search = document.getElementById('searchInput').value.trim();
      body.innerHTML = '<tr><td colspan="8" class="px-5 py-10 text-center text-slate-400">جاري تحميل طلبات الشراء...</td></tr>';
      empty.classList.add('hidden'); error.classList.add('hidden');
      try {
        const response = await fetch(`api.php?action=list_purchase_requests&limit=500&search=${encodeURIComponent(search)}`);
        const result = await response.json();
        if (!response.ok || result.status !== 'success') throw new Error(result.message || 'تعذر تحميل السجل');
        const records = result.records || [];
        count.textContent = `${records.length} طلب شراء محفوظ`;
        if (!records.length) { body.innerHTML = ''; empty.classList.remove('hidden'); return; }
        body.innerHTML = records.map(row => `
          <tr class="hover:bg-emerald-50/40 transition">
            <td class="px-5 py-3.5"><span class="font-mono font-black text-blue-800 bg-blue-50 border border-blue-100 rounded-lg px-2 py-1">${escapeHtml(row.pr_number)}</span></td>
            <td class="px-5 py-3.5 font-mono text-xs text-slate-600">${formatDate(row.request_date)}</td>
            <td class="px-5 py-3.5 font-bold text-slate-700">${escapeHtml(row.department || '—')}</td>
            <td class="px-5 py-3.5 text-slate-600">${escapeHtml(row.requested_by || '—')}</td>
            <td class="px-5 py-3.5 text-center"><span class="inline-flex px-2.5 py-1 rounded-full text-xs font-black ${priorityClasses[row.priority] || priorityClasses.normal}">${priorityNames[row.priority] || 'عادي'}</span></td>
            <td class="px-5 py-3.5 text-center font-black text-slate-700">${Number(row.item_count || 0)}</td>
            <td class="px-5 py-3.5 font-mono text-[11px] text-slate-500">${formatDate(String(row.updated_at || '').slice(0, 10))}</td>
            <td class="px-5 py-3.5 text-center"><button onclick="openRequest('${encodeURIComponent(row.pr_number)}')" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black transition">فتح / طباعة</button></td>
          </tr>`).join('');
      } catch (err) {
        body.innerHTML = '';
        error.textContent = `تعذر الاتصال بقاعدة البيانات: ${err.message}`;
        error.classList.remove('hidden');
        count.textContent = 'تعذر تحميل السجل';
      }
    }

    async function openRequest(encodedPrNumber) {
      const prNumber = decodeURIComponent(encodedPrNumber);
      try {
        const response = await fetch(`api.php?action=get_purchase_request&pr_number=${encodeURIComponent(prNumber)}`);
        const result = await response.json();
        if (!response.ok || result.status !== 'success') throw new Error(result.message || 'تعذر فتح الطلب');
        currentRecord = result.record;
        renderRequest(currentRecord);
        document.getElementById('detailsModal').classList.remove('hidden');
      } catch (err) { alert(`تعذر فتح الطلب: ${err.message}`); }
    }

    function closeDetails() { document.getElementById('detailsModal').classList.add('hidden'); }

    function renderRequest(record) {
      const data = record.form_data || {};
      const items = data.items || [];
      document.getElementById('modalTitle').textContent = `طلب شراء ${record.pr_number}`;
      document.getElementById('modalMeta').textContent = `محفوظ ومحدث في قاعدة البيانات المركزية · ${formatDate(String(record.updated_at).slice(0, 10))}`;
      const itemRows = items.length ? items.map((item, index) => `<tr><td>${index + 1}</td><td>${escapeHtml(item.code)}</td><td class="text-right">${escapeHtml(item.desc)}</td><td>${escapeHtml(item.unit)}</td><td>${escapeHtml(item.qty)}</td><td>${escapeHtml(item.stock)}</td><td class="text-right">${escapeHtml(item.remarks)}</td></tr>`).join('') : '<tr><td colspan="7" class="text-center">لا توجد بنود مسجلة</td></tr>';
      document.getElementById('printableRequest').innerHTML = `
        <div class="border-2 border-slate-950 p-4 text-slate-950">
          <div class="flex items-start justify-between gap-6 border-b-2 border-slate-950 pb-3 mb-3">
            <div><h1 class="text-2xl font-black">طلب شراء</h1><p class="text-xs font-black tracking-widest">PURCHASE REQUISITION</p></div>
            <div class="text-center"><h2 class="font-black text-xl">مصنع المنار للبلاستيك</h2><p class="text-xs font-black tracking-wider">AL MANAR PLASTIC FACTORY</p></div>
            <div class="text-left text-xs leading-6"><div><b>PR No:</b> ${escapeHtml(record.pr_number)}</div><div><b>ISO:</b> 9001:2015</div></div>
          </div>
          <div class="grid grid-cols-4 gap-3 text-xs border-b border-slate-400 pb-3 mb-3">
            <div><b>القسم الطالب:</b><br>${escapeHtml(record.department || '—')}</div>
            <div><b>مقدم الطلب:</b><br>${escapeHtml(record.requested_by || '—')}</div>
            <div><b>تاريخ الطلب:</b><br>${formatDate(record.request_date)}</div>
            <div><b>تاريخ الاحتياج:</b><br>${formatDate(record.needed_date)}</div>
          </div>
          <table class="w-full border-collapse text-xs text-center"><thead><tr class="bg-slate-100"><th>م</th><th>كود الصنف</th><th>وصف الصنف والمواصفات</th><th>الوحدة</th><th>الكمية</th><th>رصيد المخزن</th><th>ملاحظات</th></tr></thead><tbody>${itemRows}</tbody></table>
          <div class="mt-3 border border-slate-400 p-2 text-xs min-h-16"><b>الغرض من الشراء / Purpose:</b><br>${escapeHtml(data.justification || '—')}</div>
          <div class="grid grid-cols-4 mt-3 border border-slate-950 text-center text-xs">${[['مراجعة المخزن', data.signatures?.store], ['مدير الإنتاج', data.signatures?.production], ['إدارة المشتريات', data.signatures?.purchasing], ['اعتماد الإدارة', data.signatures?.management]].map(([label, name]) => `<div class="p-3 min-h-20 border-l border-slate-950"><b>${label}</b><br><span class="text-slate-600">${escapeHtml(name || 'الاسم: ........................')}</span><br><span class="text-slate-600">التوقيع: ........................</span></div>`).join('')}</div>
        </div>`;
    }

    function printCurrentRequest() {
      if (!currentRecord) return;
      const source = document.getElementById('printableRequest').innerHTML;
      const win = window.open('', '_blank');
      if (!win) { alert('يرجى السماح بالنوافذ المنبثقة لإعادة الطباعة.'); return; }
      win.document.write(`<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>${escapeHtml(currentRecord.pr_number)}</title><style>@page{size:A4 landscape;margin:4mm}*{box-sizing:border-box;font-family:Arial,'Cairo',sans-serif}body{margin:0;color:#000}.border-2,.border{border-color:#000!important}.border-2{border-width:1.5px}.border{border-width:1px}.border-b-2{border-bottom:1.5px solid #000}.border-b{border-bottom:1px solid #000}.border-l{border-left:1px solid #000}.p-4{padding:4mm}.p-3{padding:3mm}.p-2{padding:2mm}.pb-3{padding-bottom:3mm}.mb-3{margin-bottom:3mm}.mt-3{margin-top:3mm}.flex{display:flex}.items-start{align-items:flex-start}.justify-between{justify-content:space-between}.gap-6{gap:6mm}.text-center{text-align:center}.text-left{text-align:left}.text-right{text-align:right}.text-2xl{font-size:20px}.text-xl{font-size:16px}.text-xs{font-size:10px}.font-black{font-weight:800}.font-bold{font-weight:700}.tracking-widest{letter-spacing:1px}.tracking-wider{letter-spacing:.5px}.leading-6{line-height:1.6}.grid{display:grid}.grid-cols-4{grid-template-columns:repeat(4,1fr)}.gap-3{gap:3mm}.min-h-16{min-height:16mm}.min-h-20{min-height:20mm}table{border-collapse:collapse;width:100%;table-layout:fixed}th,td{border:1px solid #000;padding:2px 3px;vertical-align:middle;word-break:break-word}th{background:#f1f5f9}.bg-slate-100{background:#f1f5f9}.text-slate-600{color:#475569}</style></head><body>${source}<script>window.onload=()=>window.print()<\/script></body></html>`);
      win.document.close();
    }

    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeDetails(); });
    loadRequests();
  </script>
</body>
</html>
