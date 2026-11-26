
interface MarketOverviewProps {
  totalCoins: number
  totalMarketCap: number
  totalVolume: number
  gainers: number
  losers: number
}

function MarketOverview({
  totalCoins,
  totalMarketCap,
  totalVolume,
  gainers,
  losers
}: MarketOverviewProps) {

  const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      notation: 'compact',
      maximumFractionDigits: 2
    }).format(value)
  }

  return (
    <section>
      <h2>Market Overview</h2>

      <div>
        <h3>Cryptocurrencies tracked</h3>
        <p>{totalCoins}</p>
      </div>

      <div>
        <h3>Total tracked market cap</h3>
        <p>{formatCurrency(totalMarketCap)}</p>
      </div>

      <div>
        <h3>Total tracked 24h volume</h3>
        <p>{formatCurrency(totalVolume)}</p>
      </div>

      <div>
        <h3>24h Market breadth</h3>
        <p>
          {gainers} gainers / {losers} losers
        </p>
      </div>
    </section>
  )
}

export default MarketOverview
