<template>
  <div class="auth-container">
    <h1>Login</h1>
    <form @submit.prevent="submit">
      <div>
        <label>Email</label>
        <input v-model="form.email" type="email" required />
      </div>
      <div>
        <label>Senha</label>
        <input v-model="form.password" type="password" required />
      </div>
      <button type="submit">Entrar</button>
      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="success" class="success">{{ success }}</p>
    </form>
    <RouterLink to="/register">Criar conta</RouterLink>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import axios from 'axios'

const form = reactive({ email: '', password: '' })
const error = ref('')
const success = ref('')

// Envia os dados para a API e salva o token em caso de sucesso
async function submit() {
  error.value = ''
  success.value = ''
  try {
    const { data } = await axios.post('/api/auth/login', form)
    if (data.success) {
      success.value = data.message
      localStorage.setItem('token', data.data.token)
      localStorage.setItem('token_type', data.data.token_type)
      localStorage.setItem('user', JSON.stringify(data.data.user))
    } else {
      error.value = data.message
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Erro ao logar'
  }
}
</script>

<style scoped>
.auth-container { max-width: 400px; margin: 0 auto; }
.error { color: red; }
.success { color: green; }
</style>
