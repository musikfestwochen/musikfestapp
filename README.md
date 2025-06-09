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

## 🧰 Development Tools

Musikfestapp comes with several helpful Composer scripts to streamline development:

### Testing & Quality Assurance

- **Run all tests**: `composer test`
- **Static code analysis**: `composer test:sca`
- **Test coverage**: `composer test:coverage`
- **Type coverage**: `composer test:types`
- **Mutation testing**: `composer test:mutation`

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
