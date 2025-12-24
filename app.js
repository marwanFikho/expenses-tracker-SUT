// Main App Initialization
// This file loads all modules and sets up event listeners

// Initialize app on window load
window.addEventListener('load', async () => {
  // Check authentication
  if (!Auth.checkAuth()) return;

  // Initialize theme toggle
  const toggleBtn = document.getElementById('themeToggle');
  if (toggleBtn) {
    toggleBtn.innerText = document.body.classList.contains('dark-mode') ? "☀️" : "🌙";
    toggleBtn.addEventListener('click', () => UI.toggleTheme());
  }

  // Load initial state
  try {
    await State.refresh();
    
    // Update UI with loaded state
    UI.setWalletBalance(State.getWallet());
    UI.setAIPref(State.isAIEnabled());
    Settings.loadFromState();

    // Render all sections
    Expenses.render();
    Income.render();
    Analytics.updateUI();
    Analytics.renderChart();
    Analytics.renderIncomeChart();

    // Refresh analytics every 500ms
    setInterval(() => Analytics.updateUI(), 500);
  } catch (err) {
    console.error('Failed to initialize app:', err);
    UI.showToast('Failed to load app');
  }
});

// Page navigation
function showPage(id) {
  UI.showPage(id);
}

// Logout handler
function logout() {
  Auth.clearAuth();
  window.location.href = 'login.html';
}

// Dashboard - Add Expense
document.addEventListener('DOMContentLoaded', () => {
  const addExpenseBtn = document.querySelector('button[onclick="addExpense()"]');
  if (addExpenseBtn) {
    addExpenseBtn.onclick = () => ExpenseFlow.start();
  }
});

function addExpense() {
  ExpenseFlow.start();
}

// Profile - Add Money
async function topUpWallet() {
  const amt = Number(document.getElementById('addMoneyInput').value);
  const source = (document.getElementById('addMoneySource').value || '').trim();

  if (!(amt > 0)) {
    alert('Enter a positive amount');
    return;
  }
  if (!source) {
    alert('Enter where this money is from');
    return;
  }

  try {
    await Income.add(amt, source);
    document.getElementById('addMoneyInput').value = '';
    document.getElementById('addMoneySource').value = '';
    await State.refresh();
    UI.setWalletBalance(State.getWallet());
    Income.render();
    Analytics.updateUI();
  } catch (err) {
    console.error(err);
  }
}

// Profile - Save Caps
async function saveCaps() {
  const caps = UI.getCapInputs();
  try {
    await Settings.saveCaps(caps.day, caps.week, caps.month);
    await State.refresh();
  } catch (err) {
    console.error(err);
  }
}

// Profile - Save Preferences
async function savePreferences() {
  const aiEnabled = UI.getAIPref();
  try {
    await Settings.savePrefs(aiEnabled);
    await State.refresh();
  } catch (err) {
    console.error(err);
  }
}

// Analytics - Get AI Advice
document.addEventListener('DOMContentLoaded', () => {
  const aiBtn = document.getElementById('getAIAdviceBtn');
  if (aiBtn) {
    aiBtn.addEventListener('click', () => ChatBot.getAdvice());
  }
});

// Setup keyboard shortcut for chatbot (Enter to send)
document.addEventListener('DOMContentLoaded', () => {
  const chatInput = document.getElementById('chatInput');
  if (chatInput) {
    chatInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') ChatBot.sendMessage();
    });
  }
});