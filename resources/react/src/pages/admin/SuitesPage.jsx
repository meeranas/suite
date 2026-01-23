import React, { useState, useEffect } from 'react';
import axios from 'axios';

export default function SuitesPage() {
  const [suites, setSuites] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [tiers, setTiers] = useState([]);
  const [formData, setFormData] = useState({
    name: '',
    description: '',
    status: 'hidden',
    subscription_tiers: [],
  });

  useEffect(() => {
    fetchTiers();
  }, []);

  useEffect(() => {
    fetchSuites();
  }, []);

  const fetchTiers = async () => {
    try {
      const response = await axios.get('/api/admin/subscription-tiers');
      setTiers(response.data);
    } catch (error) {
      console.error('Failed to fetch tiers:', error);
    }
  };

  const fetchSuites = async () => {
    try {
      const response = await axios.get('/api/suites');
      setSuites(response.data);
    } catch (error) {
      console.error('Failed to fetch suites:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      await axios.post('/api/suites', formData);
      setShowModal(false);
      setFormData({ name: '', description: '', status: 'hidden', subscription_tiers: [] });
      fetchSuites();
    } catch (error) {
      console.error('Failed to create suite:', error);
    }
  };

  if (loading) {
    return (
      <div className="panel" style={{ textAlign: 'center', padding: '40px' }}>
        <div style={{ color: 'var(--text-muted)' }}>Loading...</div>
      </div>
    );
  }

  return (
    <div>
      <section className="panel">
        <div className="panel-header">
          <div className="panel-title">Suites</div>
          <button
            onClick={() => setShowModal(true)}
            className="btn btn-outline"
            style={{ fontSize: '11px' }}
          >
            + New Suite
          </button>
        </div>
        <div className="panel-sub">
          Grouping of agents into productised bundles for the front-end.
        </div>
        <table className="admin-table">
          <thead>
            <tr>
              <th>Suite</th>
              <th>Description</th>
              <th>Agents</th>
              <th>Enabled</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {suites.map((suite) => {
              const isActive = suite.status === 'active';
              const canDelete = !isActive || (isActive && new Date(suite.created_at).getTime() + 60 * 24 * 60 * 60 * 1000 < Date.now());
              const daysSinceCreation = Math.floor((Date.now() - new Date(suite.created_at).getTime()) / (1000 * 60 * 60 * 24));
              const daysRemaining = isActive ? Math.max(0, 60 - daysSinceCreation) : 0;

              return (
                <tr key={suite.id}>
                  <td style={{ fontWeight: 500 }}>{suite.name}</td>
                  <td style={{ fontSize: '11px', color: 'var(--text-muted)' }}>
                    {suite.description || '-'}
                  </td>
                  <td>{suite.agents?.length || 0}</td>
                  <td>
                    <span
                      className={`toggle ${isActive ? 'on' : ''}`}
                      onClick={async (e) => {
                        e.stopPropagation();
                        try {
                          await axios.put(`/api/suites/${suite.id}`, {
                            status: isActive ? 'hidden' : 'active',
                          });
                          fetchSuites();
                        } catch (error) {
                          console.error('Failed to toggle suite:', error);
                          alert('Failed to update suite status');
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
                          if (confirm('Are you sure you want to delete this suite? This action cannot be undone.')) {
                            try {
                              await axios.delete(`/api/suites/${suite.id}`);
                              fetchSuites();
                            } catch (error) {
                              console.error('Failed to delete suite:', error);
                              alert(error.response?.data?.message || 'Failed to delete suite');
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
                    {!suite.archived_at && (
                      <button
                        className="btn btn-sm btn-outline"
                        onClick={async (e) => {
                          e.stopPropagation();
                          if (confirm('Archive this suite? It will be hidden from users.')) {
                            try {
                              await axios.post(`/api/suites/${suite.id}/archive`);
                              fetchSuites();
                            } catch (error) {
                              console.error('Failed to archive suite:', error);
                              alert(error.response?.data?.message || 'Failed to archive suite');
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
      </section>

      {showModal && (
        <div
          style={{
            position: 'fixed',
            inset: 0,
            zIndex: 50,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            background: 'rgba(0, 0, 0, 0.5)',
          }}
        >
          <div
            className="panel"
            style={{
              width: '100%',
              maxWidth: '500px',
              margin: '20px',
            }}
          >
            <div className="panel-title" style={{ marginBottom: '16px' }}>
              Create Suite
            </div>
            <form onSubmit={handleSubmit}>
              <div className="field-label">Name</div>
              <input
                type="text"
                value={formData.name}
                onChange={(e) =>
                  setFormData({ ...formData, name: e.target.value })
                }
                className="input"
                required
              />
              <div className="field-label">Description</div>
              <textarea
                value={formData.description}
                onChange={(e) =>
                  setFormData({ ...formData, description: e.target.value })
                }
                className="input"
                rows="3"
              />
              <div className="field-label">Status</div>
              <select
                value={formData.status}
                onChange={(e) =>
                  setFormData({ ...formData, status: e.target.value })
                }
                className="input"
              >
                <option value="hidden">Hidden</option>
                <option value="active">Active</option>
                <option value="archived">Archived</option>
              </select>
              <div className="field-label">Subscription Tiers</div>
              <div style={{ marginTop: '6px' }}>
                {tiers.map((tier) => (
                  <label
                    key={tier.id}
                    style={{
                      display: 'flex',
                      alignItems: 'center',
                      gap: '8px',
                      marginBottom: '6px',
                      fontSize: '12px',
                    }}
                  >
                    <input
                      type="checkbox"
                      checked={formData.subscription_tiers.includes(tier.id)}
                      onChange={(e) => {
                        const newTiers = e.target.checked
                          ? [...formData.subscription_tiers, tier.id]
                          : formData.subscription_tiers.filter((t) => t !== tier.id);
                        setFormData({ ...formData, subscription_tiers: newTiers });
                      }}
                      style={{ width: '16px', height: '16px' }}
                    />
                    <span>{tier.name}</span>
                  </label>
                ))}
              </div>
              <div className="note">Leave empty to allow all tiers</div>
              <div
                style={{
                  marginTop: '16px',
                  display: 'flex',
                  gap: '8px',
                  justifyContent: 'flex-end',
                }}
              >
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="btn btn-outline"
                >
                  Cancel
                </button>
                <button type="submit" className="btn btn-primary">
                  Create
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

