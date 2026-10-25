# KDP MART — Project Report (GTU Diploma, Computer Engineering)

## 1. Title Page
- Project Title: KDP MART — Multi-Vendor E-Commerce Marketplace
- Course: Diploma in Computer Engineering
- University: Gujarat Technological University (GTU)
- Mentor: [Mentor Name]
- Team: [Team Member Names]
- Academic Year: 2025-26
- Submission Date: 30 October 2026

## 2. Abstract
KDP MART is a Laravel-based multi-vendor e-commerce marketplace supporting customers, sellers, delivery partners, and administrators. It implements product catalog, cart, wishlist, coupons, taxes, shipping, INR currency handling, Smart Coins wallet, Razorpay/Cashfree sandbox payments, Cash on Delivery, order fulfilment, returns/refunds, PDF invoices, and role-based access control.

## 3. Introduction
Online marketplaces connect buyers and sellers on a single platform. KDP MART provides a complete buying/selling workflow with secure authentication and payment handling.

## 4. Problem Statement
Small vendors lack an affordable, integrated platform for online selling with order management, payments, and delivery coordination.

## 5. Objectives
- Build a multi-role marketplace.
- Secure authentication and authorization.
- Reliable order/payment lifecycle.
- Smart Coins loyalty system.
- Admin reporting and seller tools.

## 6. Project Scope
In scope: catalog, cart, checkout, payments (sandbox), orders, returns, refunds, wallet, admin/seller/delivery panels.
Out of scope: live payment capture, production hosting, mobile app.

## 7. Existing vs Proposed System
Existing: manual/offline sales. Proposed: web-based marketplace with role-based dashboards.

## 8. Functional Requirements
- User registration/login/logout/password reset.
- Product CRUD with ownership (sellers).
- Cart, wishlist, coupons.
- Checkout with Razorpay/Cashfree (sandbox) and COD.
- Order tracking, returns, refunds.
- Smart Coins redemption.
- PDF invoices.
- Admin dashboard/reports.

## 9. Non-Functional Requirements
- Security: CSRF, validation, mass-assignment protection, role checks.
- Performance: pagination on large lists.
- Reliability: idempotent webhooks.
- Maintainability: Laravel conventions.

## 10. Hardware/Software Requirements
- PHP ^8.3, Composer, Node/npm, MySQL (or SQLite for tests).
- Web server (Apache/Nginx) or `php artisan serve`.
- Browser for UI.

## 11. Technologies Used
- Laravel 13, PHP 8.5, MySQL/SQLite, Blade, Vite, Tailwind/Bootstrap, Razorpay SDK, Cashfree SDK, DomPDF.

## 12. System Architecture
Layered: Presentation (Blade), Application (Controllers/Services), Data (Eloquent/DB). See DIAGRAMS.md.

## 13. Module Descriptions
Customer, Seller, Delivery Partner, Admin, Cart/Checkout, Payments, Wallet (Smart Coins), Returns/Refunds, Invoices.

## 14. Database Design
29 Eloquent models including User, Product, Category, Cart, Order, OrderItem, Refund, SmartWallet, SmartCoinTransaction, ReturnRequest, Coupon, etc. See migrations and ER diagram in DIAGRAMS.md.

## 15. UML & DFD
See DIAGRAMS.md (use-case, DFD L0/L1, ER, order/payment workflow).

## 16. Implementation Details
Laravel MVC, form requests, policies, middleware, service classes for payments, PDF generation via DomPDF.

## 17. Testing
Unit: 1 passed. Feature: 577 passed, 25 failed (4838 assertions). See FINAL_TEST_REPORT.md. Untested: browser E2E, live gateway.

## 18. Limitations
- 25 feature-test expectation mismatches.
- Sandbox payments only; no live charges.
- No automated browser tests.

## 19. Future Enhancements
- Fix remaining tests; add E2E; production deployment; 2FA; image compression.

## 20. Conclusion
KDP MART delivers a functional multi-vendor marketplace suitable for academic demonstration, with sandbox payments and role-based access.

## 21. References
- Laravel Documentation (https://laravel.com/docs)
- Razorpay Docs, Cashfree Docs, DomPDF, GTU project guidelines.
