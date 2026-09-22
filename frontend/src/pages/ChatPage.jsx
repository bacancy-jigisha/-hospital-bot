import { useEffect, useRef, useState } from 'react'
import { getHistory, sendMessage } from '../api/chat'
import ChatMessage from '../components/ChatMessage'

const SESSION_KEY = 'hospital-bot-session-id'

// Duplicated from the backend's HOSPITAL_EMERGENCY_PHONE (see .env) — there
// is no config-exposure endpoint in v1, so this is hardcoded here too.
const EMERGENCY_PHONE = '+1-555-0111'

function loadOrCreateSessionId() {
  const existing = localStorage.getItem(SESSION_KEY)
  if (existing) return existing

  const created = crypto.randomUUID()
  localStorage.setItem(SESSION_KEY, created)
  return created
}

export default function ChatPage() {
  const [sessionId, setSessionId] = useState(loadOrCreateSessionId)
  const [messages, setMessages] = useState([])
  const [input, setInput] = useState('')
  const [sending, setSending] = useState(false)
  const [loadingHistory, setLoadingHistory] = useState(true)
  const bottomRef = useRef(null)

  useEffect(() => {
    setLoadingHistory(true)
    getHistory(sessionId)
      .then(setMessages)
      .finally(() => setLoadingHistory(false))
  }, [sessionId])

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  async function handleSend(event) {
    event.preventDefault()
    const question = input.trim()
    if (!question || sending) return

    // Shown immediately so the conversation feels responsive — the
    // backend only returns the assistant's reply, not an echo of this.
    setMessages((prev) => [...prev, { role: 'user', content: question }])
    setInput('')
    setSending(true)

    try {
      const reply = await sendMessage(sessionId, question)
      setMessages((prev) => [...prev, reply])
    } catch {
      setMessages((prev) => [
        ...prev,
        { role: 'assistant', content: 'Something went wrong sending that message. Please try again.' },
      ])
    } finally {
      setSending(false)
    }
  }

  function handleNewConversation() {
    const created = crypto.randomUUID()
    localStorage.setItem(SESSION_KEY, created)
    setSessionId(created)
    setMessages([])
  }

  return (
    <div style={{ padding: 24, maxWidth: 700, margin: '0 auto', display: 'flex', flexDirection: 'column', height: '100vh', boxSizing: 'border-box' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1>Hospital information chat</h1>
        <button onClick={handleNewConversation}>New conversation</button>
      </div>

      <p style={{ fontSize: 13, color: '#6b7280', borderTop: '1px solid #e5e7eb', borderBottom: '1px solid #e5e7eb', padding: '8px 0' }}>
        General hospital information only, not medical advice. In an emergency, call {EMERGENCY_PHONE}.
      </p>

      <div style={{ flex: 1, overflowY: 'auto', padding: '12px 0' }}>
        {loadingHistory ? (
          <p>Loading conversation…</p>
        ) : (
          messages.map((message, i) => <ChatMessage key={message.id ?? i} message={message} />)
        )}
        {sending && <p style={{ color: '#6b7280' }}>Thinking…</p>}
        <div ref={bottomRef} />
      </div>

      <form onSubmit={handleSend} style={{ display: 'flex', gap: 8 }}>
        <input
          type="text"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          maxLength={1000}
          placeholder="Ask about departments, doctors, visiting hours…"
          disabled={sending}
          style={{ flex: 1 }}
        />
        <button type="submit" disabled={sending || !input.trim()}>
          Send
        </button>
      </form>
    </div>
  )
}
