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
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {agents.map((agent) => {
              const badges = getFeatureBadges(agent);
              const suite = suites.find((s) => s.id === agent.suite_id);
              const isActive = agent.is_active;
              const canDelete = !isActive || (isActive && new Date(agent.created_at).getTime() + 60 * 24 * 60 * 60 * 1000 < Date.now());
              const daysSinceCreation = Math.floor((Date.now() - new Date(agent.created_at).getTime()) / (1000 * 60 * 60 * 24));
              const daysRemaining = isActive ? Math.max(0, 60 - daysSinceCreation) : 0;

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
                      className={`toggle ${isActive ? 'on' : ''}`}
                      onClick={async (e) => {
                        e.stopPropagation();
                        try {
                          await axios.put(`/api/agents/${agent.id}`, {
                            is_active: !isActive,
                          });
                          fetchData();
                        } catch (error) {
                          console.error('Failed to toggle agent:', error);
                          alert('Failed to update agent status');
                        }
                      }}
                    ></span>
                  </td>
                  <td>
                    {canDelete ? (
                      <button
                        className="btn btn-sm btn-outline"
                        onClick={async (e) => {
                          e.stopPropagation();
                          if (confirm('Are you sure you want to delete this agent? This action cannot be undone.')) {
                            try {
                              await axios.delete(`/api/agents/${agent.id}`);
                              fetchData();
                            } catch (error) {
                              console.error('Failed to delete agent:', error);
                              alert(error.response?.data?.message || 'Failed to delete agent');
                            }
                          }
                        }}
                        style={{ fontSize: '11px', padding: '4px 8px', color: '#ef4444' }}
                      >
                        Delete
                      </button>
                    ) : (
                      <div style={{ fontSize: '10px', color: '#6b7280' }}>
                        {daysRemaining > 0 ? `${daysRemaining} days` : 'Can delete'}
                      </div>
                    )}
                    {!agent.archived_at && (
                      <button
                        className="btn btn-sm btn-outline"
                        onClick={async (e) => {
                          e.stopPropagation();
                          if (confirm('Archive this agent? It will be deactivated and hidden from users.')) {
                            try {
                              await axios.post(`/api/agents/${agent.id}/archive`);
                              fetchData();
                            } catch (error) {
                              console.error('Failed to archive agent:', error);
                              alert(error.response?.data?.message || 'Failed to archive agent');
                            }
                          }
                        }}
                        style={{ fontSize: '11px', padding: '4px 8px', marginLeft: '4px' }}
                      >
                        Archive
                      </button>
                    )}
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

