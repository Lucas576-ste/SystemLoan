import { useEffect, useState } from 'react'
import ToolImageModal from '../components/ToolImageModal'
import api from '../services/api'

function isToolAvailable(value) {
  return value === true || value === 1 || value === '1' || value === 't' || value === 'true'
}

function ToolList() {
  const [tools, setTools] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [submittingId, setSubmittingId] = useState(null)
  const [modalTool, setModalTool] = useState(null)

  const fetchTools = async () => {
    try {
      setLoading(true)
      const { data } = await api.get('/tools')
      setTools(Array.isArray(data) ? data : [])
      setError('')
    } catch (err) {
      setError(err?.response?.data?.error ?? 'Não foi possível carregar as ferramentas.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchTools()
  }, [])

  const handleBorrow = async (toolId) => {
    try {
      setSubmittingId(toolId)
      await api.post('/loans', { tool_id: toolId })
      await fetchTools()
      setModalTool((prev) => (prev?.id === toolId ? null : prev))
    } catch (err) {
      setError(err?.response?.data?.error ?? 'Não foi possível realizar o empréstimo.')
    } finally {
      setSubmittingId(null)
    }
  }

  if (loading) return <p className="muted">Carregando ferramentas...</p>

  return (
    <section>
      <ToolImageModal
        key={modalTool?.id ?? 'tool-modal'}
        open={Boolean(modalTool)}
        onClose={() => setModalTool(null)}
        tool={modalTool}
        available={modalTool ? isToolAvailable(modalTool.is_available) : false}
        borrowing={modalTool ? submittingId === modalTool.id : false}
        onBorrow={handleBorrow}
      />
      {error ? <p className="alert-error">{error}</p> : null}
      {tools.length === 0 ? (
        <p className="muted">Nenhuma ferramenta cadastrada.</p>
      ) : (
        <div className="grid-list">
          {tools.map((tool) => {
            const available = isToolAvailable(tool.is_available)
            return (
              <article key={tool.id} className="item-card">
                <div className="item-header">
                  <h3>{tool.name}</h3>
                  <span className={available ? 'status-pill ok' : 'status-pill off'}>
                    {available ? 'Disponível' : 'Indisponível'}
                  </span>
                </div>

                <p className="item-desc">{tool.description || 'Sem descrição'}</p>
                <p className="item-meta">Dono: {tool.owner_name}</p>

                <button
                  type="button"
                  className="tool-card-link"
                  onClick={() => setModalTool(tool)}
                >
                  Visualizar imagem
                </button>

                <button
                  type="button"
                  className="btn-primary"
                  onClick={() => handleBorrow(tool.id)}
                  disabled={!available || submittingId === tool.id}
                >
                  {submittingId === tool.id ? 'Emprestando...' : 'Emprestar'}
                </button>
              </article>
            )
          })}
        </div>
      )}
    </section>
  )
}

export default ToolList
