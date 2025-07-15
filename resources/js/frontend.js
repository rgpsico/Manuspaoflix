// Simple frontend to interact with auth routes
const apiUrl = '/api';

function getToken() {
    return localStorage.getItem('token');
}

function setToken(token) {
    localStorage.setItem('token', token);
    document.getElementById('token-display').textContent = token;
}

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const meBtn = document.getElementById('btn-me');
    const profileForm = document.getElementById('profile-form');
    const passwordForm = document.getElementById('password-form');
    const logoutBtn = document.getElementById('logout-btn');
    const output = document.getElementById('output');

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = {
            email: loginForm.email.value,
            password: loginForm.password.value,
        };
        const resp = await fetch(`${apiUrl}/auth/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        });
        const result = await resp.json();
        if (resp.ok) {
            setToken(result.data.token);
            output.textContent = 'Login realizado';
        } else {
            output.textContent = result.message || 'Erro no login';
        }
    });

    meBtn.addEventListener('click', async () => {
        const token = getToken();
        if (!token) return (output.textContent = 'Token não encontrado');
        const resp = await fetch(`${apiUrl}/auth/me`, {
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`,
            },
        });
        output.textContent = JSON.stringify(await resp.json(), null, 2);
    });

    profileForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const token = getToken();
        if (!token) return (output.textContent = 'Token não encontrado');
        const data = {
            name: profileForm.name.value,
            email: profileForm.email.value,
            phone: profileForm.phone.value,
        };
        const resp = await fetch(`${apiUrl}/auth/profile`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`,
            },
            body: JSON.stringify(data),
        });
        output.textContent = JSON.stringify(await resp.json(), null, 2);
    });

    passwordForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const token = getToken();
        if (!token) return (output.textContent = 'Token não encontrado');
        const data = {
            current_password: passwordForm.current_password.value,
            password: passwordForm.password.value,
            password_confirmation: passwordForm.password_confirmation.value,
        };
        const resp = await fetch(`${apiUrl}/auth/password`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`,
            },
            body: JSON.stringify(data),
        });
        output.textContent = JSON.stringify(await resp.json(), null, 2);
    });

    logoutBtn.addEventListener('click', async () => {
        const token = getToken();
        if (!token) return (output.textContent = 'Token não encontrado');
        const resp = await fetch(`${apiUrl}/auth/logout`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`,
            },
        });
        localStorage.removeItem('token');
        output.textContent = JSON.stringify(await resp.json(), null, 2);
    });
});
