import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import axios from 'axios';

export default function AgentFormPage() {
  const { suiteId, agentId } = useParams();
  const navigate = useNavigate();
  const isEdit = !!agentId;

  const [loading, setLoading] = useState(false);
  const [providers, setProviders] = useState([]);
  const [models, setModels] = useState([]);
  const [externalApis, setExternalApis] = useState([]);
  const [suites, setSuites] = useState([]);

  const [formData, setFormData] = useState({
    suite_id: suiteId || '',
    name: '',
    description: '',
    system_prompt: '',
    model_provider: '',
    model_name: '',
    model_config: {
      temperature: 0.7,
      max_tokens: 2000,
    },
    enable_rag: false,
    enable_web_search: false,
    enable_external_apis: false,
    external_api_configs: [],
    is_active: true,
    order: 0,
  });

  useEffect(() => {
    fetchInitialData();
    if (isEdit) {
      fetchAgent();
    }
  }, [agentId]);

  useEffect(() => {
    if (formData.model_provider) {
      fetchModels(formData.model_provider);
    }
  }, [formData.model_provider]);

  const fetchInitialData = async () => {
    try {
      const [providersRes, apisRes, suitesRes] = await Promise.all([
        axios.get('/api/admin/providers'),
        axios.get('/api/admin/external-apis'),
        axios.get('/api/suites'),
      ]);
      setProviders(providersRes.data);
      setExternalApis(apisRes.data);
      setSuites(suitesRes.data);
    } catch (error) {
      console.error('Failed to fetch initial data:', error);
    }
  };

  const fetchModels = async (provider) => {
    try {
      const response = await axios.get(`/api/admin/providers/${provider}/models`);
      setModels(response.data.models || []);
    } catch (error) {
      console.error('Failed to fetch models:', error);
    }
  };

  const fetchAgent = async () => {
    try {
      setLoading(true);
      const response = await axios.get(`/api/agents/${agentId}`);
      const agent = response.data;
      setFormData({
        suite_id: agent.suite_id,
        name: agent.name,
        description: agent.description || '',
        system_prompt: agent.system_prompt || '',
        model_provider: agent.model_provider,
        model_name: agent.model_name,
        model_config: agent.model_config || { temperature: 0.7, max_tokens: 2000 },
        enable_rag: agent.enable_rag || false,
        enable_web_search: agent.enable_web_search || false,
        enable_external_apis: agent.enable_external_apis || false,
        external_api_configs: (agent.external_api_configs || []).map(id => String(id)), // Convert to strings for select
        is_active: agent.is_active !== false,
        order: agent.order || 0,
      });
    } catch (error) {
      console.error('Failed to fetch agent:', error);
      alert('Failed to load agent data');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      // Ensure external_api_configs are sent as integers
      const submitData = {
        ...formData,
        external_api_configs: formData.external_api_configs
          .map(id => {
            const numId = Number(id);
            return isNaN(numId) ? null : numId;
          })
          .filter(id => id !== null), // Remove any invalid values
      };
      setLoading(true);
      const url = isEdit
        ? `/api/agents/${agentId}`
        : `/api/suites/${formData.suite_id}/agents`;
      const method = isEdit ? 'put' : 'post';

      await axios[method](url, submitData);
      navigate('/admin/agents');
    } catch (error) {
      console.error('Failed to save agent:', error);
      if (error.response?.data?.errors) {
        const errorMessages = Object.values(error.response.data.errors).flat().join(', ');
        alert(`Failed to save agent: ${errorMessages}`);
      } else {
        alert('Failed to save agent. Please check all fields.');
      }
    } finally {
      setLoading(false);
    }
  };

  const toggleExternalApi = (apiId) => {
    setFormData((prev) => ({
      ...prev,
      external_api_configs: prev.external_api_configs.includes(apiId)
        ? prev.external_api_configs.filter((id) => id !== apiId)
        : [...prev.external_api_configs, apiId],
    }));
  };

  if (loading && isEdit) {
    return (
      <div className="panel" style={{ textAlign: 'center', padding: '40px' }}>
        <div style={{ color: 'var(--text-muted)' }}>Loading...</div>
      </div>
    );
  }

  return (
    <div className="grid-two">
      <section className="panel">
        <div className="panel-header">
          <div className="panel-title">
            {isEdit ? 'Edit Agent' : 'Create / Edit Agent'}
          </div>
        </div>
        <div className="panel-sub">
          Define how this agent behaves, what models & APIs it uses, how IAM applies.
        </div>

        <form onSubmit={handleSubmit}>
          <div className="field-label">Agent name</div>
          <input
            type="text"
            value={formData.name}
            onChange={(e) =>
              setFormData({ ...formData, name: e.target.value })
            }
            className="input"
            placeholder="e.g. Gap Explorer G0-2"
            required
          />

          <div className="field-label">Short description</div>
          <input
            type="text"
            value={formData.description}
            onChange={(e) =>
              setFormData({ ...formData, description: e.target.value })
            }
            className="input"
            placeholder="One-line description for lists and search."
          />

          <div className="field-label">System / base prompt</div>
          <textarea
            value={formData.system_prompt}
            onChange={(e) =>
              setFormData({ ...formData, system_prompt: e.target.value })
            }
            className="input"
            placeholder="Describe behaviour, tone, structure, and how to use documents / web / APIs."
            rows="6"
          />

          <div className="field-label">Suite *</div>
          <select
            value={formData.suite_id}
            onChange={(e) =>
              setFormData({ ...formData, suite_id: e.target.value })
            }
            className="input"
            required
            disabled={isEdit}
          >
            <option value="">Select a Suite</option>
            {suites.map((suite) => (
              <option key={suite.id} value={suite.id}>
                {suite.name}
              </option>
            ))}
          </select>

          <div className="field-label">AI Provider & Model</div>
          <div className="two-col-inline">
            <div>
              <select
                value={formData.model_provider}
                onChange={(e) =>
                  setFormData({
                    ...formData,
                    model_provider: e.target.value,
                    model_name: '',
                  })
                }
                className="input"
                required
              >
                <option value="">Select Provider</option>
                {providers.map((provider) => (
                  <option key={provider.id} value={provider.id}>
                    {provider.name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <select
                value={formData.model_name}
                onChange={(e) =>
                  setFormData({ ...formData, model_name: e.target.value })
                }
                className="input"
                required
                disabled={!formData.model_provider}
              >
                <option value="">Select Model</option>
                {models.map((model) => (
                  <option key={model} value={model}>
                    {model}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="two-col-inline" style={{ marginTop: '6px' }}>
            <div>
              <div className="field-label">Temperature</div>
              <input
                type="number"
                min="0"
                max="2"
                step="0.1"
                value={formData.model_config.temperature}
                onChange={(e) =>
                  setFormData({
                    ...formData,
                    model_config: {
                      ...formData.model_config,
                      temperature: parseFloat(e.target.value),
                    },
                  })
                }
                className="input"
              />
            </div>
            <div>
              <div className="field-label">Max tokens</div>
              <input
                type="number"
                min="100"
                step="100"
                value={formData.model_config.max_tokens}
                onChange={(e) =>
                  setFormData({
                    ...formData,
                    model_config: {
                      ...formData.model_config,
                      max_tokens: parseInt(e.target.value),
                    },
                  })
                }
                className="input"
              />
            </div>
          </div>

          <div className="field-label">Data sources allowed</div>
          <div className="pill-row">
            <label
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '6px',
                cursor: 'pointer',
              }}
            >
              <input
                type="checkbox"
                checked={formData.enable_rag}
                onChange={(e) =>
                  setFormData({ ...formData, enable_rag: e.target.checked })
                }
                style={{ width: '14px', height: '14px' }}
              />
              <span className="pill-chip">Document uploads (RAG)</span>
            </label>
            <label
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '6px',
                cursor: 'pointer',
              }}
            >
              <input
                type="checkbox"
                checked={formData.enable_web_search}
                onChange={(e) =>
                  setFormData({ ...formData, enable_web_search: e.target.checked })
                }
                style={{ width: '14px', height: '14px' }}
              />
              <span className="pill-chip">Web search</span>
            </label>
            <label
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '6px',
                cursor: 'pointer',
              }}
            >
              <input
                type="checkbox"
                checked={formData.enable_external_apis}
                onChange={(e) => {
                  const enabled = e.target.checked;
                  setFormData({
                    ...formData,
                    enable_external_apis: enabled,
                    external_api_configs: enabled ? formData.external_api_configs : [], // Clear if disabled
                  });
                }}
                style={{ width: '14px', height: '14px' }}
              />
              <span className="pill-chip">External APIs</span>
            </label>
          </div>

          {formData.enable_external_apis && (
            <>
              <div className="field-label">External APIs (multi-select)</div>
              <select
                className="input"
                multiple
                value={formData.external_api_configs.map(String)} // Convert to strings for comparison
                onChange={(e) => {
                  const selected = Array.from(e.target.selectedOptions, (option) => option.value);
                  setFormData({ ...formData, external_api_configs: selected });
                }}
                style={{ minHeight: '100px' }}
              >
                {externalApis.map((api) => (
                  <option key={api.id} value={String(api.id)}>
                    {api.name} {api.provider ? `(${api.provider})` : ''}
                  </option>
                ))}
              </select>
              <div className="note">
                Multiple external APIs can be selected. The universal API connector handles auth, base URLs and parameters.
              </div>
            </>
          )}

          <div className="two-col-inline" style={{ marginTop: '6px' }}>
            <div>
              <div className="field-label">Order</div>
              <input
                type="number"
                value={formData.order}
                onChange={(e) =>
                  setFormData({ ...formData, order: parseInt(e.target.value) })
                }
                className="input"
              />
            </div>
            <div>
              <div className="field-label">Status</div>
              <div style={{ marginTop: '8px' }}>
                <span
                  className={`toggle ${formData.is_active ? 'on' : ''}`}
                  onClick={() =>
                    setFormData({ ...formData, is_active: !formData.is_active })
                  }
                ></span>
                <span style={{ marginLeft: '8px', fontSize: '11px' }}>Active</span>
              </div>
            </div>
          </div>

          <div
            style={{
              marginTop: '16px',
              display: 'flex',
              justifyContent: 'flex-end',
              gap: '8px',
            }}
          >
            <button
              type="button"
              onClick={() => navigate('/admin/agents')}
              className="btn btn-outline"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={loading}
              className="btn btn-primary"
            >
              {loading ? 'Saving...' : isEdit ? 'Update Agent' : 'Save Agent'}
            </button>
          </div>
        </form>
      </section>
    </div>
  );
}


