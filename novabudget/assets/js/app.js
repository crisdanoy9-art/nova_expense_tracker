/* assets/js/app.js — NovaBudget shared JS */

/* ── SIDEBAR ── */
function openSidebar()  { document.getElementById('sidebar')?.classList.add('open'); document.getElementById('sidebar-overlay')?.classList.add('open'); }
function closeSidebar() { document.getElementById('sidebar')?.classList.remove('open'); document.getElementById('sidebar-overlay')?.classList.remove('open'); }

/* ── TOAST ── */
function showToast(msg, type) {
  type = type || 'info';
  const box = document.getElementById('toast-box');
  if (!box) return;
  const t = document.createElement('div');
  t.className = 'toast-n ' + type;
  const icons = { ok:'<i class="bi bi-check-circle-fill" style="color:#4ade80;font-size:15px"></i>', err:'<i class="bi bi-exclamation-circle-fill" style="color:#f87171;font-size:15px"></i>', info:'<i class="bi bi-info-circle-fill" style="color:#00e5ff;font-size:15px"></i>', warn:'<i class="bi bi-exclamation-triangle-fill" style="color:#f59e0b;font-size:15px"></i>' };
  t.innerHTML = (icons[type]||icons.info) + '<span style="flex:1">' + msg + '</span>';
  box.appendChild(t);
  t.onclick = () => dismiss(t);
  const timer = setTimeout(() => dismiss(t), 3200);
  function dismiss(el) { clearTimeout(timer); el.classList.add('toast-out'); setTimeout(() => el.remove(), 260); }
}

/* ── FIELD VALIDATION HELPERS ── */
function showErr(id, msg) { const e = document.getElementById(id); if(e){e.textContent=msg||e.textContent;e.style.display='block';} }
function hideErr(id)      { const e = document.getElementById(id); if(e) e.style.display='none'; }
function markErr(id)      { document.getElementById(id)?.classList.add('err'); }
function clearErr(id)     { document.getElementById(id)?.classList.remove('err'); hideErr(id+'-err'); }
function togglePwd(id, btn) {
  const i = document.getElementById(id);
  if(!i) return;
  const show = i.type === 'password';
  i.type = show ? 'text' : 'password';
  btn.innerHTML = show ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
}

/* ── CHART DEFAULTS ── */
const CHART_TOOLTIP_OPTS = {
  backgroundColor:'rgba(11,17,32,.95)',
  titleColor:'#f1f5f9',bodyColor:'#94a3b8',
  borderColor:'rgba(255,255,255,.12)',borderWidth:1,padding:11,cornerRadius:8
};
const CHART_SCALE_OPTS = {
  x:{ grid:{color:'rgba(255,255,255,.04)'}, ticks:{color:'#475569',font:{size:11}} },
  y:{ grid:{color:'rgba(255,255,255,.04)'}, ticks:{color:'#475569',font:{size:11}}, beginAtZero:true }
};

const _charts = {};
function makeChart(id, config) {
  if (_charts[id]) _charts[id].destroy();
  const canvas = document.getElementById(id);
  if (!canvas) return null;
  _charts[id] = new Chart(canvas, config);
  return _charts[id];
}

/* ── CALENDAR RENDERER ── */
function renderCalendar(year, month, dayData, onCellClick) {
  const gridId = 'cal-grid';
  const grid = document.getElementById(gridId);
  if (!grid) return;
  const dayHeaders = Array.from(grid.querySelectorAll('.cal-dh'));
  grid.innerHTML = '';
  dayHeaders.forEach(h => grid.appendChild(h));

  const today = new Date();
  const firstDay = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const daysInPrev = new Date(year, month, 0).getDate();
  const isCurrentMonth = today.getFullYear() === year && today.getMonth() === month;

  const addCell = (day, isCurrentM, isToday) => {
    const cell = document.createElement('div');
    const dateStr = year + '-' + String(month+1).padStart(2,'0') + '-' + String(day).padStart(2,'0');
    cell.className = 'cal-cell' + (!isCurrentM?' other-month':'') + (isToday?' today':'');
    const numEl = document.createElement('div');
    numEl.className = 'cal-day-num';
    numEl.textContent = day;
    cell.appendChild(numEl);
    if (isCurrentM && dayData && dayData[day]) {
      const d = dayData[day];
      const dots = document.createElement('div');
      dots.className = 'cal-dots';
      (d.colors||[]).slice(0,4).forEach(c => {
        const dot = document.createElement('span');
        dot.className='cal-dot'; dot.style.background=c;
        dots.appendChild(dot);
      });
      cell.appendChild(dots);
      const total = document.createElement('div');
      total.className='cal-total';
      total.textContent='$' + (d.total||0).toFixed(0);
      cell.appendChild(total);
    }
    if (isCurrentM && onCellClick) {
      cell.onclick = () => {
        document.querySelectorAll('.cal-cell').forEach(c=>c.classList.remove('selected'));
        cell.classList.add('selected');
        onCellClick(day, dateStr, dayData?.[day]||null);
      };
    }
    grid.appendChild(cell);
  };

  for (let i = firstDay - 1; i >= 0; i--) addCell(daysInPrev - i, false, false);
  for (let d = 1; d <= daysInMonth; d++) addCell(d, true, isCurrentMonth && today.getDate() === d);
  const totalCells = Math.ceil((firstDay + daysInMonth) / 7) * 7;
  for (let n = 1; n <= totalCells - firstDay - daysInMonth; n++) addCell(n, false, false);
}

/* ── NOTIFICATIONS ── */
function toggleNotifPanel() {
  const p = document.getElementById('notif-panel');
  if (!p) return;
  const open = p.style.display === 'block';
  p.style.display = open ? 'none' : 'block';
  if (!open) loadNotifications();
}
function loadNotifications() {
  fetch('/api/notifications.php')
    .then(r => r.json())
    .then(data => {
      const list = document.getElementById('notif-list');
      const dot  = document.getElementById('notif-dot');
      if (!list) return;
      const unread = (data.notifications||[]).filter(n=>!n.is_read);
      if (dot) dot.style.display = unread.length ? 'block' : 'none';
      if (!data.notifications?.length) { list.innerHTML='<div style="padding:20px;text-align:center;color:var(--text3);font-size:13px">All caught up! 🎉</div>'; return; }
      list.innerHTML = data.notifications.map(n => `
        <div class="notif-item ${n.is_read?'':'unread'}" onclick="readNotif('${n.id}')">
          <div class="notif-item-title">${n.title}</div>
          <div class="notif-item-msg">${n.message}</div>
          <div class="notif-item-time">${n.created_at}</div>
        </div>`).join('');
    }).catch(()=>{});
}
function readNotif(id) {
  fetch('/api/notifications.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'read',id}) });
  const el = document.querySelector(`[onclick="readNotif('${id}')"]`);
  if (el) el.classList.remove('unread');
}
function markAllRead() {
  fetch('/api/notifications.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'read_all'}) });
  document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
  const dot = document.getElementById('notif-dot');
  if (dot) dot.style.display = 'none';
}

/* ── AI CLAUDE CALL ── */
async function callClaude(systemPrompt, userMsg, opts) {
  opts = opts || {};
  const elId = opts.outputEl;
  if (elId) setAILoading(elId);
  try {
    const res = await fetch('/api/claude.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({ system: systemPrompt, message: userMsg, max_tokens: opts.maxTokens||800 })
    });
    const data = await res.json();
    if (!res.ok || data.error) throw new Error(data.error||'API error');
    if (elId) setAIText(elId, data.text, false);
    return data.text;
  } catch (e) {
    const msg = e.message||'AI service unavailable.';
    if (elId) setAIText(elId, msg, true);
    return null;
  }
}
function setAILoading(elId) {
  const el = document.getElementById(elId);
  if (!el) return;
  el.style.display='block';
  el.innerHTML='<div class="ai-dots"><span></span><span></span><span></span></div>';
}
function setAIText(elId, text, isErr) {
  const el = document.getElementById(elId);
  if (!el) return;
  el.style.display='block';
  el.innerHTML=`<span style="color:${isErr?'var(--text3)':'var(--text1)'}">${text}</span>`;
}

/* ── MODAL HELPERS ── */
function openModal(id)  { const el=document.getElementById(id); if(el) el.style.display='flex'; }
function closeModal(id) { const el=document.getElementById(id); if(el) el.style.display='none'; }
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) closeModal(e.target.id);
  // Close notif panel on outside click
  const np=document.getElementById('notif-panel');
  const nb=document.getElementById('notif-btn');
  if(np&&nb&&!nb.contains(e.target)&&!np.contains(e.target)) np.style.display='none';
});

/* ── TABLE SORT ── */
function sortTable(tableId, colIdx) {
  const tbl = document.getElementById(tableId);
  if (!tbl) return;
  const tbody = tbl.querySelector('tbody');
  const rows = Array.from(tbody.querySelectorAll('tr'));
  const th = tbl.querySelectorAll('th')[colIdx];
  const asc = !th.classList.contains('sort-asc');
  tbl.querySelectorAll('th').forEach(h => h.classList.remove('sort-asc','sort-desc'));
  th.classList.add(asc ? 'sort-asc' : 'sort-desc');
  rows.sort((a,b) => {
    const va = a.cells[colIdx]?.textContent.trim()||'';
    const vb = b.cells[colIdx]?.textContent.trim()||'';
    const na = parseFloat(va.replace(/[$,]/g,''));
    const nb2 = parseFloat(vb.replace(/[$,]/g,''));
    if (!isNaN(na) && !isNaN(nb2)) return asc ? na-nb2 : nb2-na;
    return asc ? va.localeCompare(vb) : vb.localeCompare(va);
  });
  rows.forEach(r => tbody.appendChild(r));
}

/* ── EXPORT HELPERS ── */
function exportTableCSV(tableId, filename) {
  const tbl = document.getElementById(tableId);
  if (!tbl) return;
  const rows = Array.from(tbl.querySelectorAll('tr'));
  const csv = rows.map(r => Array.from(r.cells).map(c => '"'+c.textContent.trim().replace(/"/g,'""')+'"').join(',')).join('\n');
  const a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  a.download = (filename||'export') + '-' + new Date().toISOString().split('T')[0] + '.csv';
  a.click();
  showToast('CSV exported!','ok');
}

/* ── CONFIRM DELETE ── */
function confirmDelete(msg, formId) {
  if (confirm(msg||'Are you sure?')) {
    const form = document.getElementById(formId);
    if (form) form.submit();
    return true;
  }
  return false;
}

/* ── PRINT PAGE ── */
function printPage() { window.print(); }

/* ── INIT on DOM ready ── */
document.addEventListener('DOMContentLoaded', () => {
  // Auto-hide flash messages after 4s
  document.querySelectorAll('.flash-msg').forEach(el => setTimeout(() => { el.style.opacity='0'; el.style.transition='opacity .4s'; setTimeout(()=>el.remove(),400); }, 4000));
  // Load notification badge count
  fetch('/api/notifications.php?count=1').then(r=>r.json()).then(d => {
    const dot = document.getElementById('notif-dot');
    if (dot && d.unread > 0) dot.style.display='block';
  }).catch(()=>{});
});
