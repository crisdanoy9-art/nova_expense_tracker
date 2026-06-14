/* assets/js/auth.js — NovaBudget Auth Page Scripts
   Star Canvas · Particle Engine · Form Interactions · Toast
*/

/* ═══════════════════════════════════════════════════
   DYNAMIC STAR CANVAS
═══════════════════════════════════════════════════ */
(function () {
  const canvas = document.getElementById('star-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W, H, stars = [], mouse = { x: -999, y: -999 };

  const STAR_COLORS = [
    '#e8f4ff', '#00fff7', '#39ff14', '#b400ff',
    '#ff2d2d', '#ff6b00', '#aaffaa', '#0090ff'
  ];

  function resize() {
    W = canvas.width  = window.innerWidth;
    H = canvas.height = window.innerHeight;
  }

  function createStars() {
    stars = Array.from({ length: 200 }, () => ({
      x:     Math.random() * W,
      y:     Math.random() * H,
      vx:    (Math.random() - 0.5) * 0.25,
      vy:    (Math.random() - 0.5) * 0.25,
      r:     Math.random() * 2 + 0.4,
      color: STAR_COLORS[Math.floor(Math.random() * STAR_COLORS.length)],
      alpha: Math.random() * 0.7 + 0.15,
      phase: Math.random() * Math.PI * 2,
      speed: 0.015 + Math.random() * 0.025,
    }));
  }

  function hexA(hex, a) {
    const r = parseInt(hex.slice(1,3),16);
    const g = parseInt(hex.slice(3,5),16);
    const b = parseInt(hex.slice(5,7),16);
    return `rgba(${r},${g},${b},${a})`;
  }

  function drawStar(s) {
    s.phase += s.speed;
    const a = s.alpha * (0.5 + 0.5 * Math.sin(s.phase));
    ctx.beginPath();
    ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
    ctx.fillStyle = hexA(s.color.startsWith('#') ? s.color : '#e8f4ff', a);
    ctx.fill();
    if (s.r > 1.1) {
      const grd = ctx.createRadialGradient(s.x, s.y, 0, s.x, s.y, s.r * 4);
      grd.addColorStop(0, hexA(s.color.startsWith('#') ? s.color : '#e8f4ff', a * 0.4));
      grd.addColorStop(1, 'transparent');
      ctx.beginPath();
      ctx.arc(s.x, s.y, s.r * 4, 0, Math.PI * 2);
      ctx.fillStyle = grd;
      ctx.fill();
    }
  }

  function drawConnections() {
    const MAX = 110;
    for (let i = 0; i < stars.length; i++) {
      // Star-to-star
      for (let j = i + 1; j < stars.length; j++) {
        const dx = stars[i].x - stars[j].x;
        const dy = stars[i].y - stars[j].y;
        const d  = Math.sqrt(dx * dx + dy * dy);
        if (d < MAX) {
          ctx.beginPath();
          ctx.moveTo(stars[i].x, stars[i].y);
          ctx.lineTo(stars[j].x, stars[j].y);
          ctx.strokeStyle = `rgba(0,255,247,${(1 - d / MAX) * 0.14})`;
          ctx.lineWidth = 0.5;
          ctx.stroke();
        }
      }
      // Star-to-mouse
      const dmx = stars[i].x - mouse.x;
      const dmy = stars[i].y - mouse.y;
      const dm  = Math.sqrt(dmx * dmx + dmy * dmy);
      if (dm < 170) {
        const a = (1 - dm / 170) * 0.55;
        ctx.beginPath();
        ctx.moveTo(stars[i].x, stars[i].y);
        ctx.lineTo(mouse.x, mouse.y);
        ctx.strokeStyle = `rgba(57,255,20,${a})`;
        ctx.lineWidth = 0.8;
        ctx.stroke();
        // Pull stars gently toward mouse
        stars[i].x += (mouse.x - stars[i].x) * 0.0012;
        stars[i].y += (mouse.y - stars[i].y) * 0.0012;
      }
    }
  }

  function tick() {
    ctx.clearRect(0, 0, W, H);
    stars.forEach(s => {
      s.x += s.vx; s.y += s.vy;
      if (s.x < 0)  s.x = W;
      if (s.x > W)  s.x = 0;
      if (s.y < 0)  s.y = H;
      if (s.y > H)  s.y = 0;
      drawStar(s);
    });
    drawConnections();
    requestAnimationFrame(tick);
  }

  window.addEventListener('mousemove', e => { mouse.x = e.clientX; mouse.y = e.clientY; });
  window.addEventListener('touchmove', e => {
    const t = e.touches[0];
    mouse.x = t.clientX; mouse.y = t.clientY;
  }, { passive: true });
  window.addEventListener('resize', () => { resize(); createStars(); });
  resize(); createStars(); tick();
})();

/* ═══════════════════════════════════════════════════
   PARTICLE EMITTER (floating debris)
═══════════════════════════════════════════════════ */
(function () {
  const wrap = document.getElementById('particle-field');
  if (!wrap) return;
  const colors = ['#39ff14','#00fff7','#ff2d2d','#b400ff','#ff6b00','#0090ff','#ff4da6'];
  const shapes = ['50%','4px','2px'];

  for (let i = 0; i < 60; i++) {
    const p   = document.createElement('div');
    const s   = Math.random() * 4 + 1.2;
    const col = colors[Math.floor(Math.random() * colors.length)];
    const br  = shapes[Math.floor(Math.random() * shapes.length)];
    const h   = br === '50%' ? s : s * 3;

    p.style.cssText = [
      'position:absolute',
      `width:${s}px`, `height:${h}px`,
      `background:${col}`,
      `left:${Math.random() * 100}%`,
      'bottom:-20px',
      `border-radius:${br}`,
      `box-shadow:0 0 ${s * 4}px ${col}`,
      'animation:particleFloat linear infinite',
      `animation-duration:${Math.random() * 16 + 10}s`,
      `animation-delay:-${Math.random() * 24}s`,
      `--tx:${(Math.random() - 0.5) * 160}px`,
      `--rot:${Math.random() > 0.5 ? 720 : -720}deg`,
      `--sc:${Math.random() * 1.2 + 0.8}`,
      'opacity:0',
    ].join(';');
    wrap.appendChild(p);
  }
})();

/* ═══════════════════════════════════════════════════
   METEOR EMITTER
═══════════════════════════════════════════════════ */
(function () {
  const wrap = document.getElementById('meteor-field');
  if (!wrap) return;
  const colors = ['rgba(255,255,255,.9)','rgba(0,255,247,.85)','rgba(57,255,20,.8)','rgba(255,45,45,.8)','rgba(180,0,255,.75)'];

  for (let i = 0; i < 14; i++) {
    const m   = document.createElement('div');
    const len = Math.random() * 90 + 45;
    const col = colors[Math.floor(Math.random() * colors.length)];
    const ang = 22 + Math.random() * 28;

    m.style.cssText = [
      'position:absolute',
      'width:2px', `height:${len}px`,
      'border-radius:2px',
      `background:linear-gradient(180deg,${col},transparent)`,
      `box-shadow:0 0 7px ${col}`,
      `top:${Math.random() * 45 - 8}%`,
      `left:${Math.random() * 100}%`,
      `--angle:${ang}deg`,
      'animation:meteorFall linear infinite',
      `animation-duration:${Math.random() * 5 + 3}s`,
      `animation-delay:-${Math.random() * 10}s`,
      'opacity:0',
    ].join(';');
    wrap.appendChild(m);
  }
})();

/* ═══════════════════════════════════════════════════
   HUD CYCLING TEXT
═══════════════════════════════════════════════════ */
(function () {
  const sets = [
    ['INVASION: ACTIVE', 'SHIELDS: 73%',   'PHP ₱: DEFAULT'],
    ['FLEET: DETECTED',  'ENCRYPT: AES256', 'STATUS: ONLINE'],
    ['THREAT LVL: HIGH', 'DB: CONNECTED',   'AI: STANDBY'],
    ['WARP: ENGAGED',    'AUTH: SECURE',     'VER: 2.0.0'],
  ];
  const els = [
    document.getElementById('hud-line-1'),
    document.getElementById('hud-line-2'),
    document.getElementById('hud-line-3'),
  ];
  if (!els[0]) return;

  let idx = 0;
  function update() {
    const s = sets[idx % sets.length];
    els.forEach((el, i) => {
      if (!el) return;
      el.style.opacity = '0';
      setTimeout(() => {
        el.textContent = s[i];
        el.style.transition = 'opacity .5s';
        el.style.opacity = '1';
      }, 300 + i * 100);
    });
    idx++;
  }
  update();
  setInterval(update, 3200);
})();

/* ═══════════════════════════════════════════════════
   FORM INTERACTIONS
═══════════════════════════════════════════════════ */

/** Toggle password visibility */
function togglePassword(inputId, btn) {
  const inp  = document.getElementById(inputId);
  if (!inp) return;
  const show = inp.type === 'password';
  inp.type   = show ? 'text' : 'password';
  const icon = btn.querySelector('i');
  if (icon) icon.className = show ? 'bi bi-eye' : 'bi bi-eye-slash';
}

/** Auto-fill demo credentials and submit */
function fillDemo() {
  const em = document.getElementById('input-email');
  const pw = document.getElementById('input-password');
  if (!em || !pw) return;
  em.value  = 'demo@novabudget.ai';
  pw.value  = 'demo1234';
  pw.type   = 'text';
  const eyeIcon = document.querySelector('.eye-toggle i');
  if (eyeIcon) eyeIcon.className = 'bi bi-eye';
  showToast('Demo access loaded — initiating sequence…', 'info');
  setTimeout(() => {
    const form = document.getElementById('login-form');
    if (form) form.submit();
  }, 800);
}

/** Password strength meter (register page) */
function checkPasswordStrength(val) {
  const bar   = document.getElementById('pwd-strength-bar');
  const label = document.getElementById('pwd-strength-label');
  if (!bar || !label) return;

  let score = 0;
  if (val.length >= 8)            score++;
  if (val.length >= 12)           score++;
  if (/[A-Z]/.test(val))          score++;
  if (/[0-9]/.test(val))          score++;
  if (/[^A-Za-z0-9]/.test(val))  score++;

  const levels = [
    { w: '0%',   c: 'transparent',        l: 'Enter a password',  lc: 'var(--text-muted)'     },
    { w: '20%',  c: '#ff2d2d',            l: 'Very weak',         lc: '#ff2d2d'               },
    { w: '40%',  c: '#ff6b00',            l: 'Weak',              lc: '#ff6b00'               },
    { w: '60%',  c: '#0090ff',            l: 'Fair',              lc: '#0090ff'               },
    { w: '80%',  c: '#39ff14',            l: 'Strong',            lc: '#39ff14'               },
    { w: '100%', c: 'var(--plasma-cyan)', l: 'Excellent 🛸',      lc: 'var(--plasma-cyan)'    },
  ];

  const lvl    = levels[Math.min(score, 5)];
  bar.style.width      = lvl.w;
  bar.style.background = lvl.c;
  label.textContent    = lvl.l;
  label.style.color    = lvl.lc;
}

/** Confirm password match indicator */
function checkPasswordMatch() {
  const pw   = document.getElementById('input-password');
  const cf   = document.getElementById('input-confirm');
  const msg  = document.getElementById('match-msg');
  if (!pw || !cf || !msg) return;
  if (!cf.value) { msg.innerHTML = ''; return; }
  if (pw.value === cf.value) {
    msg.innerHTML = '<i class="bi bi-check-circle-fill" style="color:#39ff14"></i> <span style="color:#39ff14">Passwords match</span>';
  } else {
    msg.innerHTML = '<i class="bi bi-x-circle-fill" style="color:#ff2d2d"></i> <span style="color:#ff2d2d">Passwords do not match</span>';
  }
}

/** Submit loading state */
function setSubmitLoading(formId, btnId, loadingText) {
  const form = document.getElementById(formId);
  const btn  = document.getElementById(btnId);
  if (!form || !btn) return;
  form.addEventListener('submit', function () {
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner"></span> ${loadingText}`;
    setTimeout(() => {
      btn.disabled = false;
      btn.innerHTML = btn.dataset.original || btn.innerHTML;
    }, 6000);
  });
  btn.dataset.original = btn.innerHTML;
}

/* ═══════════════════════════════════════════════════
   TOAST SYSTEM
═══════════════════════════════════════════════════ */
function showToast(msg, type) {
  type = type || 'info';
  const container = document.getElementById('toast-container');
  if (!container) return;

  const icons = {
    success: '<i class="bi bi-check-circle-fill" style="color:#39ff14;font-size:14px"></i>',
    error:   '<i class="bi bi-exclamation-circle-fill" style="color:#ff2d2d;font-size:14px"></i>',
    info:    '<i class="bi bi-info-circle-fill" style="color:#00fff7;font-size:14px"></i>',
  };

  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.innerHTML = (icons[type] || icons.info) + `<span>${msg}</span>`;
  container.appendChild(t);

  t.onclick = () => dismiss(t);
  const timer = setTimeout(() => dismiss(t), 3600);

  function dismiss(el) {
    clearTimeout(timer);
    el.style.opacity   = '0';
    el.style.transform = 'translateX(10px)';
    setTimeout(() => el.remove(), 300);
  }
}
