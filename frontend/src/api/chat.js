import client from './client'

export function sendMessage(sessionId, message) {
  return client
    .post('/chat', { session_id: sessionId, message })
    .then((res) => res.data.data)
}

export function getHistory(sessionId) {
  return client.get(`/chat/${sessionId}`).then((res) => res.data.data)
}
