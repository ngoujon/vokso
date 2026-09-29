import React, { useEffect, useState } from 'react';
import { apiRequest } from '../../AuthContext';
import { config } from '../../config';

/** Téléchargement authentifié : /invoice-download exige un Bearer token, un simple <a href> ne le fournirait pas. */
async function downloadInvoice(id, number, setError) {
  const token = localStorage.getItem('auth_token');
  try {
    const response = await fetch(`${config.apiUrl}/invoice-download?id=${id}`, {
      headers: token ? { Authorization: `Bearer ${token}` } : {},
    });
    if (!response.ok) {
      const data = await response.json().catch(() => ({}));
      throw new Error(data.error || 'Téléchargement impossible.');
    }
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${number}.pdf`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  } catch (e) {
    setError(e.message);
  }
}

export default function InvoiceHistory() {
  const [invoices, setInvoices] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    apiRequest('invoices')
      .then((data) => setInvoices(data.data))
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return null;
  }

  // Service gratuit : seuls les comptes ayant payé un ancien abonnement
  // ont des factures, la section est masquée pour tous les autres.
  if (invoices.length === 0) {
    return null;
  }

  return (
    <section>
      <h2>Mes factures</h2>
      {error && <p className="auth-error">{error}</p>}
      <table className="data-table">
        <thead>
          <tr>
            <th>Numéro</th>
            <th>Date</th>
            <th>Description</th>
            <th>Montant TTC</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {invoices.map((invoice) => (
            <tr key={invoice.id}>
              <td>{invoice.number}</td>
              <td>{new Date(invoice.issued_at).toLocaleDateString('fr-FR')}</td>
              <td>{invoice.description}</td>
              <td>{Number(invoice.amount_total).toFixed(2)} {invoice.currency}</td>
              <td>
                <button onClick={() => downloadInvoice(invoice.id, invoice.number, setError)}>
                  Télécharger (Factur-X)
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  );
}
