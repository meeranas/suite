import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';

export default function ChatsListPage() {
  const [chats, setChats] = useState([]);
  const [suites, setSuites] = useState([]);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    try {
      const [chatsRes, suitesRes] = await Promise.all([
        axios.get('/api/chats'),
        axios.get('/api/suites'),
      ]);
      setChats(chatsRes.data.data || chatsRes.data);
      setSuites(suitesRes.data);
    } catch (error) {
      console.error('Failed to fetch data:', error);
    } finally {
      setLoading(false);
    }
  };

  const createChat = async (suiteId) => {
    try {
      const response = await axios.post('/api/chats', {
        suite_id: suiteId,
      });
      navigate(`/chat/${response.data.id}`);
    } catch (error) {
      console.error('Failed to create chat:', error);
    }
  };

  if (loading) return <div className="p-8">Loading...</div>;

  return (
    <div className="mx-auto max-w-7xl p-8">
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-bold">Your Chats</h1>
      </div>

      {/* Available Suites */}
      <div className="mb-8">
        <h2 className="mb-4 text-lg font-semibold">Start New Chat</h2>
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {suites
            .filter((s) => s.status === 'active')
            .map((suite) => (
              <div
                key={suite.id}
                className="cursor-pointer rounded-lg bg-white p-6 shadow hover:shadow-lg"
                onClick={() => createChat(suite.id)}
              >
                <h3 className="text-lg font-semibold">{suite.name}</h3>
                <p className="mt-2 text-sm text-gray-600">{suite.description}</p>
              </div>
            ))}
        </div>
      </div>

      {/* Chat History */}
      <div>
        <h2 className="mb-4 text-lg font-semibold">Recent Chats</h2>
        <div className="space-y-2">
          {chats.length === 0 ? (
            <p className="text-gray-500">No chats yet. Start a new chat above!</p>
          ) : (
            chats.map((chat) => (
              <div
                key={chat.id}
                onClick={() => navigate(`/chat/${chat.id}`)}
                className="cursor-pointer rounded-lg bg-white p-4 shadow hover:shadow-lg"
              >
                <h3 className="font-semibold">{chat.title || 'Untitled Chat'}</h3>
                <p className="mt-1 text-sm text-gray-600">
                  {chat.last_message_at
                    ? new Date(chat.last_message_at).toLocaleString()
                    : 'No messages'}
                </p>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  );
}

