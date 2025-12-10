import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './services/auth';
import AdminLayout from './components/admin/AdminLayout';
import UserLayout from './components/user/UserLayout';
import Login from './pages/auth/Login';

function App() {
  return (
    <AuthProvider>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/admin/*" element={<AdminLayout />} />
        <Route path="/*" element={<UserLayout />} />
      </Routes>
    </AuthProvider>
  );
}

export default App;

