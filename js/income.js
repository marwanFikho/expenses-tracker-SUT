// Income Management
const Income = {
  async add(amount, source) {
    try {
      await API.addIncome(amount, source, Date.now());
      UI.showToast(`+${amount} EGP from ${source} 🎉`);
      UI.triggerConfetti();
    } catch (err) {
      console.error(err);
      UI.showToast('Failed to add income');
      throw err;
    }
  },

  render() {
    const target = document.getElementById('income-list');
    if (!target) return;
    target.innerHTML = '';
    State.getIncomes().forEach((inc) => {
      const p = document.createElement('p');
      const dateStr = new Date(inc.ts).toLocaleDateString();
      p.innerHTML = `+ ${inc.amount} from ${inc.source} <span style="opacity:0.6;">(${dateStr})</span>`;
      target.appendChild(p);
    });
  }
};
