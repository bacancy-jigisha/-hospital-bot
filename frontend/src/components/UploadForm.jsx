import { useState } from 'react'
import { uploadDocument } from '../api/documents'

const CATEGORIES = [
  'general',
  'departments',
  'doctors',
  'services',
  'admission',
  'facilities',
  'policies',
]

export default function UploadForm({ onUploaded }) {
  const [title, setTitle] = useState('')
  const [category, setCategory] = useState(CATEGORIES[0])
  const [file, setFile] = useState(null)
  const [progress, setProgress] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setSubmitting(true)
    setProgress(0)

    try {
      const document = await uploadDocument({ title, category, file }, (progressEvent) => {
        if (progressEvent.total) {
          setProgress(Math.round((progressEvent.loaded / progressEvent.total) * 100))
        }
      })
      onUploaded(document)
      setTitle('')
      setFile(null)
      event.target.reset()
    } catch (err) {
      if (err.response?.status === 422) {
        setErrors(err.response.data.errors ?? {})
      } else {
        setErrors({ file: ['Upload failed. Please try again.'] })
      }
    } finally {
      setSubmitting(false)
      setProgress(null)
    }
  }

  return (
    <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: 10, maxWidth: 420 }}>
      <label>
        Title
        <input
          type="text"
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          required
          maxLength={255}
          style={{ display: 'block', width: '100%' }}
        />
        {errors.title && <FieldError messages={errors.title} />}
      </label>

      <label>
        Category
        <select value={category} onChange={(e) => setCategory(e.target.value)} style={{ display: 'block', width: '100%' }}>
          {CATEGORIES.map((c) => (
            <option key={c} value={c}>
              {c}
            </option>
          ))}
        </select>
        {errors.category && <FieldError messages={errors.category} />}
      </label>

      <label>
        PDF file
        <input
          type="file"
          accept="application/pdf"
          onChange={(e) => setFile(e.target.files[0] ?? null)}
          required
          style={{ display: 'block', width: '100%' }}
        />
        {errors.file && <FieldError messages={errors.file} />}
      </label>

      {progress !== null && (
        <div style={{ background: '#e5e7eb', borderRadius: 4, overflow: 'hidden', height: 8 }}>
          <div style={{ width: `${progress}%`, background: '#3b82f6', height: '100%' }} />
        </div>
      )}

      <button type="submit" disabled={submitting || !file}>
        {submitting ? 'Uploading…' : 'Upload document'}
      </button>
    </form>
  )
}

function FieldError({ messages }) {
  return <div style={{ color: '#dc2626', fontSize: 13 }}>{messages.join(' ')}</div>
}
