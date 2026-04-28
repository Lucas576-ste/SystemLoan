import { useEffect, useState } from 'react'
import api from '../services/api'

function MyLoans() {
  const [loans, setLoans] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [returningId, setReturningId] = useState(null)

  const fetchMyLoans = async () => {
    try {
      setLoading(true)
      const { data } = await api.get('/loans/mine')
      setLoans(Array.isArray(data) ? data : [])
      setError('')
    } catch (err) {
      setError(err?.response?.data?.error ?? 'Não foi possível carregar seus empréstimos.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchMyLoans()
  }, [])

  const handleReturn = async (loanId) => {
    try {
      setReturningId(loanId)
      await api.patch(`/loans/${loanId}/return`)
      await fetchMyLoans()
    } catch (err) {
      setError(err?.response?.data?.error ?? 'Não foi possível devolver a ferramenta.')
    } finally {
      setReturningId(null)
    }
  }

  if (loading) return <p className="muted">Carregando empréstimos ativos...</p>

  return (
    <section>
      {error ? <p className="alert-error">{error}</p> : null}

      {loans.length === 0 ? (
        <p className="muted">Você não possui empréstimos ativos no momento.</p>
      ) : (
        <div className="grid-list">
          {loans.map((loan) => (
            <article key={loan.id} className="item-card">
              <h3>{loan.tool_name}</h3>
              <p className="item-desc">{loan.tool_description || 'Sem descrição'}</p>
              <p className="item-meta">Proprietário: {loan.owner_name}</p>
              <p className="item-meta">Emprestado em: {new Date(loan.loan_date).toLocaleString('pt-BR')}</p>

              <button
                type="button"
                className="btn-primary"
                onClick={() => handleReturn(loan.id)}
                disabled={returningId === loan.id}
              >
                {returningId === loan.id ? 'Devolvendo...' : 'Devolver'}
              </button>
            </article>
          ))}
        </div>
      )}
    </section>
  )
}

export default MyLoans
