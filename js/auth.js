// Auth Management
const Auth = {
  checkAuth() {
    const token = localStorage.getItem('token');
    if (!token) {
      window.location.href = 'login.html';
      return false;
    }
    return true;
  },

  saveAuth(token, user_id) {
    localStorage.setItem('token', token);
    localStorage.setItem('user_id', user_id);
  },

  clearAuth() {
    localStorage.removeItem('token');
    localStorage.removeItem('user_id');
  },

  getToken() {
    return localStorage.getItem('token');
  },

  getUserId() {
    return localStorage.getItem('user_id');
  }
};
