import { Outlet } from 'react-router-dom'
import { NotificationsProvider } from '../contexts/NotificationsContext'
import Navbar from './Navbar'

function PrivateLayout() {
  return (
    <NotificationsProvider>
      <div className="app-shell">
        <Navbar />
        <main className="app-content">
          <Outlet />
        </main>
      </div>
    </NotificationsProvider>
  )
}

export default PrivateLayout
