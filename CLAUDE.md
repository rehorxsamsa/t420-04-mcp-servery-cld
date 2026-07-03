# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Tohle je **Díl 04 tutoriálu Claude Code — MCP servery**. Repo má dvě části:

1. **Výukový text** (`README.md`) — teorie MCP (Model Context Protocol), `claude mcp add`, stdio vs. `--transport http`, scope, `/mcp`. Obsahuje i test s odpověďmi. Když upravuješ README, drž český styl a formát `<details>` odpovědí.
2. **Cvičná aplikace** `task-library/` — malá PHP-OOP „Knihovna úkolů", na které se MCP prakticky zkouší (Playwright pro E2E ověření UI, Context7 pro aktuální Bootstrap docs).

## MCP servery (jádro tohoto dílu)

Projektový scope je nastavený v `.mcp.json` v kořeni — **playwright** (stdio, `npx @playwright/mcp@latest`) a **context7** (HTTP, `https://mcp.context7.com/mcp`). Oba jsou už povolené v `.claude/settings.local.json` (`enabledMcpjsonServers`). Stav ověříš přes `/mcp`, z CLI `claude mcp list`.

- **Playwright** používej k E2E ověření UI: naviguj na běžící aplikaci, pracuj přes accessibility tree (ne podle pixelů), na konec dělej screenshot.
- **Context7** používej při generování kódu proti knihovnám (hlavně Bootstrap 5.3 v této app) — do promptu patří `use context7`, ať Claude negeneruje z paměti.

---

## Cvičná aplikace `task-library/`

Malá aplikace na správu úkolů (TODO list) napsaná v čistém **PHP 8.3 OOP**, bez frameworku a bez Composeru. Cílem je ukázat poctivé vrstvení a OOP idiomy na co nejmenším množství kódu. Podrobný rozklad architektury je v `ARCHITECTURE.md`.

### Stack
- PHP 8.3 (čisté OOP, žádný framework, žádný Composer)
- SQLite (PDO) — souborová DB, vzniká za běhu
- Bootstrap 5 (přes CDN) — žádný build krok pro frontend
- Docker: nginx + PHP-FPM

### Architektura
- Ruční PSR-4 autoloader (`task-library/autoload.php`), namespace `App\` → `task-library/src/`. Nová třída musí ležet v `src/` podle namespace, jinak ji nic nenačte.
- Front controller: `task-library/public/index.php`
- Vrstvení: **Controller → Service → Repository → (PDO)**
- Šablony: `task-library/templates/*.php` (prosté PHP, žádný šablonovací engine). `TaskController::render()` udělá `extract($data)` a `require` šablonu.

Tok requestu (chování je rozprostřené přes víc souborů):

```
nginx (web) → public/index.php → Router → TaskController → TaskService → TaskRepository → Database (PDO/SQLite)
```

Co není vidět z jednoho souboru:
- **Routy se registrují jako tabulka v `public/index.php`** (`$router->add(...)`), ne anotacemi. `Router` umí jen literální cesty a jeden parametr `{id}` (regex `(?P<id>\d+)`). Novou cestu přidáváš do `index.php`, ne k controlleru.
- **`Database::connection()` je singleton, který si při prvním volání sám vytvoří schéma a naseeduje 3 úkoly** (`migrate()`). DB soubor `data/tasks.sqlite` vzniká za běhu, je gitignorovaný a v Dockeru musí být zapisovatelný pro `www-data`.
- **DI „chudého muže"** — žádný kontejner. Každá vyšší vrstva má závislost jako default v konstruktoru (`new TaskService()`, `new TaskRepository()`); pro testy lze závislost předat ručně.
- **Audit log** je append-only — `TaskService` zapisuje změny přes `AuditLogRepository`, záznamy se nikdy neupravují ani nemažou; zobrazuje se na `GET /audit`.

### Příkazy

> **PHP je jen v Dockeru** — na hostiteli není a nemá být. Všechny `php` příkazy spouštěj přes `docker exec` v kontejneru `task-library-app-t420-04`.

```bash
cd task-library
docker compose up -d --build      # poprvé / po změně Dockerfile; aplikace běží na http://localhost:8080
docker compose up -d              # příště
docker compose down

# Lint jednoho souboru (v kontejneru)
docker exec task-library-app-t420-04 php -l src/Service/TaskService.php
# Lint všech PHP (přeskočí generovanou DB)
docker exec task-library-app-t420-04 sh -c 'find . -name "*.php" -not -path "./data/*" -exec php -l {} \;'
```

Změny zdrojáků se díky bind mountu (`./:/var/www/html`) projeví bez rebuildu.

### Testy
Testovací framework zatím **není**. `TaskService::progress()` (procento hotových úkolů) je v tutoriálu kandidát na první testy a `/review`.

## Konvence
- `declare(strict_types=1)` v každém PHP souboru
- Třídy jsou `final`, vlastnosti `readonly` kde to dává smysl
- Repository je jediné místo, kde se sahá na DB — Controller na ni nikdy nesahá přímo
- Business logika patří do Service, ne do Controlleru
- Komentáře i UI texty jsou česky; README a app jsou učební materiál — změny mají zůstat srozumitelné

## Don't
- Nepřidávej Composer ani framework — celý smysl je čisté OOP
- Neměň `data/tasks.sqlite` ručně (vytváří se automaticky při startu)
- Nepřidávej build kroky ani závislosti, pokud o to tutoriál výslovně nežádá
