# PlutoPHP Framework

![PlutoPHP](https://img.shields.io/badge/PlutoPHP-v1.0.0-blue.svg)
![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-8892BF.svg)
![License](https://img.shields.io/badge/License-MIT-green.svg)

A lightweight, modern PHP framework designed for rapid development. PlutoPHP leverages PHP 8's latest features like Attributes for clean, co-located routing, and comes with a reactive frontend component system, making it ideal for building admin panels, dashboards, and content-driven websites with speed and consistency.

---

### Key Features

*   **Attribute-Based Routing:** Define your routes directly on your controller methods. No more separate route files!
*   **MVC Architecture:** A clean and organized structure based on the Model-View-Controller pattern.
*   **Reactive Web Components with Pluto.js:** A minimal-footprint JavaScript library for building interactive UIs with custom HTML tags.
*   **Powerful CLI Tool:** A built-in `pluto` command-line tool to generate controllers, models, views, and even full modules.
*   **Database Migrations:** Version control for your database schema.
*   **Blade-like Template Engine:** Use familiar syntax like `@extends`, `@section`, and `@yield` in your `.phtml` view files.
*   **Simple ORM & Query Builder:** An intuitive database layer for fluent query building.

### Requirements

*   PHP 8.0 or higher (for Attribute support)
*   Composer
*   MySQL Database
*   A web server (Apache, Nginx, etc.)

### Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/PlutoSW/PlutoPHP.git
    cd PlutoPHP
    ```

3.  **Configure your environment:**
    *   Copy the example environment file: `cp .env.example .env`
    *   Open the `.env` file and update the `DB_` variables with your database credentials.

4.  **Set up your web server:**
    *   Point your web server's document root to the project's root directory where `index.php` is located.
    *   Ensure URL rewriting is enabled to direct all requests to `index.php`. The included `.htaccess` file does this for Apache.

---

## Getting Started: The PlutoPHP Way

### 1. Routing with Attributes

In PlutoPHP, routes live where they belong: right above the controller method that handles them.

Create a controller using the CLI:
```bash
php pluto make:controller UserController
```

Now, open `app/Controllers/UserController.php` and define your routes.

```php
<?php

namespace App\Controllers;

use Pluto\Controller;
use Pluto\Route; // The Route attribute class

class UserController extends Controller
{
    /**
     * This method handles GET requests to /users
     */
    #[Route('/users', 'GET')]
    public function index()
    {
        // Fetch users from a model
        // $users = User::all();
        return view('users.index', ['users' => []]);
    }

    /**
     * This method handles GET requests for a specific user, e.g., /user/123
     * The {id} parameter is automatically injected into the $id variable.
     * You can even type-hint parameters: {<int>id}, {<str>slug}
     */
    #[Route('/user/{<int>id}', 'GET')]
    public function show(int $id)
    {
        // Find user by ID
        // $user = User::find($id);
        return view('users.show', ['userId' => $id]);
    }
}
```

### 2. Views and Templating

Views are stored in `app/Views` and use `.phtml` files with a Blade-like syntax.

Create a view file:
```bash
php pluto make:view users.index
```

**`app/Views/users/index.phtml`**
```php
@extends('app')

@section('title', 'All Users')

@section('content')
    <h1>User List</h1>
    <button>Create New User</button>
@endsection
```

### A Deeper Look at the Template Engine

PlutoPHP's template engine provides a simple yet powerful way to build your UIs with reusable layouts and components.

#### 1. Defining a Layout

A layout is a master view that other views can extend. It typically contains the `<html>`, `<head>`, and `<body>` tags, along with placeholders for content. The `pluto make:view` command can generate a default layout for you.

**`app/Views/app.phtml`** (Example Layout)
```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'My PlutoPHP App')</title>
    {{-- This is where page-specific CSS will be injected --}}
    @stack('css')
</head>
<body>
    @include('partials.header')

    <main>
        {{-- The main content from child views will go here --}}
        @yield('content')
    </main>

    @include('partials.footer')

    {{-- This is where page-specific JavaScript will be injected --}}
    @stack('js')
</body>
</html>
```

#### 2. Extending a Layout

You can use the `@extends` and `@section` directives in your view to use the layout.

**`app/Views/home.phtml`**
```php
{{-- Extend the main application layout --}}
@extends('app')

{{-- Define the content for the 'title' section --}}
@section('title', 'Homepage')

{{-- Define the content for the 'content' section --}}
@section('content')
    <h1>Welcome to the Homepage!</h1>
    <p>This content will be placed in the `@yield('content')` section of the layout.</p>
@endsection
```

#### 3. Pushing to Stacks (`@push` & `@stack`)

Stacks are useful for adding CSS or JavaScript from a child view to the layout's `<head>` or `<body>`.

**`app/Views/users/profile.phtml`**
```php
@extends('app')

@section('title', 'User Profile')

@section('content')
    <h2>User Profile Page</h2>
    <div id="user-profile-component"></div>
@endsection

{{-- Push a specific script to the 'js' stack in the layout --}}
@push('js')
    <script src="/js/user-profile.js"></script>
@endpush

{{-- Push specific styles to the 'css' stack in the layout --}}
@push('css')
    <link rel="stylesheet" href="/css/user-profile.css">
@endpush
```

#### 4. Including Partials (`@include`)

The `@include` directive allows you to insert a view from within another view. This is great for reusable components like headers, footers, or sidebars.

**`app/Views/partials/header.phtml`**
```php
<header>
    <nav>
        <a href="/">Home</a>
        <a href="/about">About</a>
        <a href="/contact">Contact</a>
    </nav>
</header>
```
This partial is included in the main layout using `@include('partials.header')`.

### Frontend: Reactive Web Components with Pluto.js

PlutoPHP is not just a backend framework; it includes **Pluto.js**, a minimal-footprint library for creating reactive, stateful **Web Components**. This allows you to build interactive UIs using custom HTML tags.

The core idea is to use custom HTML tags like `<pluto-modal>`, `<pluto-tabs>`, and `<pluto-accordion>` directly in your views. Pluto.js will automatically initialize them.

#### Example: Using the `<pluto-modal>` Component

Here’s how you can implement a modal dialog with just a few lines of HTML and JavaScript.

**1. Add the HTML to your view:**

Place the `<pluto-modal>` tag in your `.phtml` file. You can configure it using attributes like `header`. Use slots to inject content into the modal's body and footer.

**`app/Views/some-page.phtml`**
```html
@extends('app')

@section('content')
    <h1>Modal Example</h1>

    <button id="open-modal-btn">Open Modal</button>

    <pluto-modal id="my-modal" header="My First Modal">
        {{-- This content goes into the default slot (the modal body) --}}
        <p>This is the content of the modal dialog.</p>

        {{-- This content goes into the 'footer' slot --}}
        <div slot="footer">
            <button id="close-modal-btn">Close</button>
            <button class="primary">Save Changes</button>
        </div>
    </pluto-modal>
@endsection
```

**2. Control the Component with JavaScript:**

You can control the component by calling its methods. Push your script to the `js` stack.

**`app/Views/some-page.phtml` (continued)**
```html
@push('js')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const myModal = document.getElementById('my-modal');
        const openBtn = document.getElementById('open-modal-btn');
        const closeBtn = document.getElementById('close-modal-btn');

        // Call the .show() method to open the modal
        openBtn.addEventListener('click', () => myModal.show());

        // Call the .hide() method to close it
        closeBtn.addEventListener('click', () => myModal.hide());
    });
</script>
@endpush
```

This same pattern of using custom tags, attributes, and slots applies to other components provided by Pluto.js, such as `<pluto-accordion>` and `<pluto-tabs>`.

#### Example: Using `<pluto-card>` and `<pluto-input>`

Cards are versatile containers for content. You can combine them with other components like inputs and buttons to build forms.

**`app/Views/users/create.phtml`**
```html
@extends('app')

@section('content')
    <pluto-card header-title="Create New User">

        <p>Please fill out the form below to register a new user.</p>

        <pluto-input label="Full Name" name="fullname" placeholder="e.g., John Doe"></pluto-input>
        <pluto-input label="Email Address" type="email" name="email" placeholder="e.g., user@example.com"></pluto-input>

        <div slot="footer">
            <pluto-button label="Register User" variant="success"></pluto-button>
        </div>
    </pluto-card>
@endsection
```

#### Creating Your Own Reactive Components

Pluto.js makes it easy to create your own custom HTML tags. This is a powerful way to encapsulate complex HTML, CSS, and JavaScript logic into reusable components.

Let's create a simple `<user-profile>` component.

**1. Create the Component's JavaScript File:**

Create a new file, for example, in `public/js/components/UserProfile.js`.

```javascript
// public/js/components/UserProfile.js

class UserProfile extends PlutoElement {
    // Define properties that can be passed as attributes
    static get props() {
        return {
            name: { type: String },
            role: { type: String }
        };
    }

    // Render the component's HTML
    render() {
        return html`
            <div class="profile-card">
                <h3>${this.name}</h3>
                <p>Role: ${this.role}</p>
            </div>
        `;
    }
}

// Register the custom element with the browser
Pluto.assign('user-profile', UserProfile);
```

**2. Use the Component in a View:**

Now you can use `<user-profile>` in any of your `.phtml` files. Just make sure to include the component's JavaScript file.

**`app/Views/some-page.phtml`**
```html
@extends('app')

@section('content')
    <h1>User Details</h1>

    <user-profile name="Jane Doe" role="Administrator"></user-profile>
    <user-profile name="John Smith" role="Editor"></user-profile>
@endsection

@push('js')
    {{-- Import the main Pluto.js library and your new component --}}
    <script type="module" src="/public/js/components/UserProfile.js"></script>
@endpush
```

This approach allows you to build a library of custom components tailored to your application's needs, keeping your views clean and maintainable.

### 3. Console Commands (`pluto` CLI)

PlutoPHP includes a powerful CLI tool named `pluto` to speed up your development.

| Command                  | Description                                                  |
| ------------------------ | ------------------------------------------------------------ |
| `migrate`                | Run all pending database migrations.                         |
| `migrate:make <name>`    | Create a new migration file.                                 |
| `migrate:rollback`       | Roll back the last batch of migrations.                      |
| `make:controller <Name>` | Create a new controller in `app/Controllers`.                |
| `make:model <Name>`      | Create a new model in `app/Models`.                          |
| `make:view <name>`       | Create a new view file in `app/Views`. (e.g., `posts.show`)  |
| `make:module <Name>`     | **Powerful!** Creates a Controller, Model, and View for a new module all at once. |

**Example: Creating a "Post" module**
```bash
php pluto make:module Post
```
This command will generate:
*   `app/Models/Post.php`
*   `app/Controllers/PostController.php`
*   `app/Views/post/index.phtml`

## License

The PlutoPHP framework is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).