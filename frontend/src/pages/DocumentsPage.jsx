import { useEffect, useState } from 'react'
import { listDocuments, pollDocumentStatus, retryDocument } from '../api/documents'
import UploadForm from '../components/UploadForm'
import StatusBadge from '../components/StatusBadge'

const ACTIVE_STATUSES = ['pending', 'processing']

export default function DocumentsPage() {
  const [documents, setDocuments] = useState([])
  const [loading, setLoading] = useState(true)
  const [retryingId, setRetryingId] = useState(null)

  useEffect(() => {
    listDocuments()
      .then(setDocuments)
      .finally(() => setLoading(false))
  }, [])

  // Poll while anything is still pending/processing; stop once nothing is
  // active. Re-running this effect on every `documents` change is what
  // lets it re-evaluate "should I keep polling" after each poll — and
  // after an upload — without a stale closure over the old list.
  useEffect(() => {
    const hasActive = documents.some((doc) => ACTIVE_STATUSES.includes(doc.status))
    if (!hasActive) return

    const interval = setInterval(() => {
      pollDocumentStatus().then(setDocuments)
    }, 3000)

    return () => clearInterval(interval)
  }, [documents])

  function handleUploaded(document) {
    setDocuments((prev) => [document, ...prev])
  }

  async function handleRetry(id) {
    setRetryingId(id)
    try {
      const updated = await retryDocument(id)
      setDocuments((prev) => prev.map((doc) => (doc.id === id ? updated : doc)))
    } finally {
      setRetryingId(null)
    }
  }

  return (
    <div style={{ padding: 24, maxWidth: 900, margin: '0 auto' }}>
      <h1>Knowledge base documents</h1>

      <UploadForm onUploaded={handleUploaded} />

      <h2 style={{ marginTop: 32 }}>Documents</h2>
      {loading ? (
        <p>Loading…</p>
      ) : documents.length === 0 ? (
        <p>No documents uploaded yet.</p>
      ) : (
        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
          <thead>
            <tr style={{ textAlign: 'left', borderBottom: '2px solid #e5e7eb' }}>
              <th style={{ padding: 8 }}>Document</th>
              <th style={{ padding: 8 }}>Category</th>
              <th style={{ padding: 8 }}>Chunks</th>
              <th style={{ padding: 8 }}>Status</th>
              <th style={{ padding: 8 }} />
            </tr>
          </thead>
          <tbody>
            {documents.map((doc) => (
              <tr key={doc.id} style={{ borderBottom: '1px solid #f3f4f6' }}>
                <td style={{ padding: 8 }}>{doc.title}</td>
                <td style={{ padding: 8 }}>{doc.category}</td>
                <td style={{ padding: 8 }}>{doc.chunk_count}</td>
                <td style={{ padding: 8 }}>
                  <StatusBadge status={doc.status} />
                  {doc.status === 'failed' && doc.error_message && (
                    <div style={{ color: '#dc2626', fontSize: 13, marginTop: 4 }}>{doc.error_message}</div>
                  )}
                </td>
                <td style={{ padding: 8 }}>
                  {doc.status === 'failed' && (
                    <button onClick={() => handleRetry(doc.id)} disabled={retryingId === doc.id}>
                      {retryingId === doc.id ? 'Retrying…' : 'Retry'}
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  )
}
