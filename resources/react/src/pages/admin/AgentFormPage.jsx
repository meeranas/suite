import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useAuth } from '../../services/auth';
import axios from 'axios';

export default function AgentFormPage() {
  const { suiteId, agentId } = useParams();
  const navigate = useNavigate();
  const { user, loading: authLoading } = useAuth();
  const isEdit = !!agentId;

  const [loading, setLoading] = useState(false);
  const [providers, setProviders] = useState([]);
  const [models, setModels] = useState([]);
  const [externalApis, setExternalApis] = useState([]);
  const [webSearchApis, setWebSearchApis] = useState([]);
  const [suites, setSuites] = useState([]);
  const [agentFiles, setAgentFiles] = useState([]);
  const [uploadingFile, setUploadingFile] = useState(false);

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
    web_search_provider_id: null,
    enable_external_apis: false,
    external_api_configs: [],
    is_active: true,
    order: 0,
  });

  useEffect(() => {
    // Wait for auth to be ready before making API calls
    if (authLoading) return;
    
    // Ensure token is set in axios headers
    const token = localStorage.getItem('jwt_token');
    if (token) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    
    fetchInitialData();
    if (isEdit) {
      fetchAgent();
    }
  }, [agentId, authLoading]);

  useEffect(() => {
    // Fetch files when enable_rag is toggled on
    if (isEdit && agentId && formData.enable_rag) {
      fetchAgentFiles();
    } else if (!formData.enable_rag) {
      // Clear files when RAG is disabled
      setAgentFiles([]);
    }
  }, [isEdit, agentId, formData.enable_rag]);

  // Auto-refresh file status when there are files being processed
  useEffect(() => {
    if (!isEdit || !agentId || !formData.enable_rag) return;

    // Check if there are any files that need processing
    const hasUnprocessedFiles = agentFiles.some(
      file => !file.is_processed || !file.is_embedded
    );

    // Only poll if there are unprocessed files
    if (!hasUnprocessedFiles) return;

    let pollCount = 0;
    const maxPolls = 30; // Stop after 5 minutes (30 * 10 seconds)
    let rateLimitHit = false;
    let intervalId = null;

    const pollFiles = async () => {
      // Stop if rate limit was hit
      if (rateLimitHit) {
        if (intervalId) clearInterval(intervalId);
        return;
      }

      pollCount++;
      
      // Stop polling after max attempts
      if (pollCount > maxPolls) {
        console.log('Stopped polling after max attempts');
        if (intervalId) clearInterval(intervalId);
        return;
      }

      try {
        await fetchAgentFiles();
        
        // Check if all files are now processed (check current state)
        // Note: This check happens after state update, so we check in next poll
      } catch (err) {
        // If we hit rate limit, stop polling
        if (err.response?.status === 429) {
          console.warn('Rate limit reached, stopping auto-refresh. Please use the Refresh button manually.');
          rateLimitHit = true;
          if (intervalId) clearInterval(intervalId);
        }
      }
    };

    // Poll every 10 seconds (6 requests per minute, well under 60/min limit)
    intervalId = setInterval(pollFiles, 10000);

    return () => {
      if (intervalId) clearInterval(intervalId);
    };
  }, [isEdit, agentId, formData.enable_rag, agentFiles.length]); // Only depend on length, not full array

  useEffect(() => {
    if (formData.model_provider) {
      fetchModels(formData.model_provider);
    }
  }, [formData.model_provider]);

  const fetchInitialData = async () => {
    // Ensure token is set
    const token = localStorage.getItem('jwt_token');
    if (!token) {
      console.error('No authentication token found');
      return;
    }
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

    try {
      const [providersRes, apisRes, suitesRes] = await Promise.all([
        axios.get('/api/admin/providers'),
        axios.get('/api/admin/external-apis'),
        axios.get('/api/suites'),
      ]);
      setProviders(providersRes.data);
      const allApis = apisRes.data;
      setExternalApis(allApis.filter(api => api.type === 'data_api' || !api.type)); // Only Data API types
      setWebSearchApis(allApis.filter(api => api.type === 'web_search')); // Only Web Search types
      setSuites(suitesRes.data);
    } catch (error) {
      console.error('Failed to fetch initial data:', error);
      if (error.response?.status === 401) {
        alert('Authentication failed. Please login again.');
        navigate('/login');
      }
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
    // Ensure token is set
    const token = localStorage.getItem('jwt_token');
    if (!token) {
      console.error('No authentication token found');
      return;
    }
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

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
        web_search_provider_id: agent.metadata?.web_search_provider_id ? Number(agent.metadata.web_search_provider_id) : null,
        enable_external_apis: agent.enable_external_apis || false,
        external_api_configs: (agent.external_api_configs || []).map(id => String(id)), // Convert to strings for select
        is_active: agent.is_active !== false,
        order: agent.order || 0,
      });
      // Fetch files after agent data is loaded (if RAG is enabled)
      if (agent.enable_rag) {
        await fetchAgentFiles();
      }
    } catch (error) {
      console.error('Failed to fetch agent:', error);
      if (error.response?.status === 401) {
        alert('Authentication failed. Please login again.');
        navigate('/login');
      } else {
        alert('Failed to load agent data');
      }
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
        suite_id: formData.suite_id ? Number(formData.suite_id) : null,
        metadata: {
          web_search_provider_id: formData.web_search_provider_id ? Number(formData.web_search_provider_id) : null,
        },
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

  const fetchAgentFiles = async () => {
    if (!agentId) return;
    
    // Ensure token is set
    const token = localStorage.getItem('jwt_token');
    if (token) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    
    try {
      const response = await axios.get(`/api/agents/${agentId}/files`);
      const files = response.data || [];
      setAgentFiles(files);
      
      // Log processing status
      const processing = files.filter(f => !f.is_processed || !f.is_embedded);
      if (processing.length > 0) {
        console.log(`${processing.length} file(s) still processing...`);
      }
    } catch (error) {
      console.error('Failed to fetch agent files:', error);
      if (error.response?.status === 429) {
        console.warn('Rate limit hit, will retry later...');
        // Don't clear files on rate limit, just log the warning
      } else if (error.response?.status === 401) {
        alert('Authentication failed. Please login again.');
        navigate('/login');
      } else {
        console.error('Error details:', error.response?.data);
        // Only clear on non-rate-limit errors
        if (error.response?.status !== 429) {
          setAgentFiles([]);
        }
      }
    }
  };

  const handleFileUpload = async (e) => {
    const file = e.target.files[0];
    if (!file || !agentId) return;

    setUploadingFile(true);
    try {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('agent_id', agentId);

      await axios.post('/api/files', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      // Refresh file list
      await fetchAgentFiles();
      e.target.value = ''; // Reset file input
    } catch (error) {
      console.error('Failed to upload file:', error);
      alert('Failed to upload file. Please try again.');
    } finally {
      setUploadingFile(false);
    }
  };

  const handleFileDelete = async (fileId) => {
    if (!confirm('Are you sure you want to delete this file? This will also remove all associated embeddings from the vector database.')) return;
    
    try {
      await axios.delete(`/api/files/${fileId}`);
      setAgentFiles(agentFiles.filter(f => f.id !== fileId));
      alert('File deleted successfully!');
    } catch (error) {
      console.error('Failed to delete file:', error);
      alert(error.response?.data?.message || 'Failed to delete file. Please try again.');
    }
  };

  const handleFileView = async (fileId) => {
    try {
      // Ensure token is set
      const token = localStorage.getItem('jwt_token');
      if (!token) {
        alert('Authentication required. Please login again.');
        navigate('/login');
        return;
      }

      // Find the file to get its MIME type
      const file = agentFiles.find(f => f.id === fileId);
      if (!file) {
        alert('File not found.');
        return;
      }

      // Fetch file as blob with authentication
      const response = await axios.get(`/api/files/${fileId}/download`, {
        responseType: 'blob',
        headers: {
          'Authorization': `Bearer ${token}`,
        },
      });

      // Create blob URL and open in new tab for viewing
      const blob = new Blob([response.data], { type: file.mime_type || response.headers['content-type'] });
      const url = window.URL.createObjectURL(blob);
      
      // Open in new tab/window for viewing
      const newWindow = window.open(url, '_blank');
      
      // If popup was blocked, fallback to download
      if (!newWindow) {
        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      }
      
      // Clean up URL after a delay (give browser time to load)
      setTimeout(() => {
        window.URL.revokeObjectURL(url);
      }, 1000);
    } catch (error) {
      console.error('Failed to view file:', error);
      if (error.response?.status === 401) {
        alert('Authentication failed. Please login again.');
        navigate('/login');
      } else if (error.response?.status === 404) {
        alert('File not found.');
      } else {
        alert('Failed to view file. Please try again.');
      }
    }
  };

  // Show loading if auth is still loading or if fetching agent data
  if (authLoading || (loading && isEdit)) {
    return (
      <div className="panel" style={{ textAlign: 'center', padding: '40px' }}>
        <div style={{ color: 'var(--text-muted)' }}>Loading...</div>
      </div>
    );
  }

  // Redirect to login if not authenticated
  if (!authLoading && !user) {
    navigate('/login');
    return null;
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

          {formData.enable_web_search && (
            <>
              <div className="field-label">Web Search Provider *</div>
              <select
                value={formData.web_search_provider_id ? String(formData.web_search_provider_id) : ''}
                onChange={(e) =>
                  setFormData({ ...formData, web_search_provider_id: e.target.value ? Number(e.target.value) : null })
                }
                className="input"
                required
              >
                <option value="">Select Web Search Provider</option>
                {webSearchApis.map((api) => (
                  <option key={api.id} value={String(api.id)}>
                    {api.name} ({api.provider})
                  </option>
                ))}
              </select>
              {webSearchApis.length === 0 && (
                <div className="note" style={{ color: 'var(--text-warning)', marginTop: '4px' }}>
                  No web search providers configured. Please add a web search API in External APIs first.
                </div>
              )}
            </>
          )}

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

          {formData.enable_rag && isEdit && (
            <>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                <div className="field-label">Admin Uploaded Files</div>
                <button
                  type="button"
                  onClick={fetchAgentFiles}
                  className="btn btn-outline"
                  style={{ fontSize: '12px', padding: '4px 8px' }}
                  title="Refresh file status"
                >
                  ↻ Refresh
                </button>
              </div>
              <div style={{ 
                border: '1px solid var(--border)', 
                borderRadius: '6px', 
                padding: '12px',
                marginBottom: '12px',
                backgroundColor: 'var(--bg-secondary)'
              }}>
                <input
                  type="file"
                  onChange={handleFileUpload}
                  disabled={uploadingFile}
                  accept=".pdf,.docx,.xlsx,.txt,.csv,.jpeg,.jpg,.png,.tiff"
                  style={{ marginBottom: '12px' }}
                />
                {uploadingFile && <div className="note">Uploading file...</div>}
                {agentFiles.some(f => !f.is_processed || !f.is_embedded) && (
                  <div className="note" style={{ color: 'var(--text-secondary)', fontSize: '12px', marginBottom: '8px' }}>
                    ⏳ Auto-refreshing every 10 seconds until processing completes...
                  </div>
                )}
                
                {agentFiles.length > 0 ? (
                  <div style={{ marginTop: '12px' }}>
                    {agentFiles.map((file) => (
                      <div
                        key={file.id}
                        style={{
                          display: 'flex',
                          justifyContent: 'space-between',
                          alignItems: 'center',
                          padding: '12px',
                          marginBottom: '8px',
                          backgroundColor: 'var(--bg)',
                          borderRadius: '6px',
                          border: '1px solid var(--border)',
                          gap: '12px',
                        }}
                      >
                        <div style={{ flex: 1, minWidth: 0, overflow: 'hidden' }}>
                          <div style={{ fontSize: '14px', fontWeight: 500, marginBottom: '4px', wordBreak: 'break-word' }}>
                            {file.original_name}
                          </div>
                          <div style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>
                            {file.size ? `${(file.size / 1024).toFixed(2)} KB` : ''}
                            {file.is_processed && file.is_embedded && (
                              <span style={{ color: 'var(--success)', marginLeft: '8px' }}>✓ Processed & Embedded</span>
                            )}
                            {file.is_processed && !file.is_embedded && (
                              <span style={{ color: 'var(--warning)', marginLeft: '8px' }}>⏳ Processing...</span>
                            )}
                            {!file.is_processed && (
                              <span style={{ color: 'var(--text-secondary)', marginLeft: '8px' }}>Pending...</span>
                            )}
                          </div>
                        </div>
                        <div style={{ display: 'flex', gap: '8px', flexShrink: 0 }}>
                          <button
                            type="button"
                            onClick={() => handleFileView(file.id)}
                            className="btn btn-primary"
                            style={{ whiteSpace: 'nowrap' }}
                            title="View this file"
                          >
                            View
                          </button>
                          <button
                            type="button"
                            onClick={() => handleFileDelete(file.id)}
                            className="btn btn-outline"
                            style={{ whiteSpace: 'nowrap' }}
                            title="Delete this file"
                          >
                            Delete
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="note" style={{ marginTop: '8px' }}>
                    No files uploaded yet. Upload files that will be available to all chats using this agent.
                  </div>
                )}
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


