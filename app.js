// Main App Initialization
// Aligns event listeners to the new HTML structure

// Initialize app on window load
window.addEventListener('load', async () => {
  // Check authentication (index.html only)
  if (!Auth.checkAuth()) return;

  // Theme toggle
  const toggleBtn = document.getElementById('theme-toggle');
  if (toggleBtn) {
    // Apply saved theme
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) document.documentElement.setAttribute('data-theme', savedTheme);
    const isDark = (document.documentElement.getAttribute('data-theme') === 'dark');
    toggleBtn.innerText = isDark ? '☀️' : '🌙';
    toggleBtn.addEventListener('click', () => UI.toggleTheme());
  }

  // Logout
  const logoutBtn = document.getElementById('logout-btn');
  if (logoutBtn) logoutBtn.addEventListener('click', () => {
    Auth.clearAuth();
    window.location.href = 'login.html';
  });

  // Navigation links
  document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const slug = link.getAttribute('data-page');
      navigateToPage(slug);
    });
  });

  // CTA navigate function is used in inline onclick
  window.navigateToPage = (slug) => {
    // Update active link
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    const activeLink = document.querySelector(`.nav-link[data-page="${slug}"]`);
    if (activeLink) activeLink.classList.add('active');
    // Show page
    UI.showPageBySlug(slug);
  };

  // Forms wiring
  const incomeForm = document.getElementById('income-form');
  if (incomeForm) {
    incomeForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const source = (document.getElementById('income-source')?.value || '').trim();
      const amount = Number(document.getElementById('income-amount')?.value || 0);
      if (!source || !(amount > 0)) return UI.showToast('Enter source and amount');
      try {
        await Income.add(amount, source);
        (document.getElementById('income-source') || {}).value = '';
        (document.getElementById('income-amount') || {}).value = '';
        await State.refresh();
        UI.setWalletBalance(State.getWallet());
        Income.render();
        Analytics.updateUI();
      } catch (err) { console.error(err); }
    });
  }

  const expenseForm = document.getElementById('expense-form');
  if (expenseForm) {
    expenseForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const category = (document.getElementById('expense-category')?.value || '').trim();
      const amount = Number(document.getElementById('expense-amount')?.value || 0);
      if (!category || !(amount > 0)) return UI.showToast('Enter category and amount');
      try {
        await Expenses.add(amount, category);
        (document.getElementById('expense-category') || {}).value = '';
        (document.getElementById('expense-amount') || {}).value = '';
        await State.refresh();
        Income.render();
        Expenses.render();
        Analytics.updateUI();
      } catch (err) { console.error(err); }
    });
  }

  const capsForm = document.getElementById('caps-form');
  if (capsForm) {
    capsForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const caps = UI.getCapInputs();
      try {
        await Settings.saveCaps(caps.day, caps.week, caps.month);
        await State.refresh();
        Analytics.updateUI();
        UI.showToast('Caps updated');
      } catch (err) { console.error(err); }
    });
  }

  const prefsBtn = document.getElementById('save-prefs-btn');
  if (prefsBtn) {
    prefsBtn.addEventListener('click', async () => {
      try {
        await Settings.savePrefs(UI.getAIPref());
        await State.refresh();
        UI.showToast('Preferences saved');
      } catch (err) { console.error(err); }
    });
  }

  const adviceBtn = document.getElementById('get-advice-btn');
  if (adviceBtn) adviceBtn.addEventListener('click', () => ChatBot.getAdvice());

  const chatbotForm = document.getElementById('chatbot-form');
  if (chatbotForm) {
    chatbotForm.addEventListener('submit', (e) => {
      e.preventDefault();
      ChatBot.sendMessage();
    });
  }

  // Initial state & render
  try {
    await State.refresh();
    UI.setWalletBalance(State.getWallet());
    Settings.loadFromState();
    Income.render();
    Expenses.render();
    Analytics.updateUI();
    // Default page
    navigateToPage('home');
    // Periodic lightweight updates
    setInterval(() => Analytics.updateUI(), 2000);
  } catch (err) {
    console.error('Failed to initialize app:', err);
    UI.showToast('Failed to load app');
  }
});

// Backward-compat helpers referenced inline in HTML
function showPage(id) { UI.showPage(id); }
