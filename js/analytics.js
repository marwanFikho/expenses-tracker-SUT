// Analytics & Rendering
const Analytics = {
  async updateUI() {
    const expenses = State.getExpenses();
    const incomes = State.getIncomes();
    const spentTotal = expenses.reduce((s, e) => s + Number(e.amount || 0), 0);
    const incomeTotal = incomes.reduce((s, i) => s + Number(i.amount || 0), 0);
    const wallet = State.getWallet();
    const { monthSpent } = State.computePeriodTotals();

    // Dashboard summary
    const dashIncome = document.getElementById('dash-income');
    const dashExpenses = document.getElementById('dash-expenses');
    const dashBalance = document.getElementById('dash-balance');
    if (dashIncome) dashIncome.innerText = formatCurrency(incomeTotal);
    if (dashExpenses) dashExpenses.innerText = formatCurrency(spentTotal);
    if (dashBalance) dashBalance.innerText = formatCurrency(wallet);

    // Analytics summary
    const analyticsMonth = document.getElementById('analytics-month');
    const analyticsBalance = document.getElementById('analytics-balance');
    if (analyticsMonth) analyticsMonth.innerText = formatCurrency(monthSpent);
    if (analyticsBalance) analyticsBalance.innerText = formatCurrency(wallet);
  },

  renderChart() {
    const canvas = document.getElementById('expenseChart');
    const ctx = canvas ? canvas.getContext('2d') : null;
    if (!ctx) return;

    const expenses = State.getExpenses();
    const merchants = [...new Set(expenses.map(e => e.merchant))];
    const amounts = merchants.map(m =>
      expenses.filter(e => e.merchant === m).reduce((s, e) => s + e.amount, 0)
    );

    if (window.chartInstance) window.chartInstance.destroy();

    window.chartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: merchants,
        datasets: [{
          label: 'Expenses by Merchant',
          data: amounts,
          backgroundColor: 'rgba(0,123,255,0.6)'
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: true }
        }
      }
    });
  },

  renderIncomeChart() {
    const canvas = document.getElementById('incomeChart');
    const ctx = canvas ? canvas.getContext('2d') : null;
    if (!ctx) return;

    const incomes = State.getIncomes();
    const sources = [...new Set(incomes.map(i => i.source))];
    const amounts = sources.map(s =>
      incomes.filter(i => i.source === s).reduce((s, a) => s + a.amount, 0)
    );

    if (window.incomeChartInstance) window.incomeChartInstance.destroy();

    window.incomeChartInstance = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: sources,
        datasets: [{
          label: 'Income by Source',
          data: amounts,
          backgroundColor: [
            'rgba(75, 192, 192, 0.6)',
            'rgba(153, 102, 255, 0.6)',
            'rgba(255, 159, 64, 0.6)',
            'rgba(255, 99, 132, 0.6)',
            'rgba(54, 162, 235, 0.6)'
          ]
        }]
      },
      options: {
        plugins: {
          legend: { display: true, position: 'bottom' }
        }
      }
    });
  }
};

function renderChart() {
  Analytics.renderChart();
}

function renderIncomeChart() {
  Analytics.renderIncomeChart();
}

function updateAnalytics() {
  Analytics.updateUI();
}
