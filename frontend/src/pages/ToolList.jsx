import { useEffect, useState } from 'react'
import LoanModal from '../components/LoanModal'
import ToolImageModal from '../components/ToolImageModal'
import { useAuth } from '../hooks/useAuth'
import api from '../services/api'

function isAvailable(value) {
  return value === true || value === 1 || value === '1' || value === 't' || value === 'true'
}

function ToolList() {
  const { user } = useAuth()
  const [tools, setTools] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [imageTool, setImageTool] = useState(null)
  const [loanTool, setLoanTool] = useState(null)

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

  const handleLoanSuccess = () => {
    setLoanTool(null)
    fetchTools()
  }

  if (loading) return <p className="muted">Carregando ferramentas...</p>

  return (
    <section>
      <ToolImageModal
        open={Boolean(imageTool)}
        onClose={() => setImageTool(null)}
        tool={imageTool}
      />

      {loanTool ? (
        <LoanModal
          tool={loanTool}
          onClose={() => setLoanTool(null)}
          onSuccess={handleLoanSuccess}
        />
      ) : null}

      {error ? <p className="alert-error">{error}</p> : null}

      {tools.length === 0 ? (
        <p className="muted">Nenhuma ferramenta cadastrada.</p>
      ) : (
        <div className="grid-list">
          {tools.map((tool) => {
            const available = isAvailable(tool.is_available)
            const isOwner = parseInt(tool.user_id) === parseInt(user?.id)
            const hasImages = Array.isArray(tool.image_urls) && tool.image_urls.length > 0

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

                {hasImages ? (
                  <button
                    type="button"
                    className="tool-card-link"
                    onClick={() => setImageTool(tool)}
                  >
                    Ver imagens ({tool.image_urls.length})
                  </button>
                ) : null}

                {isOwner ? (
                  <button
                    type="button"
                    className="btn-primary"
                    onClick={() => setLoanTool(tool)}
                    disabled={!available}
                  >
                    Emprestar
                  </button>
                ) : null}
              </article>
            )
          })}
        </div>
      )}
    </section>
  )
}

export default ToolList
