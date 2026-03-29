import FinanceDataReader as fdr

print("--- 한국거래소 종목 리스트 상위 5개 ---")
try:
    df_krx = fdr.StockListing('KRX')
    print(df_krx[['Code', 'Name', 'Market']].head())

    print("\n--- 삼성전자 최근 주가 ---")
    df = fdr.DataReader('005930')
    print(df.tail())
except Exception as e:
    print(f"에러 발생: {e}")
