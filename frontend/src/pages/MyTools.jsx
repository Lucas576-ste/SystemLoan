import { useEffect, useState } from 'react'
import api from '../services/api'

const initialForm = { name: '', description: '' }
const MAX_IMAGES = 4

function MyTools() {
  const [tools, setTools] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [editingToolId, setEditingToolId] = useState(null)
  const [form, setForm] = useState(initialForm)
  const [selectedFiles, setSelectedFiles] = useState([])
  const [submitting, setSubmitting] = useState(false)

  const fetchMyTools = async () => {
    try {
      setLoading(true)
      const { data } = await api.get('/tools/mine')
      setTools(Array.isArray(data) ? data : [])
      setError('')
    } catch (err) {
      setError(err?.response?.data?.error ?? 'Não foi possível carregar suas ferramentas.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchMyTools()
  }, [])

  const openCreateModal = () => {
    setEditingToolId(null)
    setForm(initialForm)
    setSelectedFiles([])
    setIsModalOpen(true)
  }

  const openEditModal = (tool) => {
    setEditingToolId(tool.id)
    setForm({ name: tool.name ?? '', description: tool.description ?? '' })
    setSelectedFiles([])
    setIsModalOpen(true)
  }

  const closeModal = () => {
    setIsModalOpen(false)
    setEditingToolId(null)
    setForm(initialForm)
    setSelectedFiles([])
  }

  const handleImagesChange = (event) => {
    const files = Array.from(event.target.files ?? [])
    if (files.length > MAX_IMAGES) {
      setError('Você pode enviar no máximo 4 imagens por ferramenta.')
      event.target.value = ''
      setSelectedFiles([])
      return
    }

    setError('')
    setSelectedFiles(files)
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setError('')

    const name = form.name.trim()
    const description = form.description.trim()
    if (!name) {
      setError('Nome da ferramenta é obrigatório.')
      return
    }
    if (selectedFiles.length > MAX_IMAGES) {
      setError('Você pode enviar no máximo 4 imagens por ferramenta.')
      return
    }

    try {
      setSubmitting(true)
      const formData = new FormData()
      formData.append('name', name)
      formData.append('description', description)
      selectedFiles.forEach((file) => {
        formData.append('images[]', file)
      })

      if (editingToolId) {
        await api.post(`/tools/${editingToolId}`, formData)
      } else {
        await api.post('/tools', formData)
      }

      closeModal()
      await fetchMyTools()
    } catch (err) {
      setError(err?.response?.data?.error ?? 'Não foi possível salvar a ferramenta.')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async (toolId) => {
    const confirmed = window.confirm('Deseja realmente excluir esta ferramenta?')
    if (!confirmed) return

    try {
      await api.delete(`/tools/${toolId}`)
      await fetchMyTools()
    } catch (err) {
      setError(err?.response?.data?.error ?? 'Não foi possível excluir a ferramenta.')
    }
  }

  if (loading) return <p className="muted">Carregando suas ferramentas...</p>

  return (
    <section>
      <div className="section-actions">
        <button type="button" className="btn-primary" onClick={openCreateModal}>
          Cadastrar ferramenta
        </button>
      </div>

      {error ? <p className="alert-error">{error}</p> : null}

      {tools.length === 0 ? (
        <p className="muted">Você ainda não cadastrou ferramentas.</p>
      ) : (
        <div className="grid-list">
          {tools.map((tool) => (
            <article key={tool.id} className="item-card">
              <h3>{tool.name}</h3>
              <p className="item-desc">{tool.description || 'Sem descrição'}</p>

              <div className="inline-actions">
                <button type="button" className="btn-secondary btn-sm" onClick={() => openEditModal(tool)}>
                  Editar
                </button>
                <button type="button" className="btn-danger btn-sm" onClick={() => handleDelete(tool.id)}>
                  Excluir
                </button>
              </div>
            </article>
          ))}
        </div>
      )}

      {isModalOpen ? (
        <div className="modal-backdrop" role="presentation">
          <section className="modal-card" role="dialog" aria-modal="true">
            <h3>{editingToolId ? 'Editar ferramenta' : 'Nova ferramenta'}</h3>

            <form className="auth-form" onSubmit={handleSubmit}>
              <label className="form-group">
                <span>Nome</span>
                <input
                  type="text"
                  value={form.name}
                  onChange={(e) => setForm((prev) => ({ ...prev, name: e.target.value }))}
                />
              </label>

              <label className="form-group">
                <span>Descrição</span>
                <input
                  type="text"
                  value={form.description}
                  onChange={(e) => setForm((prev) => ({ ...prev, description: e.target.value }))}
                />
              </label>

              <label className="form-group">
                <span>Imagens (até 4)</span>
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  multiple
                  onChange={handleImagesChange}
                />
              </label>

              {selectedFiles.length > 0 ? (
                <p className="item-meta">{selectedFiles.length} imagem(ns) selecionada(s).</p>
              ) : null}

              <div className="inline-actions">
                <button type="button" className="btn-secondary" onClick={closeModal}>
                  Cancelar
                </button>
                <button type="submit" className="btn-primary" disabled={submitting}>
                  {submitting ? 'Salvando...' : 'Salvar'}
                </button>
              </div>
            </form>
          </section>
        </div>
      ) : null}
    </section>
  )
}

export default MyTools
