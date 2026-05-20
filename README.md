# Bekie Service E-commerce API

A professional REST API foundation for the Bekie e-commerce platform built with Laravel 12.

## Overview

This project provides:

- Versioned API routes under `api/v1`
- Product catalog endpoints for categories, brands, products, and variants
- Cart management for guest and authenticated contexts
- Wishlist CRUD and item management
- Order checkout flow with shipping calculation
- Coupon validation endpoint
- Shipping method discovery
- Swagger API documentation available through the browser

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

If you are using Sail or Docker, adapt the commands to your environment.

## Development

Run the application locally:

```bash
php artisan serve
```

If you use frontend assets or build tooling, use:

```bash
npm install
npm run build
```

## API Documentation

The API is documented with Swagger/OpenAPI. Access the docs in your browser at:

- `http://localhost:8000/api/docs`

The raw OpenAPI definition is available at:

- `http://localhost:8000/openapi.json`

## API Structure

All endpoints are versioned under:

- `GET /api/v1/categories`
- `GET /api/v1/brands`
- `GET /api/v1/products`
- `GET /api/v1/products/{product}`
- `GET /api/v1/shipping-methods`
- `POST /api/v1/coupons/apply`
- `GET /api/v1/carts`
- `POST /api/v1/carts`
- `GET /api/v1/carts/{cart}`
- `POST /api/v1/carts/{cart}/items`
- `PATCH /api/v1/carts/{cart}/items/{item}`
- `DELETE /api/v1/carts/{cart}/items/{item}`
- `POST /api/v1/carts/{cart}/checkout`
- `GET /api/v1/wishlists`
- `POST /api/v1/wishlists`
- `GET /api/v1/wishlists/{wishlist}`
- `DELETE /api/v1/wishlists/{wishlist}`
- `POST /api/v1/wishlists/{wishlist}/items`
- `DELETE /api/v1/wishlists/{wishlist}/items/{item}`
- `GET /api/v1/orders`
- `POST /api/v1/orders`
- `GET /api/v1/orders/{order}`

## Key Project Files

- `routes/api.php` – API route definitions for version 1
- `bootstrap/app.php` – registers API routing in the Laravel bootstrap
- `app/Http/Controllers/Api/V1` – REST controllers for API resources
- `app/Http/Resources/V1` – response resources for consistent JSON responses
- `app/Http/Requests/V1` – validation classes for API input
- `resources/views/swagger.blade.php` – Swagger UI wrapper page
- `public/openapi.json` – OpenAPI definition for the v1 API

## Features Included

- Standard REST Endpoints for ecommerce catalog and checkout
- Clean versioned API routing
- Structured resource responses
- Request validation via FormRequest classes
- Swagger documentation with a browser interface
- Cart and order workflows following professional e-commerce patterns

## Next Improvements

Future enhancements can include:

- API authentication with Sanctum or Passport
- Payment gateway integration
- Customer account and address management APIs
- Order fulfillment and shipment tracking workflows
- Inventory reservation and stock management

## License

MIT
