import { BrowserRouter, NavLink, Route, Routes } from 'react-router-dom'
import ChatPage from './pages/ChatPage'
import DocumentsPage from './pages/DocumentsPage'

const linkStyle = ({ isActive }) => ({
  padding: '8px 16px',
  textDecoration: 'none',
  color: isActive ? '#111827' : '#6b7280',
  fontWeight: isActive ? 600 : 400,
  borderBottom: isActive ? '2px solid #3b82f6' : '2px solid transparent',
})

function App() {
  return (
    <BrowserRouter>
      <nav style={{ display: 'flex', borderBottom: '1px solid #e5e7eb' }}>
        <NavLink to="/" end style={linkStyle}>
          Chat
        </NavLink>
        <NavLink to="/documents" style={linkStyle}>
          Documents
        </NavLink>
      </nav>

      <Routes>
        <Route path="/" element={<ChatPage />} />
        <Route path="/documents" element={<DocumentsPage />} />
      </Routes>
    </BrowserRouter>
  )
}

export default App
