// Expense Flow - Need vs Want confirmation
let pendingExpense = null;

const ExpenseFlow = {
  start() {
    const amount = Number(document.getElementById('amount').value);
    const merchant = document.getElementById('merchant').value.trim();
    if (!(amount > 0) || !merchant) {
      alert('Enter amount (>0) and merchant/place');
      return;
    }

    pendingExpense = { amount, merchant, beneficial: 0, ts: Date.now() };

    const modal = document.getElementById('confirmModal');
    const needWantSelect = document.getElementById('needWantSelect');
    needWantSelect.value = 'want';

    // Show habits from last 30 days
    const cutoff = Date.now() - 30 * 24 * 3600 * 1000;
    const merchTotal = State.getExpenses()
      .filter(e => e.merchant === merchant && e.ts >= cutoff)
      .reduce((s, e) => s + e.amount, 0);

    const habitSummary = document.getElementById('habitSummary');
    habitSummary.innerHTML = `In the last 30 days: <b>${merchTotal} EGP</b> spent at "${merchant}".`;

    // Show caps and usage
    const { weekSpent, monthSpent } = State.computePeriodTotals();
    const caps = State.getCaps();
    document.getElementById('weeklyCap').innerText = caps.week;
    document.getElementById('monthlyCap').innerText = caps.month;
    document.getElementById('weeklySpent').innerText = weekSpent;
    document.getElementById('monthlySpent').innerText = monthSpent;

    // Show advice
    const needWant = needWantSelect.value;
    const crossingWeek = (weekSpent + amount) > caps.week;
    const crossingMonth = (monthSpent + amount) > caps.month;
    const advice = (needWant === 'need')
      ? (crossingWeek || crossingMonth ? "It's a need, but you may exceed your cap soon." : "Looks reasonable for a need.")
      : (crossingWeek || crossingMonth ? "This is a want and may exceed your cap. Consider skipping." : "It's a want; ensure it fits your budget.");
    habitSummary.innerHTML += `<br/><i>${advice}</i>`;

    // Show/hide chatbot
    this.updateChatbotVisibility(needWant);

    needWantSelect.addEventListener('change', (e) => {
      this.updateChatbotVisibility(e.target.value);
      this.clearChat();
    });

    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
  },

  cancel() {
    const modal = document.getElementById('confirmModal');
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
    pendingExpense = null;
  },

  confirm() {
    if (!pendingExpense) return this.cancel();

    const needWant = document.getElementById('needWantSelect').value;
    pendingExpense.beneficial = (needWant === 'need') ? 1 : 0;

    (async () => {
      try {
        await API.addExpense(pendingExpense.amount, pendingExpense.merchant, pendingExpense.beneficial, pendingExpense.ts);
        if (pendingExpense.beneficial === 1) {
          UI.showToast('Good call. Needs come first. ✅');
        } else {
          UI.showToast('This looks like a want. Be mindful. 🥲');
          UI.triggerSadEffect();
        }
        this.cancel();
        this.clearInputs();
        await State.refresh();
        Expenses.render();
        Income.render();
        Analytics.updateUI();
        Analytics.renderIncomeChart();
      } catch (err) {
        console.error(err);
        UI.showToast('Failed to save expense');
      }
    })();
  },

  updateChatbotVisibility(needWant) {
    const chatbotSection = document.getElementById('chatbotSection');
    if (needWant === 'want') {
      chatbotSection.style.display = 'block';
    } else {
      chatbotSection.style.display = 'none';
    }
  },

  clearChat() {
    const chatMessages = document.getElementById('chatMessages');
    chatMessages.innerHTML = `
      <div class="chat-message ai-message">
        <p>Hi! I noticed this is a <strong>Want</strong> category purchase. Before you spend, let me help you think this through. What's your main reason for this purchase?</p>
      </div>
    `;
    document.getElementById('chatInput').value = '';
  },

  clearInputs() {
    document.getElementById('amount').value = '';
    document.getElementById('merchant').value = '';
  }
};

// Alias functions for onclick handlers
function startExpenseFlow() { ExpenseFlow.start(); }
function cancelExpenseFlow() { ExpenseFlow.cancel(); }
function confirmExpenseFlow() { ExpenseFlow.confirm(); }