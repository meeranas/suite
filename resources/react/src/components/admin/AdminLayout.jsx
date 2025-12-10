import React, { useState, useEffect } from 'react';
import { Routes, Route, NavLink, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../services/auth';
import SuitesPage from '../../pages/admin/SuitesPage';
import AgentsPage from '../../pages/admin/AgentsPage';
import AgentFormPage from '../../pages/admin/AgentFormPage';
import WorkflowsPage from '../../pages/admin/WorkflowsPage';
import WorkflowFormPage from '../../pages/admin/WorkflowFormPage';
import ExternalApiConfigsPage from '../../pages/admin/ExternalApiConfigsPage';
import UsagePage from '../../pages/admin/UsagePage';
import '../../styles/admin.css';

export default function AdminLayout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [theme, setTheme] = useState('light');

  useEffect(() => {
    const savedTheme = localStorage.getItem('admin-theme') || 'light';
    setTheme(savedTheme);
    document.documentElement.setAttribute('data-theme', savedTheme);
  }, []);

  const toggleTheme = () => {
    const newTheme = theme === 'light' ? 'dark' : 'light';
    setTheme(newTheme);
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('admin-theme', newTheme);
  };

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  const isActive = (path) => {
    return location.pathname.startsWith(path);
  };

  const getPageTitle = () => {
    if (location.pathname.includes('/agents')) return 'Agents';
    if (location.pathname.includes('/suites')) return 'Suites & Tiers';
    if (location.pathname.includes('/workflows')) return 'Workflows';
    if (location.pathname.includes('/api-configs')) return 'AI Providers & APIs';
    if (location.pathname.includes('/usage')) return 'Usage & Cost';
    return 'Dashboard';
  };

  const getPageSubtitle = () => {
    if (location.pathname.includes('/agents')) return 'Configure agents, models, APIs, IAM & HTBS tests.';
    if (location.pathname.includes('/suites')) return 'Group agents into suites and attach them to plans.';
    if (location.pathname.includes('/workflows')) return 'Define multi-agent chains per user role.';
    if (location.pathname.includes('/api-configs')) return 'Register LLM providers and external data APIs once.';
    if (location.pathname.includes('/usage')) return 'Monitor usage, HTBS runs and cost per suite / agent.';
    return 'High-level view of usage, IAM and agents.';
  };

  return (
    <div className="admin-shell">
      {/* Sidebar */}
      <aside className="admin-sidebar">
        <div className="sidebar-top">
          <div className="admin-logo">AI Hub Admin</div>
          <div className="admin-logo-sub">
            Configure agents, data sources, workflows & access for the agent hub.
          </div>

          <div className="nav-section-label">Core</div>
          <NavLink
            to="/admin/agents"
            className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}
          >
            Agents
          </NavLink>
          <NavLink
            to="/admin/suites"
            className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}
          >
            Suites & Tiers
          </NavLink>
          <NavLink
            to="/admin/workflows"
            className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}
          >
            Workflows
          </NavLink>

          <div className="nav-section-label" style={{ marginTop: '18px' }}>Integrations</div>
          <NavLink
            to="/admin/api-configs"
            className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}
          >
            AI Providers & APIs
          </NavLink>

          <div className="nav-section-label" style={{ marginTop: '18px' }}>Governance</div>
          <NavLink
            to="/admin/usage"
            className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}
          >
            Usage & Cost
          </NavLink>
        </div>
        <div className="sidebar-footer">
          <div style={{ marginBottom: '8px', fontSize: '11px', color: 'var(--text-muted)' }}>
            {user?.email}
          </div>
          <button
            onClick={handleLogout}
            className="btn btn-outline"
            style={{ width: '100%', fontSize: '11px' }}
          >
            Logout
          </button>
        </div>
      </aside>

      {/* Main Content */}
      <main className="admin-main">
        <header className="topbar">
          <div>
            <div className="top-title">{getPageTitle()}</div>
            <div className="top-sub">{getPageSubtitle()}</div>
          </div>
          <div className="top-right">
            <div className="pill">Admin</div>
            <button onClick={toggleTheme} className="btn btn-outline">
              {theme === 'light' ? 'Dark' : 'Light'}
            </button>
          </div>
        </header>

        <div className="page-content">
          <Routes>
            <Route path="suites" element={<SuitesPage />} />
            <Route path="agents" element={<AgentsPage />} />
            <Route path="agents/create" element={<AgentFormPage />} />
            <Route path="agents/create/:suiteId" element={<AgentFormPage />} />
            <Route path="agents/:agentId/edit" element={<AgentFormPage />} />
            <Route path="workflows" element={<WorkflowsPage />} />
            <Route path="workflows/create" element={<WorkflowFormPage />} />
            <Route path="workflows/create/:suiteId" element={<WorkflowFormPage />} />
            <Route path="workflows/:workflowId/edit" element={<WorkflowFormPage />} />
            <Route path="api-configs" element={<ExternalApiConfigsPage />} />
            <Route path="usage" element={<UsagePage />} />
          </Routes>
        </div>
      </main>
    </div>
  );
}

