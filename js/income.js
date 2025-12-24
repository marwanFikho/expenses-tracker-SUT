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
    const targets = [
      document.getElementById('incomeList'),
      document.getElementById('incomeAnalyticsList')
    ].filter(Boolean);

    targets.forEach(target => {
      target.innerHTML = '';
      State.getIncomes().forEach((inc) => {
        const p = document.createElement('p');
        const dateStr = new Date(inc.ts).toLocaleDateString();
        p.innerHTML = `+ ${inc.amount} EGP from ${inc.source} <span style="opacity:0.6;">(${dateStr})</span>`;
        target.appendChild(p);
      });
    });
  }
};
