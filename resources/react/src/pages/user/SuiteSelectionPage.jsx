import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import { useAuth } from '../../services/auth';

export default function SuiteSelectionPage() {
  const navigate = useNavigate();
  const { user } = useAuth();
  const [suites, setSuites] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchSuites();
  }, []);

  const fetchSuites = async () => {
    try {
      const response = await axios.get('/api/suites');
      setSuites(response.data || []);
    } catch (error) {
      console.error('Failed to fetch suites:', error);
      setSuites([]);
      // If unauthorized, redirect to login
      if (error.response?.status === 401) {
        navigate('/login');
      }
    } finally {
      setLoading(false);
    }
  };

  const getUserTier = () => {
    return user?.subscription_tier?.[0] || 'free';
  };

  const isSuiteAccessible = (suite) => {
    const userTier = getUserTier();
    if (!suite.subscription_tiers || suite.subscription_tiers.length === 0) {
      return true; // No tier restriction
    }
    return suite.subscription_tiers.includes(userTier);
  };

  const handleSuiteClick = (suite) => {
    if (isSuiteAccessible(suite) && suite.status === 'active') {
      navigate(`/suite/${suite.id}/agents`);
    }
  };

  if (loading) {
    return (
      <div style={{ padding: '40px', textAlign: 'center', color: 'var(--text-soft)' }}>
        Loading suites...
      </div>
    );
  }

  return (
    <div style={{ padding: '24px', maxWidth: '1200px', margin: '0 auto' }}>
      <div style={{ marginBottom: '24px' }}>
        <h1 style={{ fontSize: '24px', fontWeight: 600, marginBottom: '8px', color: 'var(--text)' }}>
          Select a Suite
        </h1>
        <p style={{ color: 'var(--text-soft)', marginBottom: '8px' }}>
          Choose a suite to access specialized AI agents for your needs
        </p>
        <div style={{ fontSize: '12px', color: 'var(--text-soft)' }}>
          Your Tier: <span style={{ fontWeight: 600, textTransform: 'capitalize' }}>{getUserTier()}</span>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '16px' }}>
        {suites.map((suite) => {
          const accessible = isSuiteAccessible(suite);
          const isActive = suite.status === 'active';
          const isLocked = !accessible || !isActive;

          return (
            <div
              key={suite.id}
              onClick={() => handleSuiteClick(suite)}
              style={{
                position: 'relative',
                padding: '20px',
                borderRadius: '12px',
                border: `2px solid ${isLocked ? 'var(--border)' : 'var(--border)'}`,
                background: isLocked ? 'var(--bg-soft)' : 'var(--bg-panel)',
                cursor: isLocked ? 'not-allowed' : 'pointer',
                opacity: isLocked ? 0.6 : 1,
                transition: 'all 0.2s ease',
              }}
              onMouseEnter={(e) => {
                if (!isLocked) {
                  e.currentTarget.style.borderColor = 'var(--accent)';
                  e.currentTarget.style.transform = 'scale(1.02)';
                }
              }}
              onMouseLeave={(e) => {
                if (!isLocked) {
                  e.currentTarget.style.borderColor = 'var(--border)';
                  e.currentTarget.style.transform = 'scale(1)';
                }
              }}
            >
              {isLocked && (
                <div style={{ position: 'absolute', right: '12px', top: '12px', color: 'var(--text-soft)' }}>
                  🔒
                </div>
              )}

              <div style={{ marginBottom: '12px' }}>
                <h3 style={{ fontSize: '18px', fontWeight: 600, color: 'var(--text)', marginBottom: '6px' }}>
                  {suite.name}
                </h3>
                <p style={{ fontSize: '13px', color: 'var(--text-soft)' }}>{suite.description}</p>
              </div>

              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                  <span
                    style={{
                      padding: '4px 10px',
                      borderRadius: '999px',
                      fontSize: '11px',
                      fontWeight: 500,
                      background: suite.status === 'active' ? '#dcfce7' : 'var(--bg-soft)',
                      color: suite.status === 'active' ? '#166534' : 'var(--text-soft)',
                    }}
                  >
                    {suite.status}
                  </span>
                  {suite.agents && (
                    <span style={{ fontSize: '12px', color: 'var(--text-soft)' }}>
                      {suite.agents.length} agents
                    </span>
                  )}
                </div>

                {!isLocked && (
                  <div style={{ color: 'var(--accent)' }}>→</div>
                )}
              </div>

              {isLocked && (
                <div
                  style={{
                    marginTop: '12px',
                    padding: '8px',
                    borderRadius: '6px',
                    background: 'var(--bg-soft)',
                    fontSize: '11px',
                    color: 'var(--text-soft)',
                  }}
                >
                  {!accessible
                    ? 'Upgrade your tier to access this suite'
                    : 'This suite is currently unavailable'}
                </div>
              )}
            </div>
          );
        })}
      </div>

      {suites.length === 0 && (
        <div style={{ marginTop: '48px', textAlign: 'center', color: 'var(--text-soft)' }}>
          No suites available at the moment.
        </div>
      )}
    </div>
  );
}





