// Chatbot & AI
const ChatBot = {
  async sendMessage() {
    const chatInput = document.getElementById('chatbot-question');
    const userMessage = chatInput.value.trim();

    if (!userMessage) return;

    // Add user message
    const chatMessages = document.getElementById('chatbot-messages');
    const userMsgEl = document.createElement('div');
    userMsgEl.className = 'chat-message user-message';
    userMsgEl.innerHTML = `<p>${userMessage}</p>`;
    chatMessages.appendChild(userMsgEl);

    // Clear input and show loading
    chatInput.value = '';
    const loadingEl = document.createElement('div');
    loadingEl.className = 'chat-message ai-message chat-loading';
    loadingEl.innerHTML = '<p>💭 Thinking...</p>';
    chatMessages.appendChild(loadingEl);

    chatMessages.scrollTop = chatMessages.scrollHeight;

    const sendBtn = document.querySelector('#chatbot-form button[type="submit"]');
    if (sendBtn) sendBtn.disabled = true;

    try {
      const response = await API.chatbot(userMessage, pendingExpense?.amount || 0, pendingExpense?.merchant || '');
      loadingEl.remove();

      const aiMsgEl = document.createElement('div');
      aiMsgEl.className = 'chat-message ai-message';
      aiMsgEl.innerHTML = `<p>${response.reply}</p>`;
      chatMessages.appendChild(aiMsgEl);

      chatMessages.scrollTop = chatMessages.scrollHeight;
    } catch (err) {
      console.error(err);
      loadingEl.remove();

      const errorEl = document.createElement('div');
      errorEl.className = 'chat-message ai-message';
      errorEl.innerHTML = '<p>Sorry, I had trouble responding. Please try again.</p>';
      chatMessages.appendChild(errorEl);
    } finally {
      if (sendBtn) sendBtn.disabled = false;
    }
  },

  async getAdvice() {
    if (!State.isAIEnabled()) {
      UI.showToast('Enable AI in Profile first.');
      return;
    }

    const adviceEl = document.getElementById('ai-advice');
    adviceEl.innerText = 'Fetching AI advice…';

    try {
      const res = await API.getAIAdvice();
      adviceEl.innerHTML = this.renderAdvice(res.advice);
    } catch (err) {
      console.error(err);
      adviceEl.innerText = 'Failed to get AI advice.';
    }
  },

  renderAdvice(text) {
    const safe = this.escapeHtml(String(text || ''));
    const lines = safe.split(/\r?\n/);
    const blocks = [];
    let i = 0;

    while (i < lines.length) {
      if (lines[i].trim() === '') { i++; continue; }

      // Table
      if (lines[i].trim().startsWith('|')) {
        const tableLines = [];
        while (i < lines.length && lines[i].trim().startsWith('|')) {
          tableLines.push(lines[i]);
          i++;
        }
        blocks.push(this.renderTable(tableLines));
        continue;
      }

      // List
      if (/^[-*]\s/.test(lines[i].trim())) {
        const items = [];
        while (i < lines.length && /^[-*]\s/.test(lines[i].trim())) {
          items.push(lines[i].trim().replace(/^[-*]\s+/, ''));
          i++;
        }
        blocks.push('<ul>' + items.map(li => `<li>${this.inlineMarkdown(li)}</li>`).join('') + '</ul>');
        continue;
      }

      // Heading
      if (/^#{1,3}\s/.test(lines[i])) {
        const line = lines[i];
        const level = Math.min(line.match(/^#+/)[0].length, 3);
        const content = line.replace(/^#{1,3}\s+/, '');
        blocks.push(`<h${level}>${this.inlineMarkdown(content)}</h${level}>`);
        i++;
        continue;
      }

      // Blockquote
      if (lines[i].trim().startsWith('&gt;')) {
        const quote = lines[i].trim().replace(/^&gt;\s?/, '');
        blocks.push(`<blockquote>${this.inlineMarkdown(quote)}</blockquote>`);
        i++;
        continue;
      }

      // Paragraph
      const para = [];
      while (i < lines.length && lines[i].trim() !== '') {
        para.push(lines[i]);
        i++;
      }
      blocks.push(`<p>${this.inlineMarkdown(para.join(' '))}</p>`);
    }

    return blocks.join('\n');
  },

  renderTable(lines) {
    const rows = lines.map(l => l.trim().replace(/^\||\|$/g, '').split('|').map(c => this.inlineMarkdown(c.trim())));
    if (rows.length === 0) return '';
    const header = rows[0];
    const body = rows.slice(2);
    const thead = '<thead><tr>' + header.map(h => `<th>${h}</th>`).join('') + '</tr></thead>';
    const tbody = '<tbody>' + body.map(r => '<tr>' + r.map(c => `<td>${c}</td>`).join('') + '</tr>').join('') + '</tbody>';
    return `<div class="ai-table"><table>${thead}${tbody}</table></div>`;
  },

  inlineMarkdown(text) {
    return text
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.+?)\*/g, '<em>$1</em>');
  },

  escapeHtml(str) {
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }
};

function sendChatMessage() { ChatBot.sendMessage(); }
