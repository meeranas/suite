import React, { useState } from 'react';
import axios from 'axios';

export default function TokenGenerator({ onTokenGenerated }) {
  const [email, setEmail] = useState('admin@aihub.com');
  const [password, setPassword] = useState('password');
  const [loading, setLoading] = useState(false);
  const [token, setToken] = useState('');
  const [error, setError] = useState('');

  const generateToken = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      const response = await axios.post('/api/test/auth/login', {
        email,
        password,
      });

      const generatedToken = response.data.token;
      setToken(generatedToken);

      if (onTokenGenerated) {
        onTokenGenerated(generatedToken);
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to generate token');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 mb-4">
      <h3 className="text-sm font-semibold text-blue-900 mb-2">
        🧪 Development Token Generator
      </h3>
      <form onSubmit={generateToken} className="space-y-2">
        <div>
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="Email"
            className="w-full rounded border px-3 py-2 text-sm"
            required
          />
        </div>
        <div>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="Password"
            className="w-full rounded border px-3 py-2 text-sm"
            required
          />
        </div>
        <button
          type="submit"
          disabled={loading}
          className="w-full rounded bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700 disabled:opacity-50"
        >
          {loading ? 'Generating...' : 'Generate Token'}
        </button>
      </form>

      {error && (
        <div className="mt-2 rounded bg-red-100 p-2 text-sm text-red-700">
          {error}
        </div>
      )}

      {token && (
        <div className="mt-2">
          <p className="text-xs text-blue-700 mb-1">Generated Token:</p>
          <textarea
            readOnly
            value={token}
            className="w-full rounded border bg-white px-2 py-1 text-xs font-mono"
            rows="3"
            onClick={(e) => e.target.select()}
          />
          <p className="text-xs text-blue-600 mt-1">
            Copy this token and paste it in the login form above
          </p>
        </div>
      )}
    </div>
  );
}





