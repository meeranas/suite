import React, { useState, useEffect } from 'react';
import axios from 'axios';

export default function UsagePage() {
  const [usage, setUsage] = useState([]);
  const [summary, setSummary] = useState({
    total_requests: 0,
    total_input_tokens: 0,
    total_output_tokens: 0,
    total_cost: 0,
  });
  const [suiteBreakdown, setSuiteBreakdown] = useState([]);
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 50,
    total: 0,
  });
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({
    start_date: '',
    end_date: '',
    suite_id: '',
    agent_id: '',
  });
  const [suites, setSuites] = useState([]);
  const [agents, setAgents] = useState([]);

  useEffect(() => {
    fetchSuites();
    fetchUsage();
  }, []);

  useEffect(() => {
    if (filters.suite_id) {
      fetchAgents(filters.suite_id);
    } else {
      setAgents([]);
    }
  }, [filters.suite_id]);

  const fetchSuites = async () => {
    try {
      const response = await axios.get('/api/suites');
      setSuites(response.data);
    } catch (error) {
      console.error('Failed to fetch suites:', error);
    }
  };

  const fetchAgents = async (suiteId) => {
    try {
      const response = await axios.get(`/api/suites/${suiteId}/agents`);
      setAgents(response.data);
    } catch (error) {
      console.error('Failed to fetch agents:', error);
    }
  };

  const fetchUsage = async (page = 1) => {
    try {
      setLoading(true);
      const params = {
        page,
        per_page: 50,
        ...filters,
      };
      // Remove empty filters
      Object.keys(params).forEach((key) => {
        if (params[key] === '') delete params[key];
      });

      const response = await axios.get('/api/usage', { params });
      setUsage(response.data.data || []);
      setSummary(response.data.summary || summary);
      setPagination(response.data.pagination || pagination);
      setSuiteBreakdown(response.data.suite_breakdown || []);
    } catch (error) {
      console.error('Failed to fetch usage:', error);
      alert('Failed to load usage data');
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (key, value) => {
    setFilters({ ...filters, [key]: value });
    if (key === 'suite_id') {
      setFilters({ ...filters, suite_id: value, agent_id: '' });
    }
  };

  const handleApplyFilters = () => {
    fetchUsage(1);
  };

  const handleResetFilters = () => {
    setFilters({
      start_date: '',
      end_date: '',
      suite_id: '',
      agent_id: '',
    });
    fetchUsage(1);
  };

  const totalTokens = summary.total_input_tokens + summary.total_output_tokens;

  return (
    <div>
      <section className="panel">
        <div className="panel-header">
          <div className="panel-title">Usage & Cost</div>
        </div>
        <div className="panel-sub">
          Monitor tokens, external API calls, HTBS runs and cost breakdowns.
        </div>

        <div className="metric-grid">
          <div className="metric-card">
            <div className="metric-label">Total Requests</div>
            <div className="metric-value">{summary.total_requests.toLocaleString()}</div>
            <div className="metric-foot">All agents</div>
          </div>
          <div className="metric-card">
            <div className="metric-label">Total Tokens</div>
            <div className="metric-value">{totalTokens.toLocaleString()}</div>
            <div className="metric-foot">Input + Output</div>
          </div>
          <div className="metric-card">
            <div className="metric-label">Total Cost</div>
            <div className="metric-value">
              ${parseFloat(summary.total_cost || 0).toFixed(2)}
            </div>
            <div className="metric-foot">LLM + APIs</div>
          </div>
          <div className="metric-card">
            <div className="metric-label">Avg Cost/Request</div>
            <div className="metric-value">
              ${summary.total_requests > 0
                ? (parseFloat(summary.total_cost || 0) / summary.total_requests).toFixed(4)
                : '0.0000'}
            </div>
            <div className="metric-foot">Per request</div>
          </div>
        </div>
      </section>

      {/* Filters */}
      <section className="panel">
        <div className="panel-title" style={{ marginBottom: '10px' }}>Filters</div>
        <div className="grid-two">
          <div>
            <div className="field-label">Start Date</div>
            <input
              type="date"
              value={filters.start_date}
              onChange={(e) => handleFilterChange('start_date', e.target.value)}
              className="input"
            />
          </div>
          <div>
            <div className="field-label">End Date</div>
            <input
              type="date"
              value={filters.end_date}
              onChange={(e) => handleFilterChange('end_date', e.target.value)}
              className="input"
            />
          </div>
          <div>
            <div className="field-label">Suite</div>
            <select
              value={filters.suite_id}
              onChange={(e) => handleFilterChange('suite_id', e.target.value)}
              className="input"
            >
              <option value="">All Suites</option>
              {suites.map((suite) => (
                <option key={suite.id} value={suite.id}>
                  {suite.name}
                </option>
              ))}
            </select>
          </div>
          <div>
            <div className="field-label">Agent</div>
            <select
              value={filters.agent_id}
              onChange={(e) => handleFilterChange('agent_id', e.target.value)}
              className="input"
              disabled={!filters.suite_id}
            >
              <option value="">All Agents</option>
              {agents.map((agent) => (
                <option key={agent.id} value={agent.id}>
                  {agent.name}
                </option>
              ))}
            </select>
          </div>
        </div>
        <div style={{ marginTop: '12px', display: 'flex', gap: '8px' }}>
          <button onClick={handleApplyFilters} className="btn btn-primary">
            Apply Filters
          </button>
          <button onClick={handleResetFilters} className="btn btn-outline">
            Reset
          </button>
        </div>
      </section>

      {/* Suite Cost Breakdown */}
      {suiteBreakdown.length > 0 && (
        <section className="panel">
          <div className="panel-title" style={{ marginBottom: '10px' }}>Cost by Suite</div>
          <div className="panel-sub" style={{ marginBottom: '16px' }}>
            Total cost breakdown for each suite (all agents combined)
          </div>
          <table className="admin-table">
            <thead>
              <tr>
                <th>Suite</th>
                <th style={{ textAlign: 'right' }}>Requests</th>
                <th style={{ textAlign: 'right' }}>Input Tokens</th>
                <th style={{ textAlign: 'right' }}>Output Tokens</th>
                <th style={{ textAlign: 'right' }}>Total Cost</th>
                <th style={{ textAlign: 'right' }}>% of Total</th>
              </tr>
            </thead>
            <tbody>
              {suiteBreakdown.map((suite) => {
                const totalTokens = suite.total_input_tokens + suite.total_output_tokens;
                const percentage = summary.total_cost > 0 
                  ? ((suite.total_cost / summary.total_cost) * 100).toFixed(1)
                  : '0.0';
                return (
                  <tr key={suite.suite_id}>
                    <td>
                      <strong>{suite.suite_name}</strong>
                    </td>
                    <td style={{ textAlign: 'right' }}>
                      {suite.total_requests.toLocaleString()}
                    </td>
                    <td style={{ textAlign: 'right' }}>
                      {suite.total_input_tokens.toLocaleString()}
                    </td>
                    <td style={{ textAlign: 'right' }}>
                      {suite.total_output_tokens.toLocaleString()}
                    </td>
                    <td style={{ textAlign: 'right', fontWeight: '600' }}>
                      ${parseFloat(suite.total_cost).toFixed(2)}
                    </td>
                    <td style={{ textAlign: 'right', color: 'var(--text-soft)' }}>
                      {percentage}%
                    </td>
                  </tr>
                );
              })}
            </tbody>
            <tfoot>
              <tr style={{ borderTop: '2px solid var(--border)', fontWeight: '600' }}>
                <td>Total</td>
                <td style={{ textAlign: 'right' }}>
                  {summary.total_requests.toLocaleString()}
                </td>
                <td style={{ textAlign: 'right' }}>
                  {summary.total_input_tokens.toLocaleString()}
                </td>
                <td style={{ textAlign: 'right' }}>
                  {summary.total_output_tokens.toLocaleString()}
                </td>
                <td style={{ textAlign: 'right' }}>
                  ${parseFloat(summary.total_cost || 0).toFixed(2)}
                </td>
                <td style={{ textAlign: 'right' }}>100%</td>
              </tr>
            </tfoot>
          </table>
        </section>
      )}

      {/* Usage Table */}
      {loading ? (
        <section className="panel" style={{ textAlign: 'center', padding: '40px' }}>
          <div style={{ color: 'var(--text-muted)' }}>Loading usage data...</div>
        </section>
      ) : (
        <>
          <section className="panel">
            <div className="panel-title" style={{ marginBottom: '10px' }}>Usage Log</div>
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>User</th>
                  <th>Suite</th>
                  <th>Agent</th>
                  <th>Model</th>
                  <th style={{ textAlign: 'right' }}>Input Tokens</th>
                  <th style={{ textAlign: 'right' }}>Output Tokens</th>
                  <th style={{ textAlign: 'right' }}>Cost</th>
                </tr>
              </thead>
              <tbody>
                {usage.length === 0 ? (
                  <tr>
                    <td colSpan="8" style={{ textAlign: 'center', padding: '20px', color: 'var(--text-muted)' }}>
                      No usage data found
                    </td>
                  </tr>
                ) : (
                  usage.map((item) => (
                    <tr key={item.id}>
                      <td style={{ fontSize: '10px' }}>
                        {new Date(item.created_at).toLocaleString()}
                      </td>
                      <td>{item.user?.email || 'N/A'}</td>
                      <td>{item.suite?.name || 'N/A'}</td>
                      <td>{item.agent?.name || 'N/A'}</td>
                      <td style={{ fontSize: '10px' }}>
                        {item.model_provider}/{item.model_name}
                      </td>
                      <td style={{ textAlign: 'right' }}>
                        {(item.input_tokens || 0).toLocaleString()}
                      </td>
                      <td style={{ textAlign: 'right' }}>
                        {(item.output_tokens || 0).toLocaleString()}
                      </td>
                      <td style={{ textAlign: 'right' }}>
                        ${parseFloat(item.cost_usd || 0).toFixed(4)}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </section>

          {/* Pagination */}
          {pagination.last_page > 1 && (
            <div
              style={{
                marginTop: '12px',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                fontSize: '11px',
                color: 'var(--text-muted)',
              }}
            >
              <div>
                Showing {((pagination.current_page - 1) * pagination.per_page) + 1} to{' '}
                {Math.min(pagination.current_page * pagination.per_page, pagination.total)} of{' '}
                {pagination.total} results
              </div>
              <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                <button
                  onClick={() => fetchUsage(pagination.current_page - 1)}
                  disabled={pagination.current_page === 1}
                  className="btn btn-outline"
                  style={{ fontSize: '11px' }}
                >
                  Previous
                </button>
                <span style={{ padding: '0 8px' }}>
                  Page {pagination.current_page} of {pagination.last_page}
                </span>
                <button
                  onClick={() => fetchUsage(pagination.current_page + 1)}
                  disabled={pagination.current_page === pagination.last_page}
                  className="btn btn-outline"
                  style={{ fontSize: '11px' }}
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}

