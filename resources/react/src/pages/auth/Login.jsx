import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../services/auth';
import axios from 'axios';
import TokenGenerator from '../../components/common/TokenGenerator';

export default function Login() {
  const [token, setToken] = useState('');
  const [error, setError] = useState('');
  const { login } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    try {
      // Store token and set auth header
      localStorage.setItem('jwt_token', token);
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

      // Verify token by fetching user
      const response = await axios.get('/api/user');
      
      if (response.data) {
        login(token);
        // Redirect based on role
        if (response.data.roles?.includes('admin')) {
          navigate('/admin/suites');
        } else {
          navigate('/');
        }
      }
    } catch (err) {
      setError('Invalid token. Please check your JWT token.');
      localStorage.removeItem('jwt_token');
      delete axios.defaults.headers.common['Authorization'];
    }
  };

  const handleTokenGenerated = (generatedToken) => {
    setToken(generatedToken);
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-100">
      <div className="w-full max-w-md rounded-lg bg-white p-8 shadow-md">
        <h2 className="mb-6 text-2xl font-bold">AI Control Hub</h2>
        
        {/* Development Token Generator */}
        <TokenGenerator onTokenGenerated={handleTokenGenerated} />
        
        <form onSubmit={handleSubmit}>
          <div className="mb-4">
            <label className="mb-2 block text-sm font-medium text-gray-700">
              JWT Token
            </label>
            <textarea
              value={token}
              onChange={(e) => setToken(e.target.value)}
              className="w-full rounded border border-gray-300 px-3 py-2"
              rows="4"
              placeholder="Paste your JWT token here"
              required
            />
          </div>
          {error && (
            <div className="mb-4 rounded bg-red-100 p-3 text-sm text-red-700">
              {error}
            </div>
          )}
          <button
            type="submit"
            className="w-full rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
          >
            Login
          </button>
        </form>
        <p className="mt-4 text-center text-sm text-gray-600">
          Get your JWT token from the main platform
        </p>
      </div>
    </div>
  );
}

