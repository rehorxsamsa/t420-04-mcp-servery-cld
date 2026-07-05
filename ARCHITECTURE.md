# Architektura — Task Library

Referenční mini-aplikace (TODO list) v čistém **PHP 8.3 OOP**: bez frameworku, bez Composeru, ruční autoloader, PDO/SQLite. Slouží jako pískoviště pro tutoriál Claude Code (**Díl 04 — MCP servery**). Záměrem je předvést kanonické vrstvení a OOP idiomy na minimu kódu — ne produkční robustnost. Sekce [Kompromisy a hranice](#kompromisy-a-hranice) pojmenovává, kde je to vidět.

> **Kde je kód:** aplikace i `docker-compose.yml` jsou v podadresáři **`task-library/`**. Všechny cesty níže (`public/index.php`, `src/…`, `templates/…`) jsou relativní k němu, `docker` příkazy pouštěj odtud.

## Stack

- **PHP 8.3** — čisté OOP (`final` třídy, konstruktor property promotion, `readonly` kde to má smysl), žádný framework ani Composer.
- **SQLite/PDO** — souborová DB, vzniká za běhu; `ERRMODE_EXCEPTION`, `FETCH_ASSOC` jako default fetch.
- **Bootstrap 5** přes CDN — bez build kroku.
- **Docker** — nginx (static + FastCGI proxy) před PHP-FPM.

## Řez requestem

Klasický **Controller → Service → Repository → PDO**, dispatch přes tabulku rout. Vpravo zdrojový soubor:

```
prohlížeč ──HTTP :8080──▶ nginx (web) ──FastCGI :9000──▶ PHP-FPM
                                                            │
public/index.php   front controller: bootstrap + route table
        │
        ▼
Router             src/Core/Router.php     (metoda+cesta) → callable
        ▼
TaskController     src/Controller/TaskController.php   orchestrace, super­globály, render/redirect
        ▼
TaskService        src/Service/TaskService.php         business pravidla, validace, zápis auditu
        ▼
TaskRepository     src/Repository/TaskRepository.php   jediné místo se SQL
        ▼
Database           src/Core/Database.php               PDO singleton + auto-migrace + seed
```

Přes vrstvy putuje doménová entita **`Task`** (`src/Model/Task.php`) s factory `Task::fromRow()`, která typuje surový řádek (`done` INTEGER → `bool`).

## Kontrakty vrstev

| Vrstva | Soubor | Zodpovědnost | Invariant (co nesmí) |
|---|---|---|---|
| Front controller | `public/index.php` | bootstrap autoloaderu, registrace rout, dispatch | žádná business logika |
| Router | `src/Core/Router.php` | `(metoda, cesta) → callable` | nezná doménu |
| Controller | `src/Controller/TaskController.php` | čte `$_POST`/`$_GET`, deleguje na Service, render/redirect | nesahá na Repository ani PDO |
| Service | `src/Service/TaskService.php` | validace, orchestrace, audit trail | žádné SQL |
| Repository | `src/Repository/TaskRepository.php` | veškeré SQL přes prepared statements | žádná business pravidla |
| Model | `src/Model/Task.php` | držení dat úkolu | nesahá na DB |
| Database | `src/Core/Database.php` | connection, migrace, seed | nezná doménu nad rámec schématu |

Vrstvení je jediný tvrdý invariant repa: **Controller vždy přes Service**, SQL **výhradně** v Repository, business logika (validace názvu, `progress()`) **výhradně** v Service. Přidávat kód mimo tato pravidla znamená rozbít celý smysl cvičení.

## Netriviální rozhodnutí

### Routing = imperativní tabulka
Routy nejsou anotace ani konfig; registrují se za běhu v `index.php`:

```php
$router->add('GET',  '/',                  fn ()        => $controller->index());
$router->add('POST', '/tasks',             fn ()        => $controller->store());
$router->add('POST', '/tasks/{id}/toggle', fn (?int $id) => $controller->toggle((int) $id));
$router->add('POST', '/tasks/{id}/delete', fn (?int $id) => $controller->destroy((int) $id));
$router->add('GET',  '/audit',             fn ()        => $controller->audit());
```

`Router::dispatch()` iteruje routy lineárně, `{id}` expanduje na `(?P<id>\d+)` — jediný podporovaný parametr, žádné wildcardy. **Match je „metoda i cesta", jinak fallthrough na 404** — router *nerozlišuje* neznámou cestu od špatné metody (žádná 405). Nová cesta = nový `add()` v `index.php`, ne úprava controlleru.

### Database: singleton + lazy migrace + seed
`Database::connection()` drží PDO ve `static` a při **prvním** volání v rámci procesu: otevře/vytvoří `data/tasks.sqlite`, spustí idempotentní `migrate()` (`CREATE TABLE IF NOT EXISTS`) a — je-li `tasks` prázdná — naseeduje 3 řádky. Migrace tedy běží jednou na FPM worker, ne jednou na request. Žádný nástroj na verzování schématu: změna sloupce = ruční zásah do `migrate()` na existující DB.

`tasks`:

| sloupec | typ | pozn. |
|---|---|---|
| `id` | INTEGER PK AUTOINCREMENT | |
| `title` | TEXT NOT NULL | |
| `done` | INTEGER NOT NULL DEFAULT 0 | 0/1, mapuje se na `bool` |
| `created_at` | TEXT NOT NULL | ISO 8601 (`date('c')`) |

`audit_log` — **append-only** stopa změn, zapisuje ji `TaskService` přes `AuditLogRepository`; záznamy se nikdy neupravují ani nemažou:

| sloupec | typ | pozn. |
|---|---|---|
| `id` | INTEGER PK AUTOINCREMENT | |
| `action` | TEXT NOT NULL | strojový klíč: `task.created`, `task.completed`, `task.reopened`, `task.deleted` |
| `task_id` | INTEGER | **bez FK** — záznam přežije smazání úkolu |
| `detail` | TEXT NOT NULL | česky, human-readable |
| `created_at` | TEXT NOT NULL | ISO 8601 |

Render auditu: `GET /audit` → `templates/audit.php`. DB soubor je generovaný, gitignorovaný a v kontejneru musí být zapisovatelný pro `www-data` (uid 82).

### Audit trail se odvozuje z pre-mutace stavu
`TaskService::toggle()`/`remove()` jsou **read-modify-write**: nejdřív `find($id)`, pak mutace, pak zápis auditu odvozený ze *starého* stavu (`$task->done` před přepnutím určuje `reopened` vs. `completed`). Trojice operací **není v transakci** a `find` vrací kopii — konkurenční požadavky na stejné `id` nejsou serializované (u single-user SQLite dema irelevantní, u čehokoli reálného ne). Když úkol mezitím zmizí (`find === null`), mutace proběhne naprázdno a audit se nezapíše.

### DI „chudého muže"
Žádný kontejner; každá vrstva má závislost jako **default hodnotu konstruktoru** vyhodnocenou při instanciaci:

```php
final class TaskController {
    public function __construct(
        private readonly TaskService $service = new TaskService(),
    ) {}
}
```

Produkčně se řetěz složí sám (`new TaskController()` → `new TaskService()` → `new TaskRepository()`); v testu se injektne fake (`new TaskService($fakeTaskRepo, $fakeAuditRepo)`). Pozor: `new TaskService()` jako default se vyhodnocuje **per-instance**, ne sdíleně — sdílení PDO řeší až singleton v `Database`, ne DI.

### Šablony = plain PHP
Bez engine. `TaskController::render()` udělá `extract($data, EXTR_OVERWRITE)` a `require templates/<name>.php` v scope metody. Klíče `$data` jsou tak v šabloně lokální proměnné — escaping (`htmlspecialchars`) je odpovědnost šablony, ne vrstvy. Šablony: `templates/tasks.php`, `templates/audit.php`. Controller renderuje přímo do výstupu (žádný output buffering), takže veškerý `header()`/`redirect()` musí předcházet jakémukoli echu.

### Ruční PSR-4 autoloader
`autoload.php` registruje `spl_autoload_register` s mapou prefix `App\` → `src/`:

```
App\Service\TaskService  →  src/Service/TaskService.php
```

`public/index.php` includuje jen `autoload.php`; zbytek se dotahuje on-demand. **Nová třída musí ležet v `src/` podle namespace**, jinak ji nic nenačte.

## Kompromisy a hranice

Vědomé zjednodušení kvůli výukovému rozsahu — u seniorního čtenáře nechceme, aby si je vykládal jako vzor:

- **`Task` není immutable.** `id` a `createdAt` jsou `readonly`, ale `title` a `done` zůstávají mutable public — entita je otevřená modifikaci mimo Service. „Poctivá" doména by je zapouzdřila nebo udělala celý objekt `readonly`.
- **`index()` čte tabulku dvakrát.** `list()` i `progress()` samostatně volají `repository->all()` → dva full-table scany na jeden render. `progress()` se dá spočítat nad už načteným seznamem, nebo agregací v SQL.
- **Žádná ochrana zápisu.** Mutace jedou přes `POST`, ale bez CSRF tokenu a bez idempotence; validace se omezuje na neprázdný `title`.
- **Chybové stavy jsou tiché.** Neplatný název → `InvalidArgumentException` v Service → Controller ho spolkne a redirectne na `/` bez zpětné vazby (potvrzení „přidáno" jede přes `?added=1` a toast v šabloně).
- **Žádné transakce, žádná concurrency strategie.** Viz audit read-modify-write výše.

Nic z toho není bug v kontextu dema — je to hranice záběru. Kdykoli se sáhne na tuhle app v tutoriálu, drž vrstvení a výše uvedené invarianty; ostatní body jsou přirozené cíle pro `/review`.

## Docker

Dvě služby v `docker-compose.yml`, obě s bind-mountem `./:/var/www/html` (změny zdrojáků bez rebuildu):

- **`app`** — `php:8.3-fpm-alpine` + `pdo_sqlite` (`docker/Dockerfile`), PHP-FPM na :9000, ven se nepublikuje.
- **`web`** — `nginx:1.27-alpine`, publikuje `8080:80`, statiku servíruje sám, PHP předává FastCGI na `app:9000` (`docker/nginx/default.conf`).

**Gotcha:** bind mount překryje vše, co Dockerfile vytvořil uvnitř `/var/www/html` — včetně `data/` a jeho práv. Adresář `data/` proto musí existovat a být zapisovatelný pro `www-data` na hostitelské straně mountu, jinak selže vytvoření `tasks.sqlite` za běhu.

## Příkazy

> **PHP je jen v Dockeru** — na hostiteli není. `php` (lint atd.) spouštěj přes `docker exec` v kontejneru `task-library-app-t420-04`.

```bash
cd task-library

docker compose up -d --build   # poprvé / po změně Dockerfile → http://localhost:8080
docker compose up -d           # příště
docker compose down

# Lint všech PHP (přeskočí generovanou DB)
docker exec task-library-app-t420-04 sh -c 'find . -name "*.php" -not -path "./data/*" -exec php -l {} \;'
# Lint jednoho souboru
docker exec task-library-app-t420-04 php -l src/Service/TaskService.php
```

## Stav testů

Testovací framework zatím **není**. `TaskService::progress()` (procento hotových) je nejčistší kandidát na první unit test a `/review` — čistá funkce nad injektovatelným repository, žádné vedlejší efekty.
