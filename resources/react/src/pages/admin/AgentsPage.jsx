import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';

export default function AgentsPage() {
  const navigate = useNavigate();
  const [agents, setAgents] = useState([]);
  const [suites, setSuites] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    try {
      const [suitesRes, agentsRes] = await Promise.all([
        axios.get('/api/suites'),
        axios.get('/api/agents'),
      ]);
      setSuites(suitesRes.data);
      setAgents(agentsRes.data);
    } catch (error) {
      console.error('Failed to fetch data:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (agentId) => {
    if (!confirm('Are you sure you want to delete this agent?')) return;
    try {
      await axios.delete(`/api/agents/${agentId}`);
      fetchData();
    } catch (error) {
      console.error('Failed to delete agent:', error);
      alert('Failed to delete agent');
    }
  };

  const getFeatureBadges = (agent) => {
    const badges = [];
    if (agent.enable_rag) badges.push('Docs');
    if (agent.enable_web_search) badges.push('Web');
    if (agent.external_api_configs?.length > 0) badges.push('APIs');
    return badges;
  };

  if (loading) {
    return (
      <div className="panel" style={{ textAlign: 'center', padding: '40px' }}>
        <div style={{ color: 'var(--text-muted)' }}>Loading...</div>
      </div>
    );
  }

  return (
    <div className="grid-two">
      {/* Existing Agents */}
      <section className="panel">
        <div className="panel-header">
          <div className="panel-title">Existing Agents</div>
          <button
            onClick={() => navigate('/admin/agents/create')}
            className="btn btn-outline"
            style={{ fontSize: '11px' }}
          >
            + New Agent
          </button>
        </div>
        <div className="panel-sub">
          High-level list of agents with suite, model, sources and status.
        </div>
        <table className="admin-table">
          <thead>
            <tr>
              <th>Agent</th>
              <th>Suite</th>
              <th>Model</th>
              <th>Sources</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {agents.map((agent) => {
              const badges = getFeatureBadges(agent);
              const suite = suites.find((s) => s.id === agent.suite_id);
              return (
                <tr
                  key={agent.id}
                  style={{ cursor: 'pointer' }}
                  onClick={() => navigate(`/admin/agents/${agent.id}/edit`)}
                >
                  <td>
                    <div style={{ fontWeight: 500 }}>{agent.name}</div>
                    {agent.description && (
                      <div
                        style={{
                          fontSize: '10px',
                          color: 'var(--text-muted)',
                          marginTop: '2px',
                        }}
                      >
                        {agent.description}
                      </div>
                    )}
                  </td>
                  <td>{suite?.name || '-'}</td>
                  <td>
                    {agent.model_provider} – {agent.model_name}
                  </td>
                  <td>
                    {badges.map((badge, idx) => (
                      <span key={idx} className="tag">
                        {badge}
                      </span>
                    ))}
                    {badges.length === 0 && (
                      <span style={{ fontSize: '10px', color: 'var(--text-muted)' }}>
                        None
                      </span>
                    )}
                  </td>
                  <td>
                    <span
                      className={`toggle ${agent.is_active ? 'on' : ''}`}
                      onClick={(e) => {
                        e.stopPropagation();
                        // Toggle status
                      }}
                    ></span>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
        {agents.length === 0 && (
          <div style={{ textAlign: 'center', padding: '20px', color: 'var(--text-muted)' }}>
            No agents found. Create your first agent!
          </div>
        )}
      </section>

      {/* Quick Stats */}
      <section className="panel">
        <div className="panel-header">
          <div className="panel-title">Agent Overview</div>
        </div>
        <div className="metric-grid">
          <div className="metric-card">
            <div className="metric-label">Total Agents</div>
            <div className="metric-value">{agents.length}</div>
            <div className="metric-foot">Across all suites</div>
          </div>
          <div className="metric-card">
            <div className="metric-label">Active Agents</div>
            <div className="metric-value">
              {agents.filter((a) => a.is_active).length}
            </div>
            <div className="metric-foot">Currently enabled</div>
          </div>
          <div className="metric-card">
            <div className="metric-label">Suites</div>
            <div className="metric-value">{suites.length}</div>
            <div className="metric-foot">With agents</div>
          </div>
        </div>
      </section>
    </div>
  );
}

