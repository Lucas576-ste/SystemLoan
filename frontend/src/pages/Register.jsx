import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'

function Register() {
  const navigate = useNavigate()
  const { login, register } = useAuth()

  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const handleSubmit = async (event) => {
    event.preventDefault()
    setError('')

    if (!name || !email || !password) {
      setError('Preencha nome, email e senha.')
      return
    }

    if (password.length < 6) {
      setError('A senha deve ter no mínimo 6 caracteres.')
      return
    }

    setSubmitting(true)
    try {
      await register(name, email, password)
      await login(email, password)
      navigate('/dashboard', { replace: true })
    } catch (err) {
      const message = err?.response?.data?.error ?? 'Não foi possível criar sua conta.'
      setError(message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <main className="auth-page">
      <section className="auth-card">
        <h1 className="auth-title">Criar conta</h1>
        <p className="auth-subtitle">Comece a usar o SystemLoan em poucos segundos.</p>

        <form className="auth-form" onSubmit={handleSubmit}>
          <label className="form-group">
            <span>Nome</span>
            <input
              type="text"
              placeholder="Seu nome"
              value={name}
              onChange={(e) => setName(e.target.value)}
              autoComplete="name"
            />
          </label>

          <label className="form-group">
            <span>Email</span>
            <input
              type="email"
              placeholder="voce@exemplo.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              autoComplete="email"
            />
          </label>

          <label className="form-group">
            <span>Senha</span>
            <input
              type="password"
              placeholder="Mínimo de 6 caracteres"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="new-password"
            />
          </label>

          {error ? <p className="alert-error">{error}</p> : null}

          <button className="btn-primary" type="submit" disabled={submitting}>
            {submitting ? 'Criando conta...' : 'Criar conta'}
          </button>
        </form>

        <p className="auth-footer">
          Já tem conta? <Link to="/login">Fazer login</Link>
        </p>
      </section>
    </main>
  )
}

export default Register
