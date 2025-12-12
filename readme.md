# Samuh Platform

**Version:** MVP (Phase 1)  
**Generated for:** Sunil Kumar  

---

## Project Overview
Samuh platform ek multi-tenant system hai jaha groups apna online saving system manage karenge.  
- Platform owner (tum) platform provide karega  
- Har group apna admin create karega  
- Admin members add karega, contributions aur loans track karega  

**MVP:**  
- Payments simulated rahenge  
- Documents manual verify honge  

---

## Features (MVP)
1. Multi-tenant signup (group creation + admin)  
2. Member signup with document upload (manual KYC review)  
3. Contribution recording (manual / simulated payment)  
4. Member dashboard (transactions, balance)  
5. Admin dashboard (approve members, add contributions, reports)  
6. Email OTP verification (optional)  
7. File uploads secure folder me  

---

## Folder & File Structure

samuh-website/
│
├─ public/
│ ├─ index.php # Landing page
│ ├─ signup.php # Group / Member signup
│ ├─ login.php # Login page
│ ├─ logout.php # Logout handler
│ └─ assets/ # CSS, JS, images
│
├─ member/
│ ├─ dashboard.php # Member main page
│ ├─ my_saving.php # Contribution / saving history
│ ├─ my_loan.php # Loan tracking page
│ └─ profile.php # Profile management
│
├─ admin/
│ ├─ admin_dashboard.php
│ ├─ members_list.php
│ ├─ add_saving.php
│ ├─ add_loan.php
│ ├─ repayment.php
│ └─ reports.php
│
├─ includes/
│ ├─ config.php # Database connection & constants
│ ├─ header.php # Common header
│ ├─ footer.php # Common footer
│ └─ session_check.php # Role-based access
│
├─ uploads/
│ ├─ documents/ # KYC documents
│ ├─ photos/ # Member photos
│ └─ signature/ # Digital signatures
│
├─ database.sql # Database schema
├─ README.md
└─ error.php


---

## Database Tables (High-Level)

| Table Name | Description |
|------------|-------------|
| groups | Group info, owner, status, bank info |
| users | Members info, role, KYC status |
| kyc_documents | User KYC documents, verification status |
| group_bank_accounts | Group bank details, verification |
| transactions | Contribution & loan transactions |
| payouts | Payouts to accounts |
| audit_logs | Track all user/admin actions |

---

## Security & Best Practices
- HTTPS use kare  
- Passwords strong hashing (`password_hash`)  
- Prepared statements for DB queries  
- Files outside webroot, secure path store  
- Mask / encrypt sensitive info (bank details)  
- Role-based access control  
- Audit logs for financial actions  

---

## Vendors & Integrations (Future)
- Email Provider (Brevo / SMTP)  
- Payment Gateway (Razorpay, Cashfree)  
- Hosting (VPS / Cloud)  
- Future: KYC provider, Bank APIs, Cloud storage (S3)  

---

## Monetization Ideas
- Ads / partner promotions  
- Affiliate / commission partnerships  
- Premium analytics / reports  
- Grants / CSR funding for social impact  

---

## Phase-wise Roadmap
- **Phase 1 (0-2 months):** MVP with manual KYC, simulated payments  
- **Phase 2 (2-6 months):** Payment gateway integration, reconciliation, notifications  
- **Phase 3 (6+ months):** Bank integration, automated KYC, direct payouts / virtual accounts  

---

## Pilot Launch Checklist
- database.sql imported & verified  
- includes/config.php ready  
- header/footer templates responsive  
- signup & member flows tested  
- admin panel basic functionality ready  
- Security & backups enabled  
- User guide (README) ready  

---

*Generated for: Sunil Kumar — Samuh Platform planning.*







member dashboard pe otpion hai abhi ke liye 
isme option hai 
payment karne ka iska dusra page hai page name hai 
## payment_request.php
isme payment ka  option hai do tarah se first rahega 
