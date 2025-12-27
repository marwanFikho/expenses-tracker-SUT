// DOM & UI Helpers
const UI = {
  showPage(id) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
  },

  showPageBySlug(slug) {
    const id = `${slug}-page`;
    this.showPage(id);
  },

  showToast(message) {
    const el = document.createElement('div');
    el.className = 'toast';
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2800);
  },

  triggerConfetti() {
    if (typeof confetti === 'undefined') return;
    confetti({
      particleCount: 100,
      spread: 60,
      origin: { y: 0.6 }
    });
  },

  triggerSadEffect() {
    const effects = document.getElementById('effects');
    const rain = document.createElement('div');
    rain.className = 'sad-rain';
    const emoji = document.createElement('div');
    emoji.className = 'sad-emoji';
    emoji.textContent = '🥲';

    effects.appendChild(rain);
    effects.appendChild(emoji);

    setTimeout(() => {
      document.body.classList.remove('sad-mode');
      rain.remove();
      emoji.remove();
    }, 2500);
  },

  toggleTheme() {
    const toggleBtn = document.getElementById('theme-toggle');
    if (!toggleBtn) return;
    const rect = toggleBtn.getBoundingClientRect();
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height / 2;

    const currentTheme = (document.documentElement.getAttribute('data-theme') === 'dark') ? 'dark' : 'light';
    const goingDark = currentTheme !== 'dark';
    const targetColor = goingDark ? '#121212' : '#f0f2f5';

    const ripple = document.createElement('div');
    ripple.className = 'theme-ripple';
    ripple.style.background = targetColor;
    ripple.style.left = `${cx}px`;
    ripple.style.top = `${cy}px`;

    const maxDim = Math.hypot(window.innerWidth, window.innerHeight);
    const diameter = maxDim * 2;
    ripple.style.width = `${diameter}px`;
    ripple.style.height = `${diameter}px`;

    const bgLayer = document.getElementById('bg-ripple') || document.body;
    bgLayer.appendChild(ripple);

    const newTheme = goingDark ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', newTheme);
    try { localStorage.setItem('theme', newTheme); } catch {}
    toggleBtn.innerText = newTheme === 'dark' ? "☀️" : "🌙";

    setTimeout(() => {
      ripple.style.transition = 'opacity 120ms ease';
      ripple.style.opacity = '0';
      setTimeout(() => ripple.remove(), 120);
    }, 200);
  },

  setWalletBalance(amount) {
    const dash = document.getElementById('dash-balance');
    const analytics = document.getElementById('analytics-balance');
    if (dash) dash.innerText = formatCurrency(amount);
    if (analytics) analytics.innerText = formatCurrency(amount);
  },

  setCapInputs(day, week, month) {
    const d = document.getElementById('cap-daily');
    const w = document.getElementById('cap-weekly');
    const m = document.getElementById('cap-monthly');
    if (d) d.value = day || '';
    if (w) w.value = week || '';
    if (m) m.value = month || '';
  },

  getCapInputs() {
    return {
      day: Number(document.getElementById('cap-daily')?.value || 0),
      week: Number(document.getElementById('cap-weekly')?.value || 0),
      month: Number(document.getElementById('cap-monthly')?.value || 0)
    };
  },

  setAIPref(enabled) {
    const el = document.getElementById('ai-enabled');
    if (el) el.checked = !!enabled;
  },

  getAIPref() {
    return !!document.getElementById('ai-enabled')?.checked;
  },

  clearElement(id) {
    const el = document.getElementById(id);
    if (el) el.innerHTML = '';
  }
};

function formatCurrency(n) {
  const num = Number(n);
  if (!isFinite(num)) return `$0.00`;
  return `$${num.toFixed(2)}`;
}
