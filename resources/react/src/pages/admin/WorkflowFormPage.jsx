import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import axios from 'axios';

export default function WorkflowFormPage() {
  const { suiteId, workflowId } = useParams();
  const navigate = useNavigate();
  const isEdit = !!workflowId;

  const [loading, setLoading] = useState(false);
  const [suites, setSuites] = useState([]);
  const [agents, setAgents] = useState([]);
  const [selectedAgents, setSelectedAgents] = useState([]);

  const [formData, setFormData] = useState({
    suite_id: suiteId || '',
    name: '',
    description: '',
    agent_sequence: [],
    workflow_config: {
      stop_on_error: false,
    },
    is_active: true,
  });

  useEffect(() => {
    fetchInitialData();
    if (isEdit) {
      fetchWorkflow();
    }
  }, [workflowId, suiteId]);

  useEffect(() => {
    if (formData.suite_id) {
      fetchAgents();
    }
  }, [formData.suite_id]);

  const fetchInitialData = async () => {
    try {
      const response = await axios.get('/api/suites');
      setSuites(response.data);
    } catch (error) {
      console.error('Failed to fetch suites:', error);
    }
  };

  const fetchAgents = async () => {
    try {
      const response = await axios.get(`/api/suites/${formData.suite_id}/agents`);
      setAgents(response.data.filter((a) => a.is_active));
    } catch (error) {
      console.error('Failed to fetch agents:', error);
    }
  };

  const fetchWorkflow = async () => {
    try {
      setLoading(true);
      const response = await axios.get(`/api/workflows/${workflowId}`);
      const workflow = response.data;
      setFormData({
        suite_id: workflow.suite_id,
        name: workflow.name,
        description: workflow.description || '',
        agent_sequence: workflow.agent_sequence || [],
        workflow_config: workflow.workflow_config || { stop_on_error: false },
        is_active: workflow.is_active !== false,
      });
      setSelectedAgents(workflow.agent_sequence || []);
    } catch (error) {
      console.error('Failed to fetch workflow:', error);
      alert('Failed to load workflow data');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      setLoading(true);
      const url = isEdit
        ? `/api/workflows/${workflowId}`
        : `/api/suites/${formData.suite_id}/workflows`;
      const method = isEdit ? 'put' : 'post';

      await axios[method](url, {
        ...formData,
        agent_sequence: selectedAgents,
      });
      navigate('/admin/workflows');
    } catch (error) {
      console.error('Failed to save workflow:', error);
      alert('Failed to save workflow. Please check all fields.');
    } finally {
      setLoading(false);
    }
  };

  const addAgentToSequence = (agentId) => {
    if (!selectedAgents.includes(agentId)) {
      setSelectedAgents([...selectedAgents, agentId]);
    }
  };

  const removeAgentFromSequence = (index) => {
    setSelectedAgents(selectedAgents.filter((_, i) => i !== index));
  };

  const moveAgent = (index, direction) => {
    const newSequence = [...selectedAgents];
    const targetIndex = direction === 'up' ? index - 1 : index + 1;
    if (targetIndex >= 0 && targetIndex < newSequence.length) {
      [newSequence[index], newSequence[targetIndex]] = [
        newSequence[targetIndex],
        newSequence[index],
      ];
      setSelectedAgents(newSequence);
    }
  };

  if (loading && isEdit) {
    return (
      <div className="panel" style={{ textAlign: 'center', padding: '40px' }}>
        <div style={{ color: 'var(--text-muted)' }}>Loading...</div>
      </div>
    );
  }

  const availableAgents = agents.filter(
    (a) => !selectedAgents.includes(a.id)
  );

  return (
    <div className="grid-two">
      <section className="panel">
        <div className="panel-title">Edit workflow</div>
        <div className="panel-sub">Simple ordered list – no drag & drop needed.</div>

        <form onSubmit={handleSubmit}>
          <div className="field-label">Workflow name</div>
          <input
            type="text"
            value={formData.name}
            onChange={(e) =>
              setFormData({ ...formData, name: e.target.value })
            }
            className="input"
            required
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

          <div className="field-label">Description</div>
          <textarea
            value={formData.description}
            onChange={(e) =>
              setFormData({ ...formData, description: e.target.value })
            }
            className="input"
            rows="2"
          />

          <div className="field-label">Steps in chain (one per line)</div>
          <textarea
            value={selectedAgents
              .map((agentId, index) => {
                const agent = agents.find((a) => a.id === agentId);
                return `${index + 1}. ${agent?.name || 'Unknown Agent'}`;
              })
              .join('\n')}
            onChange={(e) => {
              // Parse textarea input to update sequence
              const lines = e.target.value.split('\n').filter((line) => line.trim());
              const newSequence = lines.map((line) => {
                const match = line.match(/\d+\.\s*(.+)/);
                if (match) {
                  const agentName = match[1].trim();
                  const agent = agents.find((a) => a.name === agentName);
                  return agent?.id;
                }
                return null;
              }).filter((id) => id !== null);
              setSelectedAgents(newSequence);
            }}
            className="input"
            rows="6"
            placeholder="1. Agent Name 1&#10;2. Agent Name 2&#10;3. Agent Name 3"
          />
          <div className="note">
            Example: Agent 1 feeds into Agents 2 & 3; order defines sequence when "Run full workflow" is used.
          </div>

          {/* Add Agent Dropdown */}
          {availableAgents.length > 0 && (
            <>
              <div className="field-label">Add Agent to Sequence</div>
              <select
                onChange={(e) => {
                  if (e.target.value) {
                    addAgentToSequence(parseInt(e.target.value));
                    e.target.value = '';
                  }
                }}
                className="input"
              >
                <option value="">Select an agent...</option>
                {availableAgents.map((agent) => (
                  <option key={agent.id} value={agent.id}>
                    {agent.name}
                  </option>
                ))}
              </select>
            </>
          )}

          {formData.suite_id && agents.length === 0 && (
            <div className="note" style={{ color: '#f59e0b' }}>
              No active agents available in this suite.
            </div>
          )}

          <div className="field-label">Options</div>
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
                checked={formData.workflow_config.stop_on_error}
                onChange={(e) =>
                  setFormData({
                    ...formData,
                    workflow_config: {
                      ...formData.workflow_config,
                      stop_on_error: e.target.checked,
                    },
                  })
                }
                style={{ width: '14px', height: '14px' }}
              />
              <span className="pill-chip">Stop on Policy Guard violation</span>
            </label>
          </div>

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
              justifyContent: 'flex-end',
              gap: '8px',
            }}
          >
            <button
              type="button"
              onClick={() => navigate('/admin/workflows')}
              className="btn btn-outline"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={loading || selectedAgents.length === 0}
              className="btn btn-primary"
            >
              {loading ? 'Saving...' : isEdit ? 'Update Workflow' : 'Save workflow'}
            </button>
          </div>
        </form>
      </section>
    </div>
  );
}


