import { useEffect, useState } from 'react'

function App() {
  const [status, setStatus] = useState('loading')
  const [message, setMessage] = useState(null)

  useEffect(() => {
    fetch(`${import.meta.env.VITE_API_BASE_URL}/ping`)
      .then((res) => res.json())
      .then((body) => {
        setMessage(body.data.message)
        setStatus('ok')
      })
      .catch(() => setStatus('error'))
  }, [])

  return (
    <main style={{ fontFamily: 'sans-serif', padding: '2rem' }}>
      <h1>Hospital Bot — Phase 0</h1>
      {status === 'loading' && <p>Calling /api/ping…</p>}
      {status === 'ok' && <p>Backend responded: "{message}"</p>}
      {status === 'error' && (
        <p>Could not reach the backend. Is `php artisan serve` running?</p>
      )}
    </main>
  )
}

export default App
