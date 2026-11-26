
import './Navbar.css'

function Navbar() {
  return (
    <header className="navbar">
      <h2 className="navbar-brand">CryptoScrape</h2>

      <nav className="nav-links">
        <a href="#overview">Overview</a>
        <a href="#cryptocurrencies">Cryptocurrencies</a>
        <a href="#about">About</a>
      </nav>
    </header>
  )
}

export default Navbar
