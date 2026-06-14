/**
 * NovaBudget — assets/js/auth-battle.js
 * Alien War Battle Scene — All canvas + DOM animation logic
 */

/* ═══════════════════════════════════════════
   1. STAR FIELD CANVAS
═══════════════════════════════════════════ */
(function initStarField() {
  const canvas = document.getElementById('star-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W, H, stars = [];
  const COLORS = ['#00cfff','#ff3366','#44ff00','#ffaa00','#ffffff','#a855f7'];

  function resize() {
    W = canvas.width  = window.innerWidth;
    H = canvas.height = window.innerHeight;
  }

  function makeStars(n) {
    stars = [];
    for (let i = 0; i < n; i++) {
      stars.push({
        x:  Math.random() * W,
        y:  Math.random() * H,
        vx: (Math.random() - 0.5) * 0.25,
        vy: (Math.random() - 0.5) * 0.25,
        r:  Math.random() * 2.2 + 0.3,
        col: COLORS[Math.floor(Math.random() * COLORS.length)],
        alpha: Math.random() * 0.55 + 0.1,
        phase: Math.random() * Math.PI * 2,
        spd: 0.015 + Math.random() * 0.025,
      });
    }
  }

  let mouseX = W / 2, mouseY = H / 2;
  window.addEventListener('mousemove', e => { mouseX = e.clientX; mouseY = e.clientY; });

  function draw() {
    ctx.clearRect(0, 0, W, H);

    // Draw connections between nearby stars
    for (let i = 0; i < stars.length; i++) {
      for (let j = i + 1; j < stars.length; j++) {
        const dx = stars[i].x - stars[j].x;
        const dy = stars[i].y - stars[j].y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < 110) {
          const a = (1 - dist / 110) * 0.14;
          ctx.beginPath();
          ctx.moveTo(stars[i].x, stars[i].y);
          ctx.lineTo(stars[j].x, stars[j].y);
          ctx.strokeStyle = `rgba(0,207,255,${a})`;
          ctx.lineWidth = 0.5;
          ctx.stroke();
        }
      }
      // Connect to mouse
      const mx = stars[i].x - mouseX;
      const my = stars[i].y - mouseY;
      const md = Math.sqrt(mx * mx + my * my);
      if (md < 160) {
        const a = (1 - md / 160) * 0.4;
        ctx.beginPath();
        ctx.moveTo(stars[i].x, stars[i].y);
        ctx.lineTo(mouseX, mouseY);
        ctx.strokeStyle = `rgba(68,255,0,${a})`;
        ctx.lineWidth = 0.7;
        ctx.stroke();
      }
    }

    // Draw each star
    stars.forEach(s => {
      s.phase += s.spd;
      s.x += s.vx; s.y += s.vy;
      if (s.x < 0) s.x = W; if (s.x > W) s.x = 0;
      if (s.y < 0) s.y = H; if (s.y > H) s.y = 0;
      const a = s.alpha * (0.55 + 0.45 * Math.sin(s.phase));
      const hex = Math.round(a * 255).toString(16).padStart(2, '0');
      ctx.beginPath();
      ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
      ctx.fillStyle = s.col + hex;
      ctx.fill();
      if (s.r > 1.2) {
        const g = ctx.createRadialGradient(s.x, s.y, 0, s.x, s.y, s.r * 3.5);
        g.addColorStop(0, s.col + '28');
        g.addColorStop(1, 'transparent');
        ctx.beginPath();
        ctx.arc(s.x, s.y, s.r * 3.5, 0, Math.PI * 2);
        ctx.fillStyle = g;
        ctx.fill();
      }
    });

    requestAnimationFrame(draw);
  }

  window.addEventListener('resize', () => { resize(); makeStars(170); });
  resize();
  makeStars(170);
  draw();
})();

/* ═══════════════════════════════════════════
   2. PARTICLE / DEBRIS FIELD
═══════════════════════════════════════════ */
(function initParticles() {
  const wrap = document.getElementById('particle-field-el');
  if (!wrap) return;
  const colors = ['#00cfff','#ff3366','#44ff00','#ffaa00','#a855f7','#ffffff'];
  const shapes = ['50%','3px','0px','50% 0'];
  for (let i = 0; i < 60; i++) {
    const p  = document.createElement('div');
    p.className = 'shard';
    const s   = Math.random() * 4 + 1;
    const col = colors[Math.floor(Math.random() * colors.length)];
    const br  = shapes[Math.floor(Math.random() * shapes.length)];
    const ht  = br === '50%' ? s : s * (Math.random() * 3 + 1);
    p.style.cssText = `
      width:${s}px; height:${ht}px;
      background:${col};
      left:${Math.random() * 100}%;
      bottom:-20px;
      border-radius:${br};
      box-shadow:0 0 ${s * 4}px ${col};
      animation-duration:${Math.random() * 18 + 10}s;
      animation-delay:-${Math.random() * 25}s;
      --sx:${(Math.random() - 0.5) * 160}px;
      --sr:${Math.random() > 0.5 ? 540 : -540}deg;
    `;
    wrap.appendChild(p);
  }
})();

/* ═══════════════════════════════════════════
   3. DYNAMIC EXPLOSIONS (spawned on interval)
═══════════════════════════════════════════ */
(function initExplosions() {
  const layer = document.getElementById('explosion-layer-el');
  if (!layer) return;

  const COLORS = ['#ff3366','#ffaa00','#44ff00','#00cfff'];

  function spawnExplosion() {
    const ex = document.createElement('div');
    const col = COLORS[Math.floor(Math.random() * COLORS.length)];
    const x = 15 + Math.random() * 70; // right side of screen (% from right)
    const y = 5  + Math.random() * 70;
    ex.style.cssText = `
      position:absolute;
      right:${x}%; top:${y}%;
      pointer-events:none;
    `;

    // Core flash
    const core = document.createElement('div');
    core.style.cssText = `
      width:16px; height:16px; border-radius:50%;
      background:radial-gradient(circle,#fff 0%,${col} 45%,transparent 75%);
      box-shadow:0 0 24px ${col},0 0 48px rgba(255,100,0,.4);
      animation:explodeOut .8s ease-out forwards;
    `;
    // Ring 1
    const r1 = document.createElement('div');
    r1.style.cssText = `
      position:absolute; width:40px; height:40px;
      top:-12px; left:-12px; border-radius:50%;
      border:2px solid ${col};
      box-shadow:0 0 10px ${col};
      animation:ringExpand .8s ease-out forwards;
    `;
    // Ring 2
    const r2 = document.createElement('div');
    r2.style.cssText = `
      position:absolute; width:64px; height:64px;
      top:-24px; left:-24px; border-radius:50%;
      border:1px solid rgba(255,255,255,.3);
      animation:ringExpand .8s ease-out forwards;
      animation-delay:.12s;
    `;
    ex.appendChild(core); ex.appendChild(r1); ex.appendChild(r2);

    // Debris shards
    for (let i = 0; i < 8; i++) {
      const d = document.createElement('div');
      const angle = (i / 8) * 360;
      const dist  = 30 + Math.random() * 40;
      const dx    = Math.cos(angle * Math.PI / 180) * dist;
      const dy    = Math.sin(angle * Math.PI / 180) * dist;
      d.style.cssText = `
        position:absolute; width:3px; height:3px;
        border-radius:50%; background:${col};
        box-shadow:0 0 6px ${col};
        top:7px; left:7px;
        animation:debrisFly .9s ease-out forwards;
        --dx:${dx}px; --dy:${dy}px;
      `;
      ex.appendChild(d);
    }

    layer.appendChild(ex);
    setTimeout(() => ex.remove(), 1000);
  }

  // Spawn at random intervals
  function scheduleNext() {
    const delay = 1200 + Math.random() * 2500;
    setTimeout(() => { spawnExplosion(); scheduleNext(); }, delay);
  }
  scheduleNext();
})();

/* ═══════════════════════════════════════════
   4. METEORS (CSS-spawned into DOM)
═══════════════════════════════════════════ */
(function initMeteors() {
  const wrap = document.getElementById('meteor-field-el');
  if (!wrap) return;
  const colors = ['rgba(255,255,255,.9)','rgba(0,207,255,.8)','rgba(68,255,0,.7)','rgba(255,170,0,.75)'];
  for (let i = 0; i < 14; i++) {
    const m   = document.createElement('div');
    const col = colors[Math.floor(Math.random() * colors.length)];
    const len = Math.random() * 90 + 40;
    m.style.cssText = `
      position:absolute;
      width:2px; height:${len}px;
      border-radius:2px;
      background:linear-gradient(180deg,${col},transparent);
      box-shadow:0 0 6px ${col};
      top:${Math.random() * 45 - 5}%;
      left:${Math.random() * 100}%;
      --angle:${22 + Math.random() * 28}deg;
      animation:meteorFall ${Math.random() * 5 + 4}s linear infinite;
      animation-delay:-${Math.random() * 10}s;
      pointer-events:none;
    `;
    wrap.appendChild(m);
  }
})();

/* ═══════════════════════════════════════════
   5. HUD — CYCLED TACTICAL TEXT
═══════════════════════════════════════════ */
(function initHud() {
  const sets = [
    ['SECTOR: ALPHA-7','THREAT: CRITICAL','SHIELDS: 87%'],
    ['ENEMY: DETECTED','LASER: CHARGED','SYSTEM: ONLINE'],
    ['BUDGET: SECURED','FIREWALL: ACTIVE','ENC: AES-256'],
    ['TARGETS: 4','FUEL: 94%','AI: READY'],
  ];
  let idx = 0;
  const ids = ['hud-l1','hud-l2','hud-l3'];
  function update() {
    const s = sets[idx % sets.length];
    ids.forEach((id, i) => {
      const el = document.getElementById(id);
      if (!el) return;
      el.style.opacity = '0';
      el.style.transition = 'opacity .4s';
      setTimeout(() => {
        el.textContent = s[i];
        el.style.opacity = '1';
      }, 300);
    });
    idx++;
  }
  update();
  setInterval(update, 3200);
})();

/* ═══════════════════════════════════════════
   6. FORM UTILITIES
═══════════════════════════════════════════ */

/** Toggle password visibility */
function togglePwd(inputId, btn) {
  const inp = document.getElementById(inputId);
  if (!inp) return;
  const showing = inp.type === 'text';
  inp.type = showing ? 'password' : 'text';
  const icon = btn.querySelector('i');
  if (icon) icon.className = showing ? 'bi bi-eye-slash' : 'bi bi-eye';
}

/** Password strength meter */
function checkPwdStrength(val) {
  const bar = document.getElementById('pwd-fill-bar');
  const lbl = document.getElementById('pwd-strength-label');
  if (!bar || !lbl) return;
  let score = 0;
  if (val.length >= 8)  score++;
  if (val.length >= 12) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const levels = [
    { w:'0%',   c:'transparent',  l:'',              lc:'var(--t3)' },
    { w:'20%',  c:'#f87171',      l:'WEAK',          lc:'#f87171'   },
    { w:'40%',  c:'#f59e0b',      l:'FAIR',          lc:'#f59e0b'   },
    { w:'60%',  c:'#3b82f6',      l:'GOOD',          lc:'#3b82f6'   },
    { w:'80%',  c:'#22c55e',      l:'STRONG',        lc:'#22c55e'   },
    { w:'100%', c:'#44ff00',      l:'IMPENETRABLE ✦', lc:'#44ff00'  },
  ];
  const lvl = levels[Math.min(score, 5)];
  bar.style.width = lvl.w;
  bar.style.background = lvl.c;
  bar.style.boxShadow = score > 0 ? `0 0 8px ${lvl.c}` : 'none';
  lbl.textContent = lvl.l;
  lbl.style.color = lvl.lc;
  checkConfirmMatch();
}

/** Confirm password match */
function checkConfirmMatch() {
  const pw = document.getElementById('pw-input');
  const cf = document.getElementById('cf-input');
  const msg = document.getElementById('match-msg');
  if (!pw || !cf || !msg || !cf.value) { if (msg) msg.textContent = ''; return; }
  if (pw.value === cf.value) {
    msg.innerHTML = '<i class="bi bi-check-circle-fill" style="color:#44ff00"></i> <span style="color:#44ff00;font-size:10px;font-family:Orbitron,monospace;letter-spacing:.5px">MATCH CONFIRMED</span>';
  } else {
    msg.innerHTML = '<i class="bi bi-x-circle-fill" style="color:#ff3366"></i> <span style="color:#ff3366;font-size:10px;font-family:Orbitron,monospace;letter-spacing:.5px">MISMATCH</span>';
  }
}

/** Demo autofill + submit */
function fillDemo() {
  const em = document.getElementById('email-input');
  const pw = document.getElementById('pw-input');
  if (em) em.value = 'demo@novabudget.ai';
  if (pw) { pw.value = 'demo1234'; pw.type = 'text'; }
  const eyeBtn = document.querySelector('.eye-btn');
  if (eyeBtn) { const i = eyeBtn.querySelector('i'); if(i) i.className='bi bi-eye'; }
  showToast('Access credentials loaded. Initiating…','info');
  setTimeout(() => {
    const form = document.getElementById('auth-form');
    if (form) form.submit();
  }, 800);
}

/** Loading state on submit */
function setLoadingState(formId, btnId, loadingText) {
  const form = document.getElementById(formId);
  const btn  = document.getElementById(btnId);
  if (!form || !btn) return;
  form.addEventListener('submit', function() {
    btn.disabled = true;
    btn.innerHTML = `<span class="war-spin"></span> ${loadingText}`;
    setTimeout(() => {
      btn.disabled = false;
      btn.innerHTML = btn.dataset.original || btn.innerHTML;
    }, 8000);
  });
}

/* ═══════════════════════════════════════════
   7. TOAST NOTIFICATIONS
═══════════════════════════════════════════ */
function showToast(msg, type) {
  const box = document.getElementById('toast-box');
  if (!box) return;
  type = type || 'info';
  const t = document.createElement('div');
  t.className = 'toast-n ' + type;
  const icons = {
    ok:   '<i class="bi bi-check-circle-fill" style="color:#44ff00;font-size:14px"></i>',
    err:  '<i class="bi bi-exclamation-circle-fill" style="color:#ff3366;font-size:14px"></i>',
    info: '<i class="bi bi-info-circle-fill" style="color:#00cfff;font-size:14px"></i>',
  };
  t.innerHTML = (icons[type] || icons.info) + '<span>' + msg + '</span>';
  box.appendChild(t);
  t.onclick = () => t.remove();
  setTimeout(() => {
    t.style.opacity = '0';
    t.style.transition = '.3s';
    setTimeout(() => t.remove(), 320);
  }, 3500);
}
