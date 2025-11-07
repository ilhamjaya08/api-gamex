<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## API

### Admin – Check H2H Balance

- Endpoint: `GET /api/admin/h2h/balance`
- Authentication: Bearer token via Sanctum (admin role required)
- Description: Fetches the current H2H server balance by proxying the OkeConnect endpoint and returns the parsed balance along with the original provider response.

#### Environment Variables

Configure the following in your `.env` file:

```
H2H_BASE_URL=https://h2h.okeconnect.com
H2H_MEMBER_ID=OK*****
H2H_PIN=******
H2H_PASSWORD=*********
```

#### Response Example

```json
{
  "balance": 284.939,
  "raw_balance": "Saldo 284.939"
}
```

If the upstream response format cannot be parsed or the remote call fails, the API returns an error with the provider response for easier troubleshooting.

### Admin – Import Products from OkeConnect

- Endpoint: `POST /api/admin/products/import`
- Authentication: Bearer token via Sanctum (admin role required)
- Description: Pulls the complete price list from OkeConnect and upserts every item into the local `products` table. New items are inserted; existing items are updated with the latest metadata.

### Admin – Refresh Existing Products

- Endpoint: `POST /api/admin/products/refresh`
- Authentication: Bearer token via Sanctum (admin role required)
- Description: Fetches the current price list and updates only products that already exist locally (matched by `kode`). Remote items without a local match are ignored.
- Note: Product-category assignments are optional; the import and refresh flows never overwrite `category_id`.

#### Environment Variables

Add the following to enable OkeConnect imports:

```
OKECONNECT_PRICE_URL=https://okeconnect.com/harga/json
OKECONNECT_PRICE_ID=905ccd028329b0a
OKECONNECT_PRICE_PRODUCTS=saldo_gojek,digital
```

#### Response Examples

Import:

```json
{
  "message": "Products imported successfully.",
  "fetched": 120,
  "created": 115,
  "updated": 5
}
```

Refresh:

```json
{
  "message": "Products refreshed successfully.",
  "updated": 42
}
```

When the upstream request fails, both endpoints return a `502` status with the failure details to aid troubleshooting.
