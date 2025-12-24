// Expense Management
const Expenses = {
  async add(amount, merchant) {
    try {
      const result = await API.addExpense(amount, merchant, 0, Date.now());
      UI.showToast('Expense added');
      return result;
    } catch (err) {
      console.error(err);
      UI.showToast('Failed to add expense');
      throw err;
    }
  },

  async update(id, amount, merchant, beneficial, ts) {
    try {
      await API.updateExpense(id, amount, merchant, beneficial, ts);
      UI.showToast('Expense updated');
    } catch (err) {
      console.error(err);
      UI.showToast('Failed to update expense');
      throw err;
    }
  },

  async delete(id) {
    try {
      await API.deleteExpense(id);
      UI.showToast('Expense deleted');
    } catch (err) {
      console.error(err);
      UI.showToast('Failed to delete expense');
      throw err;
    }
  },

  render() {
    const recentEl = document.getElementById('recentExpenses');
    const analyticsEl = document.getElementById('analyticsList');
    recentEl.innerHTML = '';
    analyticsEl.innerHTML = '';

    State.getExpenses().forEach((e, index) => {
      const p = document.createElement('p');
      p.innerHTML = `- ${e.amount} EGP | ${e.merchant} (${e.beneficial ? '✓' : '✗'})
        <button class="edit-btn" onclick="expenseUI.edit(${index})">Edit</button>
        <button class="delete-btn" onclick="expenseUI.delete(${index})">Delete</button>`;
      recentEl.appendChild(p);

      const p2 = document.createElement('p');
      p2.innerHTML = `<b>${e.amount} EGP</b> at ${e.merchant} — ${e.beneficial ? 'Beneficial' : 'Not beneficial'}`;
      analyticsEl.appendChild(p2);
    });

    renderChart();
    renderIncomeChart();
  },

  edit(index) {
    const e = State.getExpenses()[index];
    const newAmount = prompt("Enter new amount:", e.amount);
    const newMerchant = prompt("Enter new merchant:", e.merchant);
    const newBeneficial = prompt("Beneficial? 1=yes, 0=no", e.beneficial ? 1 : 0);

    if (newAmount && newMerchant) {
      (async () => {
        await this.update(e.id, Number(newAmount), newMerchant, Number(newBeneficial), e.ts || Date.now());
        await State.refresh();
        this.render();
      })();
    }
  },

  delete(index) {
    const e = State.getExpenses()[index];
    if (confirm('Delete this expense?')) {
      (async () => {
        await this.delete(e.id);
        await State.refresh();
        this.render();
      })();
    }
  }
};

const expenseUI = {
  edit: (idx) => Expenses.edit(idx),
  delete: (idx) => Expenses.delete(idx)
};
