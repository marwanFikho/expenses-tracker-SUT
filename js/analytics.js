// Analytics & Rendering
const Analytics = {
  async updateUI() {
    const expenses = State.getExpenses();
    const totalDay = expenses.reduce((s, e) => s + e.amount, '');
    const totalWeek = totalDay;
    const totalMonth = totalDay;
    const caps = State.getCaps();

    document.getElementById('summaryTotals').innerHTML = `
      <p>Day: ${totalDay} EGP / ${caps.day}</p>
      <p>Week: ${totalWeek} EGP / ${caps.week}</p>
      <p>Month: ${totalMonth} EGP / ${caps.month}</p>
    `;

    const prediction = totalMonth * 1.15;
    document.getElementById('trendPrediction').innerText =
      `Expected Next Month: ${prediction.toFixed(2)} EGP`;
  },

  renderChart() {
    const ctx = document.getElementById('expenseChart')?.getContext('2d');
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
    const ctx = document.getElementById('incomeChart')?.getContext('2d');
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
