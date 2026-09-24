import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../AuthContext';

export default function ProtectedRoute({ children, requireAdmin = false, skipPasswordCheck = false }) {
  const { user, loading } = useAuth();

  if (loading) {
    return <div className="container"><p>Chargement...</p></div>;
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (user.must_change_password && !skipPasswordCheck) {
    return <Navigate to="/changer-mot-de-passe" replace />;
  }

  if (requireAdmin && user.role !== 'admin') {
    return <Navigate to="/dashboard" replace />;
  }

  return children;
}
