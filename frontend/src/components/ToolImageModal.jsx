import { useCallback, useEffect, useState } from 'react'

const API_BASE_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'
const API_ORIGIN = API_BASE_URL.replace(/\/api\/?$/, '')

function normalizeImageUrls(tool) {
  if (!tool) return []
  const fromUrls = tool.image_urls
  if (Array.isArray(fromUrls)) {
    return fromUrls
      .filter((u) => typeof u === 'string' && u.trim() !== '')
      .map((u) => (u.startsWith('/uploads/') ? `${API_ORIGIN}${u}` : u))
  }
  const fromImages = tool.images
  if (Array.isArray(fromImages)) {
    return fromImages
      .filter((u) => typeof u === 'string' && u.trim() !== '')
      .map((u) => (u.startsWith('/uploads/') ? `${API_ORIGIN}${u}` : u))
  }
  return []
}

export default function ToolImageModal({
  open,
  onClose,
  tool,
  available,
  borrowing,
  onBorrow,
}) {
  const [index, setIndex] = useState(0)
  const [shareHint, setShareHint] = useState('')

  const name = tool?.name ?? 'Ferramenta'
  const imageUrls = normalizeImageUrls(tool)
  const count = imageUrls.length

  const goPrev = useCallback(() => {
    if (count <= 1) return
    setIndex((i) => (i <= 0 ? count - 1 : i - 1))
  }, [count])

  const goNext = useCallback(() => {
    if (count <= 1) return
    setIndex((i) => (i >= count - 1 ? 0 : i + 1))
  }, [count])

  useEffect(() => {
    if (!open) return undefined
    const onKey = (e) => {
      if (e.key === 'Escape') onClose()
      if (e.key === 'ArrowLeft') goPrev()
      if (e.key === 'ArrowRight') goNext()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [open, onClose, goPrev, goNext])

  const handleBackdropClick = (e) => {
    if (e.target === e.currentTarget) onClose()
  }

  const handleShare = async () => {
    const text = `Ferramenta: ${name}`
    const url = typeof window !== 'undefined' ? window.location.href : ''
    try {
      if (navigator.share) {
        await navigator.share({ title: name, text, url })
        setShareHint('')
      } else if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(`${text}\n${url}`)
        setShareHint('Link copiado para a área de transferência.')
        setTimeout(() => setShareHint(''), 2500)
      }
    } catch (err) {
      if (err?.name === 'AbortError') return
      setShareHint('Não foi possível compartilhar.')
      setTimeout(() => setShareHint(''), 2500)
    }
  }

  const handleBorrowClick = async () => {
    if (!tool?.id || !onBorrow) return
    await onBorrow(tool.id)
  }

  if (!open || !tool) return null

  const titleId = 'tool-image-modal-title'

  return (
    <div
      className="tool-image-modal-backdrop"
      role="presentation"
      onClick={handleBackdropClick}
    >
      <section
        className="tool-image-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        onClick={(e) => e.stopPropagation()}
      >
        <header className="tool-image-modal-header">
          <h2 id={titleId} className="tool-image-modal-title">
            Imagens da {name}
          </h2>
          <button
            type="button"
            className="tool-image-modal-close"
            onClick={onClose}
            aria-label="Fechar"
          >
            ×
          </button>
        </header>

        <div className="tool-image-modal-body">
          {count === 0 ? (
            <p className="tool-image-modal-empty muted">
              Nenhuma imagem cadastrada para esta ferramenta.
            </p>
          ) : (
            <div className="tool-image-carousel">
              <div className="tool-image-carousel-viewport">
                {count > 1 ? (
                  <button
                    type="button"
                    className="tool-image-carousel-arrow tool-image-carousel-arrow-left"
                    onClick={goPrev}
                    aria-label="Imagem anterior"
                  >
                    ‹
                  </button>
                ) : null}
                <div className="tool-image-carousel-frame">
                  <img
                    src={imageUrls[index]}
                    alt={`${name} — imagem ${index + 1} de ${count}`}
                    className="tool-image-carousel-img"
                  />
                </div>
                {count > 1 ? (
                  <button
                    type="button"
                    className="tool-image-carousel-arrow tool-image-carousel-arrow-right"
                    onClick={goNext}
                    aria-label="Próxima imagem"
                  >
                    ›
                  </button>
                ) : null}
              </div>
              {count > 1 ? (
                <div className="tool-image-carousel-dots" role="tablist" aria-label="Seletor de imagem">
                  {imageUrls.map((_, i) => (
                    <button
                      key={i}
                      type="button"
                      className={`tool-image-carousel-dot${i === index ? ' active' : ''}`}
                      onClick={() => setIndex(i)}
                      aria-label={`Imagem ${i + 1} de ${count}`}
                      aria-current={i === index ? 'true' : undefined}
                    />
                  ))}
                </div>
              ) : null}
            </div>
          )}
        </div>

        <footer className="tool-image-modal-footer">
          {shareHint ? <p className="tool-image-modal-hint">{shareHint}</p> : null}
          <div className="tool-image-modal-actions">
            <button type="button" className="btn-tool-modal-secondary" onClick={handleShare}>
              Compartilhar
            </button>
            <button
              type="button"
              className="btn-primary"
              onClick={handleBorrowClick}
              disabled={!available || borrowing}
            >
              {borrowing ? 'Emprestando...' : 'Emprestar'}
            </button>
          </div>
        </footer>
      </section>
    </div>
  )
}
