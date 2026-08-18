# Drithi Agro — Local PostgreSQL Setup Guide

## Step 1: Install PostgreSQL (if not installed)
Download from: https://www.postgresql.org/download/windows/
- Default port: 5432
- Set a password for the `postgres` user (e.g., `postgres`)

---

## Step 2: Create the Local Database

Open **pgAdmin** or **psql** and run:

```sql
CREATE DATABASE drithi_agro;
```

Or via command line (open CMD as admin):
```
psql -U postgres -c "CREATE DATABASE drithi_agro;"
```

---

## Step 3: Run SQL Files IN ORDER

Open CMD in the `database/` folder and run these one by one:

```
cd "C:\Users\ASUS\Downloads\drithi agro\Dhrithi_Agro_Backend-main\Dhrithi_Agro_Backend-main"

psql -U postgres -d drithi_agro -f database/render_migration.sql
psql -U postgres -d drithi_agro -f database/admin_seed.sql
psql -U postgres -d drithi_agro -f database/demo_products.sql
```

> `render_migration.sql` contains EVERYTHING in one file:
> schema + triggers + buyer_seller + settings + seed data + admin users

---

## Step 4: Update Backend .env for Local

Edit `.env` file in the backend root:

```
DB_HOST=localhost
DB_PORT=5432
DB_USER=postgres
DB_PASS=postgres
DB_NAME=drithi_agro
```

---

## Step 5: Fix sslmode for Local

In `config/database.php`, the DSN uses `sslmode=require` which FAILS on local.
Change it to `sslmode=disable` for local development.

---

## Admin Login Credentials

| Email                  | Password    | Role  |
|------------------------|-------------|-------|
| owner@drithiagro.com   | Admin@1234  | owner |
| admin@drithiagro.com   | Admin@1234  | admin |

---

## Tables Created

| Table                  | Purpose                        |
|------------------------|--------------------------------|
| users                  | All users (customer/vendor/admin/owner) |
| otp_cache              | OTP storage                    |
| customers              | Customer profiles              |
| customer_addresses     | Delivery addresses             |
| vendors                | Vendor/seller profiles         |
| vendor_documents       | KYC documents                  |
| categories             | Product categories             |
| brands                 | Product brands                 |
| products               | Product listings               |
| inventory              | Stock tracking                 |
| product_images         | Product photos                 |
| cart                   | Shopping cart                  |
| wishlist               | Saved items                    |
| orders                 | Customer orders                |
| order_items            | Items in each order            |
| order_status_history   | Order tracking history         |
| payments               | Payment records                |
| coupons                | Discount coupons               |
| coupon_usage           | Coupon usage tracking          |
| reviews                | Product reviews                |
| notifications          | User notifications             |
| banners                | Homepage banners/offers        |
| commission_rates       | Vendor commission rates        |
| app_settings           | App configuration              |
