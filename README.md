# Díl 04 — MCP servery

> **Co se naučíš:** co je MCP, `claude mcp add`, rozdíl mezi stdio a `--transport http`,
> dva nejužitečnější servery pro web vývoj — **Playwright** (ovládání prohlížeče)
> a **Context7** (aktuální dokumentace knihoven) — a příkaz `/mcp`.
>
> **Na čem:** na naší „Knihovně úkolů" — Playwright použijeme k E2E ověření UI,
> Context7 k získání aktuální Bootstrap dokumentace.

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

Tohle je „aha moment". Playwright dá Claude reálný prohlížeč. Spusť naši aplikaci (kopii „Knihovny úkolů" z dílu 01 najdeš přímo v tomto repu ve složce `task-library/`):

```bash
cd task-library
docker compose up -d --build   # běží na http://localhost:8080
```

V jiném terminálu v session Claude Code (s připojeným Playwrightem):

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

**5. K čemu konkrétně použiješ Playwright MCP na naší Knihovně úkolů?**

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
