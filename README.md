# PHP MVC API

This project is a PHP-based RESTful API built using the MVC (Model-View-Controller) architecture. It utilizes MySQL as the database and PDO for database interactions. The API is designed to handle various CRUD operations and includes authentication, middleware, and validation features.

## Project Structure

```
php-mvc-api
├── bootstrap
    ├── app.php
├── app
│   ├── Controllers
│   │   └── ExampleController.php
│   ├── Core
│   │   ├── App.php
│   │   ├── Controller.php
│   │   ├── Database.php
│   │   ├── Middleware.php
│   │   └── Validator.php
│   ├── Middleware
│   │   └── AuthMiddleware.php
│   ├── Models
│   │   └── ExampleModel.php
│   └── Routes
│       └── api.php
├── public
│   ├── index.php
│   └── .htaccess
├── config
│   └── database.php
├── storage
│   ├── logs
│   │   └── app.log
│   └── cache
├── tests
│   └── UserApiTest.php
├── vendor
├── composer.json
├── composer.lock
└── README.md
```

## Features

- **MVC Architecture**: Separates application logic into Models, Views, and Controllers.
- **RESTful API**: Supports standard HTTP methods (GET, POST, PUT, DELETE) for resource manipulation.
- **Database Interaction**: Uses PDO for secure and efficient database operations.
- **Authentication**: Implements middleware for user authentication.
- **Validation**: Ensures incoming request data meets specified criteria before processing.
- **Logging**: Logs application errors and information for debugging and monitoring.

## Installation

1. Clone the repository:
   ```
   git clone <repository-url>
   ```

2. Navigate to the project directory:
   ```
   cd ChanAPI
   ```

3. Install dependencies using Composer:
   ```
   composer install
   ```

4. Configure the database settings in `config/database.php`.

5. Set up the database and run migrations if applicable.

## Usage

To start the application, navigate to the `public` directory and access `index.php` through your web server. Ensure that your server is configured to route requests through `index.php` using the `.htaccess` file provided.

### Example API Endpoints

- **GET /api/users**: Retrieve a list of users.
- **GET /api/users/{id}**: Retrieve a specific example by ID.
- **POST /api/users**: Create a new example.
- **PUT /api/users/{id}**: Update an existing example by ID.
- **DELETE /api/users/{id}**: Delete an example by ID.

## Testing

Unit tests are located in the `tests` directory. You can run the tests using PHPUnit to ensure that the application functions as expected.

## License

This project is licensed under the MIT License. See the LICENSE file for more details.