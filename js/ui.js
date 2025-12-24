// DOM & UI Helpers
const UI = {
  showPage(id) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.getElementById(id).classList.add('active');
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
    const toggleBtn = document.getElementById('themeToggle');
    const rect = toggleBtn.getBoundingClientRect();
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height / 2;

    const goingDark = !document.body.classList.contains('dark-mode');
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
    document.body.classList.toggle('dark-mode');
    toggleBtn.innerText = document.body.classList.contains('dark-mode') ? "☀️" : "🌙";

    setTimeout(() => {
      ripple.style.transition = 'opacity 120ms ease';
      ripple.style.opacity = '0';
      setTimeout(() => ripple.remove(), 120);
    }, 200);
  },

  setWalletBalance(amount) {
    document.getElementById('walletBalance').innerText = amount;
  },

  setCapInputs(day, week, month) {
    document.getElementById('capDay').value = day || '';
    document.getElementById('capWeek').value = week || '';
    document.getElementById('capMonth').value = month || '';
  },

  getCapInputs() {
    return {
      day: Number(document.getElementById('capDay').value),
      week: Number(document.getElementById('capWeek').value),
      month: Number(document.getElementById('capMonth').value)
    };
  },

  setAIPref(enabled) {
    document.getElementById('aiPref').checked = enabled;
  },

  getAIPref() {
    return document.getElementById('aiPref').checked;
  },

  clearElement(id) {
    const el = document.getElementById(id);
    if (el) el.innerHTML = '';
  }
};
