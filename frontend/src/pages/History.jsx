import { useEffect, useState } from 'react'
import api from '../services/api'

const defaultFilters = {
  status: '',
  date_from: '',
  date_to: '',
  tool: '',
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
      const endpoint = query ? `/loans/history?${query}` : '/loans/history'
      const { data } = await api.get(endpoint)
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

  return (
    <section className="content-card">
      <header className="section-header">
        <h1>Histórico de empréstimos</h1>
        <p>Use os filtros para localizar operações por período, status ou ferramenta.</p>
      </header>

      <form className="filter-grid" onSubmit={handleSubmit}>
        <label className="form-group">
          <span>Status</span>
          <select
            value={filters.status}
            onChange={(e) => setFilters((prev) => ({ ...prev, status: e.target.value }))}
          >
            <option value="">Todos</option>
            <option value="active">Ativo</option>
            <option value="returned">Devolvido</option>
          </select>
        </label>

        <label className="form-group">
          <span>Data inicial</span>
          <input
            type="date"
            value={filters.date_from}
            onChange={(e) => setFilters((prev) => ({ ...prev, date_from: e.target.value }))}
          />
        </label>

        <label className="form-group">
          <span>Data final</span>
          <input
            type="date"
            value={filters.date_to}
            onChange={(e) => setFilters((prev) => ({ ...prev, date_to: e.target.value }))}
          />
        </label>

        <label className="form-group">
          <span>Ferramenta</span>
          <input
            type="text"
            placeholder="Ex.: furadeira"
            value={filters.tool}
            onChange={(e) => setFilters((prev) => ({ ...prev, tool: e.target.value }))}
          />
        </label>

        <button type="submit" className="btn-primary">
          Filtrar
        </button>
      </form>

      {error ? <p className="alert-error">{error}</p> : null}
      {loading ? <p className="muted">Carregando histórico...</p> : null}

      {!loading && !error ? (
        items.length === 0 ? (
          <p className="muted">Nenhum registro encontrado.</p>
        ) : (
          <div className="table-wrap">
            <table className="history-table">
              <thead>
                <tr>
                  <th>Ferramenta</th>
                  <th>Status</th>
                  <th>Empréstimo</th>
                  <th>Devolução</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={item.loan_id}>
                    <td>
                      <strong>{item.tool_name}</strong>
                      <p className="table-sub">{item.tool_description || 'Sem descrição'}</p>
                    </td>
                    <td>{item.status === 'returned' ? 'Devolvido' : 'Ativo'}</td>
                    <td>{new Date(item.loan_date).toLocaleString('pt-BR')}</td>
                    <td>
                      {item.return_date
                        ? new Date(item.return_date).toLocaleString('pt-BR')
                        : 'Em aberto'}
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
