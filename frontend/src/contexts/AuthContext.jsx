import { useCallback, useEffect, useMemo, useState } from 'react'
import { AuthContext } from './auth-context'
import api, { TOKEN_KEY } from '../services/api'

export function AuthProvider({ children }) {
  const [token, setToken] = useState(() => localStorage.getItem(TOKEN_KEY))
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  const clearSession = useCallback(() => {
    localStorage.removeItem(TOKEN_KEY)
    setToken(null)
    setUser(null)
  }, [])

  const applySession = useCallback((nextToken, nextUser) => {
    localStorage.setItem(TOKEN_KEY, nextToken)
    setToken(nextToken)
    setUser(nextUser)
  }, [])

  const login = useCallback(
    async (email, password) => {
      const { data } = await api.post('/login', { email, password })
      const nextToken = String(data?.token ?? '')
      const nextUser = data?.user ?? null

      if (!nextToken || !nextUser) {
        throw new Error('Resposta inválida do servidor')
      }

      applySession(nextToken, nextUser)
      return data
    },
    [applySession],
  )

  const register = useCallback(async (name, email, password) => {
    const { data } = await api.post('/register', { name, email, password })
    return data
  }, [])

  const logout = useCallback(() => {
    clearSession()
  }, [clearSession])

  useEffect(() => {
    let mounted = true

    const hydrateSession = async () => {
      if (!token) {
        if (mounted) setLoading(false)
        return
      }

      try {
        const { data } = await api.get('/me')
        if (!mounted) return

        if (!data?.user) {
          clearSession()
        } else {
          setUser(data.user)
        }
      } catch {
        if (mounted) clearSession()
      } finally {
        if (mounted) setLoading(false)
      }
    }

    hydrateSession()
    return () => {
      mounted = false
    }
  }, [token, clearSession])

  const value = useMemo(
    () => ({
      user,
      token,
      loading,
      isAuthenticated: Boolean(token && user),
      login,
      register,
      logout,
    }),
    [user, token, loading, login, register, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
