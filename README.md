[![Deploy Laravel App](https://github.com/musikfestwochen/musikfestapp/actions/workflows/deploy.yml/badge.svg)](https://github.com/musikfestwochen/musikfestapp/actions/workflows/deploy.yml) [![tests](https://github.com/musikfestwochen/musikfestapp/actions/workflows/tests.yml/badge.svg)](https://github.com/musikfestwochen/musikfestapp/actions/workflows/tests.yml) [![linter](https://github.com/musikfestwochen/musikfestapp/actions/workflows/lint.yml/badge.svg)](https://github.com/musikfestwochen/musikfestapp/actions/workflows/lint.yml)

# Musikfestapp

Musikfestapp is a Laravel-based application designed to support the organization and management of the **Winterthurer
Musikfestwochen**. It consists of multiple modules that help the organization team efficiently run the festival.

Currently, the first module in development is **"People Counting"**, a display interface for the **Axis 3D People
Counter IPCam**.

## 🚀 Features

- Laravel-based backend with Inertia.js and Vue.js frontend.
- **People Counting Module**: Displays real-time data from the Axis 3D People Counter camera.

## 🌐 Hosted at

[https://musikfestapp.ch](https://musikfestapp.ch)

## 🛠 Installation

To set up Musikfestapp locally, follow these steps:

> 💡 **Need a detailed development environment setup?** Check out the [Development Setup Guide](DEVELOPMENT_SETUP.md) for comprehensive instructions on configuring Herd, VSCode, and recommended extensions.

### Prerequisites

Ensure you have the following installed and configured on your system:

- [Laravel Herd](https://herd.laravel.com/) (for PHP)
- [Composer](https://getcomposer.org/)
- [Node.js & npm](https://nodejs.org/)
- [Mailpit](https://mailpit.axllent.org/) (for email testing)

> [!NOTE]
> Configure everything to use PHP 8.4 and Node.js 22

### Setup Steps

1. **Clone the repository**

    ```sh
    git clone https://github.com/musikfestwochen/musikfestapp.git
    cd musikfestapp
    ```

2. **Install dependencies**

    ```sh
    composer install
    npm install
    ```

3. **Environment Setup**

    ```sh
    cp .env.local .env
    php artisan key:generate
    ```

4. **Run Database Migrations**

    ```sh
    php artisan migrate
    ```

5. **Start the Development Server**

    ```sh
    npm run dev
    ```

6. **Start Mailpit** (in a separate terminal)
    ```sh
    composer mailpit
    ```
    Access the Mailpit web interface at `http://localhost:8025`

## 📚 Documentation

For more detailed information about working with this project, see these additional guides:

- **[Development Setup](DEVELOPMENT_SETUP.md)** - Detailed environment configuration with Herd, VSCode extensions, and recommended tools
- **[Testing Guidelines](TESTING_GUIDELINES.md)** - Comprehensive testing standards for Laravel (Pest), Vue (Vitest), and E2E (Playwright) testing
- **[Conventions](CONVENTIONS.md)** - Branch naming conventions and workflow standards for consistent development

## 🧰 Development Tools

Musikfestapp comes with several helpful Composer scripts to streamline development:

### Testing & Quality Assurance

- **Run all tests**: `composer test`
- **Static code analysis**: `composer test:sca`
- **Test coverage**: `composer test:coverage`
- **Type coverage**: `composer test:types`
- **Mutation testing**: `composer test:mutation`

> 💡 **Need help with testing?** Check out the [Testing Guidelines](TESTING_GUIDELINES.md) for detailed information about unit tests, integration tests, and E2E testing standards.

### Code Quality

- **Check for typos**: `composer typos`
- **Lint code**: `composer lint`
- **Run Rector (code refactoring)**: `composer rector`
- **Pre-commit checks**: `composer precommit`

### Email Testing

- **Start Mailpit**: `composer mailpit`

## 🎛 Tech Stack

### Core Technologies

- **Backend:** Laravel (PHP 8.4)
- **Frontend:** Inertia.js + Vue.js
- **Database:** SQLite (locally) / MariaDB (production)

### Key Packages & Libraries

- **Authentication:** Laravel's built-in authentication
- **Authorization:** Spatie Laravel Permission
- **API:** Laravel's built-in API resources
- **Frontend Routing:** Ziggy (for Laravel routes in JavaScript)

### Development & Testing

- **Testing Framework:** Pest PHP
- **Static Analysis:** PHPStan (Larastan)
- **Code Quality:** Laravel Pint, Rector, Peck
- **Email Testing:** Mailpit

## 📦 Modules

### 📸 People Counting Module

The **People Counting** module is a display interface for the **Axis 3D People Counter IPCam**, allowing real-time
visualization of visitor counts.

## ⏰ Timezone Handling

All datetime fields in the application are stored in **UTC** timezone in the database. This ensures consistent data storage regardless of server location or user timezone.

- **Backend**: The application is configured to use UTC for all server-side operations
- **Frontend**: The frontend is responsible for displaying dates and times in the user's local timezone
- **Database**: All timestamp columns store data in UTC format

This approach provides a standardized way to handle time-sensitive data while ensuring proper display for users in different timezones.

## 🤝 Contributing

Before contributing to this project, please review our development standards:

- **[Conventions](CONVENTIONS.md)** - Follow our branch naming conventions and workflow standards
- **[Testing Guidelines](TESTING_GUIDELINES.md)** - Ensure your code meets our testing requirements (100% coverage for unit tests)
- **[Development Setup](DEVELOPMENT_SETUP.md)** - Set up your development environment correctly

### Quick Start for Contributors

1. Create a branch following our [naming conventions](CONVENTIONS.md)
2. Write tests according to our [testing guidelines](TESTING_GUIDELINES.md)
3. Run `composer precommit` before pushing changes
4. Submit a pull request
