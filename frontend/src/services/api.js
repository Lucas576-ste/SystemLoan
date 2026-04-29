import axios from 'axios'

const rawApiUrl = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000').trim()
const apiUrlWithProtocol = /^https?:\/\//i.test(rawApiUrl) ? rawApiUrl : `https://${rawApiUrl}`
const API_ORIGIN = apiUrlWithProtocol.replace(/\/+$/, '').replace(/\/api$/i, '')
const API_BASE_URL = `${API_ORIGIN}/api`
const TOKEN_KEY = 'systemloan_token'

const api = axios.create({
  baseURL: API_BASE_URL,
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  if (!(config.data instanceof FormData) && !config.headers['Content-Type']) {
    config.headers['Content-Type'] = 'application/json'
  }

  return config
})

export { TOKEN_KEY }
export default api
