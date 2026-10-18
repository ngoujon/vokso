import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../lib/AuthContext';

interface Props {
  children: React.ReactElement;
  requireAdmin?: boolean;
  skipPasswordCheck?: boolean;
}

/**
 * Garde des pages connectées : renvoie vers /login sans session, vers le
 * changement de mot de passe s'il est imposé, et refuse /admin aux non-admins.
 */
export default function ProtectedRoute({ children, requireAdmin = false, skipPasswordCheck = false }: Props) {
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
