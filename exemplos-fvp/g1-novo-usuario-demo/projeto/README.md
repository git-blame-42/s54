## Eventos do Câmpus

Sistema de gerenciamento de eventos do câmpus, desenvolvido na disciplina TSI34D (Backend) sobre o framework da disciplina.

### Dependências

- Docker
- Docker Compose

### To run

#### Clone Repository

```
$ git clone git@github.com:VitorThorvi/tsi34d-backend.git
$ cd tsi34d-backend
```

#### Define the env variables

```
$ cp .env.example .env
```

#### Install the dependencies

```
$ ./run composer install
```

#### Up the containers

```
$ docker compose up -d
```

ou

```
$ ./run up -d
```

#### Create database and tables

```
$ ./run db:reset
```

#### Populate database

```
$ ./run db:populate
```

O populate recria as tabelas (chama `Database::migrate()`) e cria os usuários de demonstração, todos com a senha `123456`:

| E-mail               | Nome               | Papel         |
|----------------------|--------------------|---------------|
| `admin@example.com`  | Administrador      | `admin`       |
| `gestor@example.com` | Gestor             | `admin`       |
| `user@example.com`   | Usuário            | `participant` |
| `maria@example.com`  | Maria Participante | `participant` |

### Fixed uploads folder permission

```
sudo chown www-data:www-data public/assets/uploads
```

#### Run the tests

```
$ docker compose run --rm php ./vendor/bin/phpunit tests --color
```

ou

```
$ ./run test
```

#### Run the linters

[PHPCS](https://github.com/PHPCSStandards/PHP_CodeSniffer/)

```
$ ./run phpcs
```

[PHPStan](https://phpstan.org/)

```
$ ./run phpstan
```

Access [localhost](http://localhost)
