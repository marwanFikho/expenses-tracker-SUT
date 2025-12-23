function checkAuth() {
  const token = localStorage.getItem('token');
  if (!token) {
    window.location.href = 'login.html';
    return false;
  }
  return true;
}

function logout() {
  localStorage.removeItem('token');
  localStorage.removeItem('user_id');
  window.location.href = 'login.html';
}

function showPage(id){
  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  document.getElementById(id).classList.add('active');
}

let wallet = 0;
let expenses = [];
let incomes = [];
let caps = { day: 0, week: 0, month: 0 };
let aiEnabled = true;

const API_BASE = 'api.php';

async function fetchJSON(url, options = {}){
  const token = localStorage.getItem('token');
  if (!token) {
    window.location.href = 'login.html';
    throw new Error('Not authenticated');
  }

  const headers = options.headers || {};
  headers['Authorization'] = `Bearer ${token}`;
  options.headers = headers;

  const res = await fetch(url, options);
  const data = await res.json();
  
  if (res.status === 401) {
    localStorage.removeItem('token');
    localStorage.removeItem('user_id');
    window.location.href = 'login.html';
    throw new Error('Session expired');
  }
  
  if(!res.ok) throw new Error(data.error || res.statusText);
  return data;
}

async function refreshState(){
  try {
    const data = await fetchJSON(`${API_BASE}?path=state`);
    wallet = data.wallet || 0;
    caps = data.caps || { day:0, week:0, month:0 };
    expenses = Array.isArray(data.expenses) ? data.expenses : [];
    incomes = Array.isArray(data.incomes) ? data.incomes : [];
    aiEnabled = typeof data.aiEnabled === 'boolean' ? data.aiEnabled : true;
    document.getElementById('walletBalance').innerText = wallet;
    document.getElementById('aiPref').checked = aiEnabled;
    document.getElementById('capDay').value = caps.day || '';
    document.getElementById('capWeek').value = caps.week || '';
    document.getElementById('capMonth').value = caps.month || '';
    renderExpenses();
    renderIncome();
    updateAnalytics();
    renderIncomeChart();
  } catch(err){
    console.error(err);
    showToast('Failed to load data');
  }
}

document.getElementById('walletBalance').innerText = wallet;

function renderExpenses(){
  const recentEl = document.getElementById('recentExpenses');
  const analyticsEl = document.getElementById('analyticsList');
  recentEl.innerHTML = '';
  analyticsEl.innerHTML = '';

  expenses.forEach((e,index)=>{
    const p = document.createElement('p');
    p.innerHTML = `- ${e.amount} EGP | ${e.merchant} (${e.beneficial?'✓':'✗'})
      <button class="edit-btn" onclick="editExpense(${index})">Edit</button>
      <button class="delete-btn" onclick="deleteExpense(${index})">Delete</button>`;
    recentEl.appendChild(p);

    const p2 = document.createElement('p');
    p2.innerHTML = `<b>${e.amount} EGP</b> at ${e.merchant} — ${e.beneficial?'Beneficial':'Not beneficial'}`;
    analyticsEl.appendChild(p2);
  });

  renderChart();
  renderIncomeChart();
}

function renderIncome(){
  const targets = [
    document.getElementById('incomeList'),
    document.getElementById('incomeAnalyticsList')
  ].filter(Boolean);
  targets.forEach(target => {
    target.innerHTML = '';
    incomes.forEach((inc)=>{
      const p = document.createElement('p');
      const dateStr = new Date(inc.ts).toLocaleDateString();
      p.innerHTML = `+ ${inc.amount} EGP from ${inc.source} <span style="opacity:0.6;">(${dateStr})</span>`;
      target.appendChild(p);
    });
  });
}

function addExpense(){ startExpenseFlow(); }

// Flow: ask for confirmation (need/want, show habits & caps) before adding expense
let pendingExpense = null;
function startExpenseFlow(){
  const amount = Number(document.getElementById('amount').value);
  const merchant = document.getElementById('merchant').value.trim();
  if(!(amount > 0) || !merchant){ alert('Enter amount (>0) and merchant/place'); return; }

  pendingExpense = { amount, merchant, beneficial: 0, ts: Date.now() };
  
  const modal = document.getElementById('confirmModal');
  const needWantSelect = document.getElementById('needWantSelect');
  needWantSelect.value = 'want';

  // Habits: show past spending on same merchant (last 30 days)
  const cutoff = Date.now() - 30*24*3600*1000;
  const merchTotal = expenses.filter(e=> e.merchant===merchant && e.ts>=cutoff).reduce((s,e)=>s+e.amount,0);
  const habitSummary = document.getElementById('habitSummary');
  habitSummary.innerHTML = `In the last 30 days: <b>${merchTotal} EGP</b> spent at "${merchant}".`;

  // Caps and usage (true weekly/monthly using timestamps)
  const { weekSpent, monthSpent } = computePeriodTotals();
  document.getElementById('weeklyCap').innerText = caps.week;
  document.getElementById('monthlyCap').innerText = caps.month;
  document.getElementById('weeklySpent').innerText = weekSpent;
  document.getElementById('monthlySpent').innerText = monthSpent;

  // Advice line in habit summary
  const needWant = needWantSelect.value;
  const crossingWeek = (weekSpent + amount) > caps.week;
  const crossingMonth = (monthSpent + amount) > caps.month;
  const advice = (needWant==='need')
    ? (crossingWeek || crossingMonth ? 'It’s a need, but you may exceed your cap soon.' : 'Looks reasonable for a need.')
    : (crossingWeek || crossingMonth ? 'This is a want and may exceed your cap. Consider skipping.' : 'It’s a want; ensure it fits your budget.');
  habitSummary.innerHTML += `<br/><i>${advice}</i>`;

  modal.classList.add('show');
  modal.setAttribute('aria-hidden','false');
}

function cancelExpenseFlow(){
  const modal = document.getElementById('confirmModal');
  modal.classList.remove('show');
  modal.setAttribute('aria-hidden','true');
  pendingExpense = null;
}

function confirmExpenseFlow(){
  if(!pendingExpense) return cancelExpenseFlow();
  const needWant = document.getElementById('needWantSelect').value;
  
  pendingExpense.beneficial = (needWant === 'need') ? 1 : 0;

  // Save to Database
  (async()=>{
    try {
      await fetchJSON(`${API_BASE}?path=expense`, {
        method: 'POST',
        headers: { 'Content-Type':'application/json' },
        body: JSON.stringify(pendingExpense)
      });
      if(pendingExpense.beneficial === 1){
        showToast('Good call. Needs come first. ✅');
      } else {
        showToast('This looks like a want. Be mindful. 🥲');
        triggerSadEffect();
      }
      cancelExpenseFlow();
      clearExpenseInputs();
      await refreshState();
    } catch(err){
      console.error(err);
      showToast('Failed to save expense');
    }
  })();
}

function computePeriodTotals(){
  const now = Date.now();
  const dayMs = 24*3600*1000;
  const weekStart = now - 7*dayMs;
  const monthStart = now - 30*dayMs;
  const weekSpent = expenses.filter(e=> e.ts >= weekStart).reduce((s,e)=>s+e.amount,0);
  const monthSpent = expenses.filter(e=> e.ts >= monthStart).reduce((s,e)=>s+e.amount,0);
  return { weekSpent, monthSpent };
}

function clearExpenseInputs(){
  document.getElementById('amount').value = '';
  document.getElementById('merchant').value = '';
}

function editExpense(index){
  const e = expenses[index];
  const newAmount = prompt("Enter new amount:", e.amount);
  const newMerchant = prompt("Enter new merchant:", e.merchant);
  const newBeneficial = prompt("Beneficial? 1=yes, 0=no", e.beneficial ? 1 : 0);

  if(newAmount && newMerchant){
    (async()=>{
      try {
        await fetchJSON(`${API_BASE}?path=expense&id=${e.id}`, {
          method: 'PUT',
          headers: { 'Content-Type':'application/json' },
          body: JSON.stringify({
            amount: Number(newAmount),
            merchant: newMerchant,
            beneficial: Number(newBeneficial),
            ts: e.ts || Date.now()
          })
        });
        await refreshState();
      } catch(err){
        console.error(err);
        showToast('Failed to update expense');
      }
    })();
  }
}

function deleteExpense(index){
  if(confirm('Are you sure you want to delete this expense?')){
    const id = expenses[index].id;
    (async()=>{
      try {
        await fetchJSON(`${API_BASE}?path=expense&id=${id}`, { method:'DELETE' });
        await refreshState();
      } catch(err){
        console.error(err);
        showToast('Failed to delete expense');
      }
    })();
  }
}

function topUpWallet(){
  const amt = Number(document.getElementById('addMoneyInput').value);
  const source = (document.getElementById('addMoneySource').value || '').trim();
  if(!(amt > 0)){ alert('Enter a positive amount'); return; }
  if(!source){ alert('Enter where this money is from'); return; }
  (async()=>{
    try {
      await fetchJSON(`${API_BASE}?path=income`, {
        method:'POST',
        headers:{ 'Content-Type':'application/json' },
        body: JSON.stringify({ amount: amt, source, ts: Date.now() })
      });
      document.getElementById('addMoneyInput').value = '';
      document.getElementById('addMoneySource').value = '';
      showToast(`Awesome! +${amt} EGP from ${source} 🎉`);
      triggerConfetti();
      await refreshState();
    } catch(err){
      console.error(err);
      showToast('Failed to add income');
    }
  })();
}

function saveCaps(){
  caps.day = Number(document.getElementById('capDay').value);
  caps.week = Number(document.getElementById('capWeek').value);
  caps.month = Number(document.getElementById('capMonth').value);
  (async()=>{
    try {
      await fetchJSON(`${API_BASE}?path=caps`, {
        method:'POST',
        headers:{ 'Content-Type':'application/json' },
        body: JSON.stringify(caps)
      });
      showToast('Caps saved');
    } catch(err){
      console.error(err);
      showToast('Failed to save caps');
    }
  })();
}

function savePreferences(){
  aiEnabled = document.getElementById('aiPref').checked;
  (async()=>{
    try {
      await fetchJSON(`${API_BASE}?path=prefs`, {
        method:'POST',
        headers:{ 'Content-Type':'application/json' },
        body: JSON.stringify({ aiEnabled })
      });
      showToast('Preferences saved');
    } catch(err){
      console.error(err);
      showToast('Failed to save preferences');
    }
  })();
}

function updateAnalytics(){
  const totalDay = expenses.reduce((s,e)=>s+e.amount,0);
  const totalWeek = totalDay;
  const totalMonth = totalDay;

  document.getElementById('summaryTotals').innerHTML = `
    <p>Day: ${totalDay} EGP / ${caps.day}</p>
    <p>Week: ${totalWeek} EGP / ${caps.week}</p>
    <p>Month: ${totalMonth} EGP / ${caps.month}</p>
  `;

  const prediction = totalMonth * 1.15;
  document.getElementById('trendPrediction').innerText =
    `Expected Next Month: ${prediction.toFixed(2)} EGP`;

  document.getElementById('aiAdvice').innerText = aiEnabled
    ? (prediction > caps.month
        ? 'Your spending is increasing. Reduce non-essential expenses.'
        : 'Your spending is under control. Keep going!')
    : 'Enable AI to get advice.';
}

setInterval(updateAnalytics, 500);

function toggleTheme() {
  const toggleBtn = document.getElementById('themeToggle');
  const rect = toggleBtn.getBoundingClientRect();
  const cx = rect.left + rect.width / 2;
  const cy = rect.top + rect.height / 2;

  const goingDark = !document.body.classList.contains('dark-mode');
  const targetColor = goingDark ? '#121212' : '#f0f2f5';

  const ripple = document.createElement('div');
  ripple.className = 'theme-ripple';
  ripple.style.background = targetColor;
  ripple.style.left = `${cx}px`;
  ripple.style.top = `${cy}px`;

  const maxDim = Math.hypot(window.innerWidth, window.innerHeight);
  const diameter = maxDim * 2;
  ripple.style.width = `${diameter}px`;
  ripple.style.height = `${diameter}px`;

  const bgLayer = document.getElementById('bg-ripple') || document.body;
  bgLayer.appendChild(ripple);
  document.body.classList.toggle('dark-mode');
  toggleBtn.innerText = document.body.classList.contains('dark-mode') ? "☀️" : "🌙";

  setTimeout(() => {
    ripple.style.transition = 'opacity 120ms ease';
    ripple.style.opacity = '0';
    setTimeout(() => ripple.remove(), 120);
  }, 200);
}

window.onload = () => {
  if (!checkAuth()) return;
  const toggleBtn = document.getElementById('themeToggle');
  toggleBtn.innerText = document.body.classList.contains('dark-mode') ? "☀️" : "🌙";
  refreshState();
};

let chartInstance;
function renderChart(){
  const ctx = document.getElementById('expenseChart').getContext('2d');
  const merchants = [...new Set(expenses.map(e=>e.merchant))];
  const amounts = merchants.map(m => 
    expenses.filter(e=>e.merchant===m).reduce((s,e)=>s+e.amount,0)
  );

  if(chartInstance) chartInstance.destroy();

  chartInstance = new Chart(ctx, {
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
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });
}

let incomeChartInstance;
function renderIncomeChart(){
  const canvas = document.getElementById('incomeChart');
  if(!canvas) return;
  const ctx = canvas.getContext('2d');
  const sources = [...new Set(incomes.map(i=>i.source))];
  const amounts = sources.map(s =>
    incomes.filter(i=>i.source===s).reduce((sum,i)=>sum+i.amount,0)
  );

  if(incomeChartInstance) incomeChartInstance.destroy();
  incomeChartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: sources,
      datasets: [{
        label: 'Income by Source',
        data: amounts,
        backgroundColor: 'rgba(46, 164, 79, 0.6)'
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });
}

// Effects & Utilities

function triggerConfetti(){
  if(typeof confetti !== 'function') return;
  const duration = 1500;
  const end = Date.now() + duration;

  (function frame(){
    confetti({
      particleCount: 3,
      startVelocity: 40,
      spread: 360,
      ticks: 60,
      origin: { y: 0.7 }
    });
    if (Date.now() < end) requestAnimationFrame(frame);
  })();
}

function triggerSadEffect(){
  const effects = document.getElementById('effects');
  if(!effects) return;
  document.body.classList.add('sad-mode');

  const rain = document.createElement('div');
  rain.className = 'sad-rain';
  const emoji = document.createElement('div');
  emoji.className = 'sad-emoji';
  emoji.textContent = '🥲';

  effects.appendChild(rain);
  effects.appendChild(emoji);

  setTimeout(()=>{
    document.body.classList.remove('sad-mode');
    rain.remove();
    emoji.remove();
  }, 2500);
}

function showToast(message){
  const el = document.createElement('div');
  el.className = 'toast';
  el.setAttribute('role','status');
  el.setAttribute('aria-live','polite');
  el.textContent = message;
  document.body.appendChild(el);
  setTimeout(()=> el.remove(), 2800);
}