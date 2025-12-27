// State Management & Data Store
const State = {
  data: {
    wallet: 0,
    expenses: [],
    incomes: [],
    caps: { day: 0, week: 0, month: 0 },
    aiEnabled: true
  },

  async refresh() {
    try {
      const response = await API.getState();
      this.data.wallet = response.wallet || 0;
      this.data.caps = response.caps || { day: 0, week: 0, month: 0 };
      this.data.expenses = Array.isArray(response.expenses) ? response.expenses : [];
      this.data.incomes = Array.isArray(response.incomes) ? response.incomes : [];
      this.data.aiEnabled = typeof response.aiEnabled === 'boolean' ? response.aiEnabled : true;
      return this.data;
    } catch (err) {
      console.error('Failed to refresh state:', err);
      UI.showToast('Failed to load data');
      throw err;
    }
  },

  getWallet() {
    return this.data.wallet;
  },

  getExpenses() {
    return this.data.expenses;
  },

  getIncomes() {
    return this.data.incomes;
  },

  getCaps() {
    return this.data.caps;
  },

  isAIEnabled() {
    return this.data.aiEnabled;
  },

  computePeriodTotals() {
    const now = Date.now();
    const dayMs = 24 * 3600 * 1000;
    const weekStart = now - 7 * dayMs;
    const monthStart = now - 30 * dayMs;
    const weekSpent = this.data.expenses
      .filter(e => Number(e.ts) >= weekStart)
      .reduce((s, e) => s + Number(e.amount || 0), 0);
    const monthSpent = this.data.expenses
      .filter(e => Number(e.ts) >= monthStart)
      .reduce((s, e) => s + Number(e.amount || 0), 0);
    return { weekSpent, monthSpent };
  }
};
