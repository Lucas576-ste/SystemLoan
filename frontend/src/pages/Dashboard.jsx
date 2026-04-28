import { useMemo, useState } from 'react'
import MyLoans from './MyLoans'
import MyTools from './MyTools'
import ToolList from './ToolList'

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
