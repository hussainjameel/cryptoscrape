
import { useEffect, useState } from 'react'
import Navbar from './Navbar.tsx'
import MarketOverview from './MarketOverview.tsx'

// Structure of an individual cryptocurrency
interface Coin {
  id: number
  rank: number
  name: string
  symbol: string
  price: number
  market_cap?: number
  volume_24h?: number
  percent_change_24h?: number
}

// Structure of the complete PHP API response
interface ApiResponse {
  success: boolean
  source: string
  scraped_at: string
  total_coins: number
  total_available: number
  pages_requested: number
  pages_failed: number[]
  data: Coin[]
}

function App() {
  const [coins, setCoins] = useState<Coin[]>([])
  const [loading, setLoading] = useState<boolean>(true)
  const [error, setError] = useState<string | null>(null)

  // Fetch cryptocurrency data from PHP API
  useEffect(() => {
    fetch('/api.php')
      .then(response => {
        if (!response.ok) {
          throw new Error('Failed to fetch cryptocurrency data')
        }

        return response.json()
      })
      .then((result: ApiResponse) => {

        // Inspect the API response in browser console
        console.log('First coin from API:', result.data[0])
        console.log('Available coin fields:', Object.keys(result.data[0] ?? {}))

        setCoins(result.data)
        setLoading(false)
      })
      .catch((error: unknown) => {
        setError(
          error instanceof Error
            ? error.message
            : 'Something went wrong'
        )
        setLoading(false)
      })
  }, [])

  // Calculate total market capitalisation
  const totalMarketCap = coins.reduce(
    (total, coin) => total + Number(coin.market_cap ?? 0),
    0
  )

  // Calculate total 24-hour trading volume
  const totalVolume = coins.reduce(
    (total, coin) => total + Number(coin.volume_24h ?? 0),
    0
  )

  // Count cryptocurrencies with positive 24-hour change
  const gainers = coins.filter(
    coin => Number(coin.percent_change_24h ?? 0) > 0
  ).length

  // Count cryptocurrencies with negative 24-hour change
  const losers = coins.filter(
    coin => Number(coin.percent_change_24h ?? 0) < 0
  ).length

  if (loading) {
    return <h2>Loading cryptocurrencies...</h2>
  }

  if (error) {
    return <h2>Error: {error}</h2>
  }

  return (
    <div className="app">
      <Navbar />

      <main>
        <MarketOverview
          totalCoins={coins.length}
          totalMarketCap={totalMarketCap}
          totalVolume={totalVolume}
          gainers={gainers}
          losers={losers}
        />

        <h2>Cryptocurrency List</h2>

        {coins.map(coin => (
          <div key={coin.id}>
            {coin.rank}. {coin.name} ({coin.symbol})
          </div>
        ))}
      </main>
    </div>
  )
}

export default App
