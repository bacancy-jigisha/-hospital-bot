// The most useful part of the app for explaining RAG/agents to someone
// else: exactly which tools the agent chose to call, with what arguments,
// and the similarity scores behind any retrieved chunks.
export default function TracePanel({ toolTrace, sources }) {
  if (!toolTrace || toolTrace.length === 0) return null

  return (
    <details style={{ marginTop: 6, fontSize: 13 }}>
      <summary style={{ cursor: 'pointer', color: '#4b5563' }}>How this answer was produced</summary>
      <div style={{ marginTop: 8, paddingLeft: 12, borderLeft: '2px solid #e5e7eb' }}>
        <ol style={{ margin: 0, paddingLeft: 16 }}>
          {toolTrace.map((step, i) => (
            <li key={i} style={{ marginBottom: 6 }}>
              <code>{step.tool}</code>
              {' — arguments: '}
              <code>{JSON.stringify(step.arguments)}</code>
              {' — '}
              {step.result_size} result(s) in {step.duration_ms}ms
            </li>
          ))}
        </ol>

        {sources && sources.length > 0 && (
          <>
            <strong>Retrieved chunks and similarity scores:</strong>
            <ul style={{ margin: '4px 0 0', paddingLeft: 16 }}>
              {sources.map((source, i) => (
                <li key={i}>
                  {source.document_title} — score {source.score.toFixed(3)}
                </li>
              ))}
            </ul>
          </>
        )}
      </div>
    </details>
  )
}
