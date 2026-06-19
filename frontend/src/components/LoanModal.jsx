import { useState } from 'react'
import api from '../services/api'

const initialForm = { borrower_name: '', borrower_phone: '', return_date: '' }

const todayIso = new Date().toISOString().split('T')[0]

export default function LoanModal({ tool, onClose, onSuccess }) {
  const [form, setForm] = useState(initialForm)
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  if (!tool) return null

  const set = (field) => (e) => setForm((prev) => ({ ...prev, [field]: e.target.value }))

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')

    const borrower_name = form.borrower_name.trim()
    const return_date = form.return_date

    if (!borrower_name) {
      setError('Informe o nome de quem vai pegar a ferramenta.')
      return
    }
    if (!return_date) {
      setError('Informe a data prevista de devolução.')
      return
    }

    try {
      setSubmitting(true)
      const phone = form.borrower_phone.trim()
      await api.post('/loans', {
        tool_id: tool.id,
        borrower_name,
        ...(phone ? { borrower_phone: phone } : {}),
        return_date,
      })
      onSuccess()
    } catch (err) {
      setError(err?.response?.data?.error ?? 'Não foi possível registrar o empréstimo.')
    } finally {
      setSubmitting(false)
    }
  }

  const handleBackdrop = (e) => {
    if (e.target === e.currentTarget) onClose()
  }

  return (
    <div className="modal-backdrop" role="presentation" onClick={handleBackdrop}>
      <section className="modal-card" role="dialog" aria-modal="true" aria-labelledby="loan-modal-title">
        <div className="modal-header">
          <div>
            <h3 className="modal-title" id="loan-modal-title">Registrar empréstimo</h3>
            <p className="modal-subtitle">{tool.name}</p>
          </div>
          <button type="button" className="modal-close-btn" onClick={onClose} aria-label="Fechar">
            ×
          </button>
        </div>

        <div className="modal-body">
          <form className="auth-form" onSubmit={handleSubmit}>
            <label className="form-group">
              <span>
                Nome do tomador <span className="required">*</span>
              </span>
              <input
                type="text"
                placeholder="Ex.: João Silva"
                value={form.borrower_name}
                onChange={set('borrower_name')}
                autoFocus
                maxLength={255}
              />
            </label>

            <label className="form-group">
              <span>Telefone / WhatsApp</span>
              <input
                type="tel"
                placeholder="Ex.: (11) 99999-9999"
                value={form.borrower_phone}
                onChange={set('borrower_phone')}
                maxLength={20}
              />
            </label>

            <label className="form-group">
              <span>
                Data de devolução <span className="required">*</span>
              </span>
              <input
                type="date"
                value={form.return_date}
                min={todayIso}
                onChange={set('return_date')}
              />
            </label>

            {error ? <p className="alert-error">{error}</p> : null}

            <div className="inline-actions">
              <button type="button" className="btn-secondary" onClick={onClose}>
                Cancelar
              </button>
              <button type="submit" className="btn-primary" disabled={submitting}>
                {submitting ? 'Registrando...' : 'Confirmar empréstimo'}
              </button>
            </div>
          </form>
        </div>
      </section>
    </div>
  )
}
