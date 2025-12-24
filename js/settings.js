// Settings Management
const Settings = {
  async saveCaps(day, week, month) {
    try {
      await API.setCaps(day, week, month);
      UI.showToast('Caps saved');
    } catch (err) {
      console.error(err);
      UI.showToast('Failed to save caps');
      throw err;
    }
  },

  async savePrefs(aiEnabled) {
    try {
      await API.setPrefs(aiEnabled);
      UI.showToast('Preferences saved');
    } catch (err) {
      console.error(err);
      UI.showToast('Failed to save preferences');
      throw err;
    }
  },

  loadFromState() {
    const caps = State.getCaps();
    UI.setCapInputs(caps.day, caps.week, caps.month);
    UI.setAIPref(State.isAIEnabled());
  }
};
