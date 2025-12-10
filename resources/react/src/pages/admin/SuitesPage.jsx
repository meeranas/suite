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
            </tr>
          </thead>
          <tbody>
            {suites.map((suite) => (
              <tr key={suite.id}>
                <td style={{ fontWeight: 500 }}>{suite.name}</td>
                <td style={{ fontSize: '11px', color: 'var(--text-muted)' }}>
                  {suite.description || '-'}
                </td>
                <td>{suite.agents?.length || 0}</td>
                <td>
                  <span
                    className={`toggle ${suite.status === 'active' ? 'on' : ''}`}
                  ></span>
                </td>
              </tr>
            ))}
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

