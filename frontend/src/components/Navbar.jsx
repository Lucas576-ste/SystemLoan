import { NavLink } from 'react-router-dom'
import { useNotifications } from '../contexts/NotificationsContext'
import { useTheme } from '../contexts/ThemeContext'
import { useAuth } from '../hooks/useAuth'

function SunIcon() {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none"
      stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"
      aria-hidden="true">
      <circle cx="12" cy="12" r="4" />
      <line x1="12" y1="2" x2="12" y2="6" />
      <line x1="12" y1="18" x2="12" y2="22" />
      <line x1="4.93" y1="4.93" x2="7.76" y2="7.76" />
      <line x1="16.24" y1="16.24" x2="19.07" y2="19.07" />
      <line x1="2" y1="12" x2="6" y2="12" />
      <line x1="18" y1="12" x2="22" y2="12" />
      <line x1="4.93" y1="19.07" x2="7.76" y2="16.24" />
      <line x1="16.24" y1="7.76" x2="19.07" y2="4.93" />
    </svg>
  )
}

function MoonIcon() {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none"
      stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"
      aria-hidden="true">
      <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
    </svg>
  )
}

function BellIcon() {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none"
      stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"
      aria-hidden="true">
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
      <path d="M13.73 21a2 2 0 0 1-3.46 0" />
    </svg>
  )
}

function Navbar() {
  const { user, logout } = useAuth()
  const { theme, toggle } = useTheme()
  const { unreadCount } = useNotifications()

  return (
    <header className="topbar">
      <div className="topbar-brand">
        <strong>SystemLoan</strong>
        <span>Controle de ferramentas</span>
      </div>

      <nav className="topbar-nav">
        <NavLink
          to="/dashboard"
          className={({ isActive }) => (isActive ? 'nav-link active' : 'nav-link')}
        >
          Dashboard
        </NavLink>
        <NavLink
          to="/history"
          className={({ isActive }) => (isActive ? 'nav-link active' : 'nav-link')}
        >
          Histórico
        </NavLink>
      </nav>

      <div className="topbar-actions">
        <NavLink
          to="/notifications"
          className={({ isActive }) => `notif-btn${isActive ? ' active' : ''}`}
          aria-label={
            unreadCount > 0
              ? `Notificações — ${unreadCount} não ${unreadCount === 1 ? 'lida' : 'lidas'}`
              : 'Notificações'
          }
          title="Notificações"
        >
          <BellIcon />
          {unreadCount > 0 ? (
            <span className="notif-badge" aria-hidden="true">
              {unreadCount > 99 ? '99+' : unreadCount}
            </span>
          ) : null}
        </NavLink>

        <button
          type="button"
          className="theme-toggle"
          onClick={toggle}
          aria-label={theme === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro'}
          title={theme === 'dark' ? 'Modo claro' : 'Modo escuro'}
        >
          {theme === 'dark' ? <SunIcon /> : <MoonIcon />}
        </button>

        <div className="topbar-user">
          <span>{user?.name ?? 'Usuário'}</span>
          <button type="button" className="btn-secondary btn-sm" onClick={logout}>
            Sair
          </button>
        </div>
      </div>
    </header>
  )
}

export default Navbar
