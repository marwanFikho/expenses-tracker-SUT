// API Service - All backend communication
const API = {
  base: 'api.php',

  async request(path, options = {}) {
    const headers = new Headers(options.headers || {});
    const token = localStorage.getItem('token');
    if (token && !headers.has('Authorization')) {
      headers.set('Authorization', `Bearer ${token}`);
    }

    const res = await fetch(`${this.base}?path=${path}`, { ...options, headers });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || res.statusText);
    return data;
  },

  // Auth
  register(email, password) {
    return this.request('auth/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password })
    });
  },

  login(email, password) {
    return this.request('auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password })
    });
  },

  logout() {
    return this.request('auth/logout', { method: 'POST' });
  },

  // State
  getState() {
    return this.request('state', { method: 'GET' });
  },

  // Expenses
  addExpense(amount, merchant, beneficial, ts) {
    return this.request('expense', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ amount, merchant, beneficial, ts })
    });
  },

  updateExpense(id, amount, merchant, beneficial, ts) {
    return this.request(`expense&id=${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ amount, merchant, beneficial, ts })
    });
  },

  deleteExpense(id) {
    return this.request(`expense&id=${id}`, { method: 'DELETE' });
  },

  // Income
  addIncome(amount, source, ts) {
    return this.request('income', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ amount, source, ts })
    });
  },

  // Caps
  setCaps(day, week, month) {
    return this.request('caps', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ day, week, month })
    });
  },

  // Preferences
  setPrefs(aiEnabled) {
    return this.request('prefs', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ aiEnabled })
    });
  },

  // AI
  getAIAdvice() {
    return this.request('ai', { method: 'POST' });
  },

  chatbot(message, amount, merchant) {
    return this.request('chatbot', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message, amount, merchant })
    });
  }
};
