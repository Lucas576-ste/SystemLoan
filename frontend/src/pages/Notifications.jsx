import { useNotifications } from '../contexts/NotificationsContext'

function BellFilledIcon() {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
      fill="currentColor" aria-hidden="true">
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
      <path d="M13.73 21a2 2 0 0 1-3.46 0" />
    </svg>
  )
}

function timeAgo(dateString) {
  if (!dateString) return ''
  const diff = Date.now() - new Date(dateString).getTime()
  const min = Math.floor(diff / 60_000)
  if (min < 1) return 'agora mesmo'
  if (min < 60) return `há ${min} min`
  const h = Math.floor(min / 60)
  if (h < 24) return `há ${h}h`
  const d = Math.floor(h / 24)
  if (d === 1) return 'ontem'
  if (d < 7) return `há ${d} dias`
  return new Date(dateString).toLocaleDateString('pt-BR')
}

function Notifications() {
  const { notifications, unreadCount, markRead } = useNotifications()

  const handleClick = (notif) => {
    if (!notif.is_read) markRead(notif.id)
  }

  return (
    <section className="content-card">
      <header className="section-header notif-page-header">
        <div>
          <h1>Notificações</h1>
          <p>Atividades recentes da sua conta.</p>
        </div>
        {unreadCount > 0 ? (
          <span className="notif-page-badge">{unreadCount} não {unreadCount === 1 ? 'lida' : 'lidas'}</span>
        ) : null}
      </header>

      {notifications.length === 0 ? (
        <div className="notif-empty">
          <BellFilledIcon />
          <p>Nenhuma notificação ainda.</p>
          <span>As atividades da sua conta aparecerão aqui.</span>
        </div>
      ) : (
        <div className="notif-list">
          {notifications.map((notif) => (
            <button
              key={notif.id}
              type="button"
              className={`notif-item${notif.is_read ? '' : ' unread'}`}
              onClick={() => handleClick(notif)}
              aria-label={notif.is_read ? notif.message : `(não lida) ${notif.message}`}
            >
              <div className="notif-item-icon" aria-hidden="true">
                <BellFilledIcon />
              </div>

              <div className="notif-item-content">
                <p className="notif-item-message">{notif.message}</p>
                <time className="notif-item-time" dateTime={notif.created_at}>
                  {timeAgo(notif.created_at)}
                </time>
              </div>

              {!notif.is_read ? <span className="notif-item-dot" aria-hidden="true" /> : null}
            </button>
          ))}
        </div>
      )}
    </section>
  )
}

export default Notifications
