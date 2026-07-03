# Díl 04 — MCP servery

> **Co se naučíš:** co je MCP, `claude mcp add`, rozdíl mezi stdio a `--transport http`,
> dva nejužitečnější servery pro web vývoj — **Playwright** (ovládání prohlížeče)
> a **Context7** (aktuální dokumentace knihoven) — a příkaz `/mcp`.
>
> **Na čem:** na našem „Seznamu úkolů" — Playwright použijeme k E2E ověření UI,
> Context7 k získání aktuální Bootstrap dokumentace.

---

## 0. Rozběhnutí cvičné aplikace (Docker)

Celý díl pracuje s **cvičnou aplikací „Seznam úkolů"** — malý PHP 8.3 OOP TODO list (bez frameworku, bez Composeru), který je součástí tohoto repa ve složce `task-library/`. Než začneš s MCP, rozběhni ji v Dockeru.

**Předpoklad:** nainstalovaný Docker + Docker Compose. PHP na hostiteli mít **nemusíš** — běží celé v kontejneru.

```bash
cd task-library
docker compose up -d --build     # poprvé (postaví image); běží na pozadí (-d)
```

Aplikace pak jede na **http://localhost:8080**. Otevři si ji v prohlížeči — uvidíš seznam úkolů (DB se při prvním startu sama vytvoří a naseeduje 3 úkoly) a stránku audit logu na `/audit`.

Běžné příkazy:

```bash
docker compose up -d             # příště (image už existuje)
docker compose ps                # stav kontejnerů
docker compose logs -f web       # živé logy nginx (nebo: app)
docker compose down              # zastavit a smazat kontejnery
```

**Jak to je poskládané** (detaily v `../ARCHITECTURE.md`):

| Služba | Kontejner | Co dělá |
|---|---|---|
| `web` | `task-library-web-t420-04` | nginx, publikuje port `8080:80`, PHP předává FastCGI na `app:9000` |
| `app` | `task-library-app-t420-04` | `php:8.3-fpm-alpine` + `pdo_sqlite`, běží PHP-FPM (ven se nepublikuje) |

Zdrojáky jsou do kontejnerů bind-mountnuté (`./:/var/www/html`), takže **změna v kódu se projeví hned bez rebuildu** — stačí refresh prohlížeče. `docker compose up --build` potřebuješ jen po změně `Dockerfile`.

> **PHP jen v Dockeru:** případné `php` příkazy (např. lint) pouštěj přes kontejner, ne lokálně:
> ```bash
> docker exec task-library-app-t420-04 php -l src/Service/TaskService.php
> ```

Aplikaci teď necháme běžet — v sekci 4 na ni pustíme Playwright.

---

## 1. Co je MCP a proč ti změní workflow

**MCP (Model Context Protocol)** je otevřený standard od Anthropicu, který dává Claude přístup k externím nástrojům a službám — prohlížeč, GitHub, databáze, dokumentace, Slack. Je to jako „USB-C pro AI": jeden protokol, do kterého se připojují různé nástroje.

Bez MCP umí Claude Code číst a psát soubory a pouštět shell. **S MCP** umí třeba otevřít tvoji aplikaci v reálném prohlížeči, kliknout na tlačítko a ověřit, že to funguje — což je přesně to, co si za chvíli zkusíme.

---

## 2. `claude mcp add` — dva typy transportu

MCP server běží buď lokálně jako proces (**stdio**), nebo vzdáleně přes HTTP (**`--transport http`**). Z cheatsheetu:

```bash
# stdio (lokální proces přes npx) — Playwright
claude mcp add playwright npx @playwright/mcp@latest

# HTTP (vzdálená služba) — Context7
claude mcp add --transport http context7 https://mcp.context7.com/mcp
```

| | stdio | HTTP (`--transport http`) |
|---|---|---|
| Kde běží | lokální proces na tvém stroji | vzdálený server |
| Příklad | Playwright (`npx @playwright/mcp`) | Context7, Sentry, Supabase |
| Spuštění | Claude Code proces nastartuje | připojí se na URL |
| Kdy | nástroj potřebuje tvůj stroj/prohlížeč | hostovaná služba |

### Scope: kde se MCP server uloží

```bash
claude mcp add playwright npx @playwright/mcp@latest            # default scope (local)
claude mcp add -s user playwright npx @playwright/mcp@latest    # user — napříč všemi projekty
```

Pro **projektový** scope (sdílíš s týmem přes git) se používá soubor **`.mcp.json`** v kořeni repa — ten máme v tomto dílu:

```json
{
  "mcpServers": {
    "playwright": {
      "command": "npx",
      "args": ["@playwright/mcp@latest"]
    },
    "context7": {
      "type": "http",
      "url": "https://mcp.context7.com/mcp"
    }
  }
}
```

Když člen týmu naklonuje repo a spustí `claude`, Claude Code se zeptá, jestli `.mcp.json` servery povolit. Tým tak sdílí stejnou MCP výbavu.

> ⚙️ **Reálný `.mcp.json` v tomto repu** má u Playwrightu navíc pár argumentů:
> ```json
> "args": ["@playwright/mcp@latest", "--executable-path", "…/chrome", "--headless", "--isolated"]
> ```
> `--headless` a `--executable-path` (cesta k lokálnímu Chromiu) potřebuješ v prostředí **bez GUI** — typicky WSL nebo CI; `--isolated` drží čistý profil mezi běhy. Na desktopu s běžným prohlížečem si vystačíš s holým `["@playwright/mcp@latest"]` z tabulky výše.

---

## 3. `/mcp` — co je připojené

Během session:
```
> /mcp
```
Vypíše připojené MCP servery a jejich stav (✓ Connected / ✗ Failed). Když server nenaskočí, tohle je první kontrola. (Druhá je `/doctor` z dílu 02.)

```bash
# z příkazové řádky totéž:
claude mcp list
```

---

## 4. Playwright MCP — E2E ověření naší aplikace

Tohle je „aha moment". Playwright dá Claude reálný prohlížeč. Aplikace už běží ze [sekce 0](#0-rozběhnutí-cvičné-aplikace-docker) na **http://localhost:8080** (kdyby ne, spusť `cd task-library && docker compose up -d`).

V session Claude Code (s připojeným Playwrightem):

```
> Otevři http://localhost:8080, přidej úkol "Vyzkoušet Playwright MCP",
  ověř, že se objevil v seznamu, pak ho odškrtni jako hotový a zkontroluj,
  že progress bar nahoře vzrostl. Udělej screenshot výsledku.
```

Co Claude udělá přes Playwright:
1. Naviguje na `localhost:8080`
2. Najde input (přes accessibility tree, ne podle pixelů), vyplní a odešle formulář
3. Ověří, že nový úkol je v `<ul class="list-group">`
4. Klikne na toggle, ověří změnu progress baru
5. Udělá screenshot

**Proč je to silné:** Claude právě otestoval reálné chování tvé aplikace end-to-end, aniž bys psal jediný řádek Playwright kódu. Po každé změně UI můžeš nechat Claude udělat „self-QA".

> ✅ **Ověřeno na tomto repu.** Celý CRUD cyklus proběhl přes Playwright MCP a čísla seděla — progress bar se přepočítal na každou akci:
>
> | Akce (přes accessibility tree) | Hotovo | Zápis do `/audit` |
> |---|---|---|
> | přidání úkolu | 50 % → 43 % | ➕ Vytvořen |
> | odškrtnutí jako hotové | 43 % → 57 % | ✅ Dokončen |
> | smazání úkolu | 57 % → 50 % | 🗑️ Smazán |
>
> Audit log potvrdil **append-only** chování: po smazání úkolu jeho starší záznamy (Vytvořen, Dokončen) v logu **zůstaly** — mazání úkolu nemaže jeho historii (viz [zajímavost 5](#7-zajímavostí-o-cvičné-aplikaci)).
>
> Dvě praktické poznámky z běhu: tlačítko **Smazat maže rovnou**, bez potvrzovacího dialogu (odpovídá jednoduchosti tutoriálu). A v konzoli naskočí jediná chyba — **`404 favicon.ico`** — která je kosmetická a funkčnost neovlivňuje.

> 💡 **Poznámka 2026:** Microsoft nově doporučuje pro agenty `@playwright/cli` místo MCP — spotřebuje ~4× méně tokenů (snímky ukládá na disk jako YAML místo streamování celého accessibility tree do kontextu). Pro učení je MCP názornější; pokud řešíš náklady na velkém projektu, mrkni na Playwright CLI.

---

## 5. Context7 MCP — konec zastaralé dokumentace

Problém, který určitě znáš: AI vygeneruje kód s deprecated API, protože vychází z trénovacích dat. **Context7** to řeší — natáhne aktuální, verzově specifickou dokumentaci knihovny přímo do kontextu.

Na naší aplikaci (používá Bootstrap 5):

```
> use context7: ukaž mi aktuální Bootstrap 5.3 markup pro toast notifikaci
  a přidej do templates/tasks.php toast, který se zobrazí po přidání úkolu
```

Co se stane: místo hádání z paměti Claude přes Context7 stáhne **aktuální** Bootstrap 5.3 docs a vygeneruje markup, který odpovídá přesně té verzi — včetně případných breaking changes oproti starším verzím.

Klíč k použití: do promptu přidáš **`use context7`** (nebo zmíníš konkrétní knihovnu). Hodí se hlavně u rychle se měnících frameworků (Next.js, Tailwind, Laravel, Symfony…), kde se API mění mezi minor verzemi.

> Pro tebe konkrétně: u Symfony/Laravel tutoriálů je Context7 zlato — vždycky dostaneš syntaxi pro tu verzi, kterou reálně používáš, ne pro tři roky starou.

---

## 6. Kombinace serverů = workflow

Síla MCP je v kombinaci. Příklad reálného workflow nad naší aplikací:

1. **Context7** → natáhne aktuální Bootstrap docs pro novou komponentu
2. Claude napíše změnu v `templates/tasks.php`
3. **Playwright** → otevře `localhost:8080` a vizuálně ověří, že komponenta funguje
4. (s GitHub MCP) → vytvoří PR

Každý přidaný server násobí, co Claude Code zvládne. Pravidlo: **začni s jedním dvěma, co řeší tvoji největší bolest** (pro web vývoj Playwright + Context7), a rozšiřuj postupně.

---

## 7 zajímavostí o cvičné aplikaci

1. **Nula závislostí.** Žádný Composer, žádný framework — ani jeden externí balíček. I PSR-4 autoloading je napsaný ručně (`autoload.php`, ~26 řádků `spl_autoload_register`).
2. **Databáze se postaví sama.** `Database::connection()` je singleton, který při úplně prvním requestu vytvoří schéma a naseeduje 3 úkoly (`migrate()`). Žádný krok „spusť migrace" — jen otevřeš stránku.
3. **Mikro-router.** Celý routing (`Router.php`, ~46 řádků) umí jen literální cesty a jediný parametr `{id}` (`{id}` → regex `(?P<id>\d+)`). Žádné wildcardy, žádné anotace — routy jsou obyčejná tabulka v `public/index.php`.
4. **DI „chudého muže".** Závislosti neskládá žádný kontejner — jsou to defaulty v konstruktoru (`new TaskService()`). Řetěz se v produkci složí sám, a přesto jde do každé vrstvy pro testy podstrčit fake.
5. **Audit log jen přidává, nikdy nemaže.** Tabulka `audit_log` je append-only a `task_id` schválně **nemá** foreign key — auditní záznam tak přežije i smazání úkolu, ke kterému patřil.
6. **Šablony bez enginu.** Žádný Twig/Blade — `render()` udělá `extract($data)` a `require` prostý PHP soubor. Bootstrap 5 jede z CDN, takže frontend nemá **žádný** build krok.
7. **PHP na počítači vůbec nemáš.** Celá app běží v Dockeru (nginx + `php:8.3-fpm-alpine`); bind mount promítá změny kódu do kontejneru bez rebuildu. I lint (`php -l`) se pouští přes `docker exec`, ne lokálně.

> Pointa: celá tahle „opravdová" webová aplikace se vejde do ~600 řádků PHP — dost malá, aby se dala přečíst za odpoledne, a dost bohatá, aby na ní dávaly smysl MCP nástroje z tohoto dílu.

---

## Shrnutí dílu 04

Víš, co je MCP a proč rozšiřuje Claude Code za hranice „čti/piš soubory". Umíš `claude mcp add` v obou variantách — **stdio** (lokální proces, `npx @playwright/mcp@latest`) a **`--transport http`** (vzdálená služba, Context7). Znáš scope (local / `-s user` / projektový `.mcp.json` sdílený s týmem) a `/mcp` pro kontrolu stavu. Prakticky jsi nechal **Playwright** otestovat UI naší aplikace a **Context7** dodat aktuální Bootstrap dokumentaci.

---

## ✅ Test dílu 04

**1. Co je MCP a co Claude Code umožňuje, co bez něj nejde?**

<details><summary>Odpověď</summary>

**Model Context Protocol** — otevřený standard od Anthropicu pro připojení externích nástrojů a služeb (prohlížeč, dokumentace, GitHub, DB) ke Claude. Bez MCP umí Claude Code číst/psát soubory a pouštět shell; s MCP umí třeba ovládat reálný prohlížeč, stáhnout aktuální docs apod.
</details>

**2. Jaký je rozdíl mezi stdio a `--transport http` MCP serverem? Dej příklad ke každému.**

<details><summary>Odpověď</summary>

**stdio** = lokální proces na tvém stroji (Claude Code ho nastartuje), např. Playwright přes `npx @playwright/mcp@latest`. **HTTP** = vzdálená hostovaná služba, na kterou se připojíš přes URL, např. Context7 (`https://mcp.context7.com/mcp`).
</details>

**3. Chceš MCP server sdílet s celým týmem přes git. Kam ho dáš?**

<details><summary>Odpověď</summary>

Do souboru **`.mcp.json`** v kořeni repa (projektový scope). Po klonu se Claude Code zeptá, zda servery povolit. (Alternativa pro „jen pro mě napříč projekty": `claude mcp add -s user ...`.)
</details>

**4. MCP server nenaskočil. Které dva příkazy použiješ k diagnostice?**

<details><summary>Odpověď</summary>

`/mcp` (vypíše připojené servery a stav ✓/✗) a `/doctor` (health check instalace Claude Code). Z CLI lze i `claude mcp list`.
</details>

**5. K čemu konkrétně použiješ Playwright MCP na našem Seznamu úkolů?**

<details><summary>Odpověď</summary>

K E2E ověření UI: Claude otevře `localhost:8080`, přidá úkol, ověří jeho výskyt v seznamu, odškrtne ho, zkontroluje růst progress baru a udělá screenshot — vše bez ručně psaného Playwright kódu. Funguje přes accessibility tree, ne podle pixelů.
</details>

**6. Generuješ kód a bojíš se deprecated API u Bootstrap 5.3. Jak pomůže Context7 a jak ho v promptu „zapneš"?**

<details><summary>Odpověď</summary>

Context7 natáhne aktuální verzově specifickou dokumentaci knihovny přímo do kontextu, takže Claude negeneruje z (možná zastaralé) paměti. Zapneš ho tím, že do promptu přidáš **`use context7`** nebo zmíníš konkrétní knihovnu/verzi.
</details>

**7. Cheatsheet uvádí `claude mcp add playwright npx @playwright/mcp@latest`. Je to stdio, nebo HTTP server?**

<details><summary>Odpověď</summary>

**stdio** — spouští se lokální proces přes `npx`, žádné `--transport http` ani URL. HTTP varianta by vypadala `claude mcp add --transport http <jméno> <url>`.
</details>

→ Pokračuj na [Díl 05 — Hooks & automatizace](../t420-05-hooks-automatizace-cld/README.md)
