import React, { useEffect, useState } from 'react';
import { apiRequest, downloadAuthenticated, errorMessage } from '../../api';
import type { Invoice } from '../../types';

/**
 * Factures émises du temps des anciens abonnements (conservation légale).
 * Le service est gratuit : la section est masquée pour les comptes sans facture.
 */
export default function InvoiceHistory() {
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    apiRequest<{ data: Invoice[] }>('invoices')
      .then((data) => setInvoices(data.data))
      .catch(() => setInvoices([]));
  }, []);

  if (invoices.length === 0) {
    return null;
  }

  const download = (invoice: Invoice) =>
    downloadAuthenticated(`invoice-download?id=${invoice.id}`, `${invoice.number}.pdf`).catch((err) =>
      setError(errorMessage(err))
    );

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
                <button onClick={() => download(invoice)}>Télécharger (Factur-X)</button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  );
}
