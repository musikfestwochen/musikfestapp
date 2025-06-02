### Suggested Development Environment Configuration

#### 📌 Overview

This guide provides the recommended development environment setup for **Musikfestapp**, a Laravel, Vue, and Inertia-based web application. The goal is to ensure a standardized and smooth development workflow.

## 🛠️ Development Setup

### 1️⃣ **Local Development Environment - Herd**

We use **[Herd](https://herd.laravel.com/)** as the **local dependency manager** for **PHP**, **Node.js**, and as the **local web server**.

#### ➡️ Install Herd

Follow the official installation guide:
🔗 [Herd Docs - Installation](https://herd.laravel.com/docs/macos/getting-started/installation)

#### 🚀 Features & Benefits of Herd:

- Installs and manages PHP versions automatically
- Comes with **Node.js** and **PNPM**
- Provides a **fast and lightweight local web server**
- Works seamlessly with **Laravel** projects

#### 📌 After installing Herd, make sure:

- You can run `php -v` and `node -v` without errors
- The local server runs successfully (`herd restart`)

### 2️⃣ **IDE - Visual Studio Code (VSCode)**

The recommended IDE for Musikfestapp is **VSCode**.

#### ➡️ Install VSCode

Download from: [https://code.visualstudio.com/](https://code.visualstudio.com/)

#### 🔌 Recommended Extensions:

To ensure a productive development environment, install the following extensions:

| Extension                     | Description                                 |
| ----------------------------- | ------------------------------------------- |
| **Laravel**                   | Official Laravel extension for VSCode       |
| **Better Pest**               | Pest test runner for Laravel projects       |
| **Pest Snippets**             | Snippets for Pest tests in Laravel projects |
| **PHP Intelephense**          | PHP code intelligence for VSCode            |
| **Prettier**                  | Code formatter for JavaScript, CSS, etc.    |
| **Tailwind CSS IntelliSense** | Tailwind CSS IntelliSense for VSCode        |
| **Vue - Official**            | Language Support for Vue.js                 |
| **DotENV**                    | Support for `.env` files                    |
| **ESLint**                    | Integrates ESLint into VSCode               |

#### 📌 Optional (For Productivity)

- **GitHub Actions**: For CI/CD workflows
- **GitLens**: Git supercharged
- **Peacock**: Custom VSCode color themes per project

### 3️⃣ Environment Configuration

Musikfestapp uses different environment files for different scenarios:

- **Local Development:**
    - Use `.env.local` as the template for your local environment.
    - To set up your local environment, copy `.env.local` to `.env`:
        ```sh
        cp .env.local .env
        ```
    - Then generate your application key:
        ```sh
        php artisan key:generate
        ```
- **Production:**
    - The production server uses its own `.env.production` file (not committed to version control).
- **CI/CD & Examples:**
    - `.env.example` is used as a generic template for CI/CD and as a reference for required variables.

> **Note:** Always keep your `.env` files out of version control to protect sensitive data.

---

#### ➡️ Install Mailpit

Follow the official installation guide [here](https://mailpit.axllent.org/docs/install/).

#### 🚀 Features & Benefits of Mailpit:

- **Local SMTP server** for testing emails
- **Web interface** to view sent emails
- **Lightweight and easy to set up**

#### 📌 After installing Mailpit, make sure:

- run `mailpit` in a separate terminal window
- you can access the Mailpit web interface at `http://localhost:8025`
- you can send test emails from the application

---
