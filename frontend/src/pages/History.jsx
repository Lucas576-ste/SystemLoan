import { useEffect, useState } from 'react'
import api from '../services/api'

const defaultFilters = {
  status: '',
  date_from: '',
  date_to: '',
  tool: '',
}

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('pt-BR')
}

function History() {
  const [filters, setFilters] = useState(defaultFilters)
  const [items, setItems] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const fetchHistory = async (currentFilters = filters) => {
    try {
      setLoading(true)
      const params = new URLSearchParams()
      Object.entries(currentFilters).forEach(([key, value]) => {
        if (value) params.set(key, value)
      })
      const query = params.toString()
      const { data } = await api.get(query ? `/loans/history?${query}` : '/loans/history')
      setItems(Array.isArray(data) ? data : [])
      setError('')
    } catch (err) {
      setError(err?.response?.data?.error ?? 'Não foi possível carregar o histórico.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchHistory(defaultFilters)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const handleSubmit = async (event) => {
    event.preventDefault()
    await fetchHistory(filters)
  }

  const set = (field) => (e) => setFilters((prev) => ({ ...prev, [field]: e.target.value }))

  return (
    <section className="content-card">
      <header className="section-header">
        <h1>Histórico de empréstimos</h1>
        <p>Use os filtros para localizar operações por período, status ou ferramenta.</p>
      </header>

      <form className="filter-grid" onSubmit={handleSubmit}>
        <label className="form-group">
          <span>Status</span>
          <select value={filters.status} onChange={set('status')}>
            <option value="">Todos</option>
            <option value="active">Ativo</option>
            <option value="returned">Devolvido</option>
          </select>
        </label>

        <label className="form-group">
          <span>Data inicial</span>
          <input type="date" value={filters.date_from} onChange={set('date_from')} />
        </label>

        <label className="form-group">
          <span>Data final</span>
          <input type="date" value={filters.date_to} onChange={set('date_to')} />
        </label>

        <label className="form-group">
          <span>Ferramenta</span>
          <input
            type="text"
            placeholder="Ex.: furadeira"
            value={filters.tool}
            onChange={set('tool')}
          />
        </label>

        <button type="submit" className="btn-primary">
          Filtrar
        </button>
      </form>

      {error ? <p className="alert-error" style={{ marginTop: 14 }}>{error}</p> : null}
      {loading ? <p className="muted" style={{ marginTop: 14 }}>Carregando histórico...</p> : null}

      {!loading && !error ? (
        items.length === 0 ? (
          <p className="muted" style={{ marginTop: 14 }}>Nenhum registro encontrado.</p>
        ) : (
          <div className="table-wrap">
            <table className="history-table">
              <thead>
                <tr>
                  <th>Ferramenta</th>
                  <th>Tomador</th>
                  <th>Status</th>
                  <th>Empréstimo</th>
                  <th>Prev. devolução</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={item.loan_id}>
                    <td>
                      <strong>{item.tool_name}</strong>
                      {item.tool_description ? (
                        <p className="table-sub">{item.tool_description}</p>
                      ) : null}
                    </td>
                    <td>
                      {item.borrower_name}
                      {item.borrower_phone ? (
                        <p className="table-sub">{item.borrower_phone}</p>
                      ) : null}
                    </td>
                    <td>
                      <span className={item.status === 'returned' ? 'status-pill ok' : 'status-pill off'}>
                        {item.status === 'returned' ? 'Devolvido' : 'Ativo'}
                      </span>
                    </td>
                    <td>{formatDate(item.loan_date)}</td>
                    <td>
                      {formatDate(item.return_date)}
                      {item.is_overdue ? (
                        <p className="table-sub" style={{ color: 'var(--danger)', fontWeight: 600 }}>
                          Em atraso
                        </p>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      ) : null}
    </section>
  )
}

export default History
