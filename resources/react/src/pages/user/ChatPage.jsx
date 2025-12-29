import React, { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import axios from 'axios';

export default function ChatPage({ onSidebarToggle, onThemeToggle, onRefreshChats }) {
  const { chatId } = useParams();
  const navigate = useNavigate();
  const [chat, setChat] = useState(null);
  const [messages, setMessages] = useState([]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [mode, setMode] = useState('single'); // 'workflow' or 'single'
  const [activeTab, setActiveTab] = useState('settings'); // 'agents', 'workflow-steps', 'settings'
  const [selectedAgent, setSelectedAgent] = useState(null);
  const [agents, setAgents] = useState([]);
  const [attachedFiles, setAttachedFiles] = useState([]);
  const [uploadingFiles, setUploadingFiles] = useState(false);
  const [pollingTimeout, setPollingTimeout] = useState(null);
  const [useDocs, setUseDocs] = useState(true);
  const [useWebSearch, setUseWebSearch] = useState(true);
  const [useExternalApis, setUseExternalApis] = useState(true);
  const [allowedDataSources, setAllowedDataSources] = useState({
    allow_rag: true,
    allow_web_search: true,
    allow_external_apis: true,
    external_api_configs: [],
  });
  const [isFooterMinimized, setIsFooterMinimized] = useState(false);
  const [theme, setTheme] = useState(() => localStorage.getItem('user-theme') || 'light');
  const messagesEndRef = useRef(null);
  const fileInputRef = useRef(null);

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', theme);
  }, [theme]);

  useEffect(() => {
    fetchChat();
    fetchChatFiles(false); // Initial load, not polling
  }, [chatId]);

  // Cleanup polling on unmount
  useEffect(() => {
    return () => {
      if (pollingTimeout) {
        clearTimeout(pollingTimeout);
      }
    };
  }, [pollingTimeout]);

  const fetchChatFiles = async (isPolling = false) => {
    if (!chatId) return;
    
    // Clear any existing timeout before starting new poll
    if (pollingTimeout) {
      clearTimeout(pollingTimeout);
      setPollingTimeout(null);
    }
    
    try {
      const response = await axios.get(`/api/chats/${chatId}/files`);
      // Map server files to attachedFiles format
      const files = (response.data || []).map((file) => ({
        id: file.id,
        name: file.original_name,
        serverFile: file,
        uploaded: file.is_processed && file.is_embedded,
        processing: !file.is_processed || !file.is_embedded,
        createdAt: file.created_at,
      }));
      setAttachedFiles(files);
      
      // Only poll if files are processing AND they were created recently (within last 5 minutes)
      // This prevents infinite polling for old stuck files
      const now = new Date();
      const hasRecentProcessingFiles = files.some(f => {
        if (!f.processing) return false;
        const fileDate = new Date(f.createdAt || f.serverFile?.created_at);
        const minutesSinceCreation = (now - fileDate) / (1000 * 60);
        return minutesSinceCreation < 5; // Only poll files created in last 5 minutes
      });
      
      if (hasRecentProcessingFiles) {
        // Poll more frequently initially (3 seconds), then slow down (10 seconds)
        const pollInterval = isPolling ? 10000 : 3000;
        const timeout = setTimeout(() => {
          fetchChatFiles(true);
        }, pollInterval);
        setPollingTimeout(timeout);
      }
    } catch (error) {
      console.error('Failed to fetch chat files:', error);
      // Stop polling on error
      if (pollingTimeout) {
        clearTimeout(pollingTimeout);
        setPollingTimeout(null);
      }
    }
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  useEffect(() => {
    if (chat?.suite_id) {
      fetchAgents();
    }
  }, [chat?.suite_id]);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  const fetchChat = async () => {
    try {
      const response = await axios.get(`/api/chats/${chatId}`);
      const chatData = response.data;
      setChat(chatData);
      setMessages(chatData.messages || []);
      setMode(chatData.workflow ? 'workflow' : 'single');
      setSelectedAgent(chatData.agent);
      
      // Update allowed data sources from suite
      if (chatData.allowed_data_sources) {
        setAllowedDataSources(chatData.allowed_data_sources);
        // Set defaults based on what's allowed (enable by default if allowed)
        setUseDocs(chatData.allowed_data_sources.allow_rag);
        setUseWebSearch(chatData.allowed_data_sources.allow_web_search);
        setUseExternalApis(chatData.allowed_data_sources.allow_external_apis);
      }
    } catch (error) {
      console.error('Failed to fetch chat:', error);
    }
  };

  const fetchAgents = async () => {
    try {
      const response = await axios.get(`/api/suites/${chat.suite_id}/agents`);
      setAgents(response.data.filter((a) => a.is_active));
    } catch (error) {
      console.error('Failed to fetch agents:', error);
    }
  };

  const sendMessage = async (e) => {
    e.preventDefault();
    if (!input.trim() || loading) return;

    const userMessage = input;
    setInput('');
    setLoading(true);

    // Add user message to UI immediately
    const tempUserMessage = {
      id: Date.now(),
      role: 'user',
      content: userMessage,
      created_at: new Date().toISOString(),
    };
    setMessages((prev) => [...prev, tempUserMessage]);

    try {
      const response = await axios.post(`/api/chats/${chatId}/messages`, {
        message: userMessage,
      });

      // Update with actual response
      setMessages((prev) => [
        ...prev.filter((m) => m.id !== tempUserMessage.id),
        tempUserMessage,
        {
          id: response.data.message?.id || Date.now() + 1,
          role: 'assistant',
          content: response.data.response || 'No response',
          created_at: new Date().toISOString(),
        },
      ]);

      fetchChat(); // Refresh to get all messages
      if (onRefreshChats) onRefreshChats();
    } catch (error) {
      console.error('Failed to send message:', error);
      setMessages((prev) => prev.filter((m) => m.id !== tempUserMessage.id));
      alert('Failed to send message. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const generateReport = async (format) => {
    try {
      const response = await axios.post(`/api/chats/${chatId}/reports/${format}`);
      window.open(response.data.download_url, '_blank');
    } catch (error) {
      console.error('Failed to generate report:', error);
      alert('Failed to generate report. Please try again.');
    }
  };

  const handleFileUpload = async (e) => {
    const files = Array.from(e.target.files);
    if (files.length === 0) return;

    setUploadingFiles(true);

    // Upload each file to the server
    for (const file of files) {
      let tempFileId = null;
      try {
        const formData = new FormData();
        formData.append('file', file);
        // Ensure chatId is sent as integer, not string
        if (chatId) {
          formData.append('chat_id', String(chatId));
        }

        // Add to UI immediately with uploading state
        tempFileId = `temp-${Date.now()}-${Math.random()}`;
        const tempFile = {
          id: tempFileId,
          name: file.name,
          uploading: true,
          uploaded: false,
        };
        setAttachedFiles((prev) => [...prev, tempFile]);

        const response = await axios.post('/api/files', formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        });

        // Update with server response
        setAttachedFiles((prev) =>
          prev.map((f) =>
            f.id === tempFileId
              ? {
                  id: response.data.id,
                  name: file.name,
                  serverFile: response.data,
                  uploading: false,
                  uploaded: response.data.is_processed && response.data.is_embedded,
                  processing: !response.data.is_processed || !response.data.is_embedded,
                }
              : f
          )
        );
        } catch (error) {
          console.error('Failed to upload file:', error);
          const errorMessage = error.response?.data?.message 
            || error.response?.data?.error 
            || (error.response?.data?.errors ? JSON.stringify(error.response.data.errors) : null)
            || `Failed to upload ${file.name}. Please try again.`;
          alert(errorMessage);
          // Remove failed file from list
          if (tempFileId) {
            setAttachedFiles((prev) => prev.filter((f) => f.id !== tempFileId));
          }
        }
    }

    setUploadingFiles(false);

    // Refresh file list from server to get accurate status (start polling if needed)
    setTimeout(() => {
      fetchChatFiles(false);
    }, 2000);

    // Reset file input
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const removeFile = async (index) => {
    const fileToRemove = attachedFiles[index];
    
    // If file was uploaded to server, delete it
    if (fileToRemove?.id) {
      try {
        await axios.delete(`/api/files/${fileToRemove.id}`);
      } catch (error) {
        console.error('Failed to delete file from server:', error);
      }
    }

    // Remove from local state
    setAttachedFiles((prev) => prev.filter((_, i) => i !== index));
  };

  if (!chat) {
    return (
      <div style={{ padding: '40px', textAlign: 'center', color: 'var(--text-soft)' }}>
        Loading...
      </div>
    );
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100vh', maxHeight: '100vh', overflow: 'hidden' }}>
      {/* Topbar */}
      <div className="user-topbar" style={{ flexShrink: 0 }}>
        <div className="topbar-left">
          <div className="topbar-title">
            {chat.suite?.name || 'Suite'} – {chat.agent?.name || chat.workflow?.name || 'Chat'}
          </div>
          <div className="topbar-sub">
            {mode === 'workflow'
              ? 'Use full workflow or single agents with docs, web and external APIs.'
              : 'Single agent mode with docs, web and external APIs.'}
          </div>
        </div>

        <div className="topbar-right">
          <button className="menu-btn" onClick={onSidebarToggle}>
            ☰
          </button>

          {chat.workflow && (
            <div className="workflow-toggle">
              <button
                className={mode === 'workflow' ? 'active' : ''}
                onClick={() => setMode('workflow')}
              >
                Workflow Mode
              </button>
              <button
                className={mode === 'single' ? 'active' : ''}
                onClick={() => setMode('single')}
              >
                Single Agent
              </button>
            </div>
          )}

          <button
            className="theme-btn"
            onClick={() => {
              const newTheme = theme === 'light' ? 'dark' : 'light';
              setTheme(newTheme);
              localStorage.setItem('user-theme', newTheme);
              if (onThemeToggle) onThemeToggle();
            }}
          >
            {theme === 'light' ? '🌙' : '☀️'}
          </button>
        </div>
      </div>

      {/* Agent Tabs */}
      <div className="agent-tabs" style={{ flexShrink: 0 }}>
        <div className="agent-tabs-inner">
          <div
            className={`agent-tab ${activeTab === 'agents' ? 'active' : ''}`}
            onClick={() => setActiveTab('agents')}
          >
            Agents
          </div>
          {chat.workflow && (
            <div
              className={`agent-tab ${activeTab === 'workflow-steps' ? 'active' : ''}`}
              onClick={() => setActiveTab('workflow-steps')}
            >
              Workflow steps
            </div>
          )}
          <div
            className={`agent-tab ${activeTab === 'settings' ? 'active' : ''}`}
            onClick={() => setActiveTab('settings')}
          >
            Settings
          </div>
        </div>
      </div>

      {/* Agent Strip */}
      {activeTab === 'agents' && (
        <div className="agent-strip" style={{ flexShrink: 0 }}>
          {agents.map((agent) => (
            <div
              key={agent.id}
              className={`agent-pill ${selectedAgent?.id === agent.id ? 'active' : ''}`}
              onClick={() => setSelectedAgent(agent)}
            >
              {agent.name}
            </div>
          ))}
        </div>
      )}

      {/* Workflow Steps */}
      {activeTab === 'workflow-steps' && chat.workflow && (
        <div className="agent-strip" style={{ flexShrink: 0 }}>
          <div style={{ fontSize: '12px', color: 'var(--text-soft)' }}>
            Workflow chain: {chat.workflow.agent_sequence?.map((id, idx) => {
              const agent = agents.find((a) => a.id === id);
              return agent?.name || `Agent ${idx + 1}`;
            }).join(' → ') || 'No agents configured'}
          </div>
        </div>
      )}

      {/* Settings */}
      {activeTab === 'settings' && (
        <div className="agent-strip" style={{ flexShrink: 0 }}>
          <div style={{ fontSize: '12px', color: 'var(--text-soft)' }}>
            Settings: {chat.suite?.name} · HTBS tests enabled · External APIs{' '}
            {useExternalApis ? 'on' : 'off'}
          </div>
        </div>
      )}

      {/* Chat Panel */}
      <div className="chat-panel" style={{ flex: '1 1 auto', minHeight: 0, overflowY: 'auto' }}>
        {messages.length === 0 ? (
          <div className="msg agent">
            <div className="msg-label">Agent</div>
            {mode === 'workflow' ? (
              <>
                You are in <strong>Workflow Mode</strong>.<br />
                <br />
                The {chat.suite?.name} chain will run in sequence.
              </>
            ) : (
              <>
                You are using <strong>{selectedAgent?.name || chat.agent?.name || 'Agent'}</strong>{' '}
                in <strong>Single Agent Mode</strong>.<br />
                <br />
                Workflow chaining is disabled in this mode.
              </>
            )}
          </div>
        ) : (
          messages.map((message) => (
            <div key={message.id} className={`msg ${message.role}`}>
              <div className="msg-label">{message.role === 'user' ? 'You' : 'Agent'}</div>
              <div style={{ whiteSpace: 'pre-wrap', wordWrap: 'break-word', overflowWrap: 'break-word' }}>
                {message.content}
              </div>
            </div>
          ))
        )}
        {loading && (
          <div className="msg agent">
            <div className="msg-label">Agent</div>
            <div>Thinking...</div>
          </div>
        )}
        <div ref={messagesEndRef} />
      </div>

      {/* Footer / Input */}
      <div className={`user-footer ${isFooterMinimized ? 'minimized' : ''}`}>
        <div className="footer-grid">
          <div className="footer-header">
            <button
              className="footer-toggle-btn"
              onClick={() => setIsFooterMinimized(!isFooterMinimized)}
              title={isFooterMinimized ? 'Expand' : 'Minimize'}
            >
              {isFooterMinimized ? '+' : '−'}
            </button>
          </div>

          <textarea
            id="userInput"
            className="ask"
            placeholder="Ask something… (e.g. 'Summarize gaps and give expert review checklist')"
            value={input}
            onChange={(e) => setInput(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage(e);
              }
            }}
            disabled={loading}
          />

          <div className="send-row">
            <div className="send-row-left">
              {chat.suite?.name} · Uses docs / web / external APIs · HTBS-tested agents
            </div>
            <div className="send-row-right">
              {!isFooterMinimized && (
                <>
                  <button
                    className="btn-main btn-outline"
                    onClick={() => {
                      // Regenerate last message
                      if (messages.length > 0) {
                        const lastUserMessage = [...messages].reverse().find((m) => m.role === 'user');
                        if (lastUserMessage) {
                          setInput(lastUserMessage.content);
                        }
                      }
                    }}
                  >
                    Regenerate
                  </button>
                  <button
                    className="btn-main btn-outline"
                    onClick={() => generateReport('pdf')}
                  >
                    Download report
                  </button>
                </>
              )}
              <button
                className="btn-main btn-primary"
                onClick={sendMessage}
                disabled={loading || !input.trim()}
              >
                Send
              </button>
            </div>
          </div>

          {/* Upload box - only show if RAG is enabled */}
          {!isFooterMinimized && allowedDataSources.allow_rag && (
          <div className="upload-box">
            <div>
              <strong>Attach documents</strong>
            </div>
            <button
              type="button"
              className="upload-btn"
              onClick={() => fileInputRef.current?.click()}
            >
              Choose files…
            </button>
            <input
              ref={fileInputRef}
              type="file"
              multiple
              style={{ display: 'none' }}
              accept=".pdf,.docx,.xlsx"
              onChange={handleFileUpload}
            />
            <div className="upload-note">
              PDF / DOCX / XLSX — becomes context for gap & evidence agents.
            </div>

            {attachedFiles.length > 0 && (
              <div className="file-chips">
                {attachedFiles.map((file, idx) => (
                  <div key={file.id || idx} className="file-chip">
                    <span>
                      {file.name || file.original_name}
                      {file.uploading && (
                        <span style={{ marginLeft: '6px', fontSize: '10px', color: 'var(--text-soft)' }}>
                          uploading...
                        </span>
                      )}
                      {!file.uploading && file.serverFile && file.serverFile.is_processed && file.serverFile.is_embedded && (
                        <span style={{ marginLeft: '6px', fontSize: '10px', color: 'var(--accent)' }}>
                          ✓ Ready
                        </span>
                      )}
                      {!file.uploading && file.serverFile && (!file.serverFile.is_processed || !file.serverFile.is_embedded) && (
                        <span style={{ marginLeft: '6px', fontSize: '10px', color: '#f59e0b' }}>
                          processing...
                        </span>
                      )}
                      {!file.uploading && !file.serverFile && (
                        <span style={{ marginLeft: '6px', fontSize: '10px', color: '#f59e0b' }}>
                          processing...
                        </span>
                      )}
                      {file.serverFile?.metadata?.processing_error && (
                        <span style={{ marginLeft: '6px', fontSize: '10px', color: '#ef4444' }} title={file.serverFile.metadata.processing_error}>
                          ⚠ Failed
                        </span>
                      )}
                    </span>
                    <button type="button" onClick={() => removeFile(idx)}>
                      ×
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
          )}

          {/* Data source checkboxes - show if any data source is enabled */}
          {/* {!isFooterMinimized && (allowedDataSources.allow_rag || allowedDataSources.allow_web_search || allowedDataSources.allow_external_apis) && (
            <div className="checks" style={{ marginTop: allowedDataSources.allow_rag ? '12px' : '0', padding: '12px 0' }}>
              {allowedDataSources.allow_rag && (
                <label>
                  <input
                    type="checkbox"
                    checked={useDocs}
                    onChange={(e) => setUseDocs(e.target.checked)}
                  />
                  Use documents
                </label>
              )}
              {allowedDataSources.allow_web_search && (
                <label>
                  <input
                    type="checkbox"
                    checked={useWebSearch}
                    onChange={(e) => setUseWebSearch(e.target.checked)}
                  />
                  Use web search
                </label>
              )}
              {allowedDataSources.allow_external_apis && (
                <label>
                  <input
                    type="checkbox"
                    checked={useExternalApis}
                    onChange={(e) => setUseExternalApis(e.target.checked)}
                  />
                  Use external APIs
                </label>
              )}
            </div>
          )} */}
        </div>
      </div>
    </div>
  );
}
