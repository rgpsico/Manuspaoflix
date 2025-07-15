<template>
  <div class="auth-container">
    <h1>Registro</h1>
    <form @submit.prevent="submit">
      <div>
        <label>Nome</label>
        <input v-model="form.name" type="text" required />
      </div>
      <div>
        <label>Email</label>
        <input v-model="form.email" type="email" required />
      </div>
      <div>
        <label>Senha</label>
        <input v-model="form.password" type="password" required />
      </div>
      <div>
        <label>Confirmação de Senha</label>
        <input v-model="form.password_confirmation" type="password" required />
      </div>
      <button type="submit">Registrar</button>
      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="success" class="success">{{ success }}</p>
    </form>
    <RouterLink to="/login">Voltar ao Login</RouterLink>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: ''
})
const error = ref('')
const success = ref('')

// Validações simples e chamada à API de registro
async function submit() {
  error.value = ''
  success.value = ''
  if (form.password !== form.password_confirmation) {
    error.value = 'As senhas não conferem'
    return
  }
  try {
    const { data } = await axios.post('/api/auth/register', form)
    if (data.success) {
      success.value = data.message
      localStorage.setItem('token', data.data.token)
      localStorage.setItem('token_type', data.data.token_type)
      localStorage.setItem('user', JSON.stringify(data.data.user))
      router.push('/login')
    } else {
      error.value = data.message
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Erro ao registrar'
  }
}
</script>

<style scoped>
.auth-container { max-width: 400px; margin: 0 auto; }
.error { color: red; }
.success { color: green; }
</style>
