import { useEffect, useMemo, useState } from 'react'
import api from '../services/api'
import MyLoans from './MyLoans'
import MyTools from './MyTools'
import ToolList from './ToolList'

function SummaryCards() {
  const [data, setData] = useState(null)

  useEffect(() => {
    let mounted = true

    const fetchSummary = async () => {
      try {
        const [toolsRes, loansRes] = await Promise.all([
          api.get('/tools/mine'),
          api.get('/loans/mine'),
        ])
        if (!mounted) return
        const tools = Array.isArray(toolsRes.data) ? toolsRes.data : []
        const loans = Array.isArray(loansRes.data) ? loansRes.data : []
        setData({
          totalTools: tools.length,
          activeLoans: loans.length,
          overdueLoans: loans.filter((l) => l.is_overdue).length,
        })
      } catch {
        // summary é não-crítico; falha silenciosa
      }
    }

    fetchSummary()
    return () => {
      mounted = false
    }
  }, [])

  if (!data) return null

  return (
    <div className="summary-cards">
      <div className="summary-card summary-card--tools">
        <span className="summary-label">Minhas ferramentas</span>
        <span className="summary-value">{data.totalTools}</span>
      </div>
      <div className="summary-card summary-card--active">
        <span className="summary-label">Empréstimos ativos</span>
        <span className="summary-value">{data.activeLoans}</span>
      </div>
      <div className="summary-card summary-card--overdue">
        <span className="summary-label">Em atraso</span>
        <span className="summary-value">{data.overdueLoans}</span>
      </div>
    </div>
  )
}

function Dashboard() {
  const tabs = useMemo(
    () => [
      { id: 'tools', label: 'Lista de ferramentas' },
      { id: 'mine', label: 'Minhas ferramentas' },
      { id: 'loans', label: 'Meus empréstimos' },
    ],
    [],
  )
  const [activeTab, setActiveTab] = useState('tools')

  const renderTab = () => {
    if (activeTab === 'mine') return <MyTools />
    if (activeTab === 'loans') return <MyLoans />
    return <ToolList />
  }

  return (
    <section className="content-card">
      <header className="section-header">
        <h1>Dashboard</h1>
        <p>Gerencie ferramentas, empréstimos ativos e devoluções.</p>
      </header>

      <SummaryCards />

      <div className="tabs">
        {tabs.map((tab) => (
          <button
            key={tab.id}
            type="button"
            className={activeTab === tab.id ? 'tab-btn active' : 'tab-btn'}
            onClick={() => setActiveTab(tab.id)}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <div className="tab-panel">{renderTab()}</div>
    </section>
  )
}

export default Dashboard
