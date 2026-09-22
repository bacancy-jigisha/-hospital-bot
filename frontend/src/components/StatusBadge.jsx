const COLORS = {
  pending: '#9ca3af',
  processing: '#3b82f6',
  completed: '#16a34a',
  failed: '#dc2626',
}

export default function StatusBadge({ status }) {
  return (
    <span
      style={{
        display: 'inline-block',
        padding: '2px 8px',
        borderRadius: 999,
        fontSize: 12,
        fontWeight: 600,
        color: 'white',
        background: COLORS[status] ?? '#6b7280',
        textTransform: 'capitalize',
      }}
    >
      {status}
    </span>
  )
}
