export default function SourceList({ sources }) {
  if (!sources || sources.length === 0) return null

  return (
    <div style={{ marginTop: 6, fontSize: 13, color: '#4b5563' }}>
      Sources:{' '}
      {sources.map((source, i) => (
        <span key={i}>
          {i > 0 && ', '}
          {source.document_title} ({source.score.toFixed(2)})
        </span>
      ))}
    </div>
  )
}
