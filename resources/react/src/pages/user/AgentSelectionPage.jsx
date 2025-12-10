import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import axios from 'axios';

export default function AgentSelectionPage() {
  const { suiteId } = useParams();
  const navigate = useNavigate();
  const [suite, setSuite] = useState(null);
  const [agents, setAgents] = useState([]);
  const [workflows, setWorkflows] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchData();
  }, [suiteId]);

  const fetchData = async () => {
    try {
      const [suiteRes, agentsRes, workflowsRes] = await Promise.all([
        axios.get(`/api/suites/${suiteId}`),
        axios.get(`/api/suites/${suiteId}/agents`),
        axios.get(`/api/suites/${suiteId}/workflows`),
      ]);
      setSuite(suiteRes.data);
      setAgents(agentsRes.data.filter((a) => a.is_active));
      setWorkflows(workflowsRes.data.filter((w) => w.is_active));
    } catch (error) {
      console.error('Failed to fetch data:', error);
    } finally {
      setLoading(false);
    }
  };

  const startAgentChat = async (agentId) => {
    try {
      const response = await axios.post('/api/chats', {
        suite_id: suiteId,
        agent_id: agentId,
      });
      navigate(`/chat/${response.data.id}`);
    } catch (error) {
      console.error('Failed to create chat:', error);
      alert('Failed to start chat. Please try again.');
    }
  };

  const startWorkflow = async (workflowId) => {
    try {
      const response = await axios.post('/api/chats', {
        suite_id: suiteId,
        workflow_id: workflowId,
      });
      navigate(`/chat/${response.data.id}`);
    } catch (error) {
      console.error('Failed to start workflow:', error);
      alert('Failed to start workflow. Please try again.');
    }
  };

  const getFeatureBadges = (agent) => {
    const badges = [];
    if (agent.enable_rag) badges.push({ label: 'Docs', color: 'blue' });
    if (agent.enable_web_search) badges.push({ label: 'Web Search', color: 'green' });
    if (agent.external_api_configs && agent.external_api_configs.length > 0) {
      badges.push({ label: 'API Data', color: 'purple' });
    }
    return badges;
  };

  if (loading) {
    return (
      <div className="flex h-screen items-center justify-center">
        <div className="text-center">
          <div className="mb-4 inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-blue-600 border-r-transparent"></div>
          <p className="text-gray-600">Loading agents...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-7xl p-8">
      <div className="mb-8">
        <button
          onClick={() => navigate('/')}
          className="mb-4 flex items-center text-sm text-gray-600 hover:text-gray-900"
        >
          <svg
            className="mr-2 h-4 w-4"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M15 19l-7-7 7-7"
            />
          </svg>
          Back to Suites
        </button>
        <h1 className="mb-2 text-3xl font-bold text-gray-900">{suite?.name}</h1>
        <p className="text-gray-600">{suite?.description}</p>
      </div>

      {/* Workflows Section */}
      {workflows.length > 0 && (
        <div className="mb-12">
          <h2 className="mb-4 text-xl font-semibold text-gray-900">Workflows</h2>
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {workflows.map((workflow) => (
              <div
                key={workflow.id}
                className="rounded-lg border-2 border-dashed border-blue-300 bg-blue-50 p-6"
              >
                <div className="mb-2 flex items-center gap-2">
                  <svg
                    className="h-5 w-5 text-blue-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M13 10V3L4 14h7v7l9-11h-7z"
                    />
                  </svg>
                  <h3 className="text-lg font-semibold text-gray-900">
                    {workflow.name}
                  </h3>
                </div>
                <p className="mb-4 text-sm text-gray-600">{workflow.description}</p>
                <div className="mb-4 text-xs text-gray-500">
                  {workflow.agent_sequence?.length || 0} agents in sequence
                </div>
                <button
                  onClick={() => startWorkflow(workflow.id)}
                  className="w-full rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                >
                  Run Full Workflow
                </button>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Agents Section */}
      <div>
        <h2 className="mb-4 text-xl font-semibold text-gray-900">Agents</h2>
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {agents.map((agent) => {
            const badges = getFeatureBadges(agent);
            return (
              <div
                key={agent.id}
                className="rounded-lg border bg-white p-6 shadow-md hover:shadow-lg transition-shadow"
              >
                <div className="mb-4">
                  <h3 className="mb-2 text-lg font-semibold text-gray-900">
                    {agent.name}
                  </h3>
                  <p className="mb-3 text-sm text-gray-600">{agent.description}</p>

                  <div className="mb-3 space-y-2">
                    <div className="flex items-center gap-2 text-xs text-gray-500">
                      <span className="font-medium">Model:</span>
                      <span className="rounded bg-gray-100 px-2 py-1">
                        {agent.model_provider}/{agent.model_name}
                      </span>
                    </div>

                    {badges.length > 0 && (
                      <div className="flex flex-wrap gap-2">
                        {badges.map((badge, idx) => (
                          <span
                            key={idx}
                            className={`rounded-full px-2 py-1 text-xs font-medium ${
                              badge.color === 'blue'
                                ? 'bg-blue-100 text-blue-800'
                                : badge.color === 'green'
                                ? 'bg-green-100 text-green-800'
                                : 'bg-purple-100 text-purple-800'
                            }`}
                          >
                            {badge.label}
                          </span>
                        ))}
                      </div>
                    )}
                  </div>
                </div>

                <button
                  onClick={() => startAgentChat(agent.id)}
                  className="w-full rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                >
                  Start
                </button>
              </div>
            );
          })}
        </div>
      </div>

      {agents.length === 0 && workflows.length === 0 && (
        <div className="mt-12 text-center">
          <p className="text-gray-500">No agents or workflows available in this suite.</p>
        </div>
      )}
    </div>
  );
}





