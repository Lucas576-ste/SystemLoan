import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import api from '../services/api'

const NotificationsContext = createContext(null)

const POLL_MS = 30_000

export function NotificationsProvider({ children }) {
  const [notifications, setNotifications] = useState([])

  const fetchNotifications = useCallback(async () => {
    try {
      const { data } = await api.get('/notifications')
      setNotifications(Array.isArray(data) ? data : [])
    } catch {
      // não-crítico: não exibe erro para o usuário
    }
  }, [])

  // Carga inicial + polling a cada 30s
  useEffect(() => {
    fetchNotifications()
    const timer = setInterval(fetchNotifications, POLL_MS)
    return () => clearInterval(timer)
  }, [fetchNotifications])

  // Refetch ao focar a janela (tab reativada)
  useEffect(() => {
    window.addEventListener('focus', fetchNotifications)
    return () => window.removeEventListener('focus', fetchNotifications)
  }, [fetchNotifications])

  // Atualização otimista: marca lida localmente antes da resposta da API
  const markRead = useCallback(async (id) => {
    setNotifications((prev) =>
      prev.map((n) => (n.id === id ? { ...n, is_read: true } : n)),
    )
    try {
      await api.patch(`/notifications/${id}/read`)
    } catch {
      // reverte se a API falhar
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, is_read: false } : n)),
      )
    }
  }, [])

  const unreadCount = useMemo(
    () => notifications.filter((n) => !n.is_read).length,
    [notifications],
  )

  const value = useMemo(
    () => ({ notifications, unreadCount, markRead, refresh: fetchNotifications }),
    [notifications, unreadCount, markRead, fetchNotifications],
  )

  return (
    <NotificationsContext.Provider value={value}>
      {children}
    </NotificationsContext.Provider>
  )
}

export function useNotifications() {
  const ctx = useContext(NotificationsContext)
  if (!ctx) throw new Error('useNotifications deve ser usado dentro de NotificationsProvider')
  return ctx
}
