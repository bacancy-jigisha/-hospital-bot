import SourceList from './SourceList'
import TracePanel from './TracePanel'

export default function ChatMessage({ message }) {
  const isUser = message.role === 'user'

  return (
    <div style={{ display: 'flex', justifyContent: isUser ? 'flex-end' : 'flex-start', marginBottom: 12 }}>
      <div
        style={{
          maxWidth: '75%',
          background: isUser ? '#3b82f6' : '#f3f4f6',
          color: isUser ? 'white' : 'black',
          borderRadius: 12,
          padding: '8px 12px',
          whiteSpace: 'pre-wrap',
        }}
      >
        {message.content}
        {!isUser && <SourceList sources={message.sources} />}
        {!isUser && <TracePanel toolTrace={message.tool_trace} sources={message.sources} />}
      </div>
    </div>
  )
}
