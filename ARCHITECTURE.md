# Architektura — Task Library

Malá aplikace na správu úkolů (TODO list) napsaná v čistém **PHP 8.3 OOP**, bez frameworku a bez Composeru. Slouží jako pískoviště pro tutoriál Claude Code (**Díl 04 — MCP servery**). Cílem je ukázat poctivé vrstvení a OOP idiomy na co nejmenším množství kódu.

> **Kde je kód:** zdroj aplikace i `docker-compose.yml` leží v podadresáři **`task-library/`**. Všechny cesty k souborům níže (`public/index.php`, `src/...`, `templates/...`) jsou relativní k němu a všechny `docker` příkazy pouštěj z `task-library/`.

## Stack

- **PHP 8.3** — čisté OOP, žádný framework, žádný Composer
- **SQLite** přes PDO — souborová DB, vzniká za běhu
- **Bootstrap 5** přes CDN — vzhled, žádný build krok pro frontend
- **Docker** — nginx (reverzní proxy / static) + PHP-FPM (běh PHP)

## Tok requestu

Logika aplikace je rozprostřená přes několik vrstev — chování nepochopíš z jednoho souboru. Každý HTTP request projde tímhle řetězcem:

```
prohlížeč
   │  HTTP :8080
   ▼
nginx (web)                      docker/nginx/default.conf
   │  FastCGI :9000
   ▼
public/index.php                 front controller + tabulka rout
   │
   ▼
Router                           src/Core/Router.php
   │  match (metoda + cesta) → callable
   ▼
TaskController                   src/Controller/TaskController.php
   │  orchestrace requestu, render šablony / redirect
   ▼
TaskService                      src/Service/TaskService.php
   │  business logika, validace
   ▼
TaskRepository                   src/Repository/TaskRepository.php
   │  jediné místo se SQL
   ▼
Database (PDO/SQLite)            src/Core/Database.php
                                 singleton + auto-migrace + seed
```

Datový objekt putující skrz vrstvy je **`Task`** (`src/Model/Task.php`) — neměnná-ish doménová entita s tovární metodou `fromRow()`, která mapuje řádek z DB na typovaný objekt.

## Vrstvy a jejich zodpovědnosti

| Vrstva | Soubor | Smí | Nesmí |
|---|---|---|---|
| **Front controller** | `public/index.php` | registrovat routy, spustit dispatch | obsahovat business logiku |
| **Router** | `src/Core/Router.php` | mapovat (metoda+cesta) na callable | znát doménu úkolů |
| **Controller** | `src/Controller/TaskController.php` | číst `$_POST`, volat Service, render/redirect | sahat na Repository nebo DB přímo |
| **Service** | `src/Service/TaskService.php` | business logika, validace | psát SQL |
| **Repository** | `src/Repository/TaskRepository.php` | SQL přes PDO | obsahovat business pravidla |
| **Model** | `src/Model/Task.php` | držet data úkolu | sahat na DB |
| **Database** | `src/Core/Database.php` | připojení, migrace, seed | znát doménu nad rámec schématu |

**Pravidlo vrstvení (nesmí se porušit):** Controller jde vždy přes Service, nikdy přímo na Repository ani na PDO. SQL existuje výhradně v Repository. Business logika (např. validace prázdného názvu, výpočet `progress()`) patří do Service, ne do Controlleru.

## Klíčové mechanismy (které nejsou vidět z jednoho souboru)

### Routing je tabulka v `public/index.php`
Routy se neregistrují anotacemi ani konfigem, ale imperativně:

```php
$router->add('GET',  '/',                    fn ()        => $controller->index());
$router->add('POST', '/tasks',               fn ()        => $controller->store());
$router->add('POST', '/tasks/{id}/toggle',   fn (?int $id) => $controller->toggle((int) $id));
$router->add('POST', '/tasks/{id}/delete',   fn (?int $id) => $controller->destroy((int) $id));
$router->add('GET',  '/audit',               fn ()        => $controller->audit());
```

`Router` umí jen **literální cesty a jeden parametr `{id}`** (převede `{id}` na regex `(?P<id>\d+)`). Žádné jiné parametry, žádné wildcardy. **Novou cestu přidáváš do `index.php`**, ne k controlleru.

### Database je singleton s auto-migrací a seedem
`Database::connection()` vrací jedinou sdílenou instanci PDO. Při **prvním** volání:
1. otevře (a v případě potřeby vytvoří) soubor `data/tasks.sqlite`,
2. spustí `migrate()` → `CREATE TABLE IF NOT EXISTS tasks (...)`,
3. pokud je tabulka prázdná, naseeduje 3 ukázkové úkoly.

Schéma tabulky `tasks`:

| sloupec | typ | poznámka |
|---|---|---|
| `id` | INTEGER PK AUTOINCREMENT | |
| `title` | TEXT NOT NULL | název úkolu |
| `done` | INTEGER NOT NULL DEFAULT 0 | 0/1, v PHP se mapuje na `bool` |
| `created_at` | TEXT NOT NULL | ISO 8601 (`date('c')`) |

Schéma tabulky `audit_log` (auditní stopa změn — kdo/kdy/co; zapisuje ji `TaskService` přes `AuditLogRepository`, log je **append-only**, záznamy se nikdy neupravují ani nemažou):

| sloupec | typ | poznámka |
|---|---|---|
| `id` | INTEGER PK AUTOINCREMENT | |
| `action` | TEXT NOT NULL | strojový klíč akce (`task.created`, `task.completed`, `task.reopened`, `task.deleted`) |
| `task_id` | INTEGER | ID dotčeného úkolu (bez FK — záznam přežije smazání úkolu) |
| `detail` | TEXT NOT NULL | lidsky čitelný český popis |
| `created_at` | TEXT NOT NULL | ISO 8601 (`date('c')`) |

Log se zobrazuje na `GET /audit` (`templates/audit.php`).

DB soubor je generovaný za běhu, **gitignorovaný** a v Dockeru musí být zapisovatelný pro uživatele `www-data` (uid 82).

### DI „chudého muže"
Závislosti neskládá žádný kontejner. Každá vyšší vrstva má svou závislost jako **default v konstruktoru**:

```php
final class TaskController {
    public function __construct(
        private readonly TaskService $service = new TaskService(),
    ) {}
}
```

V produkci se tak řetěz složí sám (`new TaskController()` → `new TaskService()` → `new TaskRepository()`). Pro **testy** lze závislost předat ručně (např. `new TaskService($fakeRepository)`), takže vrstvy zůstávají testovatelné i bez kontejneru.

### Šablony jsou prosté PHP
Žádný šablonovací engine. `TaskController::render()` udělá `extract($data)` a `require templates/<jméno>.php`. Proměnné předané v poli `$data` jsou tak v šabloně dostupné jako lokální proměnné. Šablony jsou dvě: `templates/tasks.php` (seznam úkolů) a `templates/audit.php` (audit log).

### Ruční PSR-4 autoloader
`autoload.php` registruje `spl_autoload_register`, který mapuje namespace prefix `App\` na adresář `src/`:

```
App\Service\TaskService  →  src/Service/TaskService.php
```

**Nová třída musí ležet v `src/` podle namespace**, jinak ji nic nenačte. `public/index.php` načítá pouze `autoload.php`, vše ostatní se dotahuje on-demand.

## Docker setup

Dvě služby v `docker-compose.yml`:

- **`app`** — `php:8.3-fpm-alpine` s doinstalovaným `pdo_sqlite` (viz `docker/Dockerfile`). Běží PHP-FPM na portu 9000, ven se nepublikuje (`expose`).
- **`web`** — `nginx:1.27-alpine`, publikuje `8080:80`, statiku servíruje sám a PHP požadavky předává FastCGI na `app:9000` (config v `docker/nginx/default.conf`).

Obě služby mají bind-mount `./:/var/www/html`, takže změny zdrojáků se projeví bez rebuildu. **Pozor:** bind mount překryje vše, co Dockerfile vytvořil uvnitř `/var/www/html` (včetně adresáře `data/` a jeho práv) — proto musí adresář `data/` existovat a být zapisovatelný pro `www-data` na straně, která mount poskytuje.

## Příkazy

> **PHP je jen v Dockeru** — na hostiteli není. `php` příkazy (lint apod.) spouštěj přes `docker exec` v kontejneru `task-library-app-t420-04`.

```bash
cd task-library

# Start (poprvé / po změně Dockerfile)
docker compose up -d --build
# příště stačí
docker compose up -d
docker compose down

# Aplikace běží na http://localhost:8080

# Lint všech PHP souborů (přeskočí generovanou DB)
docker exec task-library-app-t420-04 sh -c 'find . -name "*.php" -not -path "./data/*" -exec php -l {} \;'
# Lint jednoho souboru
docker exec task-library-app-t420-04 php -l src/Service/TaskService.php
```

## Stav testů

Testovací framework zatím **není**. Metoda `TaskService::progress()` (spočítá procento hotových úkolů) je kandidát na první testy a `/review` v dílu 02 tutoriálu.
