import React, { useState, useEffect } from 'react';
import { Routes, Route, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../services/auth';
import axios from 'axios';
import ChatPage from '../../pages/user/ChatPage';
import SuiteSelectionPage from '../../pages/user/SuiteSelectionPage';
import AgentSelectionPage from '../../pages/user/AgentSelectionPage';
import '../../styles/user.css';

export default function UserLayout() {
  const { user, loading: authLoading, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [theme, setTheme] = useState('light');
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [suites, setSuites] = useState([]);
  const [chats, setChats] = useState([]);
  const [selectedSuite, setSelectedSuite] = useState(null);

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  // Redirect to login if not authenticated
  useEffect(() => {
    if (!authLoading && !user) {
      navigate('/login');
    }
  }, [user, authLoading, navigate]);

  useEffect(() => {
    const savedTheme = localStorage.getItem('user-theme') || 'light';
    setTheme(savedTheme);
    document.documentElement.setAttribute('data-theme', savedTheme);
    
    // Only fetch if user is authenticated
    if (user) {
      fetchSuites();
      fetchChats();
    }
  }, [user]);

  const toggleTheme = () => {
    const newTheme = theme === 'light' ? 'dark' : 'light';
    setTheme(newTheme);
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('user-theme', newTheme);
  };

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
    }
  };

  const fetchChats = async () => {
    try {
      const response = await axios.get('/api/chats');
      // Ensure we always have an array
      const chatsData = response.data?.data || response.data || [];
      setChats(Array.isArray(chatsData) ? chatsData : []);
    } catch (error) {
      console.error('Failed to fetch chats:', error);
      setChats([]);
      // If unauthorized, redirect to login
      if (error.response?.status === 401) {
        navigate('/login');
      }
    }
  };

  const handleSidebarToggle = () => {
    if (window.innerWidth <= 1100) {
      setDrawerOpen(!drawerOpen);
    } else {
      setSidebarCollapsed(!sidebarCollapsed);
    }
  };

  const handleSuiteClick = (suite) => {
    setSelectedSuite(suite);
    navigate(`/suite/${suite.id}/agents`);
  };

  const handleChatClick = (chat) => {
    navigate(`/chat/${chat.id}`);
    setDrawerOpen(false);
  };

  const isMobile = window.innerWidth <= 1100;

  // Show loading or redirect if not authenticated
  if (authLoading) {
    return (
      <div style={{ padding: '40px', textAlign: 'center', color: 'var(--text-soft)' }}>
        Loading...
      </div>
    );
  }

  if (!user) {
    return null; // Will redirect to login
  }

  return (
    <div className={`user-shell ${sidebarCollapsed ? 'collapsed-sidebar' : ''}`}>
      {/* Left Sidebar */}
      <aside className={`user-sidebar ${drawerOpen ? 'drawer-open' : ''}`}>
        <div className="sidebar-top-row">
          <div className="user-logo">Agent Hub</div>
          <div className="sidebar-labels">{user?.email || 'User'}</div>
        </div>
        
        {/* Logout Button */}
        <button
          className="logout-btn"
          onClick={handleLogout}
          title="Logout"
        >
          <span className="logout-icon">🚪</span>
          {!sidebarCollapsed && <span className="sidebar-labels">Logout</span>}
        </button>

        <div className="sidebar-section-title">Suites</div>
        {suites.map((suite) => (
          <div
            key={suite.id}
            className={`suite-card ${selectedSuite?.id === suite.id ? 'active' : ''}`}
            onClick={() => handleSuiteClick(suite)}
          >
            <div className="suite-name">{suite.name}</div>
            <div className="suite-meta">
              {suite.description || `${suite.agents?.length || 0} agents`}
            </div>
          </div>
        ))}

        <div className="sidebar-section-title">Chat History</div>
        {Array.isArray(chats) && chats.slice(0, 10).map((chat) => (
          <div
            key={chat.id}
            className="history-item"
            onClick={() => handleChatClick(chat)}
          >
            <span className="history-title">{chat.title || 'Untitled Chat'}</span>
            <span className="history-sub">
              {chat.suite?.name || 'No suite'} · {chat.messages?.length || 0} messages
            </span>
          </div>
        ))}
        {(!Array.isArray(chats) || chats.length === 0) && (
          <div style={{ fontSize: '11px', color: 'var(--text-soft)', padding: '8px' }}>
            No chat history
          </div>
        )}

        {/* Moved from right sidebar */}
        {location.pathname.startsWith('/chat/') && (
          <>
            <div className="sidebar-section-title" style={{ marginTop: '20px' }}>Dashboard</div>
            <div className="side-card" style={{ marginBottom: '10px' }}>
              <ul className="side-list" style={{ margin: 0, paddingLeft: '16px' }}>
                <li>Chat runs (30d): {Array.isArray(chats) ? chats.length : 0}</li>
                <li>Documents added: {Array.isArray(chats) ? chats.reduce((sum, c) => sum + (c.files?.length || 0), 0) : 0}</li>
                <li>External APIs: Active</li>
              </ul>
            </div>

            <div className="sidebar-section-title">Current User</div>
            <div className="side-card" style={{ marginBottom: '10px' }}>
              <ul className="side-list" style={{ margin: 0, paddingLeft: '16px' }}>
                <li>User: <strong>{user?.email}</strong></li>
                <li>Plan: {user?.subscription_tier || 'Free'}</li>
              </ul>
            </div>

            <div className="sidebar-section-title">Example Actions</div>
            <div className="side-card">
              <ul className="side-list" style={{ margin: 0, paddingLeft: '16px' }}>
                <li>"Summarize my documents"</li>
                <li>"Run full workflow"</li>
                <li>"Generate report"</li>
              </ul>
            </div>
          </>
        )}
      </aside>

      {/* Backdrop for mobile drawer */}
      {drawerOpen && isMobile && (
        <div
          className="drawer-backdrop"
          onClick={() => setDrawerOpen(false)}
        />
      )}

      {/* Main Content */}
      <main className="user-main">
        <Routes>
          <Route path="/" element={<SuiteSelectionPage />} />
          <Route path="/suite/:suiteId/agents" element={<AgentSelectionPage />} />
          <Route
            path="/chat/:chatId"
            element={
              <ChatPage
                onSidebarToggle={handleSidebarToggle}
                onThemeToggle={toggleTheme}
                onRefreshChats={fetchChats}
              />
            }
          />
        </Routes>
      </main>
    </div>
  );
}
