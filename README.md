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

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).


## Marketplace integration milestone (Amazon + Flipkart)

This milestone adds connector readiness diagnostics and per-product/per-channel shipping and profit-target inputs. It intentionally does **not** mark a marketplace as connected and does not perform live writes.

### Configure credentials after seller API access is approved

1. Copy `.env.example` to `.env` only if you do not already have an environment file.
2. Keep the real `.env` on the server only. Never include it in a ZIP, commit it to Git, or paste secrets into chat.
3. Create/authorize the Amazon SP-API application and configure the `AMAZON_*` values.
4. Obtain Flipkart Seller Dashboard Developer Access and configure the `FLIPKART_*` values.
5. Run `php artisan config:clear`.
6. Open **Sales → Marketplace Connections** to inspect which required values are still missing.

All marketplace write switches remain false by default. Do not enable them until the corresponding API operation has been implemented and verified against the seller account.

### New database migration

`database/migrations/2026_11_03_000001_create_marketplace_product_settings.php`

Adds `marketplace_product_settings`, storing outbound shipping, handling, desired profit range, optional manual selling price and internal auto-price preference independently for each product and marketplace.

Run:

```bash
php artisan migrate
```

### Important operational boundary

The existing listing publish action records an internal "published" state only; it does not publish to Amazon or Flipkart. The current adapters still support report/CSV normalization. Live listing creation, catalogue pull, order API sync, acceptance, label/invoice retrieval, pickup scheduling and settlement API sync require seller credentials, verified regional endpoints, operation-specific permissions, payload mapping and sandbox/live account tests. Do not interpret configured environment values as proof of a working connection.
