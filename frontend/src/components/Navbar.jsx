import { NavLink } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'

function Navbar() {
  const { user, logout } = useAuth()

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

      <div className="topbar-user">
        <span>{user?.name ?? 'Usuário'}</span>
        <button type="button" className="btn-secondary btn-sm" onClick={logout}>
          Sair
        </button>
      </div>
    </header>
  )
}

export default Navbar
