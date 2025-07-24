# PlutoPHP Framework

Pluto is a lightweight MVC framework written in **pure PHP** (PHP ≥ 8.1) that helps you build modern web applications quickly.

* ✨ **Attribute-based routing** – Define routes right above your controller methods with the `#[Route]` attribute.
* 🗄 **Active-Record ORM** – Work with your database through intuitive model classes that extend `\Pluto\Core\Model`.
* 🖼 **Simple template engine** – Write clean HTML templates with minimal PHP-like tags.
* 🔌 **CLI tool** – Generate modules & clear logs via `php pluto`.
* 🛰 **WebSocket server** – Real-time communication out of the box.
* 📦 **Zero external dependencies** – Everything you need ships inside the `src/` folder.

---

## Requirements

* PHP ≥ 8.1 (attributes are used for routing)
* MySQL 5.7/8.0 (or compatible) with the `pdo_mysql` extension enabled
* Composer *optional*: there is no Composer dependency, but you may use it for your own code

---

## Getting started

### 1. Clone the repository

```bash
$ git clone https://github.com/your-username/pluto.git
$ cd pluto
```

### 2. Configure your environment

Create a `.env` file in the project root (it is **git-ignored**). Example:

```ini
# Database
DB_IP=127.0.0.1
DB_NAME=pluto
DB_USER=root
DB_PASS=secret

# Application
HOST=http://localhost:8000
DEBUG=true        # set false in production
```

### 3. Create the database schema

All models expect their tables to be present. You can create them manually or use the helper inside `src/core/Table.php` to generate tables from model definitions.

### 4. Serve the project

Using PHP’s built-in server:

```bash
$ php -S localhost:8000 -t public
```

or configure your favourite web server (Apache/Nginx) to point its document root to the project root.

---

## Project structure

```
├── backend
│   ├── controller   # Controllers (HTTP & API)
│   ├── model        # Active-Record models
│   └── socket       # WebSocket server & helper classes
├── frontend
│   ├── templates    # HTML templates (views)
│   └── assets       # CSS / JS / images
├── src
│   ├── core         # Framework core (Router, Model, System…)
│   ├── libraries    # 3rd-party libraries bundled with the framework
│   └── autoload.php # PSR-0 style autoloader
├── storage          # Logs, cache & uploads (writeable)
├── pluto            # CLI launcher
└── index.php        # Front controller – entry point for every HTTP request
```

> **Tip**: ensure the `storage/` directory is writeable by the web server user.

---

## Usage guide

### Routing

Add routes by annotating controller methods:

```php
namespace Pluto\Controller;

use Pluto\Core\Controller;
use Pluto\Core\Route;

class Post extends Controller
{
    #[Route(method: 'GET', endpoint: '/posts', response: 'template')]
    public function index() {
        $posts = PostModel::SearchMany();
        return $this->response->template(['posts' => $posts], 'post/index');
    }
}
```

Supported attribute arguments:
* `method`   – HTTP verb (`GET`, `POST`, …)
* `endpoint` – URI pattern (`/user/{<int>id}` supported)
* `response` – `template` or `json`
* `withToken` – require auth token (boolean)

### Models

Define a model by extending `\Pluto\Core\Model` and setting the table name:

```php
namespace Pluto\Model;

class Post extends \Pluto\Core\Model
{
    static $table = 'posts';
}
```

Common operations:

```php
$post = Post::Load(1);           // find by PK
$all  = Post::SearchMany();      // SELECT *

$post->title = 'New title';
$post->Save();                   // INSERT or UPDATE

$post->Destroy();                // DELETE
```

### Views & templates

Templates live in `frontend/templates/` and use a minimal syntax:

```html
{% foreach($posts as $post): %}
    <h2>{{ $post->title }}</h2>
{% endforeach %}
```

## Template Engine

PlutoPHP comes with a simple yet powerful template engine inspired by Twig and Blade. It allows you to write clean and readable HTML templates by separating your presentation logic from your application logic.

### Delimiters

*   `{{ ... }}`: Prints a variable or the result of an expression. HTML is NOT escaped by default. Use filters for escaping.
*   `{{{ ... }}}`: Prints a variable with HTML escaping (`htmlspecialchars`).
*   `{% ... %}`: A control structure tag, used for `if`, `each`, `extends`, etc.
*   `{# ... #}`: A comment. These are stripped out and do not appear in the final HTML.

### Printing Variables

To print a variable passed from your controller, use the double curly brace syntax:

```twig
<p>Hello, {{ $name }}</p>
```

For user-generated content that might contain HTML, always escape it to prevent XSS attacks:

```twig
<p>{{{ $user_comment }}}</p>
```
Alternatively, you can use the `e` or `escape` filter:
```twig
<p>{{ $user_comment|e }}</p>
```

### Filters

Filters allow you to modify variables before they are printed. They are separated from the variable by a pipe symbol (`|`). Filters can also accept arguments.

**Syntax:** `{{ variable|filter_name:arg1,arg2 }}`

**Examples:**

```twig
{# Text manipulation #}
{{ "hello world"|capitalize }}  {# Output: Hello World #}
{{ user.name|upper }}           {# Output: Jhon Doe #}

{# Number formatting #}
{{ 1234.56|number_format:2,',','.' }} {# Output: 1.234,56 #}

{# HTML Escaping #}
{{ user_input|e }}

{# Default value if variable is empty or null #}
{{ user.profile_picture|default('/images/default-avatar.png') }}

{# Get property from an object or array #}
{{ users|first|get:'name' }}
```

### Functions

Functions are helpers that can be called directly within your templates. They are useful for repetitive tasks like generating URLs.

**Syntax:** `{{ function_name(arg1, arg2) }}`

**Available Functions:**

*   `asset(path)`: Generates a full URL to a static asset (CSS, JS, image).
*   `url(path)`: Generates a full URL for an application route.

**Examples:**

```twig
<link rel="stylesheet" href="{{ asset('css/style.css') }}">

<a href="{{ url('users/profile') }}">My Profile</a>
```

### Control Structures

#### `if / elseif / else`

```twig
{% if $user.is_logged_in %}
    <p>Welcome, {{ $user.name }}</p>
{% elseif $user.is_guest %}
    <p>Hello guest!</p>
{% else %}
    <p><a href="/login">Login</a> or <a href="/register">Register</a></p>
{% endif %}
```

#### `each` (Looping)

You can loop over arrays using the `each` tag.

```twig
<ul>
    {% each $users as $user %}
        <li>{{ $user->name }}</li>
    {% endeach %}
</ul>
```

### Template Inheritance

Template inheritance allows you to build a base "skeleton" template that contains all the common elements of your site and defines **blocks** that child templates can override.

**1. Create a base layout (`layout.html`)**

```twig
<!DOCTYPE html>
<html>
<head>
    <title>{% @block title %}</title>
</head>
<body>
    <div class="container">
        {% @block content %}
    </div>
</body>
</html>
```

**2. Create a child template (`user/index.html`)**

The `extends` tag tells the template engine that this template "extends" another one. It then overrides the blocks from the parent.

```twig
{% extends '../layout.html' %}

{% block title %}Users Page{% endblock %}

{% block content %}
    <h1>Users list</h1>
    <ul>
        {% each $users as $user %}
            <li>{{ $user->name }}</li>
        {% endeach %}
    </ul>
{% endblock %}
```

Include common snippets (header/footer) with PHP’s `include` or your own helper.

### CLI – Module generator

```
$ php pluto -m Blog -t template -e blog --tablename posts 
```

Creates:
* `backend/controller/Blog.php`
* `backend/model/Blog.php`
* view folder `frontend/templates/blog/`

Other useful flags:

* `--type api` – generate an API-only module (JSON responses)
* `--clearlogs` – truncate `storage/logs`
* `--days 7` – keep the last 7 days of logs

Run `php pluto -h` for the full command list.

## Debugging & logging

When `DEBUG=true`, uncaught exceptions are written to `storage/logs/YYYY-MM-DD.log` via the built-in logger.

---

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a pull request

---

## License

Pluto is released under the MIT License – see the [LICENSE](LICENSE) file for details.