import { useEffect, useState } from 'react'
import api from '../services/api'

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('pt-BR')
}

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
      setError(err?.response?.data?.error ?? 'Não foi possível carregar os empréstimos.')
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
      setError(err?.response?.data?.error ?? 'Não foi possível confirmar a devolução.')
    } finally {
      setReturningId(null)
    }
  }

  if (loading) return <p className="muted">Carregando empréstimos ativos...</p>

  return (
    <section>
      {error ? <p className="alert-error">{error}</p> : null}

      {loans.length === 0 ? (
        <p className="muted">Nenhuma ferramenta sua está emprestada no momento.</p>
      ) : (
        <div className="grid-list">
          {loans.map((loan) => (
            <article key={loan.id} className="item-card">
              <div className="item-header">
                <h3>{loan.tool_name}</h3>
                {loan.is_overdue ? (
                  <span className="badge-overdue">Em atraso</span>
                ) : null}
              </div>

              <p className="item-desc">{loan.tool_description || 'Sem descrição'}</p>

              <div className="loan-info">
                <div className="loan-info-row">
                  <span className="loan-info-label">Tomador:</span>
                  <span className="loan-info-value">{loan.borrower_name}</span>
                </div>
                {loan.borrower_phone ? (
                  <div className="loan-info-row">
                    <span className="loan-info-label">Telefone:</span>
                    <span className="loan-info-value">{loan.borrower_phone}</span>
                  </div>
                ) : null}
                <div className="loan-info-row">
                  <span className="loan-info-label">Emprestado em:</span>
                  <span className="loan-info-value">{formatDate(loan.loan_date)}</span>
                </div>
                <div className="loan-info-row">
                  <span className="loan-info-label">Devolver até:</span>
                  <span className="loan-info-value" style={loan.is_overdue ? { color: 'var(--danger)', fontWeight: 700 } : {}}>
                    {formatDate(loan.return_date)}
                  </span>
                </div>
              </div>

              <button
                type="button"
                className="btn-primary"
                onClick={() => handleReturn(loan.id)}
                disabled={returningId === loan.id}
              >
                {returningId === loan.id ? 'Confirmando...' : 'Confirmar devolução'}
              </button>
            </article>
          ))}
        </div>
      )}
    </section>
  )
}

export default MyLoans
