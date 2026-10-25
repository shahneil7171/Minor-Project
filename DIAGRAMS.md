# KDP MART — Diagrams

## System Architecture (Mermaid)
```mermaid
flowchart TB
  Browser[Browser/Client] --> Laravel[Laravel App]
  Laravel --> DB[(MySQL/SQLite)]
  Laravel --> Files[Storage: images/PDFs]
  Laravel --> Razorpay[Razorpay Sandbox]
  Laravel --> Cashfree[Cashfree Sandbox]
```

## Use Case Diagram (actors)
- Customer: browse, cart, checkout, orders, returns, wallet, wishlist.
- Seller: manage products, orders, returns, payments, reports.
- Delivery Partner: assigned deliveries, status updates.
- Admin: users, products, orders, coupons, reports, settings.

## DFD Level 0
```mermaid
flowchart LR
  Customer -->|request| System[KDP MART]
  Seller -->|request| System
  Admin -->|request| System
  System -->|response| Customer
  System -->|response| Seller
  System -->|response| Admin
  System <--> DB[(Database)]
  System <--> PG[Payment Gateway]
```

## ER Diagram (key entities)
User ||--o{ Product : owns
User ||--o{ Order : places
Order ||--|{ OrderItem : contains
Product ||--o{ OrderItem
User ||--|| SmartWallet
SmartWallet ||--o{ SmartCoinTransaction
Order ||--o{ Refund
Order ||--o{ ReturnRequest
User ||--o{ Address

## Order & Payment Workflow
```mermaid
sequenceDiagram
  Customer->>Cart: add items
  Customer->>Checkout: place order
  Checkout->>Payment: initiate (Razorpay/Cashfree/COD)
  Payment->>Server: verify signature/status
  Server->>Order: mark paid / COD pending
  Seller->>Order: fulfil
  Delivery->>Order: deliver
  Customer->>Return: request return
  Server->>Refund: process (sandbox)
```
