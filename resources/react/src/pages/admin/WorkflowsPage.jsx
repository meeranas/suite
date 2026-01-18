import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';

export default function WorkflowsPage() {
  const navigate = useNavigate();
  const [workflows, setWorkflows] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchWorkflows();
  }, []);

  const fetchWorkflows = async () => {
    try {
      const response = await axios.get('/api/workflows');
      setWorkflows(response.data);
    } catch (error) {
      console.error('Failed to fetch workflows:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) return <div>Loading...</div>;

  const handleDelete = async (workflowId) => {
    if (!confirm('Are you sure you want to delete this workflow?')) return;
    try {
      await axios.delete(`/api/workflows/${workflowId}`);
      fetchWorkflows();
    } catch (error) {
      console.error('Failed to delete workflow:', error);
      alert('Failed to delete workflow');
    }
  };

  return (
    <div className="">
      <section className="panel">
        <div className="panel-header">
          <div className="panel-title">Workflows</div>
          <button
            onClick={() => navigate('/admin/workflows/create')}
            className="btn btn-outline"
            style={{ fontSize: '11px' }}
          >
            + New Workflow
          </button>
        </div>
        <div className="panel-sub">
          Each workflow is an ordered chain of agents for a user role.
        </div>
        <table className="admin-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Suite</th>
              <th>Steps</th>
              <th>Enabled</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {workflows.map((workflow) => (
              <tr
                key={workflow.id}
                style={{ cursor: 'pointer' }}
                onClick={() => navigate(`/admin/workflows/${workflow.id}/edit`)}
              >
                <td>
                  <div style={{ fontWeight: 500 }}>{workflow.name}</div>
                  {workflow.description && (
                    <div
                      style={{
                        fontSize: '10px',
                        color: 'var(--text-muted)',
                        marginTop: '2px',
                      }}
                    >
                      {workflow.description}
                    </div>
                  )}
                </td>
                <td>{workflow.suite?.name || '-'}</td>
                <td>{workflow.agent_sequence?.length || 0}</td>
                <td>
                  <span
                    className={`toggle ${workflow.is_active ? 'on' : ''}`}
                    onClick={async (e) => {
                      e.stopPropagation();
                      try {
                        await axios.put(`/api/workflows/${workflow.id}`, {
                          is_active: !workflow.is_active,
                        });
                        fetchWorkflows();
                      } catch (error) {
                        console.error('Failed to toggle workflow:', error);
                        alert('Failed to update workflow status');
                      }
                    }}
                  ></span>
                </td>
                <td>
                  <button
                    className="btn btn-sm btn-outline"
                    onClick={(e) => {
                      e.stopPropagation();
                      navigate(`/admin/workflows/${workflow.id}/edit`);
                    }}
                    style={{ fontSize: '11px', padding: '4px 8px' }}
                  >
                    Edit
                  </button>
                  <button
                    className="btn btn-sm btn-outline"
                    onClick={async (e) => {
                      e.stopPropagation();
                      if (confirm('Are you sure you want to delete this workflow?')) {
                        try {
                          await axios.delete(`/api/workflows/${workflow.id}`);
                          fetchWorkflows();
                        } catch (error) {
                          console.error('Failed to delete workflow:', error);
                          alert('Failed to delete workflow');
                        }
                      }
                    }}
                    style={{ fontSize: '11px', padding: '4px 8px', marginLeft: '4px', color: '#ef4444' }}
                  >
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {workflows.length === 0 && (
          <div
            style={{
              textAlign: 'center',
              padding: '20px',
              color: 'var(--text-muted)',
            }}
          >
            No workflows found. Create your first workflow!
          </div>
        )}
      </section>
    </div>
  );
}

