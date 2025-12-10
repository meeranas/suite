import React, { useState, useEffect } from 'react';
import axios from 'axios';

export default function ExternalApiConfigsPage() {
  const [configs, setConfigs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [editingConfig, setEditingConfig] = useState(null);

  const [formData, setFormData] = useState({
    name: '',
    provider: '',
    base_url: '',
    api_type: 'rest',
    api_key: '',
    api_secret: '',
    config: {
      endpoints: [],
      parameters: {},
      rate_limit: null,
      requires_api_key: true,
      api_key_param: 'api_key',
      api_key_location: 'query', // query, header, body
      default_limit: 100,
      pagination_type: 'offset', // offset, cursor, token
    },
    is_active: true,
  });

  const [endpointForm, setEndpointForm] = useState({
    purpose: '',
    path: '',
    method: 'GET',
  });

  const predefinedProviders = [
    { id: 'serper', name: 'Serper (Google Search)', baseUrl: 'https://google.serper.dev' },
    { id: 'bing', name: 'Bing Search', baseUrl: 'https://api.bing.microsoft.com' },
    { id: 'brave', name: 'Brave Search', baseUrl: 'https://api.search.brave.com' },
    { id: 'crunchbase', name: 'Crunchbase', baseUrl: 'https://api.crunchbase.com' },
    { id: 'patents', name: 'Google Patents', baseUrl: 'https://www.googleapis.com' },
    { id: 'fda', name: 'FDA OpenFDA', baseUrl: 'https://api.fda.gov' },
    { id: 'clinicaltrials', name: 'ClinicalTrials.gov', baseUrl: 'https://clinicaltrials.gov/api/v2' },
    { id: 'news', name: 'News API', baseUrl: 'https://newsapi.org' },
    { id: 'custom', name: 'Custom API', baseUrl: '' },
  ];

  useEffect(() => {
    fetchConfigs();
  }, []);

  const fetchConfigs = async () => {
    try {
      const response = await axios.get('/api/external-api-configs');
      setConfigs(response.data);
    } catch (error) {
      console.error('Failed to fetch configs:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleOpenModal = (config = null) => {
    if (config) {
      setEditingConfig(config);
      setFormData({
        name: config.name,
        provider: config.provider,
        base_url: config.base_url || '',
        api_type: config.api_type || 'rest',
        api_key: '', // Don't show existing key for security
        api_secret: '',
        config: {
          endpoints: config.config?.endpoints || [],
          parameters: config.config?.parameters || {},
          rate_limit: config.config?.rate_limit || null,
          requires_api_key: config.config?.requires_api_key !== false,
          api_key_param: config.config?.api_key_param || 'api_key',
          api_key_location: config.config?.api_key_location || 'query',
          default_limit: config.config?.default_limit || 100,
          pagination_type: config.config?.pagination_type || 'offset',
        },
        is_active: config.is_active,
      });
    } else {
      setEditingConfig(null);
      setFormData({
        name: '',
        provider: '',
        base_url: '',
        api_type: 'rest',
        api_key: '',
        api_secret: '',
        config: {
          endpoints: [],
          parameters: {},
          rate_limit: null,
          requires_api_key: true,
          api_key_param: 'api_key',
          api_key_location: 'query',
          default_limit: 100,
          pagination_type: 'offset',
        },
        is_active: true,
      });
      setEndpointForm({ purpose: '', path: '', method: 'GET' });
    }
    setShowModal(true);
  };

  const handleProviderChange = (providerId) => {
    const provider = predefinedProviders.find((p) => p.id === providerId);
    let newConfig = {
      ...formData.config,
    };

    if (providerId === 'fda') {
      // OpenFDA defaults
      newConfig = {
        ...newConfig,
        endpoints: [
          { purpose: 'Drug adverse events', path: '/drug/event.json', method: 'GET' },
          { purpose: 'Drug labels', path: '/drug/label.json', method: 'GET' },
          { purpose: 'Drug enforcement/recall', path: '/drug/enforcement.json', method: 'GET' },
          { purpose: 'Drug NDC', path: '/drug/ndc.json', method: 'GET' },
          { purpose: 'Device adverse events', path: '/device/event.json', method: 'GET' },
          { purpose: 'Device recalls', path: '/device/enforcement.json', method: 'GET' },
          { purpose: 'Device 510k approvals', path: '/device/510k.json', method: 'GET' },
          { purpose: 'Device PMA approvals', path: '/device/pma.json', method: 'GET' },
          { purpose: 'Device classification', path: '/device/classification.json', method: 'GET' },
          { purpose: 'Device registration', path: '/device/registration.json', method: 'GET' },
          { purpose: 'Food enforcement/recall', path: '/food/enforcement.json', method: 'GET' },
        ],
        requires_api_key: true,
        api_key_param: 'api_key',
        api_key_location: 'query',
        default_limit: 100,
        pagination_type: 'offset',
        rate_limit: 120,
      };
    } else if (providerId === 'clinicaltrials') {
      // ClinicalTrials.gov defaults
      newConfig = {
        ...newConfig,
        endpoints: [
          { purpose: 'Search studies', path: '/studies', method: 'GET' },
          { purpose: 'Get study by NCT ID', path: '/studies/{NCT}', method: 'GET' },
        ],
        requires_api_key: false,
        api_key_param: '',
        api_key_location: 'query',
        default_limit: 20,
        pagination_type: 'token',
        rate_limit: null,
      };
    } else if (providerId === 'custom') {
      // Reset to defaults for custom
      newConfig = {
        endpoints: [],
        parameters: {},
        rate_limit: null,
        requires_api_key: true,
        api_key_param: 'api_key',
        api_key_location: 'query',
        default_limit: 100,
        pagination_type: 'offset',
      };
    }

    setFormData((prev) => ({
      ...prev,
      provider: providerId,
      base_url: provider?.baseUrl || prev.base_url,
      config: newConfig,
      api_key: providerId === 'clinicaltrials' ? '' : prev.api_key, // ClinicalTrials doesn't need key
    }));
  };

  const addEndpoint = () => {
    if (!endpointForm.purpose || !endpointForm.path) return;
    setFormData((prev) => ({
      ...prev,
      config: {
        ...prev.config,
        endpoints: [...(prev.config.endpoints || []), { ...endpointForm }],
      },
    }));
    setEndpointForm({ purpose: '', path: '', method: 'GET' });
  };

  const removeEndpoint = (index) => {
    setFormData((prev) => ({
      ...prev,
      config: {
        ...prev.config,
        endpoints: prev.config.endpoints.filter((_, i) => i !== index),
      },
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      const url = editingConfig
        ? `/api/external-api-configs/${editingConfig.id}`
        : '/api/external-api-configs';
      const method = editingConfig ? 'put' : 'post';

      // Only send api_key if it's been changed (not empty) or if requires_api_key is true
      const submitData = { ...formData };
      if (!submitData.config.requires_api_key) {
        submitData.api_key = null;
      } else if (!submitData.api_key && !editingConfig) {
        // For new configs, require API key if requires_api_key is true
        if (submitData.config.requires_api_key) {
          alert('API key is required when "Requires API Key" is enabled');
          return;
        }
      }
      if (!submitData.api_key) {
        delete submitData.api_key;
      }
      if (!submitData.api_secret) {
        delete submitData.api_secret;
      }

      await axios[method](url, submitData);
      setShowModal(false);
      fetchConfigs();
    } catch (error) {
      console.error('Failed to save config:', error);
      alert('Failed to save configuration. Please check all fields.');
    }
  };

  const handleDelete = async (id) => {
    if (!confirm('Are you sure you want to delete this API configuration?')) return;
    try {
      await axios.delete(`/api/external-api-configs/${id}`);
      fetchConfigs();
    } catch (error) {
      console.error('Failed to delete config:', error);
      alert('Failed to delete configuration');
    }
  };

  const getProviderName = (providerId) => {
    return predefinedProviders.find((p) => p.id === providerId)?.name || providerId;
  };

  if (loading) return <div>Loading...</div>;

  return (
    <div className="">
      <section className="panel">
        <div className="panel-header">
          <div className="panel-title">External APIs</div>
          <button
            onClick={() => handleOpenModal()}
            className="btn btn-outline"
            style={{ fontSize: '11px' }}
          >
            + Add external API
          </button>
        </div>
        <div className="panel-sub">
          Central registry of non-LLM APIs; agents refer to these by name.
        </div>
        <table className="admin-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Provider</th>
              <th>Type</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {configs.map((config) => {
              const isSearchProvider = ['serper', 'bing', 'brave'].includes(
                config.provider
              );
              return (
                <tr
                  key={config.id}
                  style={{ cursor: 'pointer' }}
                  onClick={() => handleOpenModal(config)}
                >
                  <td style={{ fontWeight: 500 }}>{config.name}</td>
                  <td>{getProviderName(config.provider)}</td>
                  <td>
                    <span className="tag">
                      {isSearchProvider ? 'Web Search' : 'Data API'}
                    </span>
                  </td>
                  <td>
                    <span
                      className={`toggle ${config.is_active ? 'on' : ''}`}
                      onClick={(e) => {
                        e.stopPropagation();
                      }}
                    ></span>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>

        {configs.length === 0 && (
          <div
            style={{
              textAlign: 'center',
              padding: '20px',
              color: 'var(--text-muted)',
            }}
          >
            No API configurations found. Add your first configuration!
          </div>
        )}
      </section>

      {/* Modal */}
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
              maxWidth: '800px',
              margin: '20px',
              maxHeight: '90vh',
              overflowY: 'auto',
            }}
          >
            <div className="panel-title" style={{ marginBottom: '16px' }}>
              {editingConfig ? 'Edit API Configuration' : 'Add / edit external API'}
            </div>
            <form onSubmit={handleSubmit} style={{ paddingBottom: '20px' }}>
              <div className="field-label">Display name *</div>
              <input
                type="text"
                value={formData.name}
                onChange={(e) =>
                  setFormData({ ...formData, name: e.target.value })
                }
                className="input"
                required
                placeholder="e.g. OpenFDA API"
              />

              <div className="field-label">Provider / API Type *</div>
              <select
                value={formData.provider}
                onChange={(e) => handleProviderChange(e.target.value)}
                className="input"
                required
                disabled={!!editingConfig}
              >
                <option value="">Select Provider</option>
                {predefinedProviders.map((provider) => (
                  <option key={provider.id} value={provider.id}>
                    {provider.name}
                  </option>
                ))}
              </select>

              <div className="field-label">Base URL *</div>
              <input
                type="url"
                value={formData.base_url}
                onChange={(e) =>
                  setFormData({ ...formData, base_url: e.target.value })
                }
                className="input"
                required
                placeholder="https://api.example.com"
              />

              <div className="field-label">API Type</div>
              <select
                value={formData.api_type}
                onChange={(e) =>
                  setFormData({ ...formData, api_type: e.target.value })
                }
                className="input"
              >
                <option value="rest">REST</option>
                <option value="graphql">GraphQL</option>
              </select>

              <div className="section-title" style={{ marginTop: '16px' }}>API Authentication</div>

              <div style={{ marginTop: '8px' }}>
                <label style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '12px', cursor: 'pointer' }}>
                  <input
                    type="checkbox"
                    checked={formData.config.requires_api_key}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        config: {
                          ...formData.config,
                          requires_api_key: e.target.checked,
                        },
                      })
                    }
                    style={{ width: '14px', height: '14px' }}
                  />
                  <span>Requires API Key</span>
                </label>
              </div>

              {formData.config.requires_api_key && (
                <>
                  <div className="field-label">
                    API key
                    {editingConfig && (
                      <span style={{ marginLeft: '8px', fontSize: '10px', color: 'var(--text-muted)' }}>
                        (Leave empty to keep existing)
                      </span>
                    )}
                  </div>
                  <input
                    type="password"
                    value={formData.api_key}
                    onChange={(e) =>
                      setFormData({ ...formData, api_key: e.target.value })
                    }
                    className="input"
                    style={{ fontFamily: 'monospace', fontSize: '11px' }}
                    placeholder="Enter API key"
                  />

                  <div className="field-label">API Key Parameter Name</div>
                  <input
                    type="text"
                    value={formData.config.api_key_param}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        config: {
                          ...formData.config,
                          api_key_param: e.target.value,
                        },
                      })
                    }
                    className="input"
                    placeholder="e.g. api_key, key, X-API-Key"
                  />

                  <div className="field-label">API Key Location</div>
                  <select
                    value={formData.config.api_key_location}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        config: {
                          ...formData.config,
                          api_key_location: e.target.value,
                        },
                      })
                    }
                    className="input"
                  >
                    <option value="query">Query Parameter</option>
                    <option value="header">HTTP Header</option>
                    <option value="body">Request Body</option>
                  </select>
                </>
              )}

              <div className="field-label">API Secret (Optional)</div>
              <input
                type="password"
                value={formData.api_secret}
                onChange={(e) =>
                  setFormData({ ...formData, api_secret: e.target.value })
                }
                className="input"
                style={{ fontFamily: 'monospace', fontSize: '11px' }}
                placeholder="Enter API secret (if required)"
              />

              <div className="section-title" style={{ marginTop: '16px' }}>Endpoints</div>
              <div className="note">Define available endpoints for this API</div>

              <div style={{ marginBottom: '8px' }}>
                {formData.config.endpoints?.map((endpoint, index) => (
                  <div
                    key={index}
                    style={{
                      display: 'flex',
                      gap: '8px',
                      marginBottom: '6px',
                      padding: '8px',
                      background: 'var(--bg-main)',
                      borderRadius: '6px',
                      fontSize: '11px',
                    }}
                  >
                    <div style={{ flex: 1 }}>
                      <strong>{endpoint.purpose}</strong>
                      <div style={{ color: 'var(--text-muted)', marginTop: '2px' }}>
                        {endpoint.method} {endpoint.path}
                      </div>
                    </div>
                    <button
                      type="button"
                      onClick={() => removeEndpoint(index)}
                      className="btn btn-outline"
                      style={{ fontSize: '10px', padding: '4px 8px' }}
                    >
                      Remove
                    </button>
                  </div>
                ))}
              </div>

              <div className="two-col-inline">
                <div>
                  <div className="field-label">Purpose</div>
                  <input
                    type="text"
                    value={endpointForm.purpose}
                    onChange={(e) =>
                      setEndpointForm({ ...endpointForm, purpose: e.target.value })
                    }
                    className="input"
                    placeholder="e.g. Drug adverse events"
                  />
                </div>
                <div>
                  <div className="field-label">Method</div>
                  <select
                    value={endpointForm.method}
                    onChange={(e) =>
                      setEndpointForm({ ...endpointForm, method: e.target.value })
                    }
                    className="input"
                  >
                    <option value="GET">GET</option>
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                    <option value="DELETE">DELETE</option>
                  </select>
                </div>
              </div>
              <div className="field-label">Path</div>
              <input
                type="text"
                value={endpointForm.path}
                onChange={(e) =>
                  setEndpointForm({ ...endpointForm, path: e.target.value })
                }
                className="input"
                placeholder="e.g. /drug/event.json"
              />
              <button
                type="button"
                onClick={addEndpoint}
                className="btn btn-outline"
                style={{ marginTop: '6px', fontSize: '11px' }}
              >
                + Add Endpoint
              </button>

              <div className="section-title" style={{ marginTop: '16px' }}>Configuration</div>

              <div className="two-col-inline">
                <div>
                  <div className="field-label">Default Limit</div>
                  <input
                    type="number"
                    value={formData.config.default_limit}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        config: {
                          ...formData.config,
                          default_limit: parseInt(e.target.value) || 100,
                        },
                      })
                    }
                    className="input"
                    placeholder="100"
                  />
                </div>
                <div>
                  <div className="field-label">Rate Limit (req/min)</div>
                  <input
                    type="number"
                    value={formData.config.rate_limit || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        config: {
                          ...formData.config,
                          rate_limit: e.target.value ? parseInt(e.target.value) : null,
                        },
                      })
                    }
                    className="input"
                    placeholder="e.g. 120"
                  />
                </div>
              </div>

              <div className="field-label">Pagination Type</div>
              <select
                value={formData.config.pagination_type}
                onChange={(e) =>
                  setFormData({
                    ...formData,
                    config: {
                      ...formData.config,
                      pagination_type: e.target.value,
                    },
                  })
                }
                className="input"
              >
                <option value="offset">Offset (limit + skip)</option>
                <option value="cursor">Cursor-based</option>
                <option value="token">Token-based</option>
                <option value="none">No pagination</option>
              </select>

              <div style={{ marginTop: '10px' }}>
                <span
                  className={`toggle ${formData.is_active ? 'on' : ''}`}
                  onClick={() =>
                    setFormData({ ...formData, is_active: !formData.is_active })
                  }
                ></span>
                <span style={{ marginLeft: '8px', fontSize: '11px' }}>Active</span>
              </div>

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
                  {editingConfig ? 'Update' : 'Create'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}


