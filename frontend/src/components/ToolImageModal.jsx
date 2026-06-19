import { useCallback, useEffect, useState } from 'react'

const rawApiUrl = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000').trim()
const apiUrlWithProtocol = /^https?:\/\//i.test(rawApiUrl) ? rawApiUrl : `https://${rawApiUrl}`
const API_ORIGIN = apiUrlWithProtocol.replace(/\/+$/, '').replace(/\/api$/i, '')

function normalizeImageUrls(tool) {
  if (!tool) return []
  const urls = Array.isArray(tool.image_urls) ? tool.image_urls : (Array.isArray(tool.images) ? tool.images : [])
  return urls
    .filter((u) => typeof u === 'string' && u.trim() !== '')
    .map((u) => (u.startsWith('/uploads/') ? `${API_ORIGIN}${u}` : u))
}

export default function ToolImageModal({ open, onClose, tool }) {
  const [index, setIndex] = useState(0)

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
    if (!open) {
      setIndex(0)
      return undefined
    }
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

  if (!open || !tool) return null

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
        aria-labelledby="img-modal-title"
        onClick={(e) => e.stopPropagation()}
      >
        <header className="tool-image-modal-header">
          <h2 id="img-modal-title" className="tool-image-modal-title">
            {name}
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
            <p className="tool-image-modal-empty">Nenhuma imagem cadastrada.</p>
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

        <div className="tool-image-modal-footer">
          <button type="button" className="btn-secondary btn-sm" onClick={onClose}>
            Fechar
          </button>
        </div>
      </section>
    </div>
  )
}
